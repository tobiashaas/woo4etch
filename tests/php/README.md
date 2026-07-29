# Fast PHP checks (no WordPress)

Service-free tests that gate every PR (issue #10, layers 2–3). They run under
plain PHP-CLI — no WordPress, no WooCommerce, no database — because they only
inspect *metadata* (the shortcode catalog) and *data* (the layout block trees),
never execute the shortcodes themselves.

## Run

```bash
php tests/php/run.php
```

Exit code `0` = all checks passed; non-zero = at least one failed (the failures
are listed at the end). The same command runs in CI via
[`.github/workflows/test.yml`](../../.github/workflows/test.yml).

## What's covered

| File | Layer | Checks |
|---|---|---|
| `test-consistency.php` | 2 | `woo4etch.php` header `Version:` == `Woo4Etch::VERSION` == `readme.txt` `Stable tag`; every non-native catalog entry points at an existing `shortcode_*` method; no orphan `shortcode_*` methods; every registered shortcode is documented in `readme.txt`; catalog entries are complete. |
| `test-layouts.php` | 3 | Every layout block tree is well-formed; **every `etch/loop` binds to a data-path target or a `loopId`, never a bare query key like `mainQuery`** (the bug that emptied the shop archive for ~2 betas); the single-product layout keeps the Woo contract (`form.cart`, `name="add-to-cart"`, `.single_add_to_cart_button`, gallery `__image` + `data-large_image`, excerpt as `etch/raw-html`); the committed `templates/etch-copy/*.json` artifacts satisfy the same loop invariant, and any `loopId` they carry is a portable preset id (never an installer-minted `w4e_*` id, which doesn't exist on paste targets — issue #13). |

`bootstrap.php` is the WordPress shim (constants + stub functions) that lets the
plugin source load standalone. `lib.php` is a tiny pass/fail harness — no PHPUnit
or composer needed.

## Artifact drift guard

CI also runs `php tools/generate-etch-copy.php` and fails if it changes any
`templates/etch-copy/*.json` — i.e. the copy/paste artifacts must stay in sync
with the layout definitions they're generated from. If a layout test fails on a
committed artifact, regenerate and commit:

```bash
php tools/generate-etch-copy.php
```

> Heavier integration tests (upgrader preservation, `Woo4Etch_Woo_Root::build_data()`,
> external `customizations.php` loading) and an E2E smoke run need `wp-env` +
> WooCommerce and are tracked as a follow-up (issue #10, layers 4–5).
