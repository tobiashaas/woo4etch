/**
 * Woo4Etch bridge: surface WooCommerce templates inside Etch's template hub.
 *
 * Etch's hub UI (Templates.svelte) renders only slugs from its own catalog
 * (site templates + generated CPT/taxonomy entries) — templates whose slug
 * it doesn't recognize (page-cart, page-checkout, order-confirmation,
 * product-search-results, coming-soon) exist in the REST list but are never
 * displayed. Until Etch lists unknown slugs natively (feature request filed),
 * this script appends a "WooCommerce" group to the hub with edit links that
 * open each template in the builder (?etch=magic&post_id=…) — the same URL
 * scheme Etch's own "Edit with Etch" entry points use.
 *
 * Deliberately defensive: injected only when .etch-templates__content
 * appears, marked with data-w4e-hub-group to stay idempotent, and if Etch's
 * markup changes the observer simply never fires — nothing breaks.
 */
(function () {
    if (window.__w4eHubTemplates) return;
    window.__w4eHubTemplates = true;

    var cfg = window.w4eHubTemplates || {};
    var items = cfg.items || [];
    if (!items.length) return;

    var CSS = ''
        + '.w4e-hub-group{margin-top:18px;padding-top:14px;border-top:1px solid rgba(255,255,255,.08);}'
        + '.w4e-hub-group__title{margin:0 0 10px;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;opacity:.55;}'
        + '.w4e-hub-group__list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0;padding:0;list-style:none;}'
        + '.w4e-hub-group__item a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:inherit;text-decoration:none;font-size:12px;line-height:1.3;}'
        + '.w4e-hub-group__item a:hover{border-color:rgba(255,255,255,.35);background:rgba(255,255,255,.04);}'
        + '.w4e-hub-group__edit{opacity:.55;font-size:11px;white-space:nowrap;}';

    function inject(content) {
        if (content.querySelector('[data-w4e-hub-group]')) return;

        if (!document.getElementById('w4e-hub-css')) {
            var style = document.createElement('style');
            style.id = 'w4e-hub-css';
            style.textContent = CSS;
            document.head.appendChild(style);
        }

        var group = document.createElement('div');
        group.className = 'w4e-hub-group';
        group.setAttribute('data-w4e-hub-group', '1');

        var title = document.createElement('p');
        title.className = 'w4e-hub-group__title';
        title.textContent = cfg.groupLabel || 'WooCommerce';
        group.appendChild(title);

        var list = document.createElement('ul');
        list.className = 'w4e-hub-group__list';
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'w4e-hub-group__item';
            var a = document.createElement('a');
            a.href = item.url;
            a.title = item.slug;
            var name = document.createElement('span');
            name.textContent = item.name;
            var edit = document.createElement('span');
            edit.className = 'w4e-hub-group__edit';
            edit.textContent = cfg.editLabel || 'Edit';
            a.appendChild(name);
            a.appendChild(edit);
            li.appendChild(a);
            list.appendChild(li);
        });
        group.appendChild(list);
        content.appendChild(group);
    }

    var observer = new MutationObserver(function () {
        document.querySelectorAll('.etch-templates__content').forEach(inject);
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
    document.querySelectorAll('.etch-templates__content').forEach(inject);
})();
