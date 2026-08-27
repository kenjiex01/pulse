#!/usr/bin/env bash
# Creates People360 macOS DMG with app + one-click install helper (unsigned builds).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PRODUCT_NAME="People360"
VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
APP="$ROOT/dist/mac-arm64/${PRODUCT_NAME}.app"
DMG="$ROOT/dist/${PRODUCT_NAME}-${VERSION}-arm64.dmg"
STAGE="$ROOT/dist/.mac-dmg-stage-$$"
INSTALL_SCRIPT="$ROOT/scripts/install-pulse-mac.sh"
README="$ROOT/dist/${PRODUCT_NAME}-mac-install-readme.txt"

if [[ ! -d "$APP" ]]; then
  echo "ERROR: Missing $APP — run: cd \"$ROOT\" && ./scripts/build-desktop.sh mac"
  exit 1
fi

cleanup() {
  rm -rf "$STAGE"
}
trap cleanup EXIT

rm -rf "$STAGE"
mkdir -p "$STAGE"
ditto "$APP" "$STAGE/${PRODUCT_NAME}.app"
cp "$INSTALL_SCRIPT" "$STAGE/Install ${PRODUCT_NAME}.command"
chmod +x "$STAGE/Install ${PRODUCT_NAME}.command"

cat > "$README" <<EOF
${PRODUCT_NAME} ${VERSION} — macOS install (unsigned build)

1. Open this DMG and double-click "Install ${PRODUCT_NAME}.command"
   (If blocked: right-click → Open)

2. First app launch: Applications → right-click ${PRODUCT_NAME} → Open → Open

For IT: notarized builds skip these steps — see pulse/docs/desktop-mac-install.md
EOF

cp "$README" "$STAGE/README.txt"

export TMPDIR="${TMPDIR:-/tmp}"
rm -f "$DMG"
hdiutil create -volname "${PRODUCT_NAME} ${VERSION}" -srcfolder "$STAGE" -ov -format UDZO "$DMG"
ls -lah "$DMG"
echo "OK: $DMG"
