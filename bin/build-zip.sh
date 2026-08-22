#!/usr/bin/env bash
#
# Builds a distributable ZIP of this plugin, suitable for uploading via
# WordPress admin (Plugins -> Add New -> Upload Plugin) or extracting into
# wp-content/plugins/.
#
# Respects .distignore: dev-only files (tests, vendor, composer.json,
# lint/test configs, SPEC.md, etc.) are excluded from the package, matching
# the convention of the official WordPress AI provider plugins.
#
# Usage: bin/build-zip.sh [output-dir]
#   output-dir defaults to "dist" at the repo root.

set -euo pipefail

for cmd in rsync zip; do
	if ! command -v "$cmd" >/dev/null 2>&1; then
		echo "Error: required command '$cmd' not found." >&2
		exit 1
	fi
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
OUTPUT_DIR="${1:-$ROOT_DIR/dist}"

PLUGIN_FILE="$ROOT_DIR/plugin.php"
if [[ ! -f "$PLUGIN_FILE" ]]; then
	echo "Error: $PLUGIN_FILE not found." >&2
	exit 1
fi

SLUG="$(basename "$ROOT_DIR")"

VERSION="$(grep -i 'Version:' "$PLUGIN_FILE" | head -n1 | awk -F'Version:' '{print $2}' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
	echo "Error: could not determine plugin version from $PLUGIN_FILE." >&2
	exit 1
fi

BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT

PACKAGE_DIR="$BUILD_DIR/$SLUG"
mkdir -p "$PACKAGE_DIR"

RSYNC_EXCLUDE_FILE="$ROOT_DIR/.distignore"
RSYNC_ARGS=(-a)
if [[ -f "$RSYNC_EXCLUDE_FILE" ]]; then
	RSYNC_ARGS+=(--exclude-from="$RSYNC_EXCLUDE_FILE")
fi
# Always exclude the build output directory itself, even if it lives inside
# the repo and isn't listed in .distignore.
RSYNC_ARGS+=(--exclude="/$(basename "$OUTPUT_DIR")/")

rsync "${RSYNC_ARGS[@]}" "$ROOT_DIR"/ "$PACKAGE_DIR"/

mkdir -p "$OUTPUT_DIR"
ZIP_PATH="$OUTPUT_DIR/${SLUG}-${VERSION}.zip"
rm -f "$ZIP_PATH"

(cd "$BUILD_DIR" && zip -rq "$ZIP_PATH" "$SLUG")

echo "Built: $ZIP_PATH"
du -h "$ZIP_PATH" | cut -f1 | xargs -I{} echo "Size: {}"
