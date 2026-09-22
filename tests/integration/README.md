# Integration checks (layers 4–5)

The checks the service-free layer (`tests/php/`) cannot cover: they need a
**real WordPress + WooCommerce + Woo4Etch install**. Instead of a DB-wiping
wp-phpunit harness they run as standalone `wp eval-file` scripts — strictly
**non-destructive** (temp files/dirs only, every mutation reverted in
`finally`), so they are safe against a live staging site.

## Running

Inside the repo's own wp-env instance (what CI uses; needs Docker and a
`.wp-env.json` mapping `wp-content/woo4etch-tests` to `./tests`, as the repo's
config does):

```bash
tests/integration/run.sh --wp-env
```

Against a local install:

```bash
tests/integration/run.sh --path /var/www/mysite
```

Against a remote install over SSH (key auth, or `export SSHPASS=…` with
`sshpass` installed):

```bash
tests/integration/run.sh --ssh user@host --path /var/www/mysite
```

The runner copies `checks/*.php` to a remote temp dir, runs each through
`wp eval-file`, and exits non-zero if any check fails.

> Deploy first: the checks test the plugin **as installed** at the target —
> they don't sync your working tree.

## The checks

| File | Layer | What it proves |
|---|---|---|
| `01-upgrader-preservation.php` | 4 | A user-edited `includes/customizations.php` survives a simulated plugin update; an **untouched skeleton is NOT resurrected** (shipped skeleton improvements arrive). Runs the real `upgrader_pre_install` / `upgrader_post_install` filters against temp destination dirs. |
| `02-woo-root-shape.php` | 4 | `Woo4Etch_Woo_Root::build_data()` with WooCommerce active: all documented keys (`cart.items`, `checkout.url`, `account.menu`, …) exist and are shaped as the templates rely on. Read-only. |
| `03-external-customizations.php` | 4 | `wp-content/woo4etch-customizations.php` is loaded when present (verified in a freshly booted WP process), and no longer after removal. |
| `04-frontend-smoke.php` | 5 | WooCommerce's assigned pages respond 200; installed layouts render their markers; a single product page renders the Woo add-to-cart contract (`form.cart`, add-to-cart control). Layouts not installed on the target are skipped, not failed. |
| `05-checkout-rate-limit.php` | 4 | The classic-checkout sliding-window counter and its validation hook, exercised with a synthetic client fingerprint. Settings restored, transients removed. |
| `06-store-api.php` | 5 | The Store API cart layer's dependencies: the cart endpoints answer, the script/settings wiring is in place, the cart bridge exposes the coupon keys, and the shipped layouts carry the region markers. |
| `07-store-api-checkout.php` | 5 | The A+ checkout bridge: the routes the frontend module writes to, the Germanized guard pieces, and the gateway allowlist wiring. |
| `08-secondary-per-page.php` | 4 | Etch's main-query loop re-runs the request as a secondary `WP_Query` that `loop_shop_per_page` never reaches; asserts the plugin's sync so the grid and `[woo_pagination]` agree. Simulated in memory, read-only. |
| `09-checkout-address-locale.php` | 4 | **The state/province premise itself**, asked of WooCommerce rather than restated: AU's locale override renames `state` and leaves it *required*, DE hides it, AT has no state list. Then the `{options.checkout}` payload the layout loops over (states with `code`/`name`/`selected`, the label/required/hidden flags, `address_2_*`) plus the cart's shipping keys. Billing country, base country and cart restored in `finally`. |

## Why not wp-phpunit on the staging server

The wp-phpunit test suite **wipes its database** and therefore needs a
separate test DB — typical managed hosts (like the current staging server)
grant exactly one database, so the harness can't run there. These eval-file
checks deliberately avoid that requirement.

## CI

The check files are environment-agnostic, so the same suite runs in GitHub
Actions as the **`Integration (wp-env)`** job on every PR — a separate,
Docker-booting job off the fast `checks` path (issue #12, closed). It writes its
own `.wp-env.json` (latest WordPress + WooCommerce) and runs
`tests/integration/run.sh --wp-env`.

**Etch is not installed there**, so Etch-dependent assertions skip rather than
fail. A layout-rendering claim that only holds with Etch present needs a local
or staging run to actually be exercised.

> Note for check authors: wp-env installs the **current** WooCommerce, so a
> check that pins WooCommerce's own internal behaviour can start failing on a
> branch nothing changed — upstream moved. Assert the bridge's output (stable
> across versions) and treat Woo-internal quirks as version-conditional; see
> the `hidden`-flag handling in `09-checkout-address-locale.php`.
