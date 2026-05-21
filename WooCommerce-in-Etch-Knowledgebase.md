# WooCommerce in Etch — Knowledge Base

> A practical reference for rebuilding WooCommerce in Etch without the WooCommerce blocks — the markup, classes, attributes, and hooks WooCommerce actually needs.
>
> **Ready-to-use templates are in [`/templates/`](./templates/00-README.md)** — Single Product (Simple & Variable), Archive, Cart, Mini-Cart, Checkout, My Account, Thank You, Emails, and a consolidated PHP-snippets reference.
>
> **License:** [MIT](LICENSE) — free to use, modify, and redistribute (including commercially). See [CONTRIBUTING.md](CONTRIBUTING.md) to contribute.

---

## Contents

1. [Do you have to use the WooCommerce blocks?](#1-do-you-have-to-use-the-woocommerce-blocks)
2. [What you can build before adding PHP](#2-what-you-can-build-before-adding-php)
3. [Product page best practice — semantics & accessibility](#3-product-page-best-practice--semantics--accessibility)
4. [Does the WooCommerce JS need to be enqueued manually?](#4-does-the-woocommerce-js-need-to-be-enqueued-manually)
5. [WooCommerce custom layouts guide for Etch](#5-woocommerce-custom-layouts-guide-for-etch)
6. [Product gallery missing in Etch JSON output](#6-product-gallery-missing-in-etch-json-output)
7. [Changing the `added_to_cart wc-forward` link text](#7-changing-the-added_to_cart-wc-forward-link-text)
8. [Is there a WooCommerce setting for the 'View cart' text?](#8-is-there-a-woocommerce-setting-for-the-view-cart-text)
9. [`window.wc`, `window.wcSettings`, `window.wp` are `undefined`](#9-windowwc-windowwcsettings-windowwp-are-undefined)
10. [When the nonce and JS globals actually matter](#10-when-the-nonce-and-js-globals-actually-matter)

---

## 1. Do you have to use the WooCommerce blocks?

**In short:**

No, the blocks are not required. For classic WooCommerce output, the markup can be controlled through hooks, template overrides, or custom CSS. WooCommerce explicitly recommends **hooks** over full template overrides because they hold up better through core updates.

If your page builder allows HTML access, the real question isn't "can I write my own HTML?" — it's **where** you put it. Just setting classes is often not enough, because WooCommerce also expects dynamic data, form handling, nonces, validation, and JS behavior at the same spot.

**What works well:**
- Pure presentation: product lists, cards, wrappers, badges, layout containers.
- Override default styles via theme CSS, or disable Woo CSS entirely.
- Override templates safely from the child theme (`wp-content/themes/<theme>/woocommerce/...`).

**Where it gets critical:**
- Cart, checkout, add-to-cart, variations, notices, form fields, account areas.
- These don't just need CSS — they need WooCommerce logic, actions/filters, form names, security mechanisms, and JS.
- When overriding templates, **don't remove existing hooks**, or extensions won't be able to plug in.

**Practical recommendation:**
- Build the layout with your page builder.
- Adjust WooCommerce output via hooks or single template overrides.
- Write your own CSS; disable Woo's default CSS if needed.
- Use fully custom HTML only where little critical shop logic is involved.

---

## 2. What you can build before adding PHP

Without PHP, the **frontend visuals** can be built from scratch, but you can't reliably replace **WooCommerce functionality**. Product cards, grids, tabs, wrappers, UI components — fine. Real shop functions depend on server-side logic and template structure.

**Doable now, without PHP:**
- Full UI, states, interactions, responsiveness.
- Product teasers, archives, single-product layout.
- Buttons, quantity UI, accordions, tabs.

**Not finished without PHP:**
- Variable-product form with `data-product_id`, `data-product_variations`.
- Real product data, prices, availability, variation logic.
- Cart and checkout (especially in block setups).

**Sensible split:**
- **Now in HTML/CSS/JS:** the full UI, states, interactions, responsiveness.
- **Later with PHP/Woo:** product data, prices, variations, availability, add-to-cart, cart, checkout, account.
- **Don't just copy class names:** mirror the actual Woo markup structure, especially for forms.

**Recommendation:** build a **headless-style UI skeleton** for WooCommerce — same components, same states, mock data. Then replace the mock data piece by piece with real WooCommerce output via PHP.

---

## 3. Product page best practice — semantics & accessibility

Some classes must be present so WooCommerce's JS hooks in. The goal is an accessible product page with correct semantics that keeps only the Woo-specific anchors that matter.

Sensible middle ground: **don't** blindly copy the whole Woo markup; just take the few structures, classes, attributes, and live regions that Woo (and Woo-JS) actually need.

Two layers:
1. **Semantics/accessibility** for the base layout (freely designed).
2. **Woo-specific hooks/classes** for interactive bits — variations, quantity, add-to-cart.

### Base scaffolding

```html
<main id="main" class="site-main">
  <article class="product product--single" itemscope itemtype="https://schema.org/Product">
    <header class="product__header">
      <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
          <li><a href="/shop">Shop</a></li>
          <li><a href="/shop/shoes">Shoes</a></li>
          <li aria-current="page">Trail Runner X</li>
        </ol>
      </nav>

      <h1 class="product_title entry-title" itemprop="name">Trail Runner X</h1>
      <p class="price" aria-label="Price"><span itemprop="price">€129.00</span></p>
    </header>

    <div class="product__layout">
      <section class="product__gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="sr-only">Product images</h2>
        <figure>
          <img src="/assets/trail-runner-x.jpg"
               alt="Trail Runner X in black, side view"
               width="1200" height="1200" />
          <figcaption class="sr-only">Main product view</figcaption>
        </figure>
      </section>

      <section class="product__summary entry-summary" aria-labelledby="product-options-title">
        <h2 id="product-options-title">Product options</h2>
        <p class="woocommerce-product-details__short-description">
          Lightweight trail runner with grippy sole for forest and gravel.
        </p>
        <!-- Woo form goes in here later -->
      </section>
    </div>

    <section class="product__details" aria-labelledby="details-title">
      <h2 id="details-title">Product details</h2>
      <p>Breathable mesh upper, profiled outsole, neutral fit.</p>
    </section>
  </article>
</main>
```

**Important:** exactly one `h1`, meaningful `h2` regions, `nav` for breadcrumbs, `figure`/`figcaption` for images, no clickable `div`s in place of real buttons or links.

### Simple Product

```html
<form class="cart" action="/product/trail-runner-x" method="post" enctype="multipart/form-data">
  <div class="quantity">
    <label for="quantity_trail_runner_x" class="screen-reader-text">
      Trail Runner X quantity
    </label>
    <input id="quantity_trail_runner_x"
           class="input-text qty text"
           type="number" name="quantity"
           value="1" min="1" step="1"
           inputmode="numeric" autocomplete="on"
           aria-label="Product quantity" />
  </div>

  <button type="submit" name="add-to-cart" value="1234"
          class="single_add_to_cart_button button alt">
    Add to cart
  </button>
</form>
```

### Variable Product

```html
<form class="variations_form cart"
      action="/product/trail-runner-x"
      method="post"
      enctype="multipart/form-data"
      data-product_id="1234"
      data-product_variations='[]'>

  <fieldset class="product-variations">
    <legend>Choose variant</legend>

    <div class="product-variation">
      <label for="pa_size">Size</label>
      <select id="pa_size" name="attribute_pa_size" required>
        <option value="">Please choose</option>
        <option value="42">42</option>
        <option value="43">43</option>
        <option value="44">44</option>
      </select>
    </div>

    <div class="product-variation">
      <label for="pa_color">Color</label>
      <select id="pa_color" name="attribute_pa_color" required>
        <option value="">Please choose</option>
        <option value="black">Black</option>
        <option value="green">Green</option>
      </select>
    </div>

    <a class="reset_variations" href="#" aria-label="Reset options">
      Clear options
    </a>
  </fieldset>

  <div class="reset_variations_alert screen-reader-text"
       role="alert" aria-live="polite" aria-relevant="all"></div>

  <div class="single_variation_wrap">
    <div class="single_variation" aria-live="polite">
      <p class="variation-price">€129.00</p>
      <p class="variation-availability">In stock</p>
    </div>

    <div class="woocommerce-variation-add-to-cart variations_button">
      <div class="quantity">
        <label for="quantity_1234" class="screen-reader-text">Trail Runner X quantity</label>
        <input id="quantity_1234" class="input-text qty text"
               type="number" name="quantity"
               value="1" min="1" step="1"
               inputmode="numeric" aria-label="Product quantity" />
      </div>

      <button type="submit" class="single_add_to_cart_button button alt">
        Add to cart
      </button>

      <input type="hidden" name="add-to-cart" value="1234" />
      <input type="hidden" name="product_id" value="1234" />
      <input type="hidden" name="variation_id" class="variation_id" value="0" />
    </div>
  </div>
</form>
```

**Required anchors in the variations form:** `variations_form cart`, `reset_variations`, `single_variation_wrap`, `variation_id`, labeled selects, and the screen-reader live region. That's what the Woo JS hooks into.

### Accessibility rules

- Images with real alt text — don't just repeat the product name.
- Price, availability, and variant choice exposed as **text**, not only visually or via icons.
- Variant selection as real `select`, `radio`, or properly labeled buttons with `aria-pressed`/`aria-describedby`.
- Quantity input always with a label, at least a screen-reader-only one.
- Status changes communicated via `aria-live`.
- No accordions/tabs without keyboard support — `<details>`/`<summary>` is often more robust.

### Three-layer setup

1. **Layer 1:** purely semantic structure, fully under your control.
2. **Layer 2:** Woo-compatible classes/attributes only where JS or form handling needs them.
3. **Layer 3:** PHP later — real values, hidden fields, variation data, server logic.

---

## 4. Does the WooCommerce JS need to be enqueued manually?

On **normal Woo pages** it's usually there — but only if WooCommerce recognises the page as a relevant shop page and enqueues its scripts. On a classic single-product page, this is usually automatic.

For **variable products**, `wc-add-to-cart-variation` is particularly relevant. On heavily customised builder templates, loops in unusual places, or custom product forms outside the standard context, you may need to load and init it yourself:

```js
jQuery(function($) {
  $('.variations_form').each(function() {
    $(this).wc_variation_form();
  });
});
```

**Rule of thumb:**
- **Normal Woo single-product page:** usually present.
- **Custom HTML in a builder on a real product page:** usually present, but only useful if your markup is close enough to Woo's.
- **Custom placement outside the standard flow:** often needs manual enqueue + init.

**Concretely:** if you're using custom HTML on a real product page in your builder, **don't manually enqueue everything**. First check if Woo already ships the required scripts. Only if your form lives outside the normal Woo flow, or is injected via JS, do you need to add `wc-add-to-cart-variation` plus init.

---

## 5. WooCommerce custom layouts guide for Etch

### Core principle

For custom layouts, simply rebuilding HTML/CSS usually isn't enough — WooCommerce relies on specific form structures, classes, attributes, and hook positions at multiple points.

**Safe strategy:** build semantic custom markup, but at functional points keep the expected WooCommerce anchors — especially for product forms, variations, and quantity fields. Fill visible content via Etch Dynamic Keys in parallel.

### Etch Dynamic Keys

> **Important correction (verified against the Etch docs):** Etch uses **three different context keywords** depending on where you are in the layout:
> - **Templates** (Single, Archive, Search, 404): `{this.key}` — refers to the page's primary item
> - **Loops** (`{#loop … as item}{/loop}`): `{item.key}` — refers to the current iteration
> - **Taxonomies**: `{taxonomy.key}`
>
> **Context rule:** On a **Single product template**, use **`{this.title}`, `{this.meta._price}`**, etc. Use **`{item.*}`** only inside `{#loop … as item}{/loop}` (archives, related products, nested galleries). See [`templates/10-etch-context-and-templates.md`](./templates/10-etch-context-and-templates.md).

Etch uses dynamic keys with curly-brace syntax: `{this.foo}`, `{item.foo}`, `{taxonomy.foo}` (or deeper paths like `{this.image.url}`). The JSON field names are the same; only the keyword changes with context.

| Content | Single template | Loop (`{#loop … as item}`) | JSON source |
|---|---|---|---|
| Product title | `{this.title}` | `{item.title}` | `title`, `post_title` |
| Permalink (relative) | `{this.permalink.relative}` | `{item.permalink.relative}` | `permalink.relative` |
| Permalink (absolute) | `{this.permalink.full}` | `{item.permalink.full}` | `permalink.full` |
| Short description | `{this.excerpt}` | `{item.excerpt}` | `excerpt`, `post_excerpt` |
| Content / description | `{this.content}` | `{item.content}` | `content`, `post_content` |
| Featured image URL | `{this.image.url}` | `{item.image.url}` | `featuredImage`, `image.url` |
| Featured image ID | `{this.image.id}` | `{item.image.id}` | `image.id` |
| Image alt | `{this.image.alt}` | `{item.image.alt}` | `image.alt` |
| Slug | `{this.slug}` | `{item.slug}` | `slug`, `post_name` |
| SKU | `{this.meta._sku}` | `{item.meta._sku}` | `meta._sku` |
| Price | `{this.meta._price}` | `{item.meta._price}` | `meta._price` |
| Regular price | `{this.meta._regular_price}` | `{item.meta._regular_price}` | `meta._regular_price` |
| Stock status | `{this.meta._stock_status}` | `{item.meta._stock_status}` | `meta._stock_status` |
| Manufacturer (taxonomy) | `{this.pa_hersteller.0.name}` | `{item.pa_hersteller.0.name}` | `pa_hersteller[0].name` |
| Product category | `{this.product_cat.0.name}` | `{item.product_cat.0.name}` | `product_cat[0].name` |

### Required anchors by area

| Area | Classes / attributes | Why |
|---|---|---|
| Single product wrapper | `product`, `product-type-*`, `entry-summary` | Theme/plugin styling + Woo context hooks |
| Add-to-cart form (simple) | `form.cart`, `name="add-to-cart"`, `value="PRODUCT_ID"` | Submit needs to identify the product |
| Quantity | `.quantity`, `input.qty`, `name="quantity"`, `min`, `step`, `aria-label` | Required structure for quantity fields |
| Variable product form | `.variations_form.cart`, `data-product_id`, `data-product_variations` | Woo JS reads the structure |
| Variant selects | `select` with `name="attribute_*"` (`attribute_pa_<slug>` for global/taxonomy attributes, `attribute_<slug>` for custom ones) | Attribute names for variation resolution |
| Reset link | `.reset_variations` | Standard JS & UX |
| Variation status | `.reset_variations_alert.screen-reader-text[role="alert"]` | Screen-reader feedback |
| Variation output | `.single_variation_wrap` | Placeholder for price, availability, add-to-cart |
| Hidden fields | `product_id`, `variation_id`, `add-to-cart` | Server-side mapping at submit |
| Archive card | `product`, `type-product`, `woocommerce-LoopProduct-link`, `woocommerce-loop-product__title` | Compatibility with shop markup |
| Archive add-to-cart | `button`, `product_type_simple`, `add_to_cart_button`, optionally `ajax_add_to_cart` | Behavior + styling in the loop |

### Single product base layout with Etch keys

```html
<main id="main" class="site-main">
  <article class="product product--single" itemscope itemtype="https://schema.org/Product">
    <header class="product__header">
      <h1 class="product_title entry-title" itemprop="name">{this.title}</h1>
      <p class="price" aria-label="Price">{this.meta._price}</p>
      <p class="woocommerce-product-details__short-description">{this.excerpt}</p>
    </header>

    <div class="product__layout">
      <section class="product__gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="screen-reader-text">Product images</h2>
        <figure>
          <img src="{this.image.url}"
               alt="{this.title}"
               width="{this.image.width}"
               height="{this.image.height}">
        </figure>
      </section>

      <section class="product__summary entry-summary" aria-labelledby="product-options-title">
        <h2 id="product-options-title">Product options</h2>
        <!-- Real Woo form goes in here later -->
      </section>
    </div>

    <section class="product__details" aria-labelledby="details-title">
      <h2 id="details-title">Product details</h2>
      <div>{this.content}</div>
    </section>
  </article>
</main>
```

### Simple product form (with Etch keys)

```html
<form class="cart" action="{this.permalink.relative}" method="post" enctype="multipart/form-data">
  <div class="quantity">
    <label for="quantity_{this.id}" class="screen-reader-text">{this.title} quantity</label>
    <input id="quantity_{this.id}"
           class="input-text qty text"
           type="number" name="quantity"
           value="1" min="1" step="1"
           inputmode="numeric" autocomplete="on"
           aria-label="Product quantity" />
  </div>

  <button type="submit" name="add-to-cart" value="{this.id}"
          class="single_add_to_cart_button button alt">
    Add to cart
  </button>
</form>
```

### Variable product form (with Etch keys)

```html
<form class="variations_form cart"
      action="{this.permalink.relative}"
      method="post"
      enctype="multipart/form-data"
      data-product_id="{this.id}"
      data-product_variations='[]'>

  <table class="variations" cellspacing="0" role="presentation">
    <tbody>
      <tr>
        <th class="label"><label for="pa_size">Size</label></th>
        <td class="value">
          <select id="pa_size" name="attribute_pa_size" required>
            <option value="">Please choose</option>
          </select>
          <a class="reset_variations" href="#" aria-label="Clear options">Clear options</a>
        </td>
      </tr>
    </tbody>
  </table>

  <div class="reset_variations_alert screen-reader-text"
       role="alert" aria-live="polite" aria-relevant="all"></div>

  <div class="single_variation_wrap">
    <div class="single_variation" aria-live="polite"></div>

    <div class="woocommerce-variation-add-to-cart variations_button">
      <div class="quantity">
        <label for="quantity_{this.id}" class="screen-reader-text">{this.title} quantity</label>
        <input id="quantity_{this.id}" class="input-text qty text"
               type="number" name="quantity" value="1" min="1" step="1">
      </div>

      <button type="submit" class="single_add_to_cart_button button alt">Add to cart</button>
      <input type="hidden" name="add-to-cart" value="{this.id}">
      <input type="hidden" name="product_id" value="{this.id}">
      <input type="hidden" name="variation_id" class="variation_id" value="0">
    </div>
  </div>
</form>
```

### Product archive

```html
<main id="main" class="site-main">
  <header class="archive-header">
    <h1>Shop</h1>
    <p>High-quality stainless-steel products for kitchen and storage.</p>
  </header>

  <section aria-labelledby="products-title">
    <h2 id="products-title" class="screen-reader-text">Product list</h2>

    <ul class="products columns-3" role="list">
      <li class="product type-product product-type-simple instock">
        <article class="product-card">
          <a href="{item.permalink.relative}"
             class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
            <figure class="product-card__image">
              <img src="{item.image.url}"
                   alt="{item.title}"
                   width="300" height="300" loading="lazy">
            </figure>

            <div class="product-card__body">
              <p class="product-card__eyebrow">{item.product_cat.0.name}</p>
              <h3 class="woocommerce-loop-product__title">{item.title}</h3>
              <p class="price">{item.meta._price}</p>
              <p class="product-card__excerpt">{item.excerpt}</p>
            </div>
          </a>

          <div class="product-card__actions">
            <a href="?add-to-cart={item.id}"
               data-quantity="1"
               class="button product_type_simple add_to_cart_button ajax_add_to_cart"
               data-product_id="{item.id}"
               data-product_sku="{item.meta._sku}"
               aria-label="Add {item.title} to cart"
               rel="nofollow">
              Add to cart
            </a>
          </div>
        </article>
      </li>
    </ul>
  </section>
</main>
```

### Product gallery (note)

The current Etch JSON output doesn't include a gallery array. Only the main image is visible in `image` / `featuredImage`; additional images sit in WooCommerce under `get_gallery_image_ids()` / `meta._product_image_gallery` (a comma-separated ID list like `197,198,199`) and aren't directly loopable.

**Three sensible options:**
1. **Best case:** extend the JSON output with `galleryImages` (array of objects with `id`, `url`, `alt`, optionally `width`, `height`).
2. **Fallback:** start with `image.url` as the hero image, add the gallery server-side later.
3. **Cleanest Woo approach:** server-side, output an array with featured image + gallery images.

**Recommended JSON shape:**

```json
{
  "image": {
    "id": 196,
    "url": "https://example.com/main.jpg",
    "alt": "Product image"
  },
  "galleryImages": [
    { "id": 196, "url": "https://example.com/main.jpg", "alt": "Product image" },
    { "id": 197, "url": "https://example.com/gallery-1.jpg", "alt": "Detail 1" },
    { "id": 198, "url": "https://example.com/gallery-2.jpg", "alt": "Detail 2" }
  ]
}
```

**Etch gallery markup (nested loop):**

```html
<section class="product-gallery" aria-labelledby="gallery-title">
  <h2 id="gallery-title" class="screen-reader-text">Product images</h2>

  <figure class="product-gallery__main">
    <img src="{this.image.url}"
         alt="{this.title}"
         width="{this.image.width}"
         height="{this.image.height}">
  </figure>

  <!-- Nested loop over this.galleryImages (use {galleryItem.*} inside the loop) -->
  <ul class="product-gallery__thumbs" role="list">
    <li>
      <img src="{galleryItem.url}" alt="{galleryItem.alt}">
    </li>
  </ul>
</section>
```

### Important hooks (single product)

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_add_to_cart_form` | Before the product form | Notices, trust badges |
| `woocommerce_before_variations_form` | Inside the variations form, before options | Intro text, helper |
| `woocommerce_after_variations_table` | After the variations table | Additional notes, swatches, stock |
| `woocommerce_before_single_variation` | Inside `.single_variation_wrap`, before data | Prep content |
| `woocommerce_single_variation` | Inside `.single_variation_wrap` | Renders variation data + add-to-cart |
| `woocommerce_after_single_variation` | After the variation block | Additions below the buy box |
| `woocommerce_after_variations_form` | After the form | Content right after variation logic |
| `woocommerce_after_add_to_cart_form` | After the add-to-cart form | Trust elements, USPs, notes |

### Quantity hooks

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_quantity_input_field` | Before the input | Minus button, prefix, helper text |
| `woocommerce_after_quantity_input_field` | After the input | Plus button, validation notes |

### Hook strategy for custom layouts

Practical order:
1. Build your own semantic base layout.
2. Fill visible content with Etch Dynamic Keys.
3. Normalize gallery data separately if it's not directly in JSON.
4. Define the buy box as a Woo-compatible region.
5. Then check which Woo hooks are needed inside the buy box.
6. Use template overrides only where hooks alone aren't sufficient.

### Accessibility checklist

- Exactly one `h1` per product page.
- Every form field connected to a real `label`.
- No clickable `div`/`span` instead of buttons or links.
- Status changes on variations or reset announced via `aria-live`.
- Price, availability, error messages exposed as text, not only visually.
- Images with sensible `alt` and large click/touch targets in galleries.
- In archives, semantic list structure and descriptive button labels.

### What often breaks

- Only classes copied, but `name` attributes or hidden inputs missing.
- `.variations_form` present, but `data-product_variations` missing/empty/wrong.
- Variant UI built with free-form buttons without the expected form logic.
- Quantity input looks right, but lacks `name="quantity"` or valid min/step.
- Gallery data only as raw IDs, not in a loopable structure.
- Hook positions removed → plugins can't plug in.

### Quick reference

Dynamic keys below use the **single-product** context (`{this.*}`). Inside a loop (archive, related, gallery) swap `this` → `item` (or the loop's own variable).

| Element | Keep |
|---|---|
| Product title | `{this.title}` or `{this.post_title}` |
| Product image | `{this.image.url}` plus alt/size from `this.image.*` |
| Product gallery | Custom `galleryImages` array or normalize `_product_image_gallery` server-side |
| Short description | `{this.excerpt}` / `{this.post_excerpt}` |
| Content | `{this.content}` / `{this.post_content}` |
| Price | `{this.meta._price}` |
| SKU | `{this.meta._sku}` |
| Quantity field | `.quantity`, `input.qty`, `name="quantity"`, label, `aria-label` |
| Simple add to cart | `form.cart`, submit button with `name="add-to-cart"` |
| Variable form | `.variations_form.cart`, `data-product_id`, `data-product_variations` |
| Variant fields | `select` with `name="attribute_*"` |
| Reset | `.reset_variations` |
| Variation output | `.single_variation_wrap`, `.single_variation`, `.variation_id` |
| Archive card | `li.product`, `woocommerce-LoopProduct-link`, `woocommerce-loop-product__title` |
| Archive button | `add_to_cart_button`, `product_type_simple`, optionally `ajax_add_to_cart` |
| Hooks | Don't lightly remove add-to-cart and variation hooks |

---

## 6. Product gallery missing in Etch JSON output

**The problem**

The Etch JSON output for WooCommerce products currently only exposes the **featured image** (`image` / `featuredImage`), not the **full product gallery**. WooCommerce already stores gallery images (e.g. via `_product_image_gallery` / `get_gallery_image_ids()`), but these are not normalized into a usable array for Etch.

**Why it matters**

- Single product pages in Woo normally show a multi-image gallery (main image + thumbnails).
- Etch supports loops and dynamic images but needs an **array** as its source.
- Without a gallery array, every project has to add custom PHP around the Etch output.

**Proposed shape**

- A new JSON field per product, e.g. `galleryImages`:
  - Array of objects with at least `id`, `url`, `alt` (optionally `width`, `height`).
  - First item is the featured image, followed by all WooCommerce gallery images.
- Stable access pattern in Etch: `{item.galleryImages}` as the loop source, with `{galleryItem.url}` / `{galleryItem.alt}` inside the loop.
- Backwards compatible: existing `image` / `featuredImage` stay as they are; `galleryImages` is additive.

---

## 7. Changing the `added_to_cart wc-forward` link text

The `added_to_cart wc-forward` link (`<a href="..." class="added_to_cart wc-forward">View cart</a>`) is inserted dynamically by WooCommerce after add-to-cart — typically after AJAX-add-to-cart in the loop. The text isn't changed directly in HTML; you use a WordPress filter or `gettext`.

**Cleanest approach for just the text:**

```php
add_filter('gettext', function($translated_text, $text, $domain) {
    if ($domain === 'woocommerce' && $text === 'View cart') {
        $translated_text = 'Go to cart';
    }
    return $translated_text;
}, 10, 3);
```

Works as long as WooCommerce uses the original "View cart" string internally and then translates it.

For just that specific post-AJAX-add behavior, you'd need a more targeted Woo hook. The standard filters in the Woo docs mostly target the **add-to-cart button text**, not the post-add `added_to_cart` link. So for a simple text swap, `gettext` is usually the easiest path.

---

## 8. Is there a WooCommerce setting for the 'View cart' text?

No, usually not. The standard WooCommerce settings don't expose an option for "View cart". It's customized via `gettext`, a Woo filter like `wc_add_to_cart_params`, or a string-translation plugin.

WooCommerce lets you choose or edit cart and checkout **pages**, but you can't rename the AJAX link text "View cart" through the regular settings UI.

**Practical:**
- Not changeable in the normal WooCommerce settings.
- Changeable via code, translation, or plugin.
- "View cart" → "Go to cart" is a small developer task, not a pure-settings change.

---

## 9. `window.wc`, `window.wcSettings`, `window.wp` are `undefined`

These globals being `undefined` even with WooCommerce correctly installed is not unusual, and it raises a fair question: is a nonce needed at all? These JS globals are **not** loaded on every page — WooCommerce only adds them when the matching scripts are enqueued. `wcSettings` is admin-/blocks-related per Woo's own docs and shouldn't be relied on for the normal frontend.

**Why the globals are missing:**
- `window.wp` is only present when WordPress / Gutenberg / Blocks scripts have loaded. On heavily customized frontends, it's expected to be `undefined`.
- `window.wcSettings` is not intended for the frontend at all per Woo.
- `window.wc` is not a reliable global entry point for classic Woo frontends. Functions usually hang off concrete loaded scripts (e.g. `wc-add-to-cart-variation`) or jQuery plugins like `variations_form`.

**What really matters:** whether the **right Woo frontend scripts** are loaded for your specific use case. For variations, e.g., whether the variations script is present and your form gets initialized via `variations_form`.

Practical checks:
- Are Woo frontend scripts loaded on the page at all?
- Does Woo recognize the page as a real product/cart/checkout page?
- Have standard hooks / template structure been removed so Woo isn't enqueuing its assets?

**Do you need the nonce?**

For pure rendering and classic standard forms: **no**, you don't need a custom nonce in your frontend markup. Within the normal WooCommerce flow, security is handled by Woo/WordPress.

A nonce becomes relevant when you build your own **AJAX requests, custom endpoints, or your own JS POST calls**. But `window.wp` / `window.wcSettings` being `undefined` doesn't automatically mean "the nonce is missing".

---

## 10. When the nonce and JS globals actually matter

Checking for `window.wcSettings.nonce` is a smoke test for one **specific setup**: a custom add-to-cart that talks to the **WooCommerce Store API**. There it makes sense to verify whether Woo already exposes a Store-API nonce before building your own request.

For the classic scenario (classic frontend, custom markup, no Store-API flow yet):

- `undefined` only means *these globals aren't loaded here right now* — it's not a sign that Woo is broken.
- These objects only become relevant with the **Store API, block checkout, or custom JS cart logic**.
- For classic form submits plus Woo hooks, the nonce isn't needed manually in the frontend — WordPress/Woo handle it behind the scenes.

Rule of thumb: only when you build a Store-API-based custom cart do you first check whether Woo provides `wcSettings` + nonce — and add it via PHP if it's missing.

---

## Sources

External documentation referenced in this guide:

- [Etch](https://etchwp.com/?aff=06de86e5) — visual builder for WordPress
- WooCommerce Theme Development — Template Structure: <https://developer.woocommerce.com/docs/theming/theme-development/template-structure/>
- WooCommerce Code Reference — Variable Add to Cart Template: <https://woocommerce.github.io/code-reference/files/woocommerce-templates-single-product-add-to-cart-variable.html>
- WooCommerce Code Reference — Quantity Input Template: <https://woocommerce.github.io/code-reference/files/woocommerce-templates-global-quantity-input.html>
- WooCommerce Code Reference — Single Product Template: <https://woocommerce.github.io/code-reference/files/woocommerce-templates-single-product.html>
- WooCommerce — Introduction to Hooks, Actions & Filters: <https://woocommerce.com/document/introduction-to-hooks-actions-and-filters/>
- WooCommerce Block Theme Development — Cart & Checkout: <https://developer.woocommerce.com/docs/theming/block-theme-development/cart-and-checkout/>
- WooCommerce — Customizing Cart and Checkout: <https://woocommerce.com/document/woocommerce-store-editing/customizing-cart-and-checkout/>
- WooCommerce — Accessibility Features: <https://woocommerce.com/document/accessibility-features-in-woocommerce/>
- WP-Kama — `ProductGalleryUtils::get_product_gallery_image_ids`: <https://wp-kama.com/plugin/woocommerce/function/ProductGalleryUtils::get_product_gallery_image_ids>
- Etch Docs — Dynamic Data Keys: <https://docs.etchwp.com/dynamic-data/dynamic-data-keys>
- Etch Docs — Using a Component Dynamic: <https://docs.etchwp.com/components/using-a-component-dynamic>
- Etch Docs — Basic Loops: <https://docs.etchwp.com/loops/basic-loops>
- Etch Docs — Dynamic Image: <https://docs.etchwp.com/elements/dynamic-image>
- Etch Docs — Gallery Fields Integration: <https://docs.etchwp.com/integrations/custom-fields/gallery-fields>
- Stack Overflow — Change `added_to_cart wc-forward` text: <https://stackoverflow.com/questions/40212438/how-to-change-text-in-link-added-to-cart-wc-forward-in-woocommerce>
- Stack Overflow — Change "View cart" text: <https://stackoverflow.com/questions/54184098/change-the-text-of-view-cart-button-woocommerce>
- WC Frontend Scripts (WP-Kama): <https://wp-kama.com/plugin/woocommerce/function/WC_Frontend_Scripts::load_scripts>
- WooCommerce 10.5 — Add-to-cart Button disabled by default in variable products: <https://developer.woocommerce.com/2026/01/16/add-to-cart-button-disabled-by-default-in-variable-products-in-woocommerce-10-5/>
