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
iconutil -c icns icon.iconset -o icon.icns
rm -rf icon.iconset
cd "$ROOT"

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

echo "==> Regenerating employee upload template"
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); file_put_contents(resource_path('templates/employee_upload_template.xlsx'), app(App\Services\EmployeeUploadService::class)->buildTemplateBinary());"

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

echo ""
echo "==> Build complete. Installers are in: $ROOT/dist/"
ls -lah "$ROOT/dist" 2>/dev/null | grep -E 'Pulse-|total' || ls -lah "$ROOT/dist" 2>/dev/null || true
