#!/usr/bin/env bash
# Dev server with PHP limits aligned to config/uploads.php (large SQL restore uploads).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

UPLOAD="${PHP_UPLOAD_MAX_FILESIZE:-512M}"
POST="${PHP_POST_MAX_SIZE:-600M}"
MEMORY="${PHP_MEMORY_LIMIT:-2048M}"
# PHP treats CLI -d max_execution_time=0 as a 0-second cap. Serve runs until stopped — use a long cap, not 600s.
EXEC="${PHP_MAX_EXECUTION_TIME:-86400}"

HOST="${PULSE_DEV_HOST:-127.0.0.1}"
PORT="${PULSE_DEV_PORT:-8000}"

exec php \
  -d "upload_max_filesize=${UPLOAD}" \
  -d "post_max_size=${POST}" \
  -d "memory_limit=${MEMORY}" \
  -d "max_execution_time=${EXEC}" \
  -d "max_input_time=${EXEC}" \
  artisan serve --host="$HOST" --port="$PORT" "$@"
