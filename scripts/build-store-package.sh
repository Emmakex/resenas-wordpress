#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_ROOT="$ROOT/build"
PLUGIN_DIR="$BUILD_ROOT/kairoseth-reviews"

rm -rf "$PLUGIN_DIR"
mkdir -p "$PLUGIN_DIR/assets" "$PLUGIN_DIR/languages"

cp "$ROOT/resenas-wordpress.php" "$PLUGIN_DIR/kairoseth-reviews.php"
cp "$ROOT/assets/front.css" "$PLUGIN_DIR/assets/front.css"
cp "$ROOT/assets/front.js" "$PLUGIN_DIR/assets/front.js"
cp "$ROOT/assets/admin.css" "$PLUGIN_DIR/assets/admin.css"
cp "$ROOT/readme.txt" "$PLUGIN_DIR/readme.txt"
cp "$ROOT/LICENSE" "$PLUGIN_DIR/LICENSE"
cp "$ROOT/languages/kairoseth-reviews.pot" "$PLUGIN_DIR/languages/kairoseth-reviews.pot"

printf 'Prepared Store package at %s\n' "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type f -maxdepth 3 | sort
