# Etch copy/paste snippets

Ready-made layouts in **Etch's native copy/paste format**. Build a whole area in
seconds: copy a file's contents, then paste it straight into the Etch builder.

## How to use

1. Open the JSON file (e.g. [`cart.json`](./cart.json)) and copy its **entire contents**.
2. In the Etch builder, select where you want it (or the canvas root) and **Paste**
   (`⌘/Ctrl + V`). Etch recreates the full structure, the dynamic-data bindings and
   the classes — with their CSS already filled in, so it lands styled.
3. Assign the page/template as usual (e.g. the Cart page → a minimal template that
   outputs the page content).

## Requirements

- **Etch 1.4.20+** (for the product/cart dynamic data).
- The **Woo4Etch plugin** active — it exposes the cart on Etch's `options` root
  (`{options.cart_items}`, `{options.cart_total}`, `{options.cart_nonce}`,
  `{options.cross_sells}`, …). Without it those keys are empty.

## Snippets

| File | Area | Built from |
|---|---|---|
| [`cart.json`](./cart.json) | **Cart** — items loop, quantity update, coupon, remove, subtotal/total, checkout, and "You may also like" cross-sells. | Etch Dynamic Keys + the Woo4Etch cart bridge. 100% Etch elements (no shortcodes) so it renders and is editable in the builder. |

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
