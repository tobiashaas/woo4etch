/**
 * Woo4Etch product-page widget: variation selects → pill buttons, quantity
 * input → −/+ stepper. Pure progressive enhancement over WooCommerce's native
 * markup — zero extra Etch markup needed, and Woo's variation JS stays the
 * source of truth (a pill click sets the native select's value and dispatches
 * a bubbled `change` event, so price/stock/variation_id keep working
 * untouched). Scoped per form.cart; works for simple + variable products.
 *
 * Relationship to swatches.js: swatches.js bridges *hand-built* swatch markup
 * ([data-w4e-swatch] elements you lay out in Etch) into the native selects;
 * pills.js is the inverse — it auto-builds pills *from* the native selects.
 * Both feed the same native `change` event. Use one or the other per form.
 *
 * Styling uses ACSS-style tokens with plain fallbacks, so non-token sites
 * degrade gracefully. Overridable: all rules are plain classes (.w4e-pill,
 * .w4e-qty, …) injected as a <style id="w4e-pills-css"> you can out-specify.
 *
 * i18n: localized via wp_localize_script as `w4ePillsI18n`
 * { selectLabel: 'Choose %s', decrease: 'Decrease quantity', increase: 'Increase quantity' }.
 */
(function () {
    if (window.__w4ePillsJs) return;
    window.__w4ePillsJs = true;

    var i18n = window.w4ePillsI18n || {};
    var STR = {
        selectLabel: i18n.selectLabel || 'Choose %s',
        decrease:    i18n.decrease || 'Decrease quantity',
        increase:    i18n.increase || 'Increase quantity'
    };

    var CSS = ''
        + '.w4e-pills{display:flex;flex-wrap:wrap;gap:var(--space-xs,.5rem);}'
        + '.w4e-pill{font-family:var(--text-font-family,inherit);font-size:var(--text-s,.9375rem);font-weight:500;color:var(--text-dark,#16181d);background:var(--base-ultra-light,#f6f6f7);border:1px solid var(--base-light,#d9dbe0);border-radius:calc(var(--radius,10px) / 2);padding:calc(var(--space-xs,.5rem) / 1.5) var(--space-s,.85rem);cursor:pointer;}'
        + '.w4e-pill:hover{border-color:var(--primary,currentColor);}'
        + '.w4e-pill.is-selected{background:var(--primary,#111827);border-color:var(--primary,#111827);color:#fff;}'
        + '.w4e-pill:focus-visible{outline:2px solid var(--primary,#111827);outline-offset:2px;}'
        + '.variations_form .variations select[data-w4e-pills]{position:absolute;inline-size:1px;block-size:1px;clip-path:inset(50%);overflow:hidden;}'
        + '.variations_form .variations,.variations_form .variations tbody,.variations_form .variations tr,.variations_form .variations th,.variations_form .variations td{display:block;padding:0;border:0;}'
        + '.variations_form .variations tr{margin-block-end:var(--space-s,.85rem);}'
        + '.variations_form .variations th.label{margin-block-end:calc(var(--space-xs,.5rem) / 2);font-family:var(--text-font-family,inherit);font-size:var(--text-xs,.8125rem);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-dark,#16181d);}'
        + '.variations_form .reset_variations{font-size:var(--text-xs,.8125rem);color:var(--text-dark-muted,#6b7280);}'
        + '.w4e-qty{display:inline-flex;align-items:stretch;border:1px solid var(--base-light,#d9dbe0);border-radius:var(--radius,10px);overflow:hidden;background:var(--base-ultra-light,#f6f6f7);}'
        + '.w4e-qty input.qty{border:0;border-radius:0;text-align:center;inline-size:3.5rem;background:transparent;}'
        + '.w4e-qty__btn{border:0;background:transparent;color:var(--text-dark,#16181d);font-size:var(--text-m,1.0625rem);font-weight:600;inline-size:2.4rem;cursor:pointer;}'
        + '.w4e-qty__btn:hover{background:var(--base,#e8e9ec);}';

    function injectCss() {
        if (document.getElementById('w4e-pills-css')) return;
        var s = document.createElement('style');
        s.id = 'w4e-pills-css';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function labelFor(select) {
        // Prefer the row's <th class="label"> text (Woo's attribute label);
        // fall back to the select's id/name.
        var row   = select.closest('tr');
        var label = row && row.querySelector('th.label');
        return (label && label.textContent.trim()) || select.id || select.name;
    }

    function buildPills(form) {
        form.querySelectorAll('.variations select[name^="attribute_"]').forEach(function (select) {
            if (select.hasAttribute('data-w4e-pills')) return;
            select.setAttribute('data-w4e-pills', '1');
            var wrap = document.createElement('div');
            wrap.className = 'w4e-pills';
            wrap.setAttribute('role', 'group');
            wrap.setAttribute('aria-label', STR.selectLabel.replace('%s', labelFor(select)));
            Array.prototype.forEach.call(select.options, function (opt) {
                if (!opt.value) return;
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'w4e-pill';
                b.textContent = opt.textContent.trim();
                b.setAttribute('data-value', opt.value);
                b.setAttribute('aria-pressed', 'false');
                b.addEventListener('click', function () {
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                wrap.appendChild(b);
            });
            select.insertAdjacentElement('afterend', wrap);
            var sync = function () {
                wrap.querySelectorAll('.w4e-pill').forEach(function (p) {
                    var selected = p.getAttribute('data-value') === select.value;
                    p.classList.toggle('is-selected', selected);
                    p.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
            };
            select.addEventListener('change', sync);
            sync();
        });
    }

    function buildStepper(scope) {
        // name="quantity" (product forms) and cart[<key>][qty] (cart rows) are
        // the Woo contract names every form carries; the classic .quantity/.qty
        // classes are optional in hand-built Etch forms.
        scope.querySelectorAll('input[name="quantity"], input[name^="cart["]').forEach(function (input) {
            if (input.closest('.w4e-qty')) return;
            if (input.type === 'hidden') return; // sold-individually renders a hidden qty
            if (input.type !== 'number' && input.type !== 'text') return;
            var box = document.createElement('span');
            box.className = 'w4e-qty';
            input.parentNode.insertBefore(box, input);
            var minus = document.createElement('button');
            minus.type = 'button'; minus.className = 'w4e-qty__btn'; minus.textContent = '−';
            minus.setAttribute('aria-label', STR.decrease);
            var plus = document.createElement('button');
            plus.type = 'button'; plus.className = 'w4e-qty__btn'; plus.textContent = '+';
            plus.setAttribute('aria-label', STR.increase);
            box.appendChild(minus); box.appendChild(input); box.appendChild(plus);
            var step = function (dir) {
                var s = Number(input.step) || 1, min = Number(input.min) || 1, max = Number(input.max) || Infinity;
                var v = (Number(input.value) || min) + dir * s;
                input.value = Math.min(max, Math.max(min, v));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };
            minus.addEventListener('click', function () { step(-1); });
            plus.addEventListener('click', function () { step(1); });
        });
    }

    function init() {
        injectCss();
        document.querySelectorAll('form.cart').forEach(function (form) {
            buildPills(form);
            buildStepper(form);
        });
        // Cart page: same stepper on the line-item quantity fields. The
        // bubbled change event also re-enables Woo's disabled-until-change
        // "Update cart" button (its listener: .woocommerce-cart-form
        // .cart_item :input).
        document.querySelectorAll('form.woocommerce-cart-form').forEach(function (form) {
            buildStepper(form);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
