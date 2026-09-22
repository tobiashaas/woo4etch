# Releases & WordPress updates

Woo4Etch is distributed via [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases). Sites installed under **`wp-content/plugins/woo4etch/`** receive updates through the normal WordPress **Dashboard → Updates** flow.

## For maintainers (after merge to `main`)

1. **Bump the version** in both files (same semver, no `v` prefix in files):
   - `plugin/woo4etch/woo4etch.php` → `Version:`
   - `plugin/woo4etch/readme.txt` → `Stable tag:`
2. Update the changelog in **both** places: `plugin/woo4etch/readme.txt` (`== Changelog ==`) and the root `CHANGELOG.md` (including the release-link at the bottom). Move the `Unreleased` entries under the new version heading.
3. Commit and push to `main`.
4. Release — **either route**, they produce the same result:

   **a) From `main` (no local tag).** Actions → **Release** → *Run workflow*.
   The workflow reads `Version:` out of `plugin/woo4etch/woo4etch.php`, derives
   the tag (`v` + version) and creates it at the dispatched commit.

   **b) By pushing the tag yourself** (must match the plugin version):

   ```bash
   git tag v1.2.2
   git push origin v1.2.2
   ```

5. GitHub Actions (`.github/workflows/release.yml`) **refuses to publish unless
   the tag, `Version:` in `woo4etch.php` and `Stable tag:` in `readme.txt` all
   agree**, lints the plugin on PHP 8.1, builds **`woo4etch.zip`** and attaches
   it to the release.
6. WordPress installs check the latest release within ~12 hours (or immediately after **Dashboard → Updates → Check again**).

> The three version markers are also asserted by the fast checks
> (`php tests/php/run.php`), so a mismatch fails CI before it ever reaches the
> release workflow.

## Beta / pre-releases

Tag with a semver suffix (e.g. `v1.5.0-beta.1`, matching the plugin `Version:`). The workflow marks the GitHub release as **pre-release** automatically (any tag containing `-`). The updater reads `releases/latest`, which skips pre-releases — so betas are never pushed to installed sites; testers install the zip manually. The final release then uses the plain tag (`v1.5.0`), and sites on the beta are offered the update (`1.5.0-beta.1` < `1.5.0`).

## Zip layout (required)

The workflow zips `plugin/woo4etch/` so WordPress unpacks to:

```text
wp-content/plugins/woo4etch/woo4etch.php
```

Do not change the inner folder name without updating the updater.

## MU-plugin installs

Auto-updates apply to the **regular** plugin path (`wp-content/plugins/woo4etch/`). MU-plugin copies must be updated manually.

## Disable remote update checks

```php
add_filter('woo4etch/enable_github_updates', '__return_false');
```
