#!/usr/bin/env bash
# Restart php artisan serve when it exits (long pulls or PHP 8.5 time limits can kill the built-in server).
set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="${PEOPLE360_PULSE_ROOT:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
SERVE_SCRIPT="${PULSE_DEV_SERVE_SCRIPT:-${SCRIPT_DIR}/serve-dev.sh}"

LOG="${PULSE_DEV_SERVE_LOG:-${HOME}/Library/Application Support/People360/pulse-dev-serve/watchdog.log}"
mkdir -p "$(dirname "${LOG}")"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] People360 dev watchdog started (port ${PULSE_DEV_PORT:-8000})" >>"${LOG}"

while true; do
  /bin/bash "${SERVE_SCRIPT}" >>"${LOG}" 2>&1
  code=$?
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] serve-dev exited (${code}); restarting in 2s…" >>"${LOG}"
  sleep 2
done
