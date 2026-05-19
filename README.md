# WooCommerce in Etch

A pragmatic guide and template library for building **WooCommerce** shops in **[Etch](https://etchwp.com)** — without relying on the WooCommerce blocks.

Etch doesn't (yet) have native WooCommerce blocks. This repo documents what's needed to bridge the gap: which markup, classes, attributes and hooks WooCommerce actually requires, plus copy-ready Etch HTML templates with Dynamic Keys and a PHP layer for hooks and form logic.

## Contents

- [`WooCommerce-in-Etch-Knowledgebase.md`](./WooCommerce-in-Etch-Knowledgebase.md) — research notes covering the "do I have to use the WooCommerce blocks?" question, accessibility, hook strategy, JS globals, and the final Custom Layouts Guide.
- [`plugin/woo4etch-bridge/`](./plugin/woo4etch-bridge/README.md) — **companion WordPress plugin**. Drop-in shortcodes for PHP-only bits (price, stock, add-to-cart form, hooks, notices, etc.) until Etch supports them natively.
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
| [`15-bridge-plugin.md`](./templates/15-bridge-plugin.md) | Woo4Etch Bridge plugin usage |
| [`functions-snippets.md`](./templates/functions-snippets.md) | Consolidated PHP snippets |

## Each template follows the same structure

1. **When to use**
2. **Preparation** — what needs to exist in your theme / `functions.php`
3. **Etch HTML** — copy-ready markup with `{item.*}` Dynamic Keys
4. **Required classes / attributes** — what you must not drop
5. **Hooks used** — compact table
6. **PHP layer** — snippets for hooks, form logic, enqueues
7. **Common mistakes**
8. **Test checklist**

## Recommended setup

Place the PHP snippets in a dedicated mu-plugin under `wp-content/mu-plugins/wc-customizations.php` rather than in your theme's `functions.php`. MU-plugins survive theme switches, load earlier, and keep your customizations separate from theme code.

Skeleton:

```php
<?php
/**
 * Plugin Name: WC Customizations
 * Description: WooCommerce hooks, filters, and customizations for the Etch frontend.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

// Snippets from /templates/functions-snippets.md go here
```

## Status

Work in progress. Sections cover the most common areas of a WooCommerce shop; specialized areas (subscriptions, bookings, memberships) are not yet covered.

## License

MIT — see [`LICENSE`](./LICENSE) (add a license file before publishing).

## Credits

Originally compiled from a research session on WooCommerce / Etch integration. Sources are listed at the end of [`WooCommerce-in-Etch-Knowledgebase.md`](./WooCommerce-in-Etch-Knowledgebase.md#sources-quoted-from-the-perplexity-session).
