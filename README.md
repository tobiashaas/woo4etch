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
| [`10-etch-context-and-templates.md`](./templates/10-etch-context-and-templates.md) | **Read first.** `this.*` vs `item.*`, Templates vs Pages, loop syntax |
| [`01-single-product-simple.md`](./templates/01-single-product-simple.md) | Single product without variations |
| [`02-single-product-variable.md`](./templates/02-single-product-variable.md) | Single product with variations |
| [`03-product-archive.md`](./templates/03-product-archive.md) | Shop / category archive |
| [`04-cart.md`](./templates/04-cart.md) | Cart page |
| [`05-mini-cart.md`](./templates/05-mini-cart.md) | Header mini-cart with live update |
| [`06-checkout.md`](./templates/06-checkout.md) | Checkout (classic shortcode) |
| [`07-account.md`](./templates/07-account.md) | My Account |
| [`08-thank-you.md`](./templates/08-thank-you.md) | Order received / thank-you |
| [`09-emails.md`](./templates/09-emails.md) | Transactional email templates |
| [`11-conditional-tags-and-product-api.md`](./templates/11-conditional-tags-and-product-api.md) | `is_*` tags + `$product` reference |
| [`12-store-api-and-rest.md`](./templates/12-store-api-and-rest.md) | Store API + custom REST for AJAX |
| [`13-useful-snippets.md`](./templates/13-useful-snippets.md) | Curated practical snippets |
| [`14-visual-hook-guides.md`](./templates/14-visual-hook-guides.md) | Business Bloomer hook diagrams |
| [`15-woo4etch-plugin.md`](./templates/15-woo4etch-plugin.md) | Woo4Etch plugin (shortcodes + install) |
| [`functions-snippets.md`](./templates/functions-snippets.md) | Consolidated PHP snippets |

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

Admin shortcode reference: **Etch → Woo4Etch** (or **WooCommerce → Woo4Etch** without Etch).

**Updates:** regular plugin installs receive updates from [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases) via **Dashboard → Updates**. See [`.github/RELEASE.md`](.github/RELEASE.md) for the maintainer release flow.

## Status

Work in progress. Sections cover the most common areas of a WooCommerce shop; specialized areas (subscriptions, bookings, memberships) are not yet covered.

## License

Released under the **[MIT License](LICENSE)**.

You may use, copy, modify, merge, publish, distribute, sublicense, and sell this work — for free, for any purpose — as long as you include the copyright notice and license text in copies or substantial portions.

The [`woo4etch`](plugin/woo4etch/) plugin is covered by the same license.

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md).

## Credits

Originally compiled from a research session on WooCommerce / Etch integration. Sources are listed at the end of [`WooCommerce-in-Etch-Knowledgebase.md`](./WooCommerce-in-Etch-Knowledgebase.md#sources-quoted-from-the-perplexity-session).
