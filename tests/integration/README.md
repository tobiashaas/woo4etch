# Integration checks (layers 4–5)

The checks the service-free layer (`tests/php/`) cannot cover: they need a
**real WordPress + WooCommerce + Woo4Etch install**. Instead of a DB-wiping
wp-phpunit harness they run as standalone `wp eval-file` scripts — strictly
**non-destructive** (temp files/dirs only, every mutation reverted in
`finally`), so they are safe against a live staging site.

## Running

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

## Why not wp-phpunit on the staging server

The wp-phpunit test suite **wipes its database** and therefore needs a
separate test DB — typical managed hosts (like the current staging server)
grant exactly one database, so the harness can't run there. These eval-file
checks deliberately avoid that requirement.

## CI (wp-env) — the remaining piece of issue #12

The check files are environment-agnostic: inside a wp-env container the same
suite runs as

```bash
wp-env run cli -- wp eval-file tests/integration/checks/01-upgrader-preservation.php
```

Wiring that into a (slow, Docker-booting) GitHub Actions job — separate from
the fast `checks` path — is what remains of issue #12.
