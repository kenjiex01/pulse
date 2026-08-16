#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TARGET="${1:-all}"

if [[ "$TARGET" != "mac" && "$TARGET" != "mac-arm64" && "$TARGET" != "mac-x64" && "$TARGET" != "mac-intel" && "$TARGET" != "win" && "$TARGET" != "win-x64" && "$TARGET" != "all" ]]; then
  echo "Usage: $0 [all|mac|mac-x64|win]"
  echo "Default: all (macOS arm64 + Windows x64)"
  exit 1
fi

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
xattr -cr icon.iconset 2>/dev/null || true
if ! iconutil -c icns icon.iconset -o icon.icns 2>/dev/null; then
  if [[ -f icon.icns ]]; then
    echo "    WARNING: iconutil failed; reusing existing public/icon.icns"
  else
    echo "ERROR: iconutil failed and no existing public/icon.icns found"
    exit 1
  fi
fi
rm -rf icon.iconset
cd "$ROOT"

ensure_production_vite_assets() {
  rm -f "$ROOT/public/hot"
  if [[ ! -f "$ROOT/public/build/manifest.json" ]]; then
    echo "ERROR: Missing public/build/manifest.json — production Vite build required."
    exit 1
  fi
}

echo "==> Verifying Skolaris Pulse API credentials for desktop bundle"
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$key = trim((string) config('skolaris.pulse_api_key'));
\$base = trim((string) config('skolaris.pulse_api_base_url'));
if (\$key === '' || \$base === '') {
    fwrite(STDERR, \"ERROR: Set SKOLARIS_PULSE_API_KEY and SKOLARIS_PULSE_API_BASE_URL in .env before building desktop.\\n\");
    exit(1);
}
echo \"    Pulse API base: {\$base}\\n\";
"

echo "==> Verifying desktop bootstrap seeder"
php artisan db:seed --class=DesktopBootstrapSeeder --force --no-interaction

echo "==> Regenerating employee upload templates"
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); \$svc = app(App\Services\EmployeeUploadService::class); file_put_contents(resource_path('templates/employee_upload_template.xlsx'), \$svc->buildTemplateBinary('master-file')); file_put_contents(resource_path('templates/employee_salary_upload_template.xlsx'), \$svc->buildTemplateBinary('employee-salary'));"

echo "==> Building frontend assets"
ensure_production_vite_assets
npm run build
ensure_production_vite_assets

# NativePHP prune: avoid re-downloading php-bin; dump-autoload without discover scripts.
PRUNE_TRAIT="$ROOT/vendor/nativephp/electron/src/Traits/PrunesVendorDirectory.php"
PRUNE_PATCH="$ROOT/scripts/patches/PrunesVendorDirectory.php"
BUILD_CMD="$ROOT/vendor/nativephp/electron/src/Commands/BuildCommand.php"
BUILD_CMD_PATCH="$ROOT/scripts/patches/BuildCommand.php"
if [[ -f "$PRUNE_PATCH" && -f "$PRUNE_TRAIT" ]]; then
  cp "$PRUNE_PATCH" "$PRUNE_TRAIT"
  echo "    Applied offline vendor prune patch"
fi
if [[ -f "$BUILD_CMD_PATCH" && -f "$BUILD_CMD" ]]; then
  cp "$BUILD_CMD_PATCH" "$BUILD_CMD"
  echo "    Applied BuildCommand npm install + fail-fast patch"
fi
EB_CONFIG="$ROOT/vendor/nativephp/electron/resources/js/electron-builder.js"
EB_PATCH="$ROOT/scripts/patches/electron-builder.js"
if [[ -f "$EB_PATCH" && -f "$EB_CONFIG" ]]; then
  cp "$EB_PATCH" "$EB_CONFIG"
  echo "    Applied electron-builder mac ZIP-only patch (DMG via scripts/make-mac-dmg.sh)"
fi
NOTARIZE_JS="$ROOT/vendor/nativephp/electron/resources/js/build/notarize.js"
NOTARIZE_PATCH="$ROOT/scripts/patches/notarize.js"
if [[ -f "$NOTARIZE_PATCH" && -f "$NOTARIZE_JS" ]]; then
  cp "$NOTARIZE_PATCH" "$NOTARIZE_JS"
  echo "    Applied notarize skip-unless-enabled patch"
fi

# Cursor sandbox blocks unlinking node_modules/**/.vscode (EPERM). Move those packages
# aside, and use `npm install` instead of `npm ci` (ci deletes the whole tree first).
ELECTRON_JS="$ROOT/vendor/nativephp/electron/resources/js"
ELECTRON_BUILD_CMD="$ROOT/vendor/nativephp/electron/src/Commands/BuildCommand.php"
if [[ -d "$ELECTRON_JS/node_modules" ]]; then
  while IFS= read -r vscode_dir; do
    pkg="$(dirname "$vscode_dir")"
    echo "    Moving locked npm package aside: ${pkg#$ELECTRON_JS/}"
    mv "$pkg" "${pkg}.__locked_vscode__" 2>/dev/null || true
  done < <(find "$ELECTRON_JS/node_modules" -type d -name '.vscode' 2>/dev/null || true)
