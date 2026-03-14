#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_FILE="$ROOT_DIR/sml-wp-plugin.php"
PLUGIN_DIR_NAME="$(basename "$ROOT_DIR")"
RELEASE_KIND="${1:-patch}"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Fehler: $PLUGIN_FILE nicht gefunden."
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "Fehler: 'zip' ist nicht installiert."
  exit 1
fi

if ! command -v perl >/dev/null 2>&1; then
  echo "Fehler: 'perl' ist nicht installiert."
  exit 1
fi

current_version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+)[[:space:]]*$/\1/p' "$PLUGIN_FILE" | head -n1)"
if [[ -z "$current_version" ]]; then
  echo "Fehler: Konnte aktuelle Version in $PLUGIN_FILE nicht lesen."
  exit 1
fi

IFS='.' read -r major minor patch <<< "$current_version"

case "$RELEASE_KIND" in
  major)
    major=$((major + 1))
    minor=0
    patch=0
    ;;
  minor)
    minor=$((minor + 1))
    patch=0
    ;;
  patch)
    patch=$((patch + 1))
    ;;
  *)
    if [[ "$RELEASE_KIND" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      IFS='.' read -r major minor patch <<< "$RELEASE_KIND"
    else
      echo "Usage: ./build.sh [patch|minor|major|X.Y.Z]"
      exit 1
    fi
    ;;
esac

new_version="${major}.${minor}.${patch}"

# Update plugin header version (first matching "Version:" line).
tmp_file="$(mktemp)"
awk -v v="$new_version" '
  BEGIN { done = 0 }
  {
    if (!done && $0 ~ /^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+[[:space:]]*$/) {
      sub(/[0-9]+\.[0-9]+\.[0-9]+/, v)
      done = 1
    }
    print
  }
' "$PLUGIN_FILE" > "$tmp_file"
mv "$tmp_file" "$PLUGIN_FILE"

# Keep asset versions in sync for cache busting (line-based, safe).
perl -i -pe "if (/wp_enqueue_style\\('sml-admin'/) { s/'\\d+\\.\\d+\\.\\d+'/'${new_version}'/; }" "$PLUGIN_FILE"
perl -i -pe "if (/wp_enqueue_script\\('sml-admin'/) { s/'\\d+\\.\\d+\\.\\d+'/'${new_version}'/; }" "$PLUGIN_FILE"

CROWDBOOK_FILE="$ROOT_DIR/crowdbook/crowdbook.php"
if [[ -f "$CROWDBOOK_FILE" ]]; then
  perl -0777 -i -pe "s/wp_enqueue_style\\(\\s*\\n\\s*'crowdbook-css'.*?\\n\\s*'\\K\\d+\\.\\d+\\.\\d+(?='\\s*\\n\\s*\\);)/${new_version}/s" "$CROWDBOOK_FILE"
  perl -0777 -i -pe "s/wp_enqueue_script\\(\\s*\\n\\s*'crowdbook-js'.*?\\n\\s*'\\K\\d+\\.\\d+\\.\\d+(?='\\s*,\\s*\\n\\s*true\\s*\\);)/${new_version}/s" "$CROWDBOOK_FILE"
fi

# Remove hidden control chars that can break PHP parsing (keeps tab/newline/carriage return).
find "$ROOT_DIR" -name '*.php' -type f -print0 | xargs -0 perl -i -pe 's/[\x00-\x08\x0B\x0C\x0E-\x1F]//g'

zip_versioned="$ROOT_DIR/${PLUGIN_DIR_NAME}-${new_version}.zip"
zip_latest="$ROOT_DIR/${PLUGIN_DIR_NAME}.zip"
rm -f "$zip_versioned" "$zip_latest"

(
  cd "$ROOT_DIR/.."
  zip -r "$zip_versioned" "$PLUGIN_DIR_NAME" \
    -x "${PLUGIN_DIR_NAME}/.git/*" \
       "${PLUGIN_DIR_NAME}/.DS_Store" \
       "${PLUGIN_DIR_NAME}/spec.md" \
       "${PLUGIN_DIR_NAME}/sml-wp-plugin/*" \
       "${PLUGIN_DIR_NAME}/images/*" \
       "${PLUGIN_DIR_NAME}/flags/*" \
       "${PLUGIN_DIR_NAME}/boxed_background/*" \
       "${PLUGIN_DIR_NAME}/egorkhmelev-jslider/*" \
       "${PLUGIN_DIR_NAME}/revolution-slider-dev/*" \
       "${PLUGIN_DIR_NAME}/*.zip"
)

cp "$zip_versioned" "$zip_latest"

if command -v php >/dev/null 2>&1; then
  php -l "$PLUGIN_FILE" >/dev/null
  if [[ -f "$CROWDBOOK_FILE" ]]; then
    php -l "$CROWDBOOK_FILE" >/dev/null
  fi
fi

echo "Version: ${current_version} -> ${new_version}"
echo "ZIP: $zip_versioned"
echo "Latest: $zip_latest"
