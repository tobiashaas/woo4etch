# 17 — Component Blueprints (pre-wired Woo markup)

Etch **Components** with the critical WooCommerce attributes already wired in. Build each blueprint once as a component in your install, then reuse it everywhere — you style and arrange freely, while the "invisible contract" with Woo's server logic (`form.cart`, `name="add-to-cart"`, …) stays intact inside the component.

## When to use

- You don't want to (re-)remember which classes/attributes Woo's server logic requires.
- Several templates need the same add-to-cart/quantity/buy-now building blocks.
- Team setups: designers restyle components without being able to forget a required attribute by accident.

## Preparation

- Etch with Components (stored as `wp_block` CPT) — props + slots supported.
- Woo4Etch plugin active (`[do_action]`, buy-now redirect, dynamic data).

## Blueprints

Create each as an Etch component; expose the marked parts as **props** and the free areas as **slots**.

### Add to Cart (simple product)

```html
<form class="cart" action="{this.permalink.relative}" method="post" enctype="multipart/form-data">
  <!-- Slot: before-button (trust badges, delivery estimate, …) -->

  <input type="number"
         name="quantity"
         value="1" min="1" step="1"
         class="input-text qty text"
         aria-label="Quantity">

  <button type="submit"
          name="add-to-cart"
          value="{this.id}"
          class="single_add_to_cart_button button alt">
    Add to cart            <!-- Prop: button label -->
  </button>

  <!-- Slot: after-button -->
</form>
```

### Quantity input (standalone)

```html
<div class="quantity">
  <input type="number"
         name="quantity"
         value="1" min="1" step="1"
         class="input-text qty text"
         aria-label="Quantity">
</div>
```

### Buy Now button (inside an existing `form.cart`)

```html
<button type="submit"
        name="buy_now"
        value="1"
        class="single_add_to_cart_button button buy-now-button">
  Buy now                  <!-- Prop: button label -->
</button>
<input type="hidden" name="add-to-cart" value="{this.id}">
```

See [`16-one-click-checkout.md`](./16-one-click-checkout.md) for the full flow.

## Required classes / attributes — never change these inside the components

| Attribute / class | Component | Why |
|---|---|---|
| `form.cart` (the class!) | Add to Cart | Woo's handler keys off it |
| `name="add-to-cart"` + `value="{this.id}"` | Add to Cart, Buy Now | Which product to add |
| `name="quantity"` | Add to Cart, Quantity | Quantity ends up in the cart |
| `name="buy_now"` | Buy Now | The flag the Woo4Etch redirect checks |
| `.single_add_to_cart_button` | buttons | Woo JS + extensions hook onto it |
| `method="post"` + `enctype="multipart/form-data"` | Add to Cart | Add-ons (file upload) break without it |

Everything **not** in this table — wrappers, classes you add, order, labels, slots — is yours.

## Hooks used

Place `[do_action hook="woocommerce_before_add_to_cart_button"]` / `…after_add_to_cart_button` inside the form (e.g. in the slots) if extensions should inject content.

## PHP layer

None required. Optional: the buy-now filters from [`16-one-click-checkout.md`](./16-one-click-checkout.md).

## Common mistakes

- Editing a required attribute *inside* the component "just to rename it" → every instance breaks at once. The table above is the do-not-touch list.
- Recreating the markup per template instead of instancing the component → drift; fixes no longer propagate.
- Buy Now component used outside a `form.cart` → submits nothing.

## Test checklist

- Insert each component on a product template; add to cart works (product + quantity correct in cart).
- Restyle a component instance (colors, spacing, order) → still functional.
- With an extension active (e.g. Product Add-ons): hook output appears inside the form.
