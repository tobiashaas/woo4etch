# Etch copy/paste snippets

Ready-made layouts in **Etch's native copy/paste format**. Build a whole area in
seconds: copy a file's contents, then paste it straight into the Etch builder.

> **Easier route:** the Woo4Etch plugin can install all of these for you. Open
> **Etch → Woo4Etch → Ready-made layouts** and click **Add to page/template** —
> the layout is inserted straight where it renders (WooCommerce's assigned page
> or the area's Etch template; append-only, never double-inserts) and its
> classes land in Etch's style system. The **Copy JSON** button there does the
> same as copying a file from this folder. These files are generated from the
> plugin's layout definitions via `tools/generate-etch-copy.php` — edit there,
> not here.

## How to use (manual paste)

1. Open the JSON file (e.g. [`cart.json`](./cart.json)) and copy its **entire contents**.
2. In the Etch builder, select where you want it (or the canvas root) and **Paste**
   (`⌘/Ctrl + V`). Etch recreates the full structure, the dynamic-data bindings and
   the classes — with their CSS already filled in, so it lands styled.
3. Assign the page/template as usual (e.g. the Cart page → a minimal template that
   outputs the page content).

## Requirements

- **Etch 1.4.20+** (for the product/cart dynamic data).
- The **Woo4Etch plugin** active — it provides the dynamic-data bridges these
  layouts bind to: product keys (`{this.price}`, `{this.is_on_sale}`, …), cart
  (`{options.cart_items}`, `{options.cart_nonce}`, …), account
  (`{options.account_menu}`, `{options.account_endpoint}`, …) and the current
  order (`{options.order}`). Without it those keys are empty.

## Snippets

| File | Area | Built from |
|---|---|---|
| [`cart.json`](./cart.json) | **Cart** — items loop, quantity update, coupon, remove, subtotal/total, checkout, and "You may also like" cross-sells. | Etch Dynamic Keys + the Woo4Etch cart bridge. 100% Etch elements (no shortcodes) so it renders and is editable in the builder. |
| [`product-single.json`](./product-single.json) | **Single product** — featured image + gallery loop, title, `{this.price}` with `-{this.sale_percentage}%` badge, stock label, working add-to-cart form (simple products), SKU. | Product bridge (`{this.*}`) + `{this.gallery_images}`. |
| [`product-grid.json`](./product-grid.json) | **Shop archive** — heading, category slider, working filter sidebar (category counts, price min/max form — the plugin enhances it into a dual-handle slider), product cards with sale pill and AJAX add-to-cart. | Product bridge (`{item.*}`) on the main archive query + shop bridge (`{options.shop_categories}`, `{options.shop_max_price}`, …). See the loop-preset note below. |
| [`category.json`](./category.json) | **Category archive** — term title, editable SEO intro copy (placeholder text), term description via `{term.description}`, then the same filter sidebar + grid. Paste into a `taxonomy-product_cat` template; duplicate per category for bespoke pages. | Shop bridge + Etch's native `{term.*}`/`{archive.*}` context ([`03-product-archive.md`](../03-product-archive.md#category-archive-pages-seo)). |
| [`mini-cart.json`](./mini-cart.json) | **Header mini-cart** — cart link with live `{options.cart_count}` plus a hover/focus dropdown: item rows, subtotal, view-cart/checkout buttons, and a "Your cart is empty." message instead of an empty panel. The count span carries `mini-cart-count` for the fragment snippet ([`05-mini-cart.md`](../05-mini-cart.md)). | Cart bridge. |
| [`notices.json`](./notices.json) | **Woo notices region** — queued WooCommerce feedback ("Cart updated.", coupon/form errors) as styleable `.w4e-notice` markup; hides itself while empty. Included in the cart/product/account layouts; paste this standalone version into any other page layout. | `[woo_notices format="plain"]` in a styled wrapper. |
| [`account.json`](./account.json) | **My Account** — nav from `{options.account_menu}`, dashboard + orders views switched via `{options.account_endpoint}`, all other endpoints via `[woo_account_content]`. | Account bridge + endpoint conditions ([`07-account.md`](../07-account.md#how-endpoints-work-read-this-first)). |
| [`thank-you.json`](./thank-you.json) | **Thank-you / order received** — notice, order overview (number, date, total, payment), line-items loop; renders only when `{options.order}` is populated. | Order bridge ([`08-thank-you.md`](../08-thank-you.md)). |

### About the shop-archive snippet (loop preset)

The archive loop in `product-grid.json` is a **query loop**, and query loops in
Etch only work through a loop *preset* — the pasted block therefore references a
preset by id: `etch_main_query`, the Main Query preset Etch seeds every install
with. If your install's preset has a different id (deleted and recreated, or
renamed), the pasted loop points at nothing and the archive renders empty. Fix:
select the loop block in the builder and re-bind it to your Main Query preset.
The one-click installer doesn't have this caveat — it resolves the site's actual
preset at install time.

### About the cart snippet

- Items: `{#loop options.cart_items as item}` — image, name, `{item.meta}` (variation
  attributes), `{item.price}`, a **Sale** badge via a condition on `{item.on_sale}`,
  an editable quantity input (`cart[{item.key}][qty]`), `{item.subtotal}` and a
  remove link (`{item.remove_url}`).
- Working **cart form**: the `coupon_code` / `apply_coupon` / `update_cart` fields plus
  the hidden `woocommerce-cart-nonce` (`{options.cart_nonce}`) — quantity update and
  coupons work via a classic submit, no AJAX needed.
- Summary: `{options.cart_subtotal}` / `{options.cart_total}` + a checkout link.
- Cross-sells: `{#loop options.cross_sells as cs}`.

**Trade-off:** because the items are a dynamic-data loop (so they're editable in the
builder), WooCommerce's per-item cart **hooks** don't fire — third-party cart
extensions that inject through those won't show here. If you need them, use the
`[woo_cart_items]` shortcode instead (complete + all hooks, but it appears as a
shortcode placeholder in the builder). See [`../15-woo4etch-plugin.md`](../15-woo4etch-plugin.md).

> The class CSS embedded in the snippet uses literal colours (it mirrors the demo's
> `tests/manual/demo-mu/demo.css`). Tweak the classes in Etch's CSS panel to match
> your design — or wire them to your Automatic.css / design-token variables.

> **Class-stripping pitfall:** the pasted blocks carry their classes as
> **style-record references**, not as plain `class` attributes. If those style
> records ever get lost (deleted in a cleanup, or a stale builder session
> reverts them), the classes still render — until the **next save of that
> document silently strips them** and the layout falls apart while its CSS is
> still present. If you restyle the layout outside Etch's style panel (e.g.
> moving the CSS into a global stylesheet), re-apply the critical classes as
> explicit `class` attributes on the blocks first.

## First aid: a template got overwritten

If a re-scaffold or paste replaced a customized template, the fastest full
recovery is WordPress's own **`wp_template` revisions** — Etch templates are
`wp_template` posts and WordPress keeps revisions of them:

```
GET /wp-json/wp/v2/templates/<theme>%2F%2F<slug>/revisions?context=edit
```

e.g. `.../templates/etch-theme%2F%2Fsingle-product/revisions?context=edit`
(authenticated as an admin). Each revision carries the full pre-change
`content.raw`; restore it via the editor's revision UI or by `POST`ing the
revision content back to the template endpoint. Do this **before** saving the
template again in the builder — every save creates a new revision and old ones
eventually rotate out.
