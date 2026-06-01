# Woo4Etch — local demo & manual test harness

A reproducible WooCommerce-in-Etch demo running on [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/): real **Etch** plugin + **Etch theme** + **WooCommerce** + the **Woo4Etch** plugin, with sample products and demo templates built the Woo4Etch way (Etch Dynamic Keys + section/container + Woo4Etch shortcodes).

> This folder is **test/demo tooling**, not part of the shipped plugin. The plugin itself is `plugin/woo4etch/`.

## Requirements

- Docker running, Node ≥ 18.
- A built Etch plugin and the Etch theme on disk, referenced by `.wp-env.json` (paths are machine-specific — `.wp-env.json` is gitignored; copy/adjust it for your machine). The demo expects **Etch 1.4.20+** (for the product `gallery_images` Dynamic Key).

## Run

```bash
npx wp-env start                                   # boot WordPress + WooCommerce + Etch
bash tests/manual/run.sh                           # activate, install Woo pages, run the shortcode harness + checks
npx wp-env run cli wp theme activate etch-theme
npx wp-env run cli wp plugin activate etch
# import sample products (once):
npx wp-env run cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=create
# build the demo templates/pages:
npx wp-env run cli wp eval-file wp-content/woo4etch-tests/manual/seed-demo.php
npx wp-env run cli wp option update woocommerce_coming_soon no
```

Open **http://localhost:9100** (admin / `password`). Shop, single product, cart, my-account are all built with the demo templates.

## What's in here

| File | What it is |
|---|---|
| `seed-demo.php` | Builds the demo: a product loop preset (`etch_loops`), FSE templates (`single-product`, `archive-product`, `page`, `page-cart`), `header`/`footer` template parts, and the custom cart page content. All authored as **Etch block markup** (`wp:etch/element`, `wp:etch/loop`, `wp:etch/text`, `wp:etch/condition`) using the **section/container** convention and Dynamic Keys. |
| `demo-mu/demo.css` | **The demo stylesheet.** Plain CSS for the demo's own classes (`.w4e-*`) — header, product grid/gallery, shop cards, cart layout, account nav. It is *demo presentation only*; the Woo4Etch **plugin ships no CSS**. In a real Etch project you'd style via Etch's own CSS panel / global styles (which apply in the builder and the frontend automatically); here it's an external file so the demo is self-contained. |
| `demo-mu/woo4etch-demo.php` | mu-plugin that loads `demo.css` **both** on the frontend (`wp_enqueue_style`) **and** inside the Etch builder canvas (`etch/canvas/additional_stylesheets`), and also injects WooCommerce's own frontend CSS into the canvas — so the builder preview matches the live site. |
| _(cart bridge)_ | The "cart as Etch dynamic data" bridge (`{options.cart_items}` etc.) is now a **built-in Woo4Etch plugin feature** — no demo file needed. See template 15 → "cart as pure Etch HTML". |
| `shortcode-harness.php` | Seeds a product/cart/order and runs **every** Woo4Etch shortcode, printing a PASS/FAIL table. Run: `wp eval-file wp-content/woo4etch-tests/manual/shortcode-harness.php`. |
| `run.sh` | One-shot: activate plugins, install Woo pages, prove the auto theme-support fix, run the harness, HTTP-check key pages. |
| `*.png` | Screenshots from verification runs (gitignored). |

## Demo coverage

- **Single product** — Etch Dynamic Keys (`{this.title}`, `{this.image.url}`, `{this.excerpt}`, `{this.meta._*}`), gallery loop over `{this.gallery_images}`, sale price via an Etch **condition** + `numberFormat` **modifier**, and a **hand-built add-to-cart form** (no shortcode — works because `{this.id}` / `{this.permalink.relative}` resolve in attributes).
- **Shop archive** — Etch **loop** over a `wp-query` product preset (`{item.*}`).
- **Cart** — built as a real Etch **form** (no shortcodes, so it renders/edits in the builder): a loop over `{options.cart_items}` with quantity inputs, coupon fields and `{options.cart_nonce}`; quantity update / coupon / remove all work. (For third-party cart-extension hooks instead, swap in the `[woo_cart_items]` shortcode — see templates/15.)
- **My Account / Checkout** — native Woo areas inside the Etch `page` chrome.

Everything visible is an **Etch element** (no core `wp:post-title` etc. in the layout), structured as `section > container`, except where WooCommerce's runtime (cart/checkout/AJAX) genuinely requires a shortcode bridge.