fi
if [[ -f "$ELECTRON_BUILD_CMD" ]]; then
  perl -pi -e "s/->run\\('npm ci'/->run('npm install'/" "$ELECTRON_BUILD_CMD"
fi

load_mac_signing_env() {
  if [[ ! -f "$ROOT/.env" ]]; then
    return 0
  fi

  while IFS= read -r line; do
    line="${line//$'\r'/}"
    [[ -z "$line" || "$line" =~ ^# ]] && continue
    case "$line" in
      NATIVEPHP_NOTARIZE=*|NATIVEPHP_APPLE_*=*|NATIVEPHP_MAC_IDENTITY=*|CSC_*=*)
        export "$line"
        ;;
    esac
  done < "$ROOT/.env"

  if [[ "${NATIVEPHP_NOTARIZE:-}" == "1" || "${NATIVEPHP_NOTARIZE:-}" == "true" ]]; then
    export APPLE_ID="${NATIVEPHP_APPLE_ID:-}"
    export APPLE_APP_SPECIFIC_PASSWORD="${NATIVEPHP_APPLE_ID_PASS:-}"
    export APPLE_TEAM_ID="${NATIVEPHP_APPLE_TEAM_ID:-}"
    echo "    Mac notarization enabled (NATIVEPHP_NOTARIZE=1)"
  fi
}

mac_app_is_notarized() {
  local app="$1"
  xcrun stapler validate "$app" >/dev/null 2>&1
}

run_native_build() {
  local label="$1"
  shift
  ensure_production_vite_assets
  echo "==> Building $label"
  if ! php artisan native:build "$@" --no-interaction; then
    echo "ERROR: native:build failed for $label"
    exit 1
  fi
}

hdiutil_can_create_dmg() {
  local probe="$ROOT/tmp/.hdiutil-probe-$$.dmg"
  export TMPDIR="${TMPDIR:-/tmp}"
  if hdiutil create -size 2m -fs HFS+ -volname PulseProbe "$probe" >/dev/null 2>&1; then
    rm -f "$probe"
    return 0
  fi
  rm -f "$probe"
  return 1
}

create_mac_dmg_if_possible() {
  VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
  local dmg="$ROOT/dist/Pulse-${VERSION}-arm64.dmg"
  local zip="$ROOT/dist/Pulse-${VERSION}-arm64.zip"

  if [[ ! -f "$zip" && ! -d "$ROOT/dist/mac-arm64/Pulse.app" ]]; then
    echo "ERROR: Missing macOS ZIP and Pulse.app — cannot create DMG"
    return 1
  fi

  if ! hdiutil_can_create_dmg; then
    echo "    NOTE: hdiutil blocked in this environment (e.g. Cursor agent sandbox)."
    echo "          Mac ZIP is ready: $zip"
    echo "          For DMG, run in Terminal.app: $ROOT/scripts/make-mac-dmg.sh"
    return 0
  fi

  echo "==> Creating macOS DMG (hdiutil)"
  if bash "$ROOT/scripts/make-mac-dmg.sh"; then
    echo "    OK macOS DMG: $dmg"
    return 0
  fi

  echo "WARNING: DMG creation failed; Mac ZIP still available: $zip"
  return 0
}

finalize_mac_desktop_artifacts() {
  VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
  local app="$ROOT/dist/mac-arm64/Pulse.app"
  local zip="$ROOT/dist/Pulse-${VERSION}-arm64.zip"
  local dmg="$ROOT/dist/Pulse-${VERSION}-arm64.dmg"

  if [[ ! -d "$app" ]]; then
    echo "ERROR: Missing $app after mac build"
    return 1
  fi

  if mac_app_is_notarized "$app"; then
    echo "==> Notarized app verified — skipping adhoc re-sign"
  else
    bash "$ROOT/scripts/fix-mac-pulse-codesign.sh"
  fi

  echo "==> Re-packing Mac ZIP (signed app for other Macs)"
  rm -f "$zip" "${zip}.blockmap" 2>/dev/null || true
  ditto -c -k --sequesterRsrc --keepParent "$app" "$zip"

  rm -f "$dmg" "${dmg}.blockmap" 2>/dev/null || true
  create_mac_dmg_if_possible
}

run_native_build_mac() {
  load_mac_signing_env
  run_native_build "macOS (arm64 ZIP + DMG)" mac arm64
  finalize_mac_desktop_artifacts
}

case "$TARGET" in
  mac|mac-arm64)
    run_native_build_mac
    ;;
  mac-x64|mac-intel)
    run_native_build "macOS DMG (x64)" mac x64
    ;;
  win|win-x64)
    run_native_build "Windows installer (x64)" win x64
    ;;
  all)
    run_native_build_mac
    # Re-apply patches / unlock before second platform (native:build may reset vendor npm)
    if [[ -f "$PRUNE_PATCH" && -f "$PRUNE_TRAIT" ]]; then
      cp "$PRUNE_PATCH" "$PRUNE_TRAIT"
    fi
    if [[ -f "$EB_PATCH" && -f "$EB_CONFIG" ]]; then
      cp "$EB_PATCH" "$EB_CONFIG"
    fi
    if [[ -f "$BUILD_CMD_PATCH" && -f "$BUILD_CMD" ]]; then
      cp "$BUILD_CMD_PATCH" "$BUILD_CMD"
    fi
    if [[ -f "$NOTARIZE_PATCH" && -f "$NOTARIZE_JS" ]]; then
      cp "$NOTARIZE_PATCH" "$NOTARIZE_JS"
    fi
    if [[ -d "$ELECTRON_JS/node_modules" ]]; then
      while IFS= read -r vscode_dir; do
        pkg="$(dirname "$vscode_dir")"
        mv "$pkg" "${pkg}.__locked_vscode__" 2>/dev/null || true
      done < <(find "$ELECTRON_JS/node_modules" -type d -name '.vscode' 2>/dev/null || true)
    fi
    if [[ -f "$ELECTRON_BUILD_CMD" ]]; then
      perl -pi -e "s/->run\\('npm ci'/->run('npm install'/" "$ELECTRON_BUILD_CMD"
    fi
    run_native_build "Windows installer (x64)" win x64
    ;;
  *)
    echo "Usage: $0 [all|mac|mac-x64|win]"
    echo "Default: all (macOS arm64 + Windows x64)"
    exit 1
    ;;
