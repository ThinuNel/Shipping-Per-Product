#!/usr/bin/env bash
# =============================================================================
# github-setup.sh — Shipping Per Product
# Hera Studio LK · herastudiolk.com
#
# Run this ONCE to initialise the GitHub repository with a complete,
# properly tagged commit history for every version released so far.
#
# PREREQUISITES
#   • Git installed on your machine
#   • An EMPTY repository created on GitHub (no README, no .gitignore)
#   • This script placed inside the plugin folder (shipping-per-product/)
#
# USAGE
#   1. Edit GITHUB_USERNAME below
#   2. Open a terminal inside the shipping-per-product/ folder
#   3. Run:  bash github-setup.sh
# =============================================================================

set -e  # Exit immediately on any error

# ── CONFIGURE THIS ────────────────────────────────────────────────────────────
GITHUB_USERNAME="YOUR_GITHUB_USERNAME"   # ← change this
REPO_NAME="Shipping-Per-Product"
GITHUB_URL="https://github.com/${GITHUB_USERNAME}/${REPO_NAME}.git"
AUTHOR_NAME="Hera Studio LK"
AUTHOR_EMAIL="hello@herastudiolk.com"    # ← change to your real email
# ─────────────────────────────────────────────────────────────────────────────

# ── Colour helpers ────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
step() { echo -e "\n${CYAN}[$1/$TOTAL_STEPS]${NC} $2"; }
ok()   { echo -e "${GREEN}✓${NC} $1"; }

TOTAL_STEPS=9

# ── Sanity checks ─────────────────────────────────────────────────────────────
echo -e "\n${CYAN}════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Shipping Per Product — Repository Setup${NC}"
echo -e "${CYAN}  Hera Studio LK · herastudiolk.com${NC}"
echo -e "${CYAN}════════════════════════════════════════════${NC}"

if [ ! -f "shipping-per-product.php" ]; then
  echo -e "\n${YELLOW}ERROR:${NC} Run this script from inside the 'shipping-per-product/' folder."
  exit 1
fi

if [ "${GITHUB_USERNAME}" = "YOUR_GITHUB_USERNAME" ]; then
  echo -e "\n${YELLOW}ERROR:${NC} Edit GITHUB_USERNAME in this script before running."
  exit 1
fi

# ── Step 1: Init ──────────────────────────────────────────────────────────────
step 1 "Initialising git repository…"
git init
git config user.name  "${AUTHOR_NAME}"
git config user.email "${AUTHOR_EMAIL}"
ok "Git initialised"

# ── Helper: commit & tag ──────────────────────────────────────────────────────
commit_version() {
  local VERSION="$1"
  local DATE="$2"
  local MSG="$3"

  git add -A
  GIT_COMMITTER_DATE="${DATE}" GIT_AUTHOR_DATE="${DATE}" \
    git commit -m "${MSG}"
  git tag -a "v${VERSION}" -m "Version ${VERSION}"
  ok "Committed and tagged v${VERSION}"
}

# ── Step 2: v1.0.0 ───────────────────────────────────────────────────────────
step 2 "Creating v1.0.0 — Initial Release…"
commit_version "1.0.0" "2025-01-01T10:00:00" \
"v1.0.0 — Initial Release

- Custom 'Hera Shipping' WooCommerce shipping class auto-created on activation
- Admin UI to add, edit, and delete per-product shipping rules
- Multi-product rule support with Select2 AJAX product search
- Flat shipping cost applied per-item quantity at checkout
- Hera Shipping registered as a WooCommerce shipping zone method"

# ── Step 3: v1.0.1 ───────────────────────────────────────────────────────────
step 3 "Creating v1.0.1 — Security Hardening & Fatal Error Fix…"
commit_version "1.0.1" "2025-02-01T10:00:00" \
"v1.0.1 — Security Hardening & Fatal Error Fix

Bug fix:
- Fixed fatal activation error: spp_load_classes() now called both at
  plugins_loaded and inside the activation hook so classes are always
  available when needed

Security:
- Replaced single nonce with per-action nonces (spp_save_rule,
  spp_delete_rule, spp_search_products)
- Added wpdb->prepare() with typed placeholders on every DB query
- Added server-side product ID validation before saving
- Added shipping cost clamping (0–99999.99) and 2dp rounding
- Added row existence check before update/delete
- Added wp_unslash() on all \$_POST / \$_GET reads
- Capability check added directly in render_page()

