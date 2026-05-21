# 10 — Etch Context & Templates Reference

> Read this **before** the other templates. The other files assume you know which Etch context you're in.

Etch uses three different keywords to access dynamic data depending on **where** in the layout you are. Mixing them up is the most common source of "why are my fields empty?".

## The three contexts

| Context | Keyword | Used in |
|---|---|---|
| **Template context** | `{this.key}` | Single, Archive, Search, 404, Index — when Etch renders *the current page's primary item* |
| **Loop context** | `{item.key}` | Inside any `{#loop … as item}{/loop}` block — refers to the current iteration |
| **Taxonomy context** | `{taxonomy.key}` | When the current view is a taxonomy term (category, tag, custom taxonomy) |

Plus two contexts that work everywhere:

| Context | Keyword | Available |
|---|---|---|
| **User** | `{user.key}` | Current logged-in user (or empty/guest) |
| **Site** | `{site.key}` | Site-wide values (name, language, home URL) |

## Common keys per context

### `this.*` / `item.*` (same shape — differs only in *what they point to*)

| Key | Type | Description |
|---|---|---|
| `id` | string | Unique identifier |
| `title` | string | Title |
| `slug` | string | URL-friendly slug |
| `content` | string | Main body content |
| `excerpt` | string | Summary |
| `permalink.relative` | string | Relative URL path |
| `permalink.full` | string | Absolute URL |
| `image.url` | string | Featured image URL |
| `image.alt` | string | Image alt text |
| `date` | string | Publish date |
| `modified` | string | Last modified date |
| `status` | string | Post status (`publish`, `draft`, …) |
| `type` | string | Post type (`product`, `post`, `page`, …) |
| `author.displayName` | string | Author display name |
| `readingTime` | number | Estimated reading minutes |

