#!/usr/bin/env bash
# bin/build-wporg.sh — produce a WP.org-ready zip of the plugin.
# Usage: ./bin/build-wporg.sh [--version 1.2.3]
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="wp-ai-mind"
VERSION="${1:-}"

# Parse --version flag
while [[ $# -gt 0 ]]; do
    case $1 in
        --version) VERSION="$2"; shift 2 ;;
        *) shift ;;
    esac
done

# Fall back to version from main plugin file
if [[ -z "$VERSION" ]]; then
    VERSION=$(grep "Version:" "${PLUGIN_DIR}/wp-ai-mind.php" | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
fi

DIST_DIR="${PLUGIN_DIR}/dist"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
BUILD_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous build
rm -rf "${DIST_DIR}"
mkdir -p "${BUILD_DIR}"

# Run JS build
echo "Running npm build..."
cd "${PLUGIN_DIR}" && npm run build

# Copy plugin files (exclude dev artefacts)
rsync -a --delete \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='src/' \
    --exclude='bin/' \
    --exclude='tests/' \
    --exclude='dist/' \
    --exclude='.gitignore' \
    --exclude='*.lock' \
    --exclude='*.xml' \
    --exclude='*.json' \
    --exclude='*.config.js' \
    --exclude='phpunit.xml' \
    --exclude='phpcs.xml' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    "${PLUGIN_DIR}/" "${BUILD_DIR}/"

# Create zip
cd "${DIST_DIR}"
zip -r "${ZIP_NAME}" "${PLUGIN_SLUG}/"
echo "Created: ${DIST_DIR}/${ZIP_NAME}"

# Show contents summary
echo ""
echo "Contents:"
unzip -l "${ZIP_NAME}" | tail -5
