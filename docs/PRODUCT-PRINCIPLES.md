# Product principles

Standing product guidance for Woo4Etch. Use these when designing features, reviewing pull requests, and deciding whether a fix is acceptable.

Related: [`ADR-001 — No WooCommerce template overrides`](./ADR-001-no-template-overrides.md).

---

## Primary principle: Merchant and Builder Freedom

Woo4Etch exists to give site builders and merchants full control over WooCommerce shop experiences inside Etch.

The primary product goal is **not** merely to make WooCommerce work in Etch. It is to ensure that users can create, structure, style, extend, and maintain their own shop layouts in Etch without being locked into rigid WooCommerce templates, opaque block implementations, or plugin-controlled markup.

### Dual requirements

When reviewing changes, protect **both**:

1. WooCommerce workflows must remain correct, secure, and reliable.
2. The relevant shop UI and layout logic must remain meaningfully editable and composable in Etch.

A change is potentially **high severity** if it preserves a functional storefront only by removing or materially reducing a user’s ability to control the corresponding layout, markup, hooks, dynamic data, or styling in Etch.

### Anti-patterns (do not ship)

Do not introduce a fix that solves a WooCommerce issue by unnecessarily:

- hard-coding output that builders cannot edit in Etch
- forcing users into a WooCommerce block or PHP template
- bypassing Etch layouts
- reducing the ability to place and style WooCommerce elements in Etch

### Prefer solutions that preserve

- Editable Etch templates, pages, components, and predefined layouts
- Explicit placement of product, cart, checkout, account, and hook-based output
- Clear dynamic-data contracts that builders can use safely in Etch
- Native WooCommerce behavior where it is required for correctness
- Progressive enhancement instead of JavaScript-only or opaque implementations
- Interoperability with standard WooCommerce hooks, shortcodes, and extension points
- Portable, importable predefined layouts and generated Etch copy/paste artifacts
- The ability to replace, reorder, restyle, or selectively omit shop UI without breaking core commerce behavior

### Architectural regressions (careful review required)

Treat the following as architectural regressions:

| Regression | Why it hurts freedom |
|---|---|
| Replacing editable Etch markup with inaccessible or non-configurable hard-coded output | Layout ownership leaves the builder |
| Moving core layout decisions from Etch into PHP or JavaScript without a compelling correctness reason | Customization and restyling become plugin-bound |
| Introducing hidden dependencies on a specific WooCommerce block, template, DOM structure, or proprietary layout state | Layouts stop being portable and composable |
| Making a predefined layout technically render while preventing users from safely adapting it | “Works” without remaining editable |
| Removing or bypassing standard hooks, shortcode entry points, dynamic-data bindings, or documented extension points | Extension ecosystem and documented recipes break |
| Making copy/paste layouts non-portable, dependent on installation-specific IDs, or difficult to customize after import | Predefined layouts stop being a template library |

### How this maps to the mental model

| Layer | Who owns it | Freedom implication |
|---|---|---|
| Etch HTML / layouts / classes | Merchant / builder | Must stay editable, reorderable, omit-able |
| Required Woo attributes (`form.cart`, `name="add-to-cart"`, …) | Documented contract | Keep; do not replace with opaque substitutes |
| Hooks / shortcodes / dynamic-data bridges | Plugin + docs | Prefer explicit placement over hidden injection |
| Cart maths, checkout processing, stock, coupons | WooCommerce core | Correctness here may constrain *how* UI is expressed — not *whether* UI lives in Etch |

See also the core mental model in [`CLAUDE.md`](../CLAUDE.md) and [`ADR-001`](./ADR-001-no-template-overrides.md).
