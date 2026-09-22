# Field notes: building a WooCommerce layer on Etch

Running evidence log from building and shipping [Woo4Etch](../README.md) — a
complete WooCommerce layer (nine editable layouts, dynamic-data bridges, a Store
API cart/checkout) on top of Etch, without WooCommerce's Gutenberg blocks.

**Why this exists.** Every entry below is something that cost real time to find,
usually because it failed *silently* — the page still rendered, just without the
price, the form, or the products past page one. Written down, they are data: a
concrete, reproducible picture of where a deep integration meets friction, so
that whenever WooCommerce moves up Etch's list, the groundwork is already
mapped. Shared in that spirit.

**Tone, deliberately.** This is not a complaint list. WooCommerce is one
integration among many and not a current focus for the Etch team, which is a
reasonable place to put roadmap effort. Several entries below are not defects at
all — they are consequences of sound architectural decisions that simply meet
commerce at an awkward angle. The generic dynamic-data seams Etch *did* build
are what make this plugin possible in the first place; where that shows, it is
said.

**Companion document:** [`ETCH-FEATURE-REQUESTS.md`](../ETCH-FEATURE-REQUESTS.md)
holds the distilled *asks* with minimal proposals. This file holds the *evidence*
behind them, plus the friction that never became an ask.

---

## How to read an entry

Every entry uses the same five fields, so nothing has to be reverse-engineered
later:

- **Symptom** — what a user or developer actually sees, and whether it is silent.
- **Cause** — the mechanism, located in the Etch source where possible.
- **Cost** — what finding and working around it took, concretely.
- **What ships instead** — the workaround in Woo4Etch today.
- **What would remove it** — the smallest change that makes the workaround unnecessary.

Versions are always named. Facts are verified against the Etch plugin source at
a **tag**, not a working tree.

Last full re-verification: **Etch 1.6.7**, 2026-09-22.

---

## 1. Raw-HTML sanitizer strips exactly what commerce emits

- **Symptom** — A shortcode that outputs a form renders as an empty element.
  `[woo_add_to_cart]` in a Raw HTML block produces no `<form>`, no `<input>`, no
  add-to-cart button. No error, no notice; the block is simply empty. Same for
  any third-party hook output containing `<form>`, `<select>` or `<script>` —
  which is most payment-gateway and offline-payment output.
- **Cause** — Etch's raw-HTML sanitizer removes `<form>`, `<input>`, `<select>`
  and `<script>` unless the global *"allow unsafe raw HTML"* setting is on, and
  it is off by default. Shortcodes run inside that sanitized content, so their
  output is sanitized too.
- **Cost** — This shapes the whole plugin architecture, not one feature. It is
  why `[do_action]` alone was never sufficient.
- **What ships instead** — Server-side **markers**: the layout emits an empty
  `<div data-w4e-hook="…">` or `<div data-w4e-add-to-cart="{this.id}">`, and a
  `render_block` filter at priority 20 fills it *after* Etch has rendered, so
  the sanitizer never sees the generated markup
  (`plugin/woo4etch/woo4etch.php`, `render_etch_placeholders()`). Security
  settings stay untouched. The same mechanism carries `data-w4e-args` and
  `data-w4e-skip-defaults`.
- **What would remove it** — An opt-in per block, or a documented allowlist hook
  (`etch/raw_html/allowed_tags`-style), so an integration can permit form markup
  for its own blocks without turning off the global safety net for the whole
  site.

## 2. Classes survive a builder save only if a style record references them

- **Symptom** — A layout renders correctly after install. The user opens it in
  the builder, changes something unrelated, saves — and WooCommerce's contract
  classes are gone: `<form class="w4e-product__form">` instead of
  `<form class="cart w4e-product__form">`, `<button class="button">` instead of
  `class="single_add_to_cart_button button">`. The shop keeps rendering, so
  nothing looks broken, but Woo's variation/AJAX JS contract and every
  `form.cart`-scoped style are dead.
- **Cause** — Etch's save reconciliation keeps a class only when the block also
  references a **style record** whose selector matches it. Classes present only
  in `attributes.class` are dropped on the first save. Empty records for `.cart`
  and `.single_add_to_cart_button` existed on the affected site; they were simply
  not *attached* to the blocks.
