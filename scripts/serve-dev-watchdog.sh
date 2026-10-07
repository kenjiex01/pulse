#!/usr/bin/env bash
# Restart php artisan serve when it exits (long pulls or PHP 8.5 time limits can kill the built-in server).
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

LOG="${ROOT}/storage/logs/pulse-dev-serve.log"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] People360 dev watchdog started (port ${PULSE_DEV_PORT:-8000})" >>"${LOG}"

while true; do
  ./scripts/serve-dev.sh >>"${LOG}" 2>&1
  code=$?
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] serve-dev exited (${code}); restarting in 2s…" >>"${LOG}"
  sleep 2
done
