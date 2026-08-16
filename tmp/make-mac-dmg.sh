#!/usr/bin/env bash
# Creates Pulse macOS DMG from dist/mac-arm64/Pulse.app (run in Terminal.app — hdiutil fails in Cursor sandbox)
set -euo pipefail
ROOT="/Users/kentordillos/Documents/ISKOLARIS/pulse"
VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
APP="$ROOT/dist/mac-arm64/Pulse.app"
DMG="$ROOT/dist/Pulse-${VERSION}-arm64.dmg"

if [[ ! -d "$APP" ]]; then
  echo "ERROR: Missing $APP — run: cd \"$ROOT\" && ./scripts/build-desktop.sh mac"
  exit 1
fi

export TMPDIR="${TMPDIR:-/tmp}"
rm -f "$DMG"
hdiutil create -volname "Pulse ${VERSION}" -srcfolder "$APP" -ov -format UDZO "$DMG"
ls -lah "$DMG"
echo "OK: $DMG"
