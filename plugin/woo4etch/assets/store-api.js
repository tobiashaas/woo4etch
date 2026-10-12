/**
 * Woo4Etch Store API cart layer — progressive enhancement (issue #25).
 *
 * Architecture, chosen to keep the Etch advantage (your markup stays yours):
 * - WRITES go through WooCommerce's Store API (/wc/store/v1/cart/*) — that
 *   puts them under Woo's native validation, JSON error messages and Store
 *   API rate limiting (incl. fingerprinting since WC 9.8).
 * - READS stay server-rendered Etch HTML: after every write the current page
 *   is refetched and the marked regions are swapped in place. The plugin
 *   never renders cart markup client-side, so ANY hand-written layout
 *   re-renders correctly — no template convention, no client templating.
 *
 * Bindings are markup-agnostic via the Woo contract names the templates
 * already require: cart[<key>][qty] inputs, ?remove_item= links,
 * coupon_code/apply_coupon, ?remove_coupon= links, and form.cart add-to-cart
 * submits. Everything keeps working without JS — the classic POST paths are
 * untouched fallbacks.
 *
 * Regions: elements with [data-w4e-cart-region="<name>"] are swapped after
 * writes; without markers the shipped containers (.w4e-cart, .w4e-minicart)
 * are used. Custom builds: add the attribute to whatever should live-update.
 *
 * Writes are serialized (issue #41). An interaction that arrives while a
 * write is in flight — the next product card, a double click — is queued
 * behind it and runs against the cart that write left behind; it never
 * falls through to the classic full-page POST. A form, link or button whose
 * own write is still pending carries aria-busy and drops repeat submits
 * until that write settled.
 *
 * Events (issue #42): every successful write dispatches two events on
 * document. `woo4etch:cart-written` fires as soon as the Store API has
 * answered, before the page is re-fetched — detail.cart = the Store API cart
 * JSON, detail.action = the endpoint (add-item, update-item, remove-item,
 * apply-coupon, remove-coupon, select-shipping-rate, update-customer),
 * detail.request = the body sent, detail.origin = the element that triggered
 * it. It is cancelable: preventDefault() skips the region refresh for that
 * one write, as does data-w4e-refresh="none" on the form (or any ancestor
 * of the control). `woo4etch:cart-updated` fires after the regions were
 * swapped (or right away when the refresh was skipped), with detail.cart.
 * When jQuery + wc-cart-fragments are present, `wc_fragment_refresh` is
 * triggered too, so fragment-based third-party mini-carts stay in sync.
 *
 * Add-to-cart interception is deliberately conservative: a form.cart is only
 * intercepted when it contains exclusively the known Woo fields (quantity,
 * add-to-cart, product_id, variation_id, attribute_*). Any extra named input
 * means a third-party plugin collects custom item data through the classic
 * woocommerce_add_cart_item_data path — those forms submit classically so
 * nothing is lost.
 */
