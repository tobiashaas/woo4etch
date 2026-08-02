# WooCommerce in Etch

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A pragmatic guide and template library for building **WooCommerce** shops in **[Etch](https://etchwp.com/?aff=06de86e5)** — without relying on the WooCommerce blocks.

**Open source:** free to use, modify, and share — including in commercial projects. See [LICENSE](LICENSE) and [CONTRIBUTING.md](CONTRIBUTING.md).

Etch doesn't (yet) have native WooCommerce blocks. This repo documents what's needed to bridge the gap: which markup, classes, attributes and hooks WooCommerce actually requires, plus copy-ready Etch HTML templates with Dynamic Keys and a PHP layer for hooks and form logic.

## Contents

- [`WooCommerce-in-Etch-Knowledgebase.md`](./WooCommerce-in-Etch-Knowledgebase.md) — research notes covering the "do I have to use the WooCommerce blocks?" question, accessibility, hook strategy, JS globals, and the final Custom Layouts Guide.
- [`plugin/woo4etch/`](./plugin/woo4etch/README.md) — **Woo4Etch plugin**. Shortcodes plus `includes/customizations.php` for hook snippets from the templates.
- [`templates/`](./templates/00-README.md) — ready-to-use templates per WooCommerce area:

| File | Area |
|---|---|
| [`00-README.md`](./templates/00-README.md) | Conventions and shared foundations |
| [`01-single-product-simple.md`](./templates/01-single-product-simple.md) | Single product without variations |
| [`02-single-product-variable.md`](./templates/02-single-product-variable.md) | Single product with variations |
| [`03-product-archive.md`](./templates/03-product-archive.md) | Shop / category archive |
| [`04-cart.md`](./templates/04-cart.md) | Cart page |
| [`05-mini-cart.md`](./templates/05-mini-cart.md) | Header mini-cart with live update |
| [`06-checkout.md`](./templates/06-checkout.md) | Checkout (classic shortcode) |
| [`07-account.md`](./templates/07-account.md) | My Account |
| [`08-thank-you.md`](./templates/08-thank-you.md) | Order received / thank-you |
| [`09-emails.md`](./templates/09-emails.md) | Transactional email templates |
| [`10-etch-context-and-templates.md`](./templates/10-etch-context-and-templates.md) | **Read first.** `this.*` vs `item.*`, Templates vs Pages, loop syntax |
| [`11-conditional-tags-and-product-api.md`](./templates/11-conditional-tags-and-product-api.md) | `is_*` tags + `$product` reference |
| [`12-store-api-and-rest.md`](./templates/12-store-api-and-rest.md) | Store API + custom REST for AJAX |
| [`13-useful-snippets.md`](./templates/13-useful-snippets.md) | Curated practical snippets |
| [`14-visual-hook-guides.md`](./templates/14-visual-hook-guides.md) | Business Bloomer hook diagrams |
| [`15-woo4etch-plugin.md`](./templates/15-woo4etch-plugin.md) | Woo4Etch plugin (shortcodes + install) |
| [`16-one-click-checkout.md`](./templates/16-one-click-checkout.md) | One-click checkout (Buy Now → checkout → thank-you) |
| [`17-components.md`](./templates/17-components.md) | Component blueprints with pre-wired Woo markup |
| [`functions-snippets.md`](./templates/functions-snippets.md) | Consolidated PHP snippets |
| [`etch-copy/`](./templates/etch-copy/README.md) | Copy/paste Etch snippets — paste a ready-made layout (e.g. the full cart) into the builder |

## Each template follows the same structure

1. **When to use**
2. **Preparation** — what needs to exist in your theme / `functions.php`
3. **Etch HTML** — copy-ready markup with Dynamic Keys (`{this.*}` on Single templates, `{item.*}` inside loops)
4. **Required classes / attributes** — what you must not drop
5. **Hooks used** — compact table
6. **PHP layer** — snippets for hooks, form logic, enqueues
7. **Common mistakes**
8. **Test checklist**

## Recommended setup

Install **one** Woo4Etch package from [`plugin/woo4etch/`](./plugin/woo4etch/):

| Install as | Path | Notes |
|---|---|---|
| **Regular plugin** (usual) | `wp-content/plugins/woo4etch/` | Activate under **Plugins** |
| **MU-plugin** (optional) | `wp-content/mu-plugins/woo4etch/` | Same folder; loads automatically, no activation |

Copy PHP snippets from [`templates/functions-snippets.md`](./templates/functions-snippets.md) into **`includes/customizations.php`** inside that folder — not into a second plugin or loose `functions.php`, unless you prefer the theme.

Admin page: **Etch → Woo4Etch** (or **WooCommerce → Woo4Etch** without Etch), organized in four tabs — **Overview** (shop status), **Layouts**, **Settings** and the **Shortcodes** reference. WooCommerce's own template types (thank-you, product search results, coming soon) are managed inside Etch itself: the plugin adds a **"WooCommerce" group** to the builder's template hub that opens them — or creates them on click if they don't exist yet.

## Ready-made layouts — one-click shop

The plugin ships complete, editable Etch layouts for every shop area: **shop archive** (with a working filter sidebar — category counts, dual-handle price slider — and a category slider), **category archive** (SEO intro copy + `{term.description}` + filtered grid), **single product** (gallery, type-aware add-to-cart, notices), **cart** (quantity steppers, coupon, cross-sells, empty state), **header mini-cart** (hover dropdown with a proper empty state), **My Account** (login gate, dashboard, orders) and **thank-you**.

On the **Layouts** tab (Etch → Woo4Etch), **Add to page/template** installs each layout straight where it renders — the plugin resolves WooCommerce's page assignments and the area's Etch template, appends without ever touching existing content, and refuses double-inserts. **Copy JSON** exports Etch's native paste format instead (also committed under [`templates/etch-copy/`](./templates/etch-copy/README.md)). Everything renders live in the builder via the plugin's dynamic-data bridges and is restyled through plain classes — existing styles with the same selectors are reused, never overwritten.

Optional frontend enhancements (Settings / automatic): variation pills + quantity stepper, WooCommerce gallery scripts, price-range slider — their styling ships **as Etch class records inside the layouts** (editable in the builder's style panel), never as plugin stylesheets. Third-party compatibility notes: [`templates/15-woo4etch-plugin.md`](./templates/15-woo4etch-plugin.md#third-party-woocommerce-plugins).

**Updates:** regular plugin installs receive updates from [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases) via **Dashboard → Updates**. Version history: [`CHANGELOG.md`](./CHANGELOG.md). See [`.github/RELEASE.md`](.github/RELEASE.md) for the maintainer release flow.

## Status

Work in progress. Sections cover the most common areas of a WooCommerce shop; specialized areas (subscriptions, bookings, memberships) are not yet covered.

## License

Released under the **[MIT License](LICENSE)**.

You may use, copy, modify, merge, publish, distribute, sublicense, and sell this work — for free, for any purpose — as long as you include the copyright notice and license text in copies or substantial portions.

The [`woo4etch`](plugin/woo4etch/) plugin is covered by the same license.

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md).

## References

Sources and references are listed at the end of [`WooCommerce-in-Etch-Knowledgebase.md`](./WooCommerce-in-Etch-Knowledgebase.md#sources).
