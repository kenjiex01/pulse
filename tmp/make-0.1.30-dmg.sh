#!/usr/bin/env bash
# Creates Pulse-0.1.30 macOS DMG from the already-built Pulse.app
# Run in Terminal.app (Cursor sandbox blocks hdiutil):
#   /Users/kentordillos/Documents/ISKOLARIS/pulse/tmp/make-0.1.30-dmg.sh
set -euo pipefail
ROOT="/Users/kentordillos/Documents/ISKOLARIS/pulse"
APP="$ROOT/dist/mac-arm64/Pulse.app"
DMG="$ROOT/dist/Pulse-0.1.30-arm64.dmg"

if [[ ! -d "$APP" ]]; then
  echo "ERROR: Missing $APP — run: cd \"$ROOT\" && ./scripts/build-desktop.sh mac"
  exit 1
fi

rm -f "$DMG"
hdiutil create -volname "Pulse 0.1.30" -srcfolder "$APP" -ov -format UDZO "$DMG"
ls -lah "$DMG"
echo "OK: $DMG"