(function () {
    if (window.__w4eStoreApi) return;
    window.__w4eStoreApi = true;

    var cfg = window.w4eStoreApi || {};
    if (!cfg.restUrl) return;
    var nonce = cfg.nonce || '';
    var i18n = cfg.i18n || {};
    var busy = false;

    /* ---------------- Store API fetch wrapper ---------------- */

    function api(path, method, body) {
        return fetch(cfg.restUrl + path, {
            method: method || 'GET',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Nonce': nonce },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (res) {
            var fresh = res.headers.get('Nonce');
            if (fresh) nonce = fresh;
            return res.json().catch(function () { return null; }).then(function (json) {
                if (!res.ok) {
                    var err = new Error((json && json.message) || res.statusText);
                    err.status = res.status;
                    throw err;
                }
                return json;
            });
        });
    }

    /* ---------------- notices ---------------- */

    function stripTags(html) {
        var d = document.createElement('div');
        d.innerHTML = html;
        return d.textContent || '';
    }

    function notice(message, type) {
        var region = document.querySelector('.w4e-notices');
        if (!region) {
            region = document.createElement('div');
            region.className = 'w4e-notices';
            var anchor = document.querySelector('[data-w4e-cart-region], .w4e-cart, form.cart, main') || document.body;
            anchor.insertBefore(region, anchor.firstChild);
        }
        var el = document.createElement('div');
        el.className = 'w4e-notice w4e-notice--' + (type || 'notice');
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.textContent = stripTags(message);
        region.appendChild(el);
        window.setTimeout(function () { el.remove(); }, 6000);
    }

    /* ---------------- region morph (server-rendered reads) ---------------- */

    function setBusy(state) {
        busy = state;
        regionElements().forEach(function (el) {
            if (state) el.setAttribute('aria-busy', 'true');
            else el.removeAttribute('aria-busy');
        });
    }

    // Cart regions on any page, checkout regions on the A+ checkout —
    // both are swapped with freshly server-rendered HTML after writes.
    var REGION_SELECTOR = '[data-w4e-cart-region], [data-w4e-checkout-region]';

    function regionAttr(el) {
        return el.hasAttribute('data-w4e-cart-region') ? 'data-w4e-cart-region' : 'data-w4e-checkout-region';
    }

    function regionElements() {
        var marked = document.querySelectorAll(REGION_SELECTOR);
        if (marked.length) return Array.prototype.slice.call(marked);
        return Array.prototype.slice.call(document.querySelectorAll('.w4e-cart, .w4e-minicart'));
    }

    function refreshRegions() {
        return fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'woo4etch' } })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var marked = document.querySelectorAll(REGION_SELECTOR);
                if (marked.length) {
                    marked.forEach(function (el) {
                        var attr = regionAttr(el);
                        var key = el.getAttribute(attr);
                        var next = doc.querySelector('[' + attr + '="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]');
                        if (next) el.replaceWith(next);
                    });
                } else {
                    ['.w4e-cart', '.w4e-minicart'].forEach(function (sel) {
                        var cur = document.querySelector(sel);
                        var next = doc.querySelector(sel);
                        if (cur && next) cur.replaceWith(next);
                    });
                }
                // Plain-text counters anywhere (header chips etc.).
                var counts = doc.querySelectorAll('.mini-cart-count');
                if (counts.length) {
                    document.querySelectorAll('.mini-cart-count').forEach(function (el, i) {
                        var src = counts[Math.min(i, counts.length - 1)];
                        if (src) el.textContent = src.textContent;
                    });
                }
            });
    }

    function announce(cart) {
        document.dispatchEvent(new CustomEvent('woo4etch:cart-updated', { detail: { cart: cart || null } }));
        // Ecosystem glue: fragment-based mini-carts (3rd party) resync.
        if (window.jQuery && window.wc_cart_fragments_params) {
            window.jQuery(document.body).trigger('wc_fragment_refresh');
        }
    }

    /* ---------------- write queue (issue #41) ---------------- */

    // One write at a time. Before, a second interaction during a write
    // passed the `busy` guard BEFORE preventDefault() and ran as the classic
    // full-page POST — the visitor landed on the product page, sometimes
    // with the item added twice. Now the interaction waits for the write in
    // flight. `busy` holds from the first enqueue until the last job settled,
    // so aria-busy on the regions covers the whole run.
    var queue = Promise.resolve();
    var pending = 0;

    function noop() {}

    function enqueue(run) {
        pending++;
        busy = true;
        var job = queue.then(function () {
            setBusy(true);
            return run();
        });
        queue = job.then(null, noop).then(function () {
            pending--;
            if (!pending) setBusy(false);
        });
        return job;
    }

    // The element whose own write is still pending (form, link, button)
    // carries aria-busy; a repeat from it — a double click — is dropped
    // rather than queued, so one click never means two items.
    function isPending(el) {
        return !!el && 'true' === el.getAttribute('aria-busy');
    }

    // data-w4e-refresh="none" on the form, or on any ancestor of the control,
    // keeps the server-rendered regions as they are for writes from there —
    // for a layout that updates its own parts from the cart JSON (issue #42).
    // Each refresh is a full WordPress render of the current page.
    function wantsRefresh(origin) {
        var scope = origin && origin.closest ? origin.closest('[data-w4e-refresh]') : null;
        return !(scope && 'none' === scope.getAttribute('data-w4e-refresh'));
    }

    /**
     * One cart write, queued: POST the Store API, report the answer right
     * away (`woo4etch:cart-written`, cancelable), re-render the regions
     * unless that write opted out, then `woo4etch:cart-updated`. Resolves
     * with the Store API cart; rejects with the API error, after which the
     * queue moves on to the next job.
     *
     * @param {Element|null} origin  The control that triggered the write.
     * @param {string}       action  Endpoint under /cart/ (add-item, …).
     * @param {Object}       body    Request body.
     * @param {Element=}     guard   Element to mark aria-busy while pending.
     * @param {boolean=}     restore On failure, re-render the regions to the
     *                               server truth before rejecting: the DOM
     *                               may hold optimistic state (a stepper
     *                               already bumped the input Woo rejected,
     *                               e.g. sold-individually items). Done
     *                               inside the job, so the next queued
     *                               write never overlaps the restore.
     */
    function cartWrite(origin, action, body, guard, restore) {
        if (guard) guard.setAttribute('aria-busy', 'true');
        var job = enqueue(function () {
            return api('/cart/' + action, 'POST', body).then(function (cart) {
                var early = new CustomEvent('woo4etch:cart-written', {
                    cancelable: true,
                    detail: { cart: cart || null, action: action, request: body || null, origin: origin || null }
                });
                var refresh = document.dispatchEvent(early) && wantsRefresh(origin);
                return (refresh ? refreshRegions() : Promise.resolve()).then(function () {
                    announce(cart);
                    return cart;
                });
            }, function (err) {
                if (!restore) throw err;
                return refreshRegions().then(function () { throw err; });
            });
        });
        if (guard) {
            job.then(null, noop).then(function () { guard.removeAttribute('aria-busy'); });
        }
        return job;
    }

    function fail(err) {
        notice(err && err.message ? err.message : (i18n.error || 'Something went wrong.'), 'error');
    }

    /* ---------------- cart page bindings ---------------- */

    var qtyTimer = null;

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.name || !/^cart\[(.+)\]\[qty\]$/.test(input.name)) return;
        var key = input.name.match(/^cart\[(.+)\]\[qty\]$/)[1];
        var quantity = parseInt(input.value, 10);
        if (isNaN(quantity) || quantity < 0) return;
        window.clearTimeout(qtyTimer);
        qtyTimer = window.setTimeout(function () {
            // restore=true: the regions are re-rendered to the server truth
            // before the error shows (the notices region usually lives
            // inside the swapped cart region).
            (quantity === 0
                ? cartWrite(input, 'remove-item', { key: key }, null, true)
                : cartWrite(input, 'update-item', { key: key, quantity: quantity }, null, true)
            ).catch(fail);
        }, 350);
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[href*="remove_item="], a[href*="remove_coupon="]') : null;
        if (link) {
            var url;
            try { url = new URL(link.href, window.location.origin); } catch (err) { return; }
            var itemKey = url.searchParams.get('remove_item');
            var coupon = url.searchParams.get('remove_coupon');
            if (!itemKey && !coupon) return;
            e.preventDefault();
            if (isPending(link)) return;
            (itemKey
                ? cartWrite(link, 'remove-item', { key: itemKey }, link, true)
                : cartWrite(link, 'remove-coupon', { code: coupon }, link, true)
            ).catch(fail);
            return;
        }

        var couponBtn = e.target.closest ? e.target.closest('[name="apply_coupon"]') : null;
        if (couponBtn) {
            var form = couponBtn.closest('form');
            var field = form ? form.querySelector('input[name="coupon_code"]') : document.querySelector('input[name="coupon_code"]');
            var code = field ? field.value.trim() : '';
            if (!code) return; // let the classic submit handle empties
            e.preventDefault();
            if (isPending(couponBtn)) return;
            cartWrite(couponBtn, 'apply-coupon', { code: code }, couponBtn)
                .then(function () {
                    // Notices render AFTER the region swap — the notices
                    // region usually lives inside the swapped cart region.
                    notice(i18n.couponApplied || 'Coupon code applied successfully.', 'success');
                })
                .catch(fail);
        }
    });

    /* ---------------- add-to-cart interception (conservative) ---------------- */

    var KNOWN_FIELDS = /^(quantity|add-to-cart|product_id|variation_id|attribute_.+|wc_.*|woocommerce.*|_wp_http_referer)$/;

    function interceptable(form) {
        if (form.querySelector('input[name^="quantity["]')) return false; // grouped products
        var fields = form.querySelectorAll('[name]');
        for (var i = 0; i < fields.length; i++) {
            if (!KNOWN_FIELDS.test(fields[i].name)) return false; // 3rd-party item data → classic POST
        }
        return true;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList || !form.classList.contains('cart')) return;
        if (!interceptable(form)) return;

        var submitter = e.submitter || form.querySelector('[name="add-to-cart"], [name="buy_now"]');
        if (submitter && 'buy_now' === submitter.name) return; // buy-now redirect flow stays classic

        var idField = form.querySelector('[name="add-to-cart"]') || form.querySelector('[name="product_id"]');
        var id = idField ? parseInt(idField.value, 10) : 0;
        if (!id) return;

        // From here on the submit is ours — never the classic POST, not
        // even while another write runs (issue #41). A repeat from the
        // same form is dropped; a different form's submit is queued.
        e.preventDefault();
        if (isPending(form)) return;

        var variationField = form.querySelector('[name="variation_id"]');
        var variationId = variationField ? parseInt(variationField.value, 10) : 0;
        var qtyField = form.querySelector('[name="quantity"]');
        var quantity = qtyField ? Math.max(1, parseInt(qtyField.value, 10) || 1) : 1;

        var variation = [];
        form.querySelectorAll('[name^="attribute_"]').forEach(function (el) {
            if (el.value) variation.push({ attribute: el.name, value: el.value });
        });

        cartWrite(form, 'add-item', {
            id: variationId || id,
            quantity: quantity,
            variation: variation
        }, form).then(function () {
            notice(i18n.added || 'Added to your cart.', 'success');
        }).catch(fail);
    });

    /* ---------------- Store API checkout (A+, issue #27) ---------------- */
    /*
     * Opt-in via <form ... data-w4e-checkout> in the layout — the classic
     * shortcode checkout (wc-checkout.js) is never touched. The form keeps
     * working as a classic ?wc-ajax=checkout POST without JS or for gateways
     * outside the allowlist; with the layer active, address edits recalc
     * shipping/totals live (update-customer), rate picks go through
     * select-shipping-rate, and the order is placed via POST /checkout —
     * which puts the hand-built checkout under Woo's native checkout rate
     * limiting. Redirect/offline gateways only: the response's
     * payment_result.redirect_url is followed (Mollie hosted, PayPal,
     * COD/invoice → order-received).
     */


    /**
     * Assemble `payment_data` for POST /checkout — the array WooCommerce
     * hands to the gateway's process_payment(). Two sources:
     *
     * 1. Markup. Any input inside the form carrying
     *    data-w4e-payment-data="<key>" contributes its value. Inputs that sit
     *    inside ANOTHER gateway's [data-w4e-payment-fields] region are
     *    skipped, so a layout may render every gateway's fields and still
     *    post only the selected one's.
     *
     * 2. Script. A gateway that has to talk to its provider before the order
     *    exists — create a PaymentIntent, tokenize a card — cannot answer
     *    synchronously. The `woo4etch:checkout-payment-data` event carries
     *    add() and waitUntil() for exactly that:
     *
     *      document.addEventListener('woo4etch:checkout-payment-data', function (e) {
     *        if (e.detail.gateway !== 'my_gateway') return;
     *        e.detail.waitUntil(
     *          createToken().then(function (t) { e.detail.add('my_token', t); })
     *        );
     *      });
     *
     *    A rejected promise aborts the submit and surfaces as a checkout
     *    error, which is the right outcome: no order should be placed with a
     *    payment step that did not complete.
     *
     * Woo4Etch ships no gateway adapter itself. This is the seam one needs,
     * together with the woo4etch/store_api_checkout_gateways filter to put
     * the gateway on the Store API path in the first place.
     */
    function collectPaymentData(form, gateway) {
        var data    = [];
        var add     = function (key, value) {
            if (!key) return;
            data.push({ key: String(key), value: typeof value === 'boolean' ? value : String(value) });
        };
        var waiting = [];

        form.querySelectorAll('[data-w4e-payment-data]').forEach(function (el) {
            if (el.disabled) return;
            var region = el.closest ? el.closest('[data-w4e-payment-fields]') : null;
            if (region && region.getAttribute('data-w4e-payment-fields') !== gateway) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            add(el.getAttribute('data-w4e-payment-data'), el.type === 'checkbox' ? true : el.value);
        });

        document.dispatchEvent(new CustomEvent('woo4etch:checkout-payment-data', {
            detail: {
                gateway: gateway,
                form: form,
                add: add,
                waitUntil: function (p) { if (p && typeof p.then === 'function') waiting.push(p); }
            }
        }));

        return (waiting.length ? Promise.all(waiting) : Promise.resolve()).then(function () {
            return data;
        });
    }

    var ADDRESS_FIELDS = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'];

    function gatewayAllowed(id) {
        var list = cfg.checkoutGateways || [];
        for (var i = 0; i < list.length; i++) {
            var p = list[i];
            if (p.slice(-1) === '*' ? id.indexOf(p.slice(0, -1)) === 0 : id === p) return true;
        }
        return false;
    }

    function readAddress(form, prefix, withEmail) {
        var out = {};
        ADDRESS_FIELDS.forEach(function (f) {
            var el = form.querySelector('[name="' + prefix + '_' + f + '"]');
            if (el) out[f] = el.value;
        });
        if (withEmail) {
            var email = form.querySelector('[name="billing_email"]');
            if (email) out.email = email.value;
        }
        return out;
    }

    function readAddresses(form) {
        var billing  = readAddress(form, 'billing', true);
        var shipDiff = form.querySelector('[name="ship_to_different_address"]');
        var shipping = (shipDiff && shipDiff.checked) ? readAddress(form, 'shipping', false) : readAddress(form, 'billing', false);
        return { billing_address: billing, shipping_address: shipping };
    }

    var customerTimer = null;

    document.addEventListener('change', function (e) {
        var form = e.target.closest ? e.target.closest('form[data-w4e-checkout]') : null;
        if (!form) return;

        // Shipping rate picked → select-shipping-rate, totals re-render.
        // Queued like every write: an edit during a write used to be
        // dropped, leaving the totals behind the form (issue #41).
        if (/^shipping_method/.test(e.target.name || '')) {
            cartWrite(e.target, 'select-shipping-rate', { package_id: 0, rate_id: e.target.value }).catch(fail);
            return;
        }

        // Address-relevant fields → update-customer (debounced): Woo
        // recalculates shipping rates + totals server-side.
        if (/^(billing|shipping)_(country|state|postcode|city|address_1)$|^ship_to_different_address$/.test(e.target.name || '')) {
            window.clearTimeout(customerTimer);
            customerTimer = window.setTimeout(function () {
                cartWrite(form, 'update-customer', readAddresses(form)).catch(fail);
            }, 600);
        }
    });

    // CAPTURE phase: on the assigned checkout page WooCommerce enqueues
    // wc-checkout.js, whose form-level submit handler `return false`s —
    // stopping propagation before a bubble-phase listener would ever run.
    // Capturing on document fires first; when we take the submit we stop
    // the event so the classic AJAX never double-handles it.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute || !form.hasAttribute('data-w4e-checkout')) return;
        if (busy) {
            // Mid-update (address/shipping write in flight): swallow the
            // submit instead of letting it slip through as a classic POST —
            // the user clicks again once the totals settled.
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        var picked  = form.querySelector('[name="payment_method"]:checked');
        var gateway = picked ? picked.value : '';
        if (!gatewayAllowed(gateway)) return; // classic submit stays the path

        e.preventDefault();
        e.stopPropagation();

        var payload = readAddresses(form);
        payload.payment_method = gateway;
        var note = form.querySelector('[name="order_comments"]');
        if (note && note.value) payload.customer_note = note.value;

        // Germanized legal checkboxes: their Store API validation is skipped
        // entirely when this key is absent — send it ALWAYS while Germanized
        // is active. Inputs carry data-w4e-checkbox="<id>" (rendered from
        // {options.checkout.checkboxes}); a checkbox missing from the layout
        // counts as unchecked, so required boxes surface as a visible error
        // instead of being silently skipped.
        if (cfg.gzd) {
            var boxes = [];
            form.querySelectorAll('[data-w4e-checkbox]').forEach(function (el) {
                boxes.push({ id: el.getAttribute('data-w4e-checkbox'), checked: !!el.checked });
            });
            payload.extensions = { 'woocommerce-germanized': { checkboxes: boxes } };
        }

        // Placed through the same queue, so it runs after any address or
        // shipping write that is still pending — never alongside one.
        enqueue(function () {
            return collectPaymentData(form, gateway)
                .then(function (data) {
                    if (data.length) payload.payment_data = data;
                    return api('/checkout', 'POST', payload);
                })
                .then(function (res) {
                    var redirect = res && res.payment_result && res.payment_result.redirect_url;
                    if (redirect) {
                        window.location.assign(redirect);
                        return;
                    }
                    // No redirect target — should not happen for allowlisted
                    // gateways; fall back to a visible error, order state is
                    // recoverable via the account/order pages.
                    notice(i18n.error || 'Something went wrong.', 'error');
                });
        }).catch(function (err) {
            // Swap first (totals/validation state may have moved), THEN
            // show the error — the notices region can live inside a region.
            return refreshRegions().then(function () { fail(err); });
        });
    }, true);
})();
