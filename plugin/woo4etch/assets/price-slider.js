/**
 * Woo4Etch price-filter slider — progressive enhancement for the archive
 * filter form. Any form containing `input[name="min_price"]` AND
 * `input[name="max_price"]` gets a dual-handle range slider (two overlapping
 * native <input type="range"> elements with a highlighted active segment)
 * inserted above the number fields; slider and fields stay in sync both
 * ways. Submission is untouched — WooCommerce's native GET filtering.
 *
 * Upper bound comes from `data-w4e-price-max` on the form (the shipped
 * layout binds it to {options.shop_max_price_raw}); fallback is the larger
 * of the current values or 100. No dependencies, no styling assumptions:
 * tokens with plain fallbacks, plain classes (.w4e-range*) to override.
 */
(function () {
    if (window.__w4ePriceSlider) return;
    window.__w4ePriceSlider = true;

    var i18n = window.w4ePriceSliderI18n || {};
    var STR = {
        min: i18n.min || 'Minimum price',
        max: i18n.max || 'Maximum price'
    };

    var CSS = ''
        + '.w4e-range{position:relative;height:28px;margin:6px 2px 2px;}'
        + '.w4e-range__track{position:absolute;inset-inline:0;top:50%;height:4px;transform:translateY(-50%);border-radius:999px;background:var(--base-light,#e6e7eb);}'
        + '.w4e-range__fill{position:absolute;top:50%;height:4px;transform:translateY(-50%);border-radius:999px;background:var(--primary,#111827);}'
        + '.w4e-range input[type="range"]{position:absolute;inset-inline:0;top:0;width:100%;height:28px;margin:0;background:transparent;-webkit-appearance:none;appearance:none;pointer-events:none;}'
        + '.w4e-range input[type="range"]::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;pointer-events:auto;width:20px;height:20px;border-radius:999px;background:#fff;border:2px solid var(--primary,#111827);box-shadow:0 1px 4px rgba(0,0,0,.15);cursor:grab;}'
        + '.w4e-range input[type="range"]::-moz-range-thumb{pointer-events:auto;width:16px;height:16px;border-radius:999px;background:#fff;border:2px solid var(--primary,#111827);box-shadow:0 1px 4px rgba(0,0,0,.15);cursor:grab;}'
        + '.w4e-range input[type="range"]:focus-visible::-webkit-slider-thumb{outline:2px solid var(--primary,#111827);outline-offset:2px;}'
        + '.w4e-range input[type="range"]:focus-visible::-moz-range-thumb{outline:2px solid var(--primary,#111827);outline-offset:2px;}';

    function injectCss() {
        // With Etch active the slider styles ship as an Etch class record
        // (nested in the price form's record, editable in the builder) — the
        // inline CSS below is only the fallback for non-Etch installs.
        if (i18n.stylesInEtch) return;
        if (document.getElementById('w4e-price-slider-css')) return;
        var s = document.createElement('style');
        s.id = 'w4e-price-slider-css';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function enhance(form) {
        var minField = form.querySelector('input[name="min_price"]');
        var maxField = form.querySelector('input[name="max_price"]');
        if (!minField || !maxField || form.hasAttribute('data-w4e-slider')) return;
        form.setAttribute('data-w4e-slider', '1');

        var bound = parseFloat(form.getAttribute('data-w4e-price-max'))
            || Math.max(parseFloat(maxField.value) || 0, parseFloat(minField.value) || 0, 100);
        bound = Math.ceil(bound);

        var wrap = document.createElement('div');
        wrap.className = 'w4e-range';
        var track = document.createElement('div');
        track.className = 'w4e-range__track';
        var fill = document.createElement('div');
        fill.className = 'w4e-range__fill';

        var mkRange = function (label) {
            var r = document.createElement('input');
            r.type = 'range';
            r.min = '0';
            r.max = String(bound);
            r.step = '1';
            r.setAttribute('aria-label', label);
            return r;
        };
        var lo = mkRange(STR.min);
        var hi = mkRange(STR.max);
        lo.value = minField.value !== '' ? minField.value : '0';
        hi.value = maxField.value !== '' ? maxField.value : String(bound);

        wrap.appendChild(track);
        wrap.appendChild(fill);
        wrap.appendChild(lo);
        wrap.appendChild(hi);

        // Above the number fields (their row, if the layout wraps them).
        var row = minField.closest('div') && minField.closest('div').contains(maxField)
            ? minField.closest('div')
            : minField;
        row.parentNode.insertBefore(wrap, row);

        function paint() {
            var a = Number(lo.value), b = Number(hi.value);
            fill.style.left = (a / bound * 100) + '%';
            fill.style.right = (100 - b / bound * 100) + '%';
        }
        function fromSlider() {
            // keep handles ordered
            if (Number(lo.value) > Number(hi.value)) {
                if (document.activeElement === lo) hi.value = lo.value;
                else lo.value = hi.value;
            }
            minField.value = Number(lo.value) > 0 ? lo.value : '';
            maxField.value = Number(hi.value) < bound ? hi.value : '';
            paint();
        }
        function fromFields() {
            var a = parseFloat(minField.value), b = parseFloat(maxField.value);
            lo.value = isNaN(a) ? '0' : String(Math.min(Math.max(a, 0), bound));
            hi.value = isNaN(b) ? String(bound) : String(Math.min(Math.max(b, 0), bound));
            paint();
        }
        lo.addEventListener('input', fromSlider);
        hi.addEventListener('input', fromSlider);
        minField.addEventListener('input', fromFields);
        maxField.addEventListener('input', fromFields);
        paint();
    }

    function init() {
        var forms = [];
        document.querySelectorAll('input[name="min_price"]').forEach(function (input) {
            var form = input.closest('form');
            if (form && forms.indexOf(form) === -1) forms.push(form);
        });
        if (!forms.length) return;
        injectCss();
        forms.forEach(enhance);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
