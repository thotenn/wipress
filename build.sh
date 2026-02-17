#!/bin/bash
# Build WiPress plugin zip for distribution
# Usage: ./build.sh

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="wipress"
VERSION=$(grep "define('WIPRESS_VERSION'" "$PLUGIN_DIR/wipress.php" | grep -oP "'[0-9]+\.[0-9]+\.[0-9]+'" | tr -d "'")
OUTPUT_FILE="${PLUGIN_DIR}/${PLUGIN_NAME}-${VERSION}.zip"

echo "Building ${PLUGIN_NAME} v${VERSION}..."

cd "$PLUGIN_DIR/.."

zip -r "$OUTPUT_FILE" "$PLUGIN_NAME/" \
    -x "${PLUGIN_NAME}/.git/*" \
    -x "${PLUGIN_NAME}/docs/*" \
    -x "${PLUGIN_NAME}/CLAUDE.md" \
    -x "${PLUGIN_NAME}/README.md" \
    -x "${PLUGIN_NAME}/CHANGELOG.md" \
    -x "${PLUGIN_NAME}/build.sh" \
    -x "${PLUGIN_NAME}/*.zip"

echo "Created: $OUTPUT_FILE"
echo "Done."