esac

prune_old_installers() {
    local keep="${1:-3}"
    local dist="$ROOT/dist"

    if [[ ! -d "$dist" ]]; then
        return 0
    fi

    local versions
    versions="$(find "$dist" -maxdepth 1 -name 'Pulse-*' -print \
        | sed -n 's|.*/Pulse-\([0-9][0-9.]*\).*|\1|p' \
        | sort -Vu)"

    if [[ -z "$versions" ]]; then
        return 0
    fi

    local total to_remove version
    total="$(printf '%s\n' "$versions" | wc -l | tr -d ' ')"

    if [[ "$total" -le "$keep" ]]; then
        return 0
    fi

    to_remove=$((total - keep))
    echo "==> Pruning old installers (keeping $keep latest versions)"

    printf '%s\n' "$versions" | head -n "$to_remove" | while IFS= read -r version; do
        echo "    Removing Pulse-$version-*"
        rm -f "$dist"/Pulse-"$version"-*
    done
}

prune_old_installers 3

VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
DMG="$ROOT/dist/Pulse-${VERSION}-arm64.dmg"
EXE="$ROOT/dist/Pulse-${VERSION}-setup.exe"

echo ""
echo "==> Build complete. Installers are in: $ROOT/dist/"
ls -lah "$ROOT/dist" 2>/dev/null | grep -E 'Pulse-|total' || ls -lah "$ROOT/dist" 2>/dev/null || true

missing=0
if [[ "$TARGET" == "all" || "$TARGET" == "mac" || "$TARGET" == "mac-arm64" ]]; then
  ZIP="$ROOT/dist/Pulse-${VERSION}-arm64.zip"
  if [[ ! -f "$ZIP" ]]; then
    echo "ERROR: Missing macOS ZIP: $ZIP"
    missing=1
  else
    echo "    OK macOS ZIP: $ZIP"
  fi
  if [[ -f "$DMG" ]]; then
    echo "    OK macOS DMG: $DMG"
  elif hdiutil_can_create_dmg; then
    echo "ERROR: Missing macOS DMG (hdiutil works here): $DMG"
    missing=1
  else
    echo "    (DMG skipped — run scripts/make-mac-dmg.sh in Terminal.app for DMG)"
  fi
fi
if [[ "$TARGET" == "all" || "$TARGET" == "win" || "$TARGET" == "win-x64" ]]; then
  if [[ ! -f "$EXE" ]]; then
    echo "ERROR: Missing Windows installer: $EXE"
    missing=1
  else
    echo "    OK Windows: $EXE"
  fi
fi

if [[ "$missing" -eq 0 ]]; then
  BUCKET="$(grep -E '^DB_BACKUP_S3_BUCKET=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  if [[ -n "${BUCKET// }" ]]; then
    echo ""
    echo "==> Uploading installers to S3 (prefix payroll_installer/; old versions will be deleted)"
    if ! php artisan desktop:upload-installers --app-version="$VERSION" --no-interaction; then
      echo "ERROR: Failed to upload installers to S3."
      missing=1
    fi
  else
    echo ""
    echo "==> Skipping S3 installer upload (DB_BACKUP_S3_BUCKET not set)"
  fi
fi

exit "$missing"
