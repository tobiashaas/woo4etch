# Contributing

Thank you for helping improve **woo4etch**. This project is open source and free for everyone to use and build on.

## License

By contributing, you agree that your contributions will be licensed under the same [MIT License](LICENSE) as the rest of the repository.

## How to contribute

1. Fork [github.com/tobiashaas/woo4etch](https://github.com/tobiashaas/woo4etch).
2. Create a branch for your change (`git checkout -b fix/cart-notices`).
3. Make your edits in a normal git clone (not inside a OneDrive-synced folder — `.git` and sync tools conflict).
4. Commit with a clear message describing *why* the change helps.
5. Open a pull request against `main` and describe what you tested.

## What to contribute

- Fixes or clarifications in [`templates/`](templates/) or the [knowledge base](WooCommerce-in-Etch-Knowledgebase.md)
- New shortcodes or hardening in [`plugin/woo4etch/`](plugin/woo4etch/)
- Test checklists, accessibility notes, or Etch/WooCommerce version updates

## Scope

Keep changes focused. Prefer small, reviewable PRs over large rewrites. Match the existing tone: practical, copy-ready templates, minimal PHP unless the bridge plugin is the right place.

## Before you open a PR

Run the fast checks — they gate every PR and need no WordPress, database or composer:

```bash
php tests/php/run.php
```

If you changed a **layout definition**, regenerate the copy/paste artifacts, or CI will fail on drift:

```bash
php tools/generate-etch-copy.php
```

CI additionally lints PHP 8.1→8.5 and runs the integration checks against a real
WordPress + WooCommerce ([`tests/integration/`](tests/integration/README.md)).

Changing the plugin? Keep these in sync in the same PR — the fast checks assert
the version markers, and the catalog drives both registration and the admin
reference: `plugin/woo4etch/readme.txt`, the root `CHANGELOG.md` (under
`Unreleased`), `templates/15-woo4etch-plugin.md`, and the version constants.
Don't bump versions or tag releases in a PR — that's the maintainer release step below.

## Product principles (review lens)

Before opening a PR — especially plugin or layout changes — check [`docs/PRODUCT-PRINCIPLES.md`](docs/PRODUCT-PRINCIPLES.md). The primary principle is **Merchant and Builder Freedom**: WooCommerce must stay correct *and* shop UI must remain meaningfully editable in Etch. Do not “fix” commerce bugs by hard-coding markup, forcing Woo blocks/PHP templates, or removing hooks, dynamic-data contracts, or portable layout artifacts.

## Releases (maintainers)

After your PR is merged to `main`:

1. Bump `Version` in `plugin/woo4etch/woo4etch.php` and `Stable tag` in `plugin/woo4etch/readme.txt`.
2. Move the `Unreleased` entries under the new version heading in **both** `readme.txt` and the root `CHANGELOG.md`.
3. Commit and push to `main`.
4. Release either from Actions → **Release** → *Run workflow* (the tag is derived from `Version:`), or by pushing the matching tag:

   ```bash
   git tag v1.2.2
   git push origin v1.2.2
   ```

5. GitHub Actions verifies the version markers agree, builds `woo4etch.zip` and publishes the release.
6. WordPress sites with Woo4Etch installed under **Plugins** will see the update (usually within 12 hours).

Details: [`.github/RELEASE.md`](.github/RELEASE.md).

## Questions

Open a [GitHub issue](https://github.com/tobiashaas/woo4etch/issues) for bugs, ideas, or questions before large refactors.
