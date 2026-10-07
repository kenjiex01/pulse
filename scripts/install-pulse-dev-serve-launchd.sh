#!/usr/bin/env bash
# Keep People360 dev server (php artisan serve) running after login — Safari http://127.0.0.1:8000
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PULSE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
SERVE_SCRIPT="${PULSE_ROOT}/scripts/serve-dev.sh"
LABEL="com.people360.pulse-dev-serve"
PLIST="${HOME}/Library/LaunchAgents/${LABEL}.plist"
LOG_DIR="${PULSE_ROOT}/storage/logs"
PORT="${PULSE_DEV_PORT:-8000}"

mkdir -p "${LOG_DIR}" "${HOME}/Library/LaunchAgents"

if [[ ! -x "${SERVE_SCRIPT}" ]]; then
  chmod +x "${SERVE_SCRIPT}"
fi

cat >"${PLIST}" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>${LABEL}</string>
    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>${SERVE_SCRIPT}</string>
    </array>
    <key>WorkingDirectory</key>
    <string>${PULSE_ROOT}</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PULSE_DEV_PORT</key>
        <string>${PORT}</string>
        <key>PATH</key>
        <string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
    </dict>
    <key>KeepAlive</key>
    <true/>
    <key>RunAtLoad</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${LOG_DIR}/pulse-dev-serve.launchd.log</string>
    <key>StandardErrorPath</key>
    <string>${LOG_DIR}/pulse-dev-serve.launchd.log</string>
</dict>
</plist>
EOF

launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "${PLIST}"
launchctl enable "gui/$(id -u)/${LABEL}" 2>/dev/null || true
launchctl kickstart -k "gui/$(id -u)/${LABEL}" 2>/dev/null || true

sleep 2
if curl -sf --connect-timeout 5 "http://127.0.0.1:${PORT}/login" >/dev/null; then
  echo "Dev server OK: http://127.0.0.1:${PORT}"
else
  echo "LaunchAgent installed but http://127.0.0.1:${PORT} not responding yet — check ${LOG_DIR}/pulse-dev-serve.launchd.log"
  exit 1
fi

echo "Installed ${LABEL} (auto-restart on crash, starts at login)."
echo "Stop: launchctl bootout gui/$(id -u)/${LABEL}"
echo "Log: ${LOG_DIR}/pulse-dev-serve.launchd.log"
