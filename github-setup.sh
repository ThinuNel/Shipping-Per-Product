#!/usr/bin/env bash
# =============================================================================
# github-setup.sh — Shipping Per Product
#
# Initialises a git repository with one properly tagged commit per version
# and pushes everything (commits + tags) to GitHub.
#
# USAGE
#   1. Create an EMPTY repository on GitHub named "Shipping-Per-Product"
#      (do NOT tick "Add a README" or any initialisation option)
#   2. Edit the GITHUB_USERNAME line below
#   3. Open a terminal inside the "shipping-per-product" plugin folder
#   4. Run:  bash github-setup.sh
# =============================================================================

set -e  # Exit immediately on any error

# ── CONFIGURE THIS ────────────────────────────────────────────────────────────
GITHUB_USERNAME="ThinuNel"   # <-- change this
REPO_NAME="Shipping-Per-Product"
GITHUB_URL="https://github.com/${GITHUB_USERNAME}/${REPO_NAME}.git"
# ─────────────────────────────────────────────────────────────────────────────

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

# ── STEP 1: Initialise git ────────────────────────────────────────────────────
echo "[1/7] Initialising git repository..."
git init
git config user.name  "${AUTHOR_NAME}"
git config user.email "${AUTHOR_EMAIL}"

# ── STEP 2: v1.0.0 — Initial Release ─────────────────────────────────────────
echo ""
echo "[2/7] Creating commit for v1.0.0 (Initial Release)..."

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
- Flat shipping cost applied per-item quantity at checkout
- Shipping method registered as a proper WooCommerce shipping zone method"

git tag -a v1.0.0 -m "Version 1.0.0 — Initial Release"

# ── STEP 3: v1.0.1 — Security Hardening & Fatal Error Fix ────────────────────
echo ""
echo "[3/7] Creating commit for v1.0.1 (Security Hardening & Fatal Error Fix)..."

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
- Fixed fatal error on activation: class files now loaded via spp_load_classes()
  which is called both at plugins_loaded AND inside the activation hook

Security:
- Replaced single shared nonce with per-action nonces (spp_save_rule,
  spp_delete_rule, spp_search_products)
- Added \$wpdb->prepare() with typed placeholders on every DB query
- Added server-side product ID validation against wp_posts before saving
- Added shipping cost clamping (0-99999.99) and 2 decimal place rounding
- Added row existence check before update/delete operations
- Added wp_unslash() on all \$_POST / \$_GET reads
- Added capability check directly in render_page()

Other:
- Updated author to 'Hera Studio LK' linked to herastudiolk.com"

git tag -a v1.0.1 -m "Version 1.0.1 — Security Hardening & Fatal Error Fix"

# ── STEP 4: v1.0.2 — Checkout & Availability Fixes ───────────────────────────
echo ""
echo "[4/7] Creating commit for v1.0.2 (Checkout & Availability Fixes)..."

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
  cache now busted via WC_Cache_Helper after any rule is saved or deleted
- Fixed shipping method not always appearing: \$this->enabled now defaults
  to 'yes' so the method is live as soon as it is added to a zone
- Fixed method title overwritten before settings loaded: both enabled and
  title are now assigned after init() calls init_settings()

Other:
- Added README.md and .gitignore"

git tag -a v1.0.2 -m "Version 1.0.2 — Checkout & Availability Fixes"

# ── STEP 5: v1.0.3 — Brand Refresh, Auto-Updates & License ───────────────────
echo ""
echo "[5/7] Creating commit for v1.0.3 (Brand Refresh, Auto-Updates & License)..."

git add \
  shipping-per-product.php \
  includes/class-spp-shipping-class.php \
  includes/class-spp-shipping-method.php \
  includes/class-spp-admin.php \
  includes/class-spp-calculator.php \
  includes/class-spp-updater.php \
  assets/css/admin.css \
  assets/js/admin.js \
  assets/images/hera-logo.png \
  readme.txt \
  README.md \
  LICENSE.txt \
  .gitignore

GIT_COMMITTER_DATE="2025-04-01T10:00:00" \
GIT_AUTHOR_DATE="2025-04-01T10:00:00" \
git commit -m "v1.0.3 — Brand Refresh, Auto-Updates & License

UI / branding:
- Hera Studio LK company logo (favicon) added to plugin admin header
- Redesigned admin UI: black header, white primary text, cyan-to-purple
  gradient accent (matching Hera Studio brand)
- Gradient accent on primary Save button, form card top-border, and
  Select2 product chips
- DM Sans font loaded for a cleaner admin experience
- Responsive logo: sub-label hidden on small screens

Auto-updates:
- Added SPP_Updater class: hooks into WordPress's native update system
  and checks GitHub Releases API for new versions
- Admins see the standard 'Update available' notice and can update with
  one click from the Plugins list
- GitHub release notes are parsed and shown in the WP update popup
- API response cached for 12 hours to avoid GitHub rate limits

License:
- Added LICENSE.txt — proprietary license protecting the source code
  from redistribution and resale without written consent
- Updated plugin header License field to 'Proprietary'"

git tag -a v1.0.3 -m "Version 1.0.3 — Brand Refresh, Auto-Updates & License"

# ── STEP 6: Set branch and remote ────────────────────────────────────────────
echo ""
echo "[6/7] Configuring remote..."
git branch -M main
git remote add origin "${GITHUB_URL}"

# ── STEP 7: Push ──────────────────────────────────────────────────────────────
echo ""
echo "[7/7] Pushing all commits and tags to GitHub..."
git push -u origin main
git push origin --tags

echo ""
echo "==================================================="
echo "  Done! Your repository is live:"
echo "  https://github.com/${GITHUB_USERNAME}/${REPO_NAME}"
echo "==================================================="
echo ""
echo "NEXT STEPS:"
echo ""
echo "  1. Edit includes/class-spp-updater.php"
echo "     Change: const GITHUB_REPO = 'YOUR_GITHUB_USERNAME/Shipping-Per-Product';"
echo "     To:     const GITHUB_REPO = '${GITHUB_USERNAME}/Shipping-Per-Product';"
echo ""
echo "  2. Go to GitHub → Releases → Draft a new release"
echo "     For each tag (v1.0.0 … v1.0.3), create a release and write notes."
echo "     On v1.0.3, upload shipping-per-product.zip as a release asset."
echo ""
echo "  3. For future updates, see the 'Releasing a new version' section"
echo "     in README.md."
echo ""
