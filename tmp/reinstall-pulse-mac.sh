#!/usr/bin/env bash
# Reinstall Pulse 0.1.32 on this Mac from the latest local build.
set -euo pipefail

ROOT="/Users/kentordillos/Documents/ISKOLARIS/pulse"
SOURCE="$ROOT/dist/mac-arm64/Pulse.app"
TARGET="/Applications/Pulse.app"
ZIP="$ROOT/dist/Pulse-0.1.32-arm64.zip"

if [[ ! -d "$SOURCE" ]]; then
  if [[ -f "$ZIP" ]]; then
    echo "==> Extracting $ZIP"
    rm -rf "$ROOT/tmp/pulse-reinstall-extract"
    mkdir -p "$ROOT/tmp/pulse-reinstall-extract"
    ditto -xk "$ZIP" "$ROOT/tmp/pulse-reinstall-extract"
    SOURCE=$(find "$ROOT/tmp/pulse-reinstall-extract" -name 'Pulse.app' -type d | head -1)
  fi
fi

if [[ ! -d "$SOURCE" ]]; then
  echo "ERROR: Pulse.app not found. Run ./scripts/build-desktop.sh mac first."
  exit 1
fi

echo "==> Quitting Pulse if running"
pkill -x Pulse 2>/dev/null || true
osascript -e 'quit app "Pulse"' 2>/dev/null || true
sleep 2

echo "==> Removing old install"
if [[ -d "$TARGET" ]]; then
  rm -rf "$TARGET"
fi

echo "==> Installing from: $SOURCE"
ditto "$SOURCE" "$TARGET"
xattr -cr "$TARGET"

VERSION=$(/usr/libexec/PlistBuddy -c 'Print :CFBundleShortVersionString' "$TARGET/Contents/Info.plist" 2>/dev/null || echo "?")
echo "==> Installed Pulse $VERSION at $TARGET"
echo "==> Opening Pulse (use Right-click → Open if Gatekeeper blocks first launch)"
open "$TARGET"
