#!/usr/bin/env bash
# Records a Playwright video of Employee Profile upload template download + upload preview.
# Output: pulse/docs/videos/employee-profile-weekly-upload-test.webm
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
E2E="$ROOT/scripts/e2e/employee-profile-upload"
VID_DIR="$ROOT/docs/videos"
PORT="${PULSE_E2E_PORT:-8000}"
BASE_URL="http://127.0.0.1:${PORT}"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

if lsof -i ":${PORT}" -sTCP:LISTEN -t >/dev/null 2>&1; then
  echo "Using existing server on ${BASE_URL}"
else
  echo "Starting dev server on ${BASE_URL}..."
  (cd "$ROOT" && php artisan serve --host=127.0.0.1 --port="${PORT}") &
  SERVER_PID=$!
  for _ in $(seq 1 30); do
    if curl -sf "${BASE_URL}/login" >/dev/null 2>&1; then
      break
    fi
    sleep 0.5
  done
fi

mkdir -p "$VID_DIR"
cd "$E2E"
if [[ ! -d node_modules ]]; then
  npm install --no-fund --no-audit
fi
npx playwright install chromium

export PULSE_E2E_BASE_URL="$BASE_URL"
export PULSE_E2E_DEMO=1
npm run record

WEBM="$(find "$E2E/test-results" -name '*.webm' -type f | head -1)"
if [[ -z "$WEBM" ]]; then
  echo "No video file found under $E2E/test-results"
  exit 1
fi

OUT="$VID_DIR/employee-profile-weekly-upload-test.webm"
cp "$WEBM" "$OUT"
echo ""
echo "Video saved: $OUT"
