#!/usr/bin/env bash
# Keep People360 dev server (php artisan serve) running after login — http://127.0.0.1:8000
# Runner lives under Application Support so launchd is not blocked from Documents (TCC).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PULSE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_SUPPORT="${HOME}/Library/Application Support/People360/pulse-dev-serve"
WATCHDOG_SCRIPT="${APP_SUPPORT}/serve-dev-watchdog.sh"
SERVE_SCRIPT="${APP_SUPPORT}/serve-dev.sh"
LABEL="com.people360.pulse-dev-serve"
PLIST="${HOME}/Library/LaunchAgents/${LABEL}.plist"
LOG_DIR="${PULSE_ROOT}/storage/logs"
PORT="${PULSE_DEV_PORT:-8000}"

mkdir -p "${APP_SUPPORT}" "${LOG_DIR}" "${HOME}/Library/LaunchAgents"

cp "${SCRIPT_DIR}/serve-dev.sh" "${SERVE_SCRIPT}"
cp "${SCRIPT_DIR}/serve-dev-watchdog.sh" "${WATCHDOG_SCRIPT}"
chmod +x "${SERVE_SCRIPT}" "${WATCHDOG_SCRIPT}"

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
        <string>${WATCHDOG_SCRIPT}</string>
    </array>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PEOPLE360_PULSE_ROOT</key>
        <string>${PULSE_ROOT}</string>
        <key>PULSE_DEV_SERVE_SCRIPT</key>
        <string>${SERVE_SCRIPT}</string>
        <key>PULSE_DEV_SERVE_LOG</key>
        <string>${APP_SUPPORT}/watchdog.log</string>
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
    <string>${APP_SUPPORT}/launchd.log</string>
    <key>StandardErrorPath</key>
    <string>${APP_SUPPORT}/launchd.log</string>
</dict>
</plist>
EOF

launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "${PLIST}"
launchctl enable "gui/$(id -u)/${LABEL}" 2>/dev/null || true
launchctl kickstart -k "gui/$(id -u)/${LABEL}" 2>/dev/null || true

sleep 4
if curl -sf --connect-timeout 8 "http://127.0.0.1:${PORT}/login" >/dev/null; then
  echo "Dev server OK: http://127.0.0.1:${PORT}"
else
  echo "LaunchAgent installed but http://127.0.0.1:${PORT} not responding yet — check ${APP_SUPPORT}/watchdog.log and ${APP_SUPPORT}/launchd.log"
  exit 1
fi

echo "Installed ${LABEL} (watchdog + auto-restart, starts at login)."
echo "Runner: ${APP_SUPPORT}/"
echo "Stop: launchctl bootout gui/$(id -u)/${LABEL}"
echo "Logs: ${APP_SUPPORT}/watchdog.log, ${APP_SUPPORT}/launchd.log"
