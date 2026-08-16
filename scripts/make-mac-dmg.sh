#!/usr/bin/env bash
# Creates Pulse macOS DMG with app + one-click install helper (unsigned builds).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
APP="$ROOT/dist/mac-arm64/Pulse.app"
DMG="$ROOT/dist/Pulse-${VERSION}-arm64.dmg"
STAGE="$ROOT/dist/.mac-dmg-stage-$$"
INSTALL_SCRIPT="$ROOT/scripts/install-pulse-mac.sh"
README="$ROOT/dist/Pulse-mac-install-readme.txt"

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
ditto "$APP" "$STAGE/Pulse.app"
cp "$INSTALL_SCRIPT" "$STAGE/Install Pulse.command"
chmod +x "$STAGE/Install Pulse.command"

cat > "$README" <<EOF
Pulse ${VERSION} — macOS install (unsigned build)

1. Open this DMG and double-click "Install Pulse.command"
   (If blocked: right-click → Open)

2. First app launch: Applications → right-click Pulse → Open → Open

For IT: notarized builds skip these steps — see pulse/docs/desktop-mac-install.md
EOF

cp "$README" "$STAGE/README.txt"

export TMPDIR="${TMPDIR:-/tmp}"
rm -f "$DMG"
hdiutil create -volname "Pulse ${VERSION}" -srcfolder "$STAGE" -ov -format UDZO "$DMG"
ls -lah "$DMG"
echo "OK: $DMG"
