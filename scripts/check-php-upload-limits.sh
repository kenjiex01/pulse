#!/usr/bin/env bash
# Verify PHP upload limits meet Pulse payroll upload max (config/uploads.php).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

to_kb() {
    local value
    value="$(printf '%s' "$1" | tr '[:lower:]' '[:upper:]')"
    local number="${value%[KMG]*}"
    local unit="${value:${#number}}"

    case "$unit" in
        G) echo $((number * 1024 * 1024)) ;;
        M) echo $((number * 1024)) ;;
        K|"") echo "$number" ;;
        *) echo 0 ;;
    esac
}

read -r REQUIRED_UPLOAD_KB REQUIRED_UPLOAD_RAW REQUIRED_POST_RAW < <(
    php -r "
        require '$ROOT/vendor/autoload.php';
        \$app = require '$ROOT/bootstrap/app.php';
        \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo (int) config('uploads.max_file_kb') . ' ';
        echo config('uploads.php_ini.upload_max_filesize') . ' ';
        echo config('uploads.php_ini.post_max_size') . PHP_EOL;
    "
)

REQUIRED_POST_KB="$(to_kb "$REQUIRED_POST_RAW")"

upload_raw="$(php -r "echo ini_get('upload_max_filesize');")"
post_raw="$(php -r "echo ini_get('post_max_size');")"
upload_kb="$(to_kb "$upload_raw")"
post_kb="$(to_kb "$post_raw")"

echo "PHP upload_max_filesize: ${upload_raw} (${upload_kb} KB)"
echo "PHP post_max_size:       ${post_raw} (${post_kb} KB)"
echo "Pulse app requires:      upload >= ${REQUIRED_UPLOAD_KB} KB (${REQUIRED_UPLOAD_RAW}), post >= ${REQUIRED_POST_KB} KB (${REQUIRED_POST_RAW})"

if (( upload_kb < REQUIRED_UPLOAD_KB || post_kb < REQUIRED_POST_KB )); then
    echo
    echo "FAIL: PHP limits are too low for payroll uploads."
    echo "Fix (Homebrew PHP example):"
    echo "  mkdir -p /opt/homebrew/etc/php/8.5/conf.d"
    echo "  cp config/php-upload-limits.ini /opt/homebrew/etc/php/8.5/conf.d/99-pulse-uploads.ini"
    echo "Then restart php artisan serve."
    echo "Desktop builds use NativeAppServiceProvider::phpIni() from config/uploads.php automatically."
    exit 1
fi

echo "OK: PHP upload limits are sufficient."
