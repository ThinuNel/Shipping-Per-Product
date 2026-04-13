# Versioning & Release Guide
**Shipping Per Product — Hera Studio LK**

This document explains how versions are structured, how to release a new version, and how to maintain the GitHub repository over time.

---

## Version Number Format

This plugin uses **Semantic Versioning**: `MAJOR.MINOR.PATCH`

| Segment | When to increment | Example |
|---|---|---|
| `MAJOR` | Breaking change — existing data or setup must change | `2.0.0` |
| `MINOR` | New feature added, fully backward-compatible | `1.2.0` |
| `PATCH` | Bug fix or small improvement, no new features | `1.1.1` |

**Examples in this project:**
- `1.0.0 → 1.0.1` — Fatal error bug fix (PATCH)
- `1.0.1 → 1.0.2` — Checkout bug fix (PATCH)
- `1.0.2 → 1.0.3` — New UI + auto-updater (MINOR)
- `1.0.3 → 1.1.0` — New weight-based engine (MINOR)

---

## What You Must Update for Every Release

Before running `release.sh`, update **both** of these lines in `shipping-per-product.php`:

```php
 * Version:     1.2.0          ← plugin header (line ~6)

define( 'SPP_VERSION', '1.2.0' );   ← PHP constant (line ~22)
```

Also add a changelog entry to the **Changelog** section of `README.md`:

```markdown
### v1.2.0 — Short Title
- **New** Description of what was added
- **Fixed** Description of what was fixed
- **Changed** Description of what was changed
```

---

## Releasing a New Version (Step by Step)

### The short version (after setup):
```bash
# 1. Make your code changes
# 2. Bump version in shipping-per-product.php (both places)
# 3. Update README.md changelog
# 4. Run:
bash release.sh 1.2.0
# 5. Publish the GitHub Release (the script tells you the URL)
# 6. Upload the generated zip as a release asset
```

### The full version (manual, if you prefer):

**1. Make your code changes**

**2. Bump the version** in `shipping-per-product.php` — header and constant.

**3. Update `README.md`** — add changelog entry at the top of the Changelog section.

**4. Build the versioned zip**
```bash
cd ..   # go one level above the plugin folder
zip -r shipping-per-product-1.2.0.zip shipping-per-product/ \
  --exclude "*/.git/*" \
  --exclude "*/github-setup.sh" \
  --exclude "*/release.sh" \
  --exclude "*.zip"
```

**5. Stage and commit**
```bash
cd shipping-per-product
git add -A
git commit -m "v1.2.0 — Short description of changes"
```

**6. Tag the commit**
```bash
git tag -a v1.2.0 -m "Version 1.2.0"
```

**7. Push everything**
```bash
git push origin main
git push origin v1.2.0
```

**8. Publish on GitHub**
- Go to `https://github.com/YOUR_USERNAME/Shipping-Per-Product/releases/new?tag=v1.2.0`
- Title: `Shipping Per Product v1.2.0`
- Description: paste the changelog entry from `README.md`
- Attach `shipping-per-product-1.2.0.zip` as a release asset
- Click **Publish release**

> The auto-updater on installed sites checks GitHub Releases every 12 hours
> and will show the standard WordPress "Update available" notice automatically.

---

## Branch Strategy

| Branch | Purpose |
|---|---|
| `main` | Always the latest stable, released version |
| `dev` *(optional)* | Work-in-progress for the next version |

To work on the next version without affecting `main`:
```bash
git checkout -b dev
# ... make changes ...
git push origin dev

# When ready to release, merge back to main:
git checkout main
git merge dev
bash release.sh 1.2.0
```

---

## Correcting a Mistake After Pushing

**If you pushed but haven't published the GitHub Release yet:**
```bash
# Delete the local and remote tag
git tag -d v1.2.0
git push origin --delete v1.2.0

# Fix the mistake, then re-tag and push
git add -A
git commit --amend          # amend the last commit, or make a new one
git tag -a v1.2.0 -m "Version 1.2.0"
git push origin main --force-with-lease
git push origin v1.2.0
```

**If the GitHub Release is already published and sites have already seen it:**
- Do NOT delete the tag — it will break the updater for sites mid-update
- Instead, fix the issue and release as a new PATCH version (e.g. `1.2.1`)

---

## Checking the Auto-Updater is Working

After publishing a release, you can force WordPress to check for updates:

1. Go to **Dashboard → Updates** in WordPress admin
2. Click **Check Again**
3. If a newer version exists on GitHub, the plugin will appear in the update list

To test on a development site without waiting 12 hours:
```php
// Add temporarily to functions.php, remove after testing
delete_transient( 'spp_github_update_data' );
```

---

## Release History

| Version | Date | Type | Summary |
|---|---|---|---|
| `v1.1.0` | 2025-05-01 | Minor | Unified shipping engine (weight + per-product) |
| `v1.0.3` | 2025-04-01 | Minor | Brand refresh, auto-updater, proprietary license |
| `v1.0.2` | 2025-03-01 | Patch | Checkout & availability fixes |
| `v1.0.1` | 2025-02-01 | Patch | Security hardening & fatal error fix |
| `v1.0.0` | 2025-01-01 | Major | Initial release |