For WooCommerce-specific keys (price, SKU, stock, attributes), see `meta.*` and taxonomy keys in the [knowledge base](../WooCommerce-in-Etch-Knowledgebase.md#5-woocommerce-custom-layouts-guide-for-etch).

### `user.*`

| Key | Type | Description |
|---|---|---|
| `user.id` | string | User ID |
| `user.email` | string | Email |
| `user.displayName` | string | Display name |
| `user.loggedIn` | boolean | `true` if signed in |

### `site.*`

| Key | Type | Description |
|---|---|---|
| `site.name` | string | Site title |
| `site.home_url` | string | Home URL |
| `site.language` | string | Language code (`en-US`, `de-DE`) |
| `site.currentDate` | number | Current date as unix timestamp |

## Templates vs. Pages

Etch has two distinct ways to build a layout — and they behave differently with dynamic data.

### Templates (dynamic)

A **Template** is a layout that Etch applies automatically to any post matching certain conditions. They live in the **Template Hub** and are assigned via rules like "all posts of type `product`" or "all archive pages of taxonomy `product_cat`".

Etch's recommended template types:

| Template type | When WordPress uses it | Etch primary context |
|---|---|---|
| **Single** | Viewing a single post (`/product/trail-runner-x`) | `this.*` is that single post |
| **Archive** | Viewing a list (`/shop`, `/product-category/shoes`) | `this.*` is the term/archive object; loop `mainQuery` for items |
| **Search** | `/?s=keyword` | `this.*` is the query; loop `mainQuery` for results |
| **Author** | `/author/jane` | `this.*` is the author |
| **404** | Anything that doesn't match | No `this.*` |
| **Index (catch-all)** | Fallback when no specific template matches | Depends on the request |

Inside a Single template, `{this.title}` gives the product title. Inside an Archive template, `{this.title}` gives the archive title (category/tag term, or the shop page on `/shop`) and you loop products with `{#loop mainQuery as item}` — never `{this.title}` for cards inside that loop.

### Pages (static layout, dynamic data injected)

A **Page** in Etch is a regular WordPress page (`Pages → All Pages`) whose content is built in Etch's editor. Cart, Checkout, My Account, and Thank-You are all Pages — they're not generated from a post-type template.

On a Page, **there is no automatic `this.*` product context**. Dynamic data has to come from one of:
- The page's own meta (its title, its content)
- `user.*` and `site.*` (always available)
- A custom data source you wire up (e.g. a REST endpoint that returns cart contents, then a `{#loop yourSource as item}` over the result)

## WooCommerce mapping

| Area | Build as | Primary context | Notes |
|---|---|---|---|
| Single product (simple/variable) | **Single template** assigned to post type `product` | `{this.*}` for the product | Variations come from a custom field, not built in |
| Shop / category / tag | **Archive template** for product taxonomies | `{this.*}` for archive; `{#loop mainQuery as item}` for cards | WooCommerce already drives the main query |
| Cart | **Page** with shortcode `[woocommerce_cart]` (classic) or block | Custom data source via REST / PHP | See `04-cart.md` |
| Mini-cart | **Component** placed in header | Custom data source + fragments | See `05-mini-cart.md` |
| Checkout | **Page** with `[woocommerce_checkout]` | PHP renders form fields | See `06-checkout.md` |
| My Account | **Page** with `[woocommerce_my_account]` | `{user.*}` + endpoint output | See `07-account.md` |
| Order received | **Page** (checkout `order-received` endpoint) | Order data injected via PHP / REST | See `08-thank-you.md` |

## Loop syntax (full reference)

Loops in Etch are explicit blocks, not inline `{item.*}` placeholders.

### Main WordPress query

```etch
{#loop mainQuery as item}
  <article>
    <h2>{item.title}</h2>
    <a href="{item.permalink.relative}">Read more</a>
  </article>
{/loop}
```

### Main query with parameters

```etch
{#loop mainQuery($count: 3) as item}
  <h2>{item.title}</h2>
{/loop}

{#loop mainQuery($orderby: "title", $order: "ASC") as item}
  <h2>{item.title}</h2>
{/loop}

{#loop mainQuery($count: -1) as item}
  <h2>{item.title}</h2>
{/loop}
```

### Custom query

Custom queries are configured on the Loop element in the Etch UI and given a name; you then reference that name in markup:

```etch
{#loop popularProducts as item}
  <h2>{item.title}</h2>
{/loop}
```

### Nested loop (e.g. gallery inside a product card)

```etch
{#loop mainQuery as item}
  <article>
    <h2>{item.title}</h2>
    {#loop item.galleryImages as galleryItem}
      <img src="{galleryItem.url}" alt="{galleryItem.alt}">
    {/loop}
  </article>
{/loop}
```

The inner `item.galleryImages` is read from the outer loop's current `item`; inside the nested loop, `{galleryItem.*}` is its own scope.

## Quick mental model

> **In a Template** → "Etch already knows what one item I'm showing → `{this.title}`."
> **In a Loop** → "I'm iterating; the current one is `item` → `{item.title}`."
> **On a Page** → "Etch knows nothing specific; I either use `{user.*}` / `{site.*}`, or I bring my own data source."

## Common mistakes

- Writing `{item.title}` on a Single template (outside any loop). Etch doesn't know what `item` refers to → empty output.
- Writing `{this.title}` inside a `{#loop … as item}` block. `this` still points to the template's main item (the archive), not the loop's current product → wrong title.
- Forgetting to wrap card markup in `{#loop mainQuery as item}` on an archive template → only the first row renders or nothing at all.
- Using `{currentUser.*}` or `{loggedInUser.*}` — those don't exist. The correct keyword is `{user.*}`.
- Expecting WooCommerce keys like `{this.meta._price}` on a non-product page — only available where the current item is a product.

## Sources

- [Etch](https://etchwp.com/?aff=06de86e5) — visual builder for WordPress
- Etch docs — [Dynamic Data Keys](https://docs.etchwp.com/dynamic-data/dynamic-data-keys)
- Etch docs — [Dynamic Data Intro](https://docs.etchwp.com/dynamic-data/dynamic-data-intro)
- Etch docs — [Intro to Templates](https://docs.etchwp.com/templates/intro-to-templates)
- Etch docs — [Archive Templates](https://docs.etchwp.com/templates/archive-templates)
- Etch docs — [Index Template (Catch All)](https://docs.etchwp.com/templates/index-template-catch-all)
- Etch docs — [Search Results Template](https://docs.etchwp.com/templates/search-results-template)
- Etch docs — [Basic Loops](https://docs.etchwp.com/loops/basic-loops)
- Etch docs — [Main Query Loops](https://docs.etchwp.com/loops/main-query)
