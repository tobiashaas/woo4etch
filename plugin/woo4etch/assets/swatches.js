/**
 * Woo4Etch — variation swatch sync.
 *
 * The swatch markup is built entirely in Etch (loop over the attribute terms);
 * this script only bridges clicks to the native attribute <select> inside
 * form.variations_form, so WooCommerce's variation logic (price, stock,
 * variation_id) keeps working untouched.
 *
 * Expected swatch markup (anything clickable):
 *   <button type="button"
 *           data-w4e-swatch
 *           data-attribute="attribute_pa_color"
 *           data-value="blue">…</button>
 *
 * The selected swatch gets the class `is-selected` (style it in Etch) and
 * aria-pressed="true". Woo's "Clear" link resets all swatches in the same form.
 * All lookups are scoped to the owning form so multiple variation forms on one
 * page (quick views, related products) never bleed into each other.
 */
(function () {
	'use strict';

	function swatchesFor(form, attribute) {
		return form.querySelectorAll('[data-w4e-swatch][data-attribute="' + attribute + '"]');
	}

	function markSelected(form, attribute, active) {
		swatchesFor(form, attribute).forEach(function (el) {
			var selected = el === active;
			el.classList.toggle('is-selected', selected);
			el.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
	}

	function syncSelect(swatch) {
		var attribute = swatch.getAttribute('data-attribute');
		var value = swatch.getAttribute('data-value') || '';
		if (!attribute) {
			return;
		}

		var form = swatch.closest('form.variations_form') || document.querySelector('form.variations_form');
		if (!form) {
			return;
		}

		var select = form.querySelector('select[name="' + attribute + '"]');
		if (!select) {
			return;
		}

		select.value = value;

		// Woo's variation JS listens through jQuery; fall back to a native event.
		if (window.jQuery) {
			window.jQuery(select).trigger('change');
		} else {
			select.dispatchEvent(new Event('change', { bubbles: true }));
		}

		markSelected(form, attribute, value === '' ? null : swatch);
	}

	document.addEventListener('click', function (event) {
		var swatch = event.target.closest('[data-w4e-swatch]');
		if (!swatch) {
			return;
		}
		event.preventDefault();
		syncSelect(swatch);
	});

	// jQuery-side bridges. The script ships without a jquery dependency (simple
	// product pages stay jQuery-free), so jQuery may load after us — bind now
	// when available, otherwise once the DOM (and all footer scripts) are in.
	function bindJQueryBridges() {
		if (!window.jQuery) {
			return;
		}

		// Woo's "Clear" link fires reset_data on the form — unselect swatches
		// in that specific form only (scoped to avoid cross-form bleed).
		window.jQuery(document).on('reset_data', 'form.variations_form', function () {
			var form = this;
			form.querySelectorAll('[data-w4e-swatch].is-selected').forEach(function (el) {
				el.classList.remove('is-selected');
				el.setAttribute('aria-pressed', 'false');
			});
		});

		// Live price sync: mirror the chosen variation's price into every
		// element marked data-w4e-variation-price (e.g. the layout's top price
		// row) and restore the original (range) price on reset_data. Woo only
		// renders the variation price inside the form
		// (.woocommerce-variation-price); the page's main price element stays
		// static without this bridge.
		var priceTargets = function () {
			return document.querySelectorAll('[data-w4e-variation-price]');
		};
		window.jQuery(document).on('found_variation', 'form.variations_form', function (event, variation) {
			if (!variation || !variation.price_html) {
				return; // Woo omits price_html when all variations cost the same.
			}
			priceTargets().forEach(function (el) {
				if (!el.hasAttribute('data-w4e-original-price')) {
					el.setAttribute('data-w4e-original-price', el.innerHTML);
				}
				el.innerHTML = variation.price_html;
			});
		});
		window.jQuery(document).on('reset_data', 'form.variations_form', function () {
			priceTargets().forEach(function (el) {
				if (el.hasAttribute('data-w4e-original-price')) {
					el.innerHTML = el.getAttribute('data-w4e-original-price');
				}
			});
		});
	}

	if (window.jQuery) {
		bindJQueryBridges();
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindJQueryBridges);
	}
})();
