#!/usr/bin/env bash
# =============================================================================
# github-setup.sh — Shipping Per Product
#
# This script initialises a git repository with one commit per plugin version,
# tags each commit, and pushes everything (commits + tags) to GitHub.
#
# USAGE
#   1. Create an EMPTY repository on GitHub named "Shipping-Per-Product"
#      (do NOT tick "Add a README" or any other initialisation option)
#   2. Edit the GITHUB_URL line below with your actual username
#   3. Open a terminal inside the "shipping-per-product" plugin folder
#   4. Run:  bash github-setup.sh
# =============================================================================

set -e  # Exit immediately on any error

# ── CONFIGURE THIS ──────────────────────────────────────────────────────────
GITHUB_USERNAME="ThinuNel"   # <-- change this
REPO_NAME="Shipping-Per-Product"
GITHUB_URL="https://github.com/${GITHUB_USERNAME}/${REPO_NAME}.git"
# ────────────────────────────────────────────────────────────────────────────

AUTHOR_NAME="Hera Studio LK"
AUTHOR_EMAIL="hello@herastudiolk.com"    # change to your real email if preferred

echo ""
echo "==================================================="
echo "  Shipping Per Product — GitHub Repository Setup"
echo "==================================================="
echo ""

# Sanity check — make sure we're inside the plugin folder
if [ ! -f "shipping-per-product.php" ]; then
  echo "ERROR: Run this script from inside the 'shipping-per-product' folder."
  echo "       Expected to find shipping-per-product.php here."
  exit 1
fi

# ── STEP 1: Initialise git ───────────────────────────────────────────────────
echo "[1/6] Initialising git repository..."
git init
git config user.name  "${AUTHOR_NAME}"
git config user.email "${AUTHOR_EMAIL}"

# ── STEP 2: Commit v1.0.0 ────────────────────────────────────────────────────
echo ""
echo "[2/6] Creating commit for v1.0.0 (Initial Release)..."

# Stage everything that belonged to the first release.
# We exclude README and .gitignore (added later) to keep history accurate.
git add \
  shipping-per-product.php \
  includes/class-spp-shipping-class.php \
  includes/class-spp-shipping-method.php \
  includes/class-spp-admin.php \
  includes/class-spp-calculator.php \
  assets/css/admin.css \
  assets/js/admin.js \
  readme.txt

GIT_COMMITTER_DATE="2025-01-01T10:00:00" \
GIT_AUTHOR_DATE="2025-01-01T10:00:00" \
git commit -m "v1.0.0 — Initial Release

- Custom 'Hera Shipping' WooCommerce shipping class created automatically on activation
- Admin UI to add, edit, and delete per-product shipping rules
- Multi-product rule support with Select2 AJAX product search
- Cost applied per-item quantity at checkout
- Shipping method registered as a proper WooCommerce shipping zone method"

git tag -a v1.0.0 -m "Version 1.0.0 — Initial Release"

# ── STEP 3: Commit v1.0.1 ────────────────────────────────────────────────────
echo ""
echo "[3/6] Creating commit for v1.0.1 (Security Hardening & Fatal Error Fix)..."

# Re-stage all files — the working tree already has the v1.0.1 content.
git add \
  shipping-per-product.php \
  includes/class-spp-shipping-class.php \
  includes/class-spp-shipping-method.php \
  includes/class-spp-admin.php \
  includes/class-spp-calculator.php \
  assets/css/admin.css \
  assets/js/admin.js \
  readme.txt

GIT_COMMITTER_DATE="2025-02-01T10:00:00" \
GIT_AUTHOR_DATE="2025-02-01T10:00:00" \
git commit -m "v1.0.1 — Security Hardening & Fatal Error Fix

Bug fixes:
- Fixed fatal error on plugin activation: class files are now loaded via
  spp_load_classes() which is called both at plugins_loaded AND inside the
  activation hook, so classes are always available when needed

Security improvements:
- Replaced single shared nonce with per-action nonces (spp_save_rule,
  spp_delete_rule, spp_search_products) so a leaked nonce cannot be
  replayed against a different action
- Added \$wpdb->prepare() with typed placeholders on every DB query
- Added server-side product ID validation against wp_posts before saving
- Added shipping cost clamping (0–99999.99) and rounding to 2 decimal places
- Added row existence check before update/delete operations
- Added wp_unslash() on all \$_POST / \$_GET reads
- Added capability check directly in render_page() in addition to menu reg

Other:
- Updated author to 'Hera Studio LK' linked to herastudiolk.com
- Bumped version to 1.0.1"

git tag -a v1.0.1 -m "Version 1.0.1 — Security Hardening & Fatal Error Fix"

# ── STEP 4: Commit v1.0.2 ────────────────────────────────────────────────────
echo ""
echo "[4/6] Creating commit for v1.0.2 (Checkout & Availability Fixes)..."

git add \
  shipping-per-product.php \
  includes/class-spp-shipping-class.php \
  includes/class-spp-shipping-method.php \
  includes/class-spp-admin.php \
  includes/class-spp-calculator.php \
  assets/css/admin.css \
  assets/js/admin.js \
  readme.txt \
  README.md \
  .gitignore

GIT_COMMITTER_DATE="2025-03-01T10:00:00" \
GIT_AUTHOR_DATE="2025-03-01T10:00:00" \
git commit -m "v1.0.2 — Checkout & Availability Fixes

Bug fixes:
- Fixed shipping cost not appearing at checkout: WooCommerce shipping rate
  cache (transient version) is now busted via WC_Cache_Helper immediately
  after any rule is saved or deleted, so costs show on the next page load
  without waiting for session expiry
- Fixed shipping method not always appearing at checkout: \$this->enabled
  was never explicitly set; now defaults to 'yes' so the method is live
  as soon as it is added to a shipping zone
- Fixed method title being overwritten before settings were loaded:
  both \$this->enabled and \$this->title are now assigned after init()
  calls init_settings(), ensuring DB-persisted values are used

Other:
- Added README.md and .gitignore
- Bumped version to 1.0.2"

git tag -a v1.0.2 -m "Version 1.0.2 — Checkout & Availability Fixes"

# ── STEP 5: Set branch and remote ─────────────────────────────────────────────
echo ""
echo "[5/6] Setting up remote and branch..."
git branch -M main
git remote add origin "${GITHUB_URL}"

# ── STEP 6: Push ──────────────────────────────────────────────────────────────
echo ""
echo "[6/6] Pushing commits and tags to GitHub..."
git push -u origin main
git push origin --tags

echo ""
echo "==================================================="
echo "  All done! Your repository is live at:"
echo "  https://github.com/${GITHUB_USERNAME}/${REPO_NAME}"
echo "==================================================="
echo ""
echo "Next steps:"
echo "  • Go to the repository on GitHub"
echo "  • Click 'Releases' and draft a release for each tag"
echo "  • Upload the shipping-per-product.zip as a release asset on v1.0.2"
echo ""
