#!/usr/bin/env bash
# Configure Mac Developer ID signing + Apple notarization for People360 desktop builds.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env"

echo "==> People360 macOS signing + notarization setup"
echo ""

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: Missing required command: $1"
    exit 1
  fi
}

require_cmd security
require_cmd xcrun

upsert_env() {
  local key="$1"
  local value="$2"
  local escaped
  escaped="$(printf '%s' "$value" | sed 's/[\\&|]/\\&/g')"

  if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
    if [[ "$(uname)" == "Darwin" ]]; then
      sed -i '' "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
    else
      sed -i "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
    fi
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$ROOT/.env.example" "$ENV_FILE"
  echo "Created $ENV_FILE from .env.example"
fi

echo "==> Checking code signing identities in Keychain"
mapfile -t IDENTITIES < <(security find-identity -v -p codesigning 2>/dev/null | grep 'Developer ID Application' || true)

if [[ ${#IDENTITIES[@]} -eq 0 ]]; then
  echo ""
  echo "WARNING: No 'Developer ID Application' certificate found in Keychain."
  echo ""
  echo "Install one first:"
  echo "  1. Enroll at https://developer.apple.com/programs/ (\$99/year)"
  echo "  2. Certificates → + → Developer ID Application"
  echo "  3. Download .cer and double-click to add to Keychain"
  echo "  4. Re-run: $0"
  echo ""
  HAS_IDENTITY=0
else
  HAS_IDENTITY=1
  echo "Found:"
  for line in "${IDENTITIES[@]}"; do
    echo "  $line"
  done
fi

echo ""
echo "==> Apple notarization credentials"
echo "Use an app-specific password: https://appleid.apple.com → Sign-In and Security → App-Specific Passwords"
echo ""

read -r -p "Apple ID email: " APPLE_ID
read -r -s -p "App-specific password: " APPLE_PASS
echo ""
read -r -p "Team ID (10 chars, from developer.apple.com/account): " TEAM_ID

if [[ -z "$APPLE_ID" || -z "$APPLE_PASS" || -z "$TEAM_ID" ]]; then
  echo "ERROR: Apple ID, password, and Team ID are all required."
  exit 1
fi

MAC_IDENTITY=""
if [[ "$HAS_IDENTITY" -eq 1 ]]; then
  DEFAULT_IDENTITY="$(echo "${IDENTITIES[0]}" | sed -E 's/^[[:space:]]*[0-9]+)[[:space:]]+"([^"]+)".*/\1/')"
  read -r -p "Developer ID identity [${DEFAULT_IDENTITY}]: " MAC_IDENTITY
  MAC_IDENTITY="${MAC_IDENTITY:-$DEFAULT_IDENTITY}"
fi

echo ""
echo "==> Storing notarytool credentials in Keychain (profile: pulse-notarize)"
xcrun notarytool store-credentials "pulse-notarize" \
  --apple-id "$APPLE_ID" \
  --password "$APPLE_PASS" \
  --team-id "$TEAM_ID"

echo ""
echo "==> Writing .env"
upsert_env "NATIVEPHP_NOTARIZE" "1"
upsert_env "NATIVEPHP_APPLE_ID" "$APPLE_ID"
upsert_env "NATIVEPHP_APPLE_ID_PASS" "$APPLE_PASS"
upsert_env "NATIVEPHP_APPLE_TEAM_ID" "$TEAM_ID"

if [[ -n "$MAC_IDENTITY" ]]; then
  upsert_env "NATIVEPHP_MAC_IDENTITY" "$MAC_IDENTITY"
fi

echo ""
echo "Done. Next steps:"
echo "  cd $ROOT"
echo "  ./scripts/build-desktop.sh mac"
echo ""
echo "Shipped Mac builds will be signed + notarized — no Gatekeeper workaround on client Macs."
