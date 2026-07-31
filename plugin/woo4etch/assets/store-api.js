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
 * Events: every successful write dispatches `woo4etch:cart-updated`
 * (detail.cart = Store API cart JSON) on document, and — when jQuery +
 * wc-cart-fragments are present — triggers `wc_fragment_refresh` so
 * fragment-based third-party mini-carts stay in sync.
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

    function afterWrite(cart) {
        return refreshRegions().then(function () {
            announce(cart);
            setBusy(false);
        });
    }

    function fail(err) {
        setBusy(false);
        notice(err && err.message ? err.message : (i18n.error || 'Something went wrong.'), 'error');
    }

    /* ---------------- cart page bindings ---------------- */

    var qtyTimer = null;

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.name || !/^cart\[(.+)\]\[qty\]$/.test(input.name) || busy) return;
        var key = input.name.match(/^cart\[(.+)\]\[qty\]$/)[1];
        var quantity = parseInt(input.value, 10);
        if (isNaN(quantity) || quantity < 0) return;
        window.clearTimeout(qtyTimer);
        qtyTimer = window.setTimeout(function () {
            setBusy(true);
            var call = quantity === 0
                ? api('/cart/remove-item', 'POST', { key: key })
                : api('/cart/update-item', 'POST', { key: key, quantity: quantity });
            call.then(afterWrite).catch(fail);
        }, 350);
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[href*="remove_item="], a[href*="remove_coupon="]') : null;
        if (link && !busy) {
            var url;
            try { url = new URL(link.href, window.location.origin); } catch (err) { return; }
            var itemKey = url.searchParams.get('remove_item');
            var coupon = url.searchParams.get('remove_coupon');
            if (!itemKey && !coupon) return;
            e.preventDefault();
            setBusy(true);
            (itemKey
                ? api('/cart/remove-item', 'POST', { key: itemKey })
                : api('/cart/remove-coupon', 'POST', { code: coupon })
            ).then(afterWrite).catch(fail);
            return;
        }

        var couponBtn = e.target.closest ? e.target.closest('[name="apply_coupon"]') : null;
        if (couponBtn && !busy) {
            var form = couponBtn.closest('form');
            var field = form ? form.querySelector('input[name="coupon_code"]') : document.querySelector('input[name="coupon_code"]');
            var code = field ? field.value.trim() : '';
            if (!code) return; // let the classic submit handle empties
            e.preventDefault();
            setBusy(true);
            api('/cart/apply-coupon', 'POST', { code: code })
                .then(function (cart) {
                    // Notices render AFTER the region swap — the notices
                    // region usually lives inside the swapped cart region.
                    return afterWrite(cart).then(function () {
                        notice(i18n.couponApplied || 'Coupon code applied successfully.', 'success');
                    });
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
        if (!form.classList || !form.classList.contains('cart') || busy) return;
        if (!interceptable(form)) return;

        var submitter = e.submitter || form.querySelector('[name="add-to-cart"], [name="buy_now"]');
        if (submitter && 'buy_now' === submitter.name) return; // buy-now redirect flow stays classic

        var idField = form.querySelector('[name="add-to-cart"]') || form.querySelector('[name="product_id"]');
        var id = idField ? parseInt(idField.value, 10) : 0;
        if (!id) return;

        var variationField = form.querySelector('[name="variation_id"]');
        var variationId = variationField ? parseInt(variationField.value, 10) : 0;
        var qtyField = form.querySelector('[name="quantity"]');
        var quantity = qtyField ? Math.max(1, parseInt(qtyField.value, 10) || 1) : 1;

        var variation = [];
        form.querySelectorAll('[name^="attribute_"]').forEach(function (el) {
            if (el.value) variation.push({ attribute: el.name, value: el.value });
        });

        e.preventDefault();
        setBusy(true);
        api('/cart/add-item', 'POST', {
            id: variationId || id,
            quantity: quantity,
            variation: variation
        }).then(function (cart) {
            return afterWrite(cart).then(function () {
                notice(i18n.added || 'Added to your cart.', 'success');
            });
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
        if (!form || busy) return;

        // Shipping rate picked → select-shipping-rate, totals re-render.
        if (/^shipping_method/.test(e.target.name || '')) {
            setBusy(true);
            api('/cart/select-shipping-rate', 'POST', { package_id: 0, rate_id: e.target.value })
                .then(afterWrite).catch(fail);
            return;
        }

        // Address-relevant fields → update-customer (debounced): Woo
        // recalculates shipping rates + totals server-side.
        if (/^(billing|shipping)_(country|state|postcode|city|address_1)$|^ship_to_different_address$/.test(e.target.name || '')) {
            window.clearTimeout(customerTimer);
            customerTimer = window.setTimeout(function () {
                setBusy(true);
                api('/cart/update-customer', 'POST', readAddresses(form)).then(afterWrite).catch(fail);
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
        setBusy(true);

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

        api('/checkout', 'POST', payload)
            .then(function (res) {
                var redirect = res && res.payment_result && res.payment_result.redirect_url;
                if (redirect) {
                    window.location.assign(redirect);
                    return;
                }
                // No redirect target — should not happen for allowlisted
                // gateways; fall back to a visible error, order state is
                // recoverable via the account/order pages.
                setBusy(false);
                notice(i18n.error || 'Something went wrong.', 'error');
            })
            .catch(function (err) {
                // Swap first (totals/validation state may have moved), THEN
                // show the error — the notices region can live inside a region.
                refreshRegions().then(function () { fail(err); });
            });
    }, true);
})();
