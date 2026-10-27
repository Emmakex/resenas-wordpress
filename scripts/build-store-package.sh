#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_ROOT="$ROOT/build"
PLUGIN_DIR="$BUILD_ROOT/gestion-resenas"

rm -rf "$PLUGIN_DIR"
mkdir -p "$PLUGIN_DIR/assets" "$PLUGIN_DIR/languages"

cp "$ROOT/resenas-wordpress.php" "$PLUGIN_DIR/gestion-resenas.php"
cp "$ROOT/assets/front.css" "$PLUGIN_DIR/assets/front.css"
cp "$ROOT/assets/front.js" "$PLUGIN_DIR/assets/front.js"
cp "$ROOT/assets/admin.css" "$PLUGIN_DIR/assets/admin.css"
cp "$ROOT/readme.txt" "$PLUGIN_DIR/readme.txt"
cp "$ROOT/LICENSE" "$PLUGIN_DIR/LICENSE"

for language_file in "$ROOT"/languages/gestion-resenas*; do
  if [[ -f "$language_file" ]]; then
    cp "$language_file" "$PLUGIN_DIR/languages/"
  fi
done

printf 'Prepared Store package at %s\n' "$PLUGIN_DIR"
find "$PLUGIN_DIR" -maxdepth 3 -type f | sort