- **Cost** — A production incident on 2026-07-29 (Etch 1.6.4, Woo4Etch
  1.5.0-beta.6). Recovery was only possible because `wp_template` revisions still
  held the pre-save state. It also produced two follow-up issues
  ([#19](https://github.com/tobiashaas/woo4etch/issues/19),
  [#20](https://github.com/tobiashaas/woo4etch/issues/20)) whose real root cause
  was this.
- **What ships instead** — The layout builder creates a style record for **every
  literal class** it emits and attaches the record id to the block's `styles:[…]`,
  so the Woo contract survives arbitrary builder saves. A fast check asserts it
  for every shipped layout: *"every literal class has a referenced style record"*
  (`tests/php/test-layouts.php`).
- **What would remove it** — Either preserving unknown classes through save
  reconciliation, or a way to mark a class as structural/contractual so it is
  never reconciled away. The current behaviour is defensible for a design tool;
  it is dangerous specifically where a class is a machine contract rather than a
  styling hook.
- **Reference** — [issue #21](https://github.com/tobiashaas/woo4etch/issues/21).

## 3. The main-query loop is a *secondary* query, so WooCommerce's archive plumbing misses it

This one produced two separate shipped bugs, months apart, from the same root.

- **Symptom A (filters)** — On a shop or category archive, WooCommerce's native
  filter params (`?min_price`, `?max_price`, `?filter_<attribute>`) have no
  effect on the grid. The URL changes, the page reloads, the same products come
  back.
- **Symptom B (pagination)** — With more products than fit one page, products
  past a certain point are unreachable: the grid shows 10 per page while
  `[woo_pagination]` counts pages at WooCommerce's 16, so the last pages exist in
  the pagination and are empty or truncated in the grid.
- **Cause** — Etch's `main-query` loop does not iterate the main query; it
  re-runs the request as its own `WP_Query`. WooCommerce applies both its archive
  filters and `loop_shop_per_page` to the **main query only**, so neither reaches
  Etch's loop. The loop silently falls back to the blog reading setting (10).
- **Cost** — Shipped twice before being found, because both failure modes look
  like a configuration problem rather than a bug.
- **What ships instead** — The plugin re-applies both to secondary product
  queries while on a Woo archive: `pre_get_posts` + `posts_clauses` for the
  filters, and a per-page sync that copies the main query's page size onto
  secondary product loops that do not set one
  (`woo4etch/filter_secondary_product_queries`,
  `woo4etch/sync_secondary_per_page`). An integration check simulates the
  mismatch in memory (`tests/integration/checks/08-secondary-per-page.php`).
- **What would remove it** — Having the `main-query` loop iterate the actual main
  query (`$wp_query`), or, failing that, documenting prominently that it is a
  secondary query — the name reads as a promise it does not make.

## 4. Loop presets are referenced by an id that is not portable

- **Symptom** — A copy/paste layout containing a shop grid pastes into another
  site and renders **nothing**. No error; the loop simply produces no items.
- **Cause** — Loops reference a preset by `loopId`. That id is install-specific:
  Etch seeds `etch_main_query`, a plugin-created fallback is `w4e_main_query`.
  A static artifact freezes whichever existed at generation time, and on a target
  with a different id the loop points at a preset that does not exist.
- **Cost** — Two rounds. The first version used a bare `mainQuery` *target*,
  which never looped at all and emptied the shop archive for about two beta
  releases. The fix to `loopId` was strictly better and still not portable.
- **What ships instead** — The one-click installer resolves the id against the
  live site (`ensure_main_query_loop()`), and the static artifacts are asserted
  by a fast check to carry only a **portable** preset id, never an
  installer-minted `w4e_*` one.
- **What would remove it** — A stable, documented id for the seeded main-query
  preset, or allowing a loop to declare a preset *by type* (`main-query`) and let
  Etch resolve it locally at render time.
- **Reference** — [issue #13](https://github.com/tobiashaas/woo4etch/issues/13).

## 5. Content inside a false condition is invisible and uneditable in the builder

- **Symptom** — Anything inside a condition block whose condition is false in the
  builder context cannot be seen or edited. For a shop that is most of the
  interesting markup: the sale badge, the out-of-stock notice, the variation
  area, the empty-cart state, the coupon error.
- **Cause** — Conditions evaluate in the canvas the same way they do on the
  frontend, and the builder has no shopping session, no cart and no order.
- **Cost** — The standing workaround is to temporarily delete the condition,
  edit, and put it back — which is exactly the kind of manual step that ends with
  a condition forgotten and a sale badge on every product.
- **What ships instead** — Every dynamic-data bridge returns **sample data** in
  the builder canvas (sample cart rows, a sample order, sample payment methods,
  sample states), so conditions bound to *data* evaluate true and their content
  is visible and styleable. This is why `woo4etch/cart_sample_data`,
  `checkout_sample_data`, `order_sample`, `account_orders_sample` and
  `cross_sells_sample` exist at all.
- **What would remove it** — A builder-only "show all branches" or
  "force-evaluate true" affordance on condition blocks, or a documented
  builder-context flag usable *inside* a condition so an author can write
  `condition OR is-builder` themselves.
- **Reference** — [issue #4](https://github.com/tobiashaas/woo4etch/issues/4).

## 6. No endpoint-aware context: `{this.id}` is right, and still the wrong answer

- **Symptom** — On `/checkout/order-received/…`, firing
  `[do_action hook="woocommerce_thankyou" args="{this.id}"]` hands the callbacks
  the **wrong order**. Offline-gateway bank details render for some other order,
  or for none. Nothing errors.
- **Cause** — Not an Etch defect: the order-received endpoint is a rewrite on the
  checkout page, so the queried object genuinely *is* the checkout page and
  `{this.id}` correctly returns its id. The gap is that Etch's context has no
  notion of "the current order", so the correct-looking key is the wrong value
  and there is no way to tell from the builder.
- **Cost** — It was documented the wrong way round in our own reference for
  several releases, which is the clearest evidence of how natural the mistake is.
  Found and corrected on 2026-09-10.
- **What ships instead** — `{options.order.id}` and
  `{options.order.payment_method_id}`, exposed only on `order-received` /
  `view-order`, plus an explicit warning in the docs and in the docblock.
- **What would remove it** — Endpoint-aware template conditions and context
  (`is-order-received`, `is-account-endpoint:<name>`), so an integration can bind
  to the thing the page is actually about. This is
  [feature request #4](../ETCH-FEATURE-REQUESTS.md).

## 7. Programmatic layout install has to lift KSES

- **Symptom** — Writing Etch content to a post programmatically stores markup
  that Etch cannot read back: the block comments are gone.
- **Cause** — WordPress's KSES filters strip Etch's block comments for any user
  without `unfiltered_html`, which includes most admins on multisite and several
  managed hosts.
- **Cost** — Low once known, but it is invisible until a site with a restricted
  admin tries the one-click install.
- **What ships instead** — `kses_remove_filters()` around the write, restored in
  a `finally` (`class-woo4etch-health.php`, `append_to_post()`;
  `class-woo4etch-components.php` does the same for component installs).
- **What would remove it** — A supported `Etch::save_blocks( $post_id, $blocks )`
  entry point that handles this itself. Currently every integration that installs
  layouts server-side has to rediscover it.

## 8. No server-side API for components or templates

- **Symptom** — Two integrations that should be one-liners are hand-rolled.
- **Cause** — Etch's public scripting API (`etch.components`) covers component
  CRUD but runs only inside the builder. There is no server-side equivalent, and
  the template hub has no filter for plugin-registered templates.
- **Cost** — Woo4Etch writes `wp_block` posts with `etch_component_html_key` +
  `etch_component_properties` meta directly (the same approach the Bricks2Etch
  migrator uses), and adds its WooCommerce template group by **cloning Etch's own
  hub DOM in JavaScript** — which depends on seven internal class names
  (`.etch-templates__content`, `__content-item`, `__content-item-name`,
  `__content-item-wrapper`, `__content-item-wrapper-inner`, `__navigation`,
  `__navigation-item-wrapper`). All seven still match in 1.6.7, and all seven
  are a standing upgrade risk.
- **What ships instead** — `class-woo4etch-components.php` and
  `assets/etch-hub-templates.js`, both re-verified on every Etch upgrade.
- **What would remove it** — A server-side component install entry point, and a
  filter on the template hub's catalog. These are
  [feature requests #5](../ETCH-FEATURE-REQUESTS.md) and the nice-to-have below it.

## 9. Excerpt HTML renders as visible tags in a Paragraph block

- **Symptom** — A product short description containing `<strong>` or `<br>` shows
  the tags as literal text on the product page.
- **Cause** — WooCommerce allows HTML in the excerpt; a Paragraph block renders
  its content as text.
- **Cost** — Small, but it is the kind of thing every new Woo-on-Etch build hits
  once.
- **What ships instead** — The single-product layout renders the excerpt as a
  Raw HTML element, and a fast check asserts it stays one.
- **What would remove it** — Nothing on Etch's side necessarily; recorded because
  it is a predictable first-day stumble worth a line in Etch's own docs if Woo
  support ever lands.
- **Reference** — [issue #1](https://github.com/tobiashaas/woo4etch/issues/1).

---

## Cross-cutting observation

Eight of the nine entries above share one property: **they fail silently.** The
page renders, the builder looks right, and what is missing is a class, a form, a
query parameter or a page of products. That is the single most expensive
characteristic of this integration surface — far more than any individual gap —
because it turns a five-minute fix into a multi-day hunt, and because it reaches
production, where the cost is a shop that takes orders it cannot fulfil or shows
products it cannot sell.

If one thing from these notes is worth acting on, it is not any single feature
request. It is that the places where Etch drops something it does not recognise —
an unknown class on save, a tag in raw HTML, a loop preset that does not resolve —
are exactly the places where a warning would pay for itself many times over.

---

## Adding an entry

When something costs more than about an hour to figure out, it belongs here
while the detail is fresh. Use the five fields, and:

- **Name versions.** Etch, WooCommerce, WordPress, Woo4Etch. A claim without a
  version rots.
- **Verify against a tag**, never a working tree. The Etch checkout at
  `/Users/tobiashaas/Github/etch` sits on an older version than its tags, and
  `.wp-env-local/plugins/etch/` is another copy again:
  `git grep <pattern> 1.6.7 -- '*.php'`.
- **Point at the source** — file and symbol in Etch where the mechanism lives,
  and file and symbol in Woo4Etch where the workaround does.
- **Say whether it is silent.** That is the field that decides priority.
- **Separate the gap from the ask.** If it distils into a concrete proposal, add
  it to [`ETCH-FEATURE-REQUESTS.md`](../ETCH-FEATURE-REQUESTS.md) and link it
  from here; keep the evidence in this file.
- **Keep it neutral.** These notes are meant to be handed over and read by people
  who did not choose this problem. Describe the mechanism, not a verdict.
