#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TARGET="${1:-all}"

echo "==> Preparing desktop icons"
cd "$ROOT/public"
sips -z 512 512 img/skolarislogo.png --out icon.png >/dev/null
npx --yes png-to-ico icon.png > icon.ico
rm -rf icon.iconset && mkdir icon.iconset
sips -z 16 16 icon.png --out icon.iconset/icon_16x16.png >/dev/null
sips -z 32 32 icon.png --out icon.iconset/icon_16x16@2x.png >/dev/null
sips -z 32 32 icon.png --out icon.iconset/icon_32x32.png >/dev/null
sips -z 64 64 icon.png --out icon.iconset/icon_32x32@2x.png >/dev/null
sips -z 128 128 icon.png --out icon.iconset/icon_128x128.png >/dev/null
sips -z 256 256 icon.png --out icon.iconset/icon_128x128@2x.png >/dev/null
sips -z 256 256 icon.png --out icon.iconset/icon_256x256.png >/dev/null
sips -z 512 512 icon.png --out icon.iconset/icon_256x256@2x.png >/dev/null
sips -z 512 512 icon.png --out icon.iconset/icon_512x512.png >/dev/null
sips -z 1024 1024 icon.png --out icon.iconset/icon_512x512@2x.png >/dev/null
iconutil -c icns icon.iconset -o icon.icns
rm -rf icon.iconset
cd "$ROOT"

echo "==> Building frontend assets"
npm run build

case "$TARGET" in
  mac|mac-arm64)
    echo "==> Building macOS DMG (arm64)"
    php artisan native:build mac arm64 --no-interaction
    ;;
  mac-x64|mac-intel)
    echo "==> Building macOS DMG (x64)"
    php artisan native:build mac x64 --no-interaction
    ;;
  win|win-x64)
    echo "==> Building Windows installer (x64)"
    php artisan native:build win x64 --no-interaction
    ;;
  all)
    echo "==> Building macOS DMG (arm64)"
    php artisan native:build mac arm64 --no-interaction
    echo "==> Building Windows installer (x64)"
    php artisan native:build win x64 --no-interaction
    ;;
  *)
    echo "Usage: $0 [mac|mac-x64|win|all]"
    exit 1
    ;;
esac

echo ""
echo "==> Build complete. Installers are in: $ROOT/dist/"
ls -lah "$ROOT/dist" 2>/dev/null || true
