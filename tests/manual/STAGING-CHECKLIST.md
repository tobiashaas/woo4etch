# Staging test checklist — [Unreleased] (PR #18)

Manual test protocol for everything new since `1.5.0-beta.6`. Work top to
bottom; the **Setup** block once, then each numbered section independently.
Check off with `[x]` as you go.

## Setup (once)

- [ ] Test zip installed (build from the PR branch: `cd plugin && zip -r ../woo4etch.zip woo4etch -x "*.git*"`)
- [ ] **Reinstall the layouts** (Etch → Woo4Etch → Ready-made layouts → Reinstall for Cart, Single product, Account) or re-paste the JSON — empty-state, notices and their styles live in the **layouts**, not in PHP. Heavily customised live layouts: install fresh on a test page instead.
- [ ] One test product with **Inventory → "Sold individually" ✓**
- [ ] One variable product (for the required-variation notice test)

## 1. `{this.is_sold_individually}` (PR #15, @zackpyle)

Key smoke test — temporary condition block in the single-product template:

```html
{#if this.is_sold_individually}
  <p>SOLD-INDIVIDUALLY ACTIVE</p>
{/if}
```

- [ ] Sold-individually product: text renders · normal product: it doesn't

Real use case — hide the quantity picker:

```html
{#if !this.is_sold_individually}
  <input type="number" name="quantity" value="1" min="1" ...>
{/if}
```

- [ ] Sold-individually product: no quantity field, add-to-cart still works (Woo defaults to qty 1)
- [ ] Normal product: quantity field present, qty 3 arrives as 3 in the cart
- [ ] Add the sold-individually product **twice** → Woo's "cannot add another…" error appears as a styled `.w4e-notice` (previously invisible)

## 2. Cart: empty state

- [ ] Empty the cart → "Your cart is currently empty." + **Return to shop** button (→ lands on `/shop`, which also verifies `{options.shop_url}`)
- [ ] Coupon field, totals, checkout button and cross-sells are **gone**
- [ ] Add an item → everything returns

## 3. Cart: update + notices (the original bug)

- [ ] Change a quantity → "Update cart" → **either** "Cart updated." notice + new totals, **or** a real error notice (previously: silent nothing)
- [ ] If it errors: DevTools → Network → inspect the POST: body contains non-empty `update_cart`, an **interpolated** `cart[<hash>][qty]` (not a literal `{item.key}`), and `woocommerce-cart-nonce`? Response `302` = Woo processed it; `200` without a notice = handler not reached. **Record the result — it pins the root cause.**
- [ ] Apply an invalid coupon → red error notice appears

## 4. Notices: only when needed (new fix)

- [ ] Quiet cart page (no action performed): **no blank space** where the notices region sits (wrapper hides itself while empty — needs the reinstalled layout for the updated CSS)
- [ ] In the **builder**: the notices region shows a sample notice ("Cart updated. (sample notice — only shown in the builder)") so it can be selected and styled
- [ ] On the frontend the sample never appears

## 5. Cross-sells fallback (new fix)

- [ ] Cart with items whose products have **no** Linked-Products cross-sells → "You may also like" shows **random catalog products** (cart items excluded)
- [ ] Products **with** maintained cross-sells → exactly those appear (fallback doesn't override)
- [ ] Store with fallback disabled (`add_filter('woo4etch/cross_sells_fallback', '__return_false');` in customizations.php) and no cross-sells → the **whole section including heading disappears** (condition on `options.cross_sells`)

## 6. "Woo Notices" component (server-side install)

- [ ] Etch → Woo4Etch → "Woo Notices as an Etch component" → **Install component** → "Installed (id X)" appears immediately
- [ ] Builder: component library shows "Woo Notices" → place an instance → renders on the frontend (visible when a notice is queued, hidden otherwise)
- [ ] Click **Reinstall component** → same id (updated in place, no duplicate)

## 7. Page health check (new)

- [ ] Admin page → "Page health check": cart/checkout/account rows show the **WooCommerce-assigned pages** (matches WooCommerce → Settings → Advanced)
- [ ] Rows correctly report where the layout/notices were found ("found in page" / "found in template …") — including layouts living in Etch templates
- [ ] A missing notices region shows an **Insert notices** button → click → element lands at the **top** of the assigned page, existing content untouched → health row turns green
- [ ] A missing cart layout shows **Insert layout** → click → appended at the **bottom** of the page → renders on the frontend

## 8. Layout install state

- [ ] Ready-made layouts table: layouts present at their target show **"✓ On “…”"** as a link that opens the page/template editor
- [ ] A layout missing at its target shows **Add to page/template** → click → appended at the target, existing content untouched → row flips to the ✓ state
- [ ] Pushing again is refused ("already contains this layout") — no double-insert

## 9. Notices in product-single & account

- [ ] Product page: variable product, submit without choosing a variation → error notice above the layout
- [ ] Account, logged out: wrong password → error notice; save an address → success notice

## 10. product-grid.json paste (PR #17, issue #13)

- [ ] Paste `templates/etch-copy/product-grid.json` fresh into an archive template (paste route, not one-click) → `/shop` shows products (loop bound to Etch's seeded `etch_main_query` preset; previously: empty archive)

## 11. Regression glance (PR #16 — already verified live)

- [ ] `[woo_product_attributes]` still renders, gzd filter active in the classic cart, product-option checkbox reaches the order

---

**Most important feedback item:** the result of section 3 (update cart) — the
notice text or the POST inspection tells us the remaining root cause, likely a
one-line fix.
