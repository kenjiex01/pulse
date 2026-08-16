#!/usr/bin/env bash
# Reset / migrate the NativePHP pulse-dev SQLite DB used by `php artisan native:serve`.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DB="${HOME}/Library/Application Support/pulse-dev/storage/app/pulse.sqlite"
DIR="$(dirname "$DB")"

mkdir -p "$DIR"
rm -f "$DB" "${DB}-shm" "${DB}-wal"
touch "$DB"

cd "$ROOT"
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB"

echo "Migrating: $DB"
php artisan migrate --force --no-interaction
echo "Seeding..."
php artisan db:seed --force --no-interaction

echo "Done. Tables:"
sqlite3 "$DB" "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('cache','users','migrations') ORDER BY name;"
echo "Size: $(wc -c < "$DB") bytes"
echo "Restart Pulse (native:serve) and log in again."
