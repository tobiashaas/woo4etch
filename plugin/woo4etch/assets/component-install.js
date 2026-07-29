/**
 * Creates the "Woo Notices" Etch component through Etch's public scripting
 * API (window.etch — https://docs.etchwp.com/public-api/components) on a
 * builder load after the admin opted in on the Woo4Etch page.
 *
 * The API runtime only exists inside the builder, so this script is enqueued
 * exclusively on ?etch=magic requests while an install is pending
 * (Woo4Etch_Components). All mutations go through Etch's guarded paths
 * (validation, undo/redo); the outcome is reported back via admin-ajax so
 * the admin page can show the component id or the error.
 */
(function () {
    'use strict';

    var cfg = window.woo4etchComponentInstall;
    if (!cfg || !cfg.pending) {
        return;
    }

    function findEtch() {
        try {
            if (window.etch && window.etch.components) {
                return window.etch;
            }
        } catch (e) { /* no-op */ }
        try {
            // The canvas may run in a same-origin frame of the builder shell.
            if (window.parent && window.parent !== window && window.parent.etch && window.parent.etch.components) {
                return window.parent.etch;
            }
        } catch (e) { /* cross-origin — not the builder shell */ }
        return null;
    }

    function report(data) {
        var body = new window.URLSearchParams();
        body.append('action', 'woo4etch_component_result');
        body.append('_ajax_nonce', cfg.nonce);
        Object.keys(data).forEach(function (k) {
            body.append(k, data[k]);
        });
        return window.fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        });
    }

    function blocks() {
        // Authoring shape per docs.etchwp.com/public-api/types-reference.
        return [{
            type: 'etch/element',
            tag: 'div',
            attributes: { class: 'w4e-notices' },
            children: [{
                type: 'etch/raw-html',
                content: cfg.shortcode,
                unsafe: cfg.shortcode
            }]
        }];
    }

    function install(etch) {
        Promise.resolve().then(function () {
            var existing = (etch.components.list() || []).find(function (c) {
                return c.key === cfg.key;
            });
            if (existing) {
                return existing.id; // already there — just record the id.
            }
            return etch.components.createAsync(cfg.name).then(function (id) {
                return etch.components.updateAsync(id, {
                    key: cfg.key,
                    description: cfg.description,
                    blocks: blocks()
                }).then(function () {
                    return id;
                });
            });
        }).then(function (id) {
            return report({ status: 'installed', component_id: String(id) });
        }).catch(function (err) {
            return report({
                status: 'error',
                message: (err && err.message) ? String(err.message) : String(err)
            });
        });
    }

    var started = Date.now();
    (function wait() {
        var etch = findEtch();
        if (etch) {
            return install(etch);
        }
        // Older Etch without the public API: give up quietly, the flag stays
        // pending and the admin page keeps saying "waiting for a builder visit".
        if (Date.now() - started > 15000) {
            return;
        }
        window.setTimeout(wait, 200);
    })();
})();
