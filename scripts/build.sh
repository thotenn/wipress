#!/bin/bash
# Build WiPress plugin zip for distribution.
# Usage: make build   (or: bash scripts/build.sh)

set -e

# This script lives in <plugin>/scripts/, so the plugin root is its parent.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_NAME="wipress"
# Portable version extraction (BSD/macOS grep has no -P): grab the X.Y.Z from the
# WIPRESS_VERSION define line.
VERSION=$(grep "WIPRESS_VERSION" "$PLUGIN_DIR/wipress.php" | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)
if [ -z "$VERSION" ]; then
    echo "ERROR: could not read WIPRESS_VERSION from wipress.php" >&2
    exit 1
fi
OUTPUT_FILE="${PLUGIN_DIR}/${PLUGIN_NAME}-${VERSION}.zip"

echo "Building ${PLUGIN_NAME} v${VERSION}..."

# Start clean so a stale zip never gets re-added or shipped.
rm -f "$OUTPUT_FILE"

cd "$PLUGIN_DIR/.."

zip -r "$OUTPUT_FILE" "$PLUGIN_NAME/" \
    -x "${PLUGIN_NAME}/.git" \
    -x "${PLUGIN_NAME}/.git/*" \
    -x "${PLUGIN_NAME}/.gitignore" \
    -x "${PLUGIN_NAME}/.gitmodules" \
    -x "${PLUGIN_NAME}/docs/*" \
    -x "${PLUGIN_NAME}/scripts/*" \
    -x "${PLUGIN_NAME}/Makefile" \
    -x "${PLUGIN_NAME}/CLAUDE.md" \
    -x "${PLUGIN_NAME}/README.md" \
    -x "${PLUGIN_NAME}/CHANGELOG.md" \
    -x "${PLUGIN_NAME}/*.zip"

echo "Created: $OUTPUT_FILE"
echo "Done."