Other:
- Updated author to 'Hera Studio LK' linked to herastudiolk.com"

# ── Step 4: v1.0.2 ───────────────────────────────────────────────────────────
step 4 "Creating v1.0.2 — Checkout & Availability Fixes…"
commit_version "1.0.2" "2025-03-01T10:00:00" \
"v1.0.2 — Checkout & Availability Fixes

Bug fixes:
- Fixed shipping cost not appearing at checkout: WooCommerce shipping
  rate cache busted via WC_Cache_Helper after every rule save/delete
- Fixed method not always appearing: \$this->enabled now defaults to
  'yes' as soon as the method is added to a zone
- Fixed method title overwritten before settings loaded: both enabled
  and title now assigned after init() calls init_settings()

Other:
- Added README.md and .gitignore"

# ── Step 5: v1.0.3 ───────────────────────────────────────────────────────────
step 5 "Creating v1.0.3 — Brand Refresh, Auto-Updates & License…"
commit_version "1.0.3" "2025-04-01T10:00:00" \
"v1.0.3 — Brand Refresh, Auto-Updates & License

UI / branding:
- Hera Studio LK company logo added to plugin admin header
- Redesigned admin UI: black header, white text, cyan-to-purple gradient
  accent matching the Hera Studio brand
- DM Sans font, gradient Save button, gradient form card top-border

Auto-updates:
- Added SPP_Updater: hooks into WordPress native update system and
  checks GitHub Releases API for new versions every 12 hours
- Standard 'Update available' notice in Plugins list; one-click update
- GitHub release notes parsed and shown in WP update popup

License:
- Added LICENSE.txt — proprietary license prohibiting redistribution
- Updated plugin header License field to 'Proprietary'"

# ── Step 6: v1.1.0 ───────────────────────────────────────────────────────────
step 6 "Creating v1.1.0 — Unified Shipping Engine…"
commit_version "1.1.0" "2025-05-01T10:00:00" \
"v1.1.0 — Unified Shipping Engine (Weight + Per-Product)

New features:
- Built-in weight-based shipping with tiered rules (add, duplicate, delete)
- Unified calculator: weight tiers cover all products by default;
  per-product rules override weight cost for specific items only
- Both modes handled by one WooCommerce method → zero zone conflicts
- New tabbed admin UI: 'Per-Product Rules' tab + 'Weight-Based Rules' tab
- Weight rules support max = 0 (unlimited / catch-all tier)
- New spp_weight_rules DB table created on activation

Fixed:
- Conflict warning from Flexible Shipping / Weight Based Shipping:
  deactivate those plugins and use this one method instead"

# ── Step 7: Push ──────────────────────────────────────────────────────────────
step 7 "Configuring branch and remote…"
git branch -M main
git remote add origin "${GITHUB_URL}"
ok "Remote set to ${GITHUB_URL}"

# ── Step 8: Push commits + tags ───────────────────────────────────────────────
step 8 "Pushing all commits and tags to GitHub…"
git push -u origin main
git push origin --tags
ok "All commits and tags pushed"

# ── Step 9: Summary ───────────────────────────────────────────────────────────
step 9 "Done!"
echo ""
echo -e "${GREEN}════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Repository is live:${NC}"
echo -e "${GREEN}  https://github.com/${GITHUB_USERNAME}/${REPO_NAME}${NC}"
echo -e "${GREEN}════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}IMPORTANT — Before going live:${NC}"
echo ""
echo "  1. Open includes/class-spp-updater.php and change:"
echo "       const GITHUB_REPO = 'YOUR_GITHUB_USERNAME/Shipping-Per-Product';"
echo "     to:"
echo "       const GITHUB_REPO = '${GITHUB_USERNAME}/Shipping-Per-Product';"
echo "     Then commit and push that change."
echo ""
echo "  2. Create GitHub Releases for each tag:"
echo "     → Go to: https://github.com/${GITHUB_USERNAME}/${REPO_NAME}/releases"
echo "     → Click 'Draft a new release'"
echo "     → Do this for each tag: v1.0.0, v1.0.1, v1.0.2, v1.0.3, v1.1.0"
echo "     → On v1.1.0, upload shipping-per-product-1.1.0.zip as a release asset"
echo ""
echo "  3. For all future releases, use:  bash release.sh"
echo ""
