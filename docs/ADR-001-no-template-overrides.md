# ADR-001 — No WooCommerce template overrides

**Status:** Accepted · **Date:** 2026-06-12

## Context

Page builders like Bricks override WooCommerce's PHP template files (`woocommerce/cart/cart.php` etc.). Those copies go stale with every WooCommerce release, producing the well-known admin warning *"Your theme contains outdated copies of some WooCommerce template files"* and forcing the theme/builder author to re-sync templates on every release.

At the same time, Woo4Etch users must be able to customise their layouts in Etch without limits — and without updates ever overwriting their work.

## Decision

**Woo4Etch never overrides WooCommerce PHP template files.** Everything is built on hooks, shortcodes, and the dynamic-data bridges, with a strict separation of layers:

| Layer | Owner | Update behaviour |
|---|---|---|
| **PHP logic** (shortcodes, hooks, WC integration, dynamic-data bridges) | Woo4Etch plugin | Auto-updates via GitHub Releases — transparent to the user |
| **Etch layout** (HTML structure, classes, design) | User | Belongs to the user; never changed automatically |
| **Required Woo attributes** (`form.cart`, `name="add-to-cart"`, `name="quantity"`, `.single_add_to_cart_button`, …) | Documentation | The only contract between user markup and WC server logic; stable for 10+ years. Breaking changes (rare) are highlighted in the changelog and README |

## Consequences

**Positive**

- No "outdated templates" warning; no template re-sync on WooCommerce releases.
- Plugin updates (new shortcodes, fixes) reach users automatically without touching layouts.
- User layouts in Etch are infinitely customisable and update-safe.
- WooCommerce core logic (cart maths, checkout processing, stock, coupons) updates independently of the layout.

**Trade-off**

- Layout improvements in the documentation templates do not reach existing users automatically — markup changes are adopted deliberately by the user. That is intentional: the layout belongs to the user.

## Related notes

- The WooCommerce status-page warnings ("page does not contain the [woocommerce_cart] shortcode") are informational checks only. Cart/checkout logic runs server-side regardless; with correctly attributed Etch markup these warnings are safe to ignore.
