/**
 * Woo4Etch bridge: surface WooCommerce templates inside Etch's template hub.
 *
 * Etch's hub UI (Templates.svelte) renders only slugs from its own catalog
 * (site templates + generated CPT/taxonomy entries) — templates whose slug
 * it doesn't recognize (page-cart, page-checkout, order-confirmation,
 * product-search-results, coming-soon) exist in the REST list but are never
 * displayed. Until Etch lists unknown slugs natively (feature request filed),
 * this script appends a native-looking "WooCommerce" group.
 *
 * Native look without native code: the group is built by CLONING Etch's own
 * DOM nodes (navigation header, content column, template card). Svelte's
 * scoped styles ride on a hash class attached to the elements — clones keep
 * it, so all native styling (tiles, hover reveal, spacing) applies as-is.
 * cloneNode() copies no event listeners, so cloned buttons are inert until
 * we wire our own: Edit navigates via Etch's own deep-link scheme
 * (?etch=magic&post_id=…, the same URL its "Edit with Etch" entries use).
 *
 * Deliberately defensive: injected only when the hub renders, idempotent
 * via data-w4e-hub-group, and if Etch's markup changes the clone sources
 * disappear and nothing is injected — no breakage.
 */
(function () {
    if (window.__w4eHubTemplates) return;
    window.__w4eHubTemplates = true;

    var cfg = window.w4eHubTemplates || {};
    var items = cfg.items || [];
    if (!items.length) return;

    function buildCard(cardProto, item) {
        var card = cardProto.cloneNode(true);
        card.setAttribute('data-w4e-hub-item', item.slug);
        card.title = item.slug;

        var name = card.querySelector('.etch-templates__content-item-name');
        if (name) name.textContent = item.name;

        var buttons = card.querySelectorAll('button');
        buttons.forEach(function (button, index) {
            if (index === 0) {
                // The cloned "Edit" button, rewired to Etch's deep link.
                button.textContent = cfg.editLabel || 'Edit';
                button.addEventListener('click', function () {
                    window.location.href = item.url;
                });
            } else {
                // Delete/Reset make no sense here — WooCommerce backfills
                // its plugin default anyway; manage these via wp-admin.
                button.remove();
            }
        });
        return card;
    }

    function inject() {
        var nav = document.querySelector('.etch-templates__navigation');
        var content = document.querySelector('.etch-templates__content');
        if (!nav || !content) return;
        if (content.querySelector('[data-w4e-hub-group]')) return;

        var navProto = nav.querySelector('.etch-templates__navigation-item-wrapper');
        var colProto = content.querySelector('.etch-templates__content-item-wrapper');
        var cardProto = content.querySelector('.etch-templates__content-item');
        if (!navProto || !colProto || !cardProto) return;

        // Navigation header — clone, relabel, drop the "+" popover button
        // (creation stays in wp-admin; these templates always exist).
        var navItem = navProto.cloneNode(true);
        navItem.setAttribute('data-w4e-hub-group', '1');
        navItem.querySelectorAll('button').forEach(function (b) { b.remove(); });
        var label = navItem.querySelector('p');
        if (label) label.textContent = cfg.groupLabel || 'WooCommerce';
        nav.appendChild(navItem);

        // Content column — cloned wrapper + inner, filled with cloned cards.
        var col = colProto.cloneNode(false);
        col.setAttribute('data-w4e-hub-group', '1');
        var innerProto = colProto.querySelector('.etch-templates__content-item-wrapper-inner');
        var inner = innerProto ? innerProto.cloneNode(false) : document.createElement('div');
        col.appendChild(inner);

        items.forEach(function (item) {
            inner.appendChild(buildCard(cardProto, item));
        });
        content.appendChild(col);
    }

    var observer = new MutationObserver(inject);
    observer.observe(document.documentElement, { childList: true, subtree: true });
    inject();
})();
