#!/usr/bin/env bash
# Adhoc re-sign People360.app so Gatekeeper on other Macs does not show "damaged".
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/dist/mac-arm64/People360.app"

if [[ ! -d "$APP" ]]; then
  echo "    NOTE: No $APP — skip Mac codesign fix"
  exit 0
fi

echo "==> Adhoc re-signing People360.app (Gatekeeper / other Mac installs)"
codesign --force --deep --sign - "$APP"
xattr -cr "$APP"

if codesign --verify --deep --strict "$APP" 2>/dev/null; then
  echo "    OK codesign verify passed"
else
  echo "WARNING: codesign verify reported issues (app may still run after xattr on target Mac)"
fi
