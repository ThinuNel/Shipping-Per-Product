#!/usr/bin/env bash
# =============================================================================
# release.sh — Shipping Per Product
# Hera Studio LK · herastudiolk.com
#
# Run this script every time you release a new version.
# It will:
#   1. Verify the version number is bumped in shipping-per-product.php
#   2. Build a versioned zip  (shipping-per-product-X.Y.Z.zip)
#   3. Commit all changes with a standardised message
#   4. Tag the commit  (vX.Y.Z)
#   5. Push the commit and tag to GitHub
#   6. Remind you to draft a GitHub Release and upload the zip
#
# USAGE
#   bash release.sh [version]
#
# EXAMPLES
#   bash release.sh 1.2.0
#   bash release.sh          ← prompts you for the version
# =============================================================================

set -e

# ── Colours ───────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✓${NC} $1"; }
info() { echo -e "${CYAN}→${NC} $1"; }
warn() { echo -e "${YELLOW}!${NC} $1"; }
err()  { echo -e "${RED}✗${NC} $1"; exit 1; }

# ── Must run from plugin root ─────────────────────────────────────────────────
if [ ! -f "shipping-per-product.php" ]; then
  err "Run this script from inside the shipping-per-product/ folder."
fi

# ── Get version ───────────────────────────────────────────────────────────────
VERSION="${1}"
if [ -z "${VERSION}" ]; then
  read -rp "Enter the new version number (e.g. 1.2.0): " VERSION
fi

# Strip a leading 'v' if supplied (e.g. v1.2.0 → 1.2.0)
VERSION="${VERSION#v}"

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  err "Version must be in X.Y.Z format (e.g. 1.2.0). Got: ${VERSION}"
fi

TAG="v${VERSION}"
ZIP_NAME="shipping-per-product-${VERSION}.zip"

echo ""
echo -e "${CYAN}══════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Releasing Shipping Per Product ${TAG}${NC}"
echo -e "${CYAN}  Hera Studio LK · herastudiolk.com${NC}"
echo -e "${CYAN}══════════════════════════════════════════════${NC}"
echo ""

# ── Step 1: Verify the version is set in the PHP file ────────────────────────
info "Checking version in shipping-per-product.php…"

PHP_VERSION=$(grep -oP "(?<=Version:\s{5})\d+\.\d+\.\d+" shipping-per-product.php || true)
CONST_VERSION=$(grep -oP "(?<=SPP_VERSION', ')\d+\.\d+\.\d+" shipping-per-product.php || true)

if [ "${PHP_VERSION}" != "${VERSION}" ]; then
  err "Plugin header says Version: ${PHP_VERSION} but you're releasing ${VERSION}.
       Update the 'Version:' line in shipping-per-product.php first."
fi

if [ "${CONST_VERSION}" != "${VERSION}" ]; then
  err "SPP_VERSION constant says '${CONST_VERSION}' but you're releasing ${VERSION}.
       Update the define('SPP_VERSION', ...) line first."
fi

ok "Version confirmed: ${VERSION}"

# ── Step 2: Check git is clean (no uncommitted changes) ──────────────────────
info "Checking git status…"

if ! git diff-index --quiet HEAD -- 2>/dev/null; then
  warn "You have uncommitted changes. They will be included in this release commit."
  read -rp "Continue anyway? (y/N): " CONFIRM
  [[ "${CONFIRM,,}" == "y" ]] || exit 0
fi

# ── Step 3: Check the tag doesn't already exist ───────────────────────────────
info "Checking tag ${TAG} doesn't already exist…"

if git rev-parse "${TAG}" >/dev/null 2>&1; then
  err "Tag ${TAG} already exists. Have you already released this version?"
fi

ok "Tag is available"

# ── Step 4: Build the versioned zip ──────────────────────────────────────────
info "Building ${ZIP_NAME}…"

# Go one level up so the zip contains shipping-per-product/ as the root folder
cd ..
zip -r "${ZIP_NAME}" "shipping-per-product/" \
  --exclude "*/github-setup.sh" \
  --exclude "*/release.sh" \
  --exclude "*/.git/*" \
  --exclude "*/node_modules/*" \
  --exclude "*/.DS_Store" \
  --exclude "*/Thumbs.db" \
  --exclude "*.zip"

# Move zip back inside the plugin folder so it gets committed / is easy to find
mv "${ZIP_NAME}" "shipping-per-product/${ZIP_NAME}"
cd "shipping-per-product"

ok "Built: ${ZIP_NAME}"

# ── Step 5: Stage everything ──────────────────────────────────────────────────
info "Staging all changes…"
git add -A
ok "Files staged"

# ── Step 6: Commit ───────────────────────────────────────────────────────────
info "Creating release commit…"

# Build commit message — user can edit this by changing the heredoc below
COMMIT_MSG="v${VERSION} — Release

- See VERSIONING.md or README.md changelog for details"

git commit -m "${COMMIT_MSG}"
ok "Committed"

# ── Step 7: Tag ───────────────────────────────────────────────────────────────
info "Tagging ${TAG}…"
git tag -a "${TAG}" -m "Version ${VERSION}"
ok "Tagged ${TAG}"

# ── Step 8: Push ──────────────────────────────────────────────────────────────
info "Pushing commit and tag to GitHub…"
git push origin main
git push origin "${TAG}"
ok "Pushed to GitHub"

# ── Step 9: Instructions ──────────────────────────────────────────────────────
REPO_URL=$(git remote get-url origin 2>/dev/null | sed 's/\.git$//' || echo "your GitHub repo")

echo ""
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Release ${TAG} pushed successfully!${NC}"
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}ONE LAST STEP — Publish the GitHub Release:${NC}"
echo ""
echo "  1. Go to: ${REPO_URL}/releases/new?tag=${TAG}"
echo ""
echo "  2. Fill in the release title:  Shipping Per Product ${TAG}"
echo ""
echo "  3. Paste your changelog into the description box"
echo "     (copy from README.md → Changelog → ${TAG} section)"
echo ""
echo "  4. Upload the zip as a release asset:"
echo "       ${ZIP_NAME}"
echo "     (it's inside the shipping-per-product/ folder)"
echo ""
echo "  5. Click 'Publish release'"
echo ""
echo -e "${CYAN}The auto-updater on installed sites will detect this${NC}"
echo -e "${CYAN}release within 12 hours and show the update prompt.${NC}"
echo ""
