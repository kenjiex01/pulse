#!/usr/bin/env bash
# Pulse macOS install helper — removes quarantine and adhoc-signs for unsigned builds.
# For zero-warning installs on other Macs: Apple Developer notarization is required.
set -euo pipefail

APP_NAME="Pulse.app"
INSTALL_DIR="/Applications"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

notify() {
  osascript -e "display alert \"Pulse Install\" message \"$1\" as informational" 2>/dev/null || true
}

resolve_app_path() {
  local candidate="$1"
  if [[ -d "$candidate" ]]; then
    printf '%s' "$candidate"
    return 0
  fi
  return 1
}

find_pulse_app() {
  local dir
  for dir in "$SCRIPT_DIR" "$PWD" "$HOME/Downloads" "$HOME/Desktop"; do
    if resolved="$(resolve_app_path "$dir/$APP_NAME")"; then
      printf '%s' "$resolved"
      return 0
    fi
  done
  return 1
}

if [[ $# -ge 1 ]]; then
  SOURCE_APP="$1"
else
  SOURCE_APP="$(find_pulse_app || true)"
fi

if [[ -z "${SOURCE_APP:-}" || ! -d "$SOURCE_APP" ]]; then
  notify "Could not find Pulse.app. Place this installer in the same folder as Pulse.app."
  echo "Usage: $0 [/path/to/Pulse.app]"
  exit 1
fi

echo "==> Removing quarantine from: $SOURCE_APP"
xattr -cr "$SOURCE_APP"

echo "==> Adhoc signing (required for Gatekeeper on unsigned builds)"
codesign --force --deep --sign - "$SOURCE_APP"

echo "==> Installing to $INSTALL_DIR"
rm -rf "$INSTALL_DIR/$APP_NAME"
cp -R "$SOURCE_APP" "$INSTALL_DIR/"
xattr -cr "$INSTALL_DIR/$APP_NAME"

echo ""
echo "Installed to $INSTALL_DIR/$APP_NAME"
echo ""
echo "IMPORTANT — one-time step (Apple requirement for unsigned apps):"
echo "  1. Open Finder → Applications"
echo "  2. Right-click Pulse → Open"
echo "  3. Click Open again in the dialog"
echo ""
echo "Do NOT double-click Pulse the first time."

notify "Pulse copied to Applications. Right-click Pulse → Open → Open (one time only)."

open /Applications 2>/dev/null || true
