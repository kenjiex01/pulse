#!/usr/bin/env bash
# Install macOS LaunchAgent: first pull 10m after login, then every 3 minutes.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PULSE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
ISKOLARIS_ROOT="$(cd "${PULSE_ROOT}/.." && pwd)"
APP_SUPPORT="${HOME}/Library/Application Support/People360/skolaris-reference-pull"
LOOP_SCRIPT="${APP_SUPPORT}/pull-skolaris-reference-loop.sh"
PULL_SCRIPT="${APP_SUPPORT}/pull-skolaris-reference.sh"
LABEL="com.people360.skolaris-reference-pull"
PLIST="${HOME}/Library/LaunchAgents/${LABEL}.plist"
LOG_DIR="${PULSE_ROOT}/storage/logs"
INITIAL_DELAY_SECONDS="${SKOLARIS_REFERENCE_PULL_INITIAL_DELAY_SECONDS:-600}"
INTERVAL_SECONDS="${SKOLARIS_REFERENCE_PULL_INTERVAL_SECONDS:-180}"

mkdir -p "${APP_SUPPORT}" "${LOG_DIR}" "${HOME}/Library/LaunchAgents"

cp "${SCRIPT_DIR}/pull-skolaris-reference.sh" "${PULL_SCRIPT}"
cp "${SCRIPT_DIR}/pull-skolaris-reference-loop.sh" "${LOOP_SCRIPT}"
chmod +x "${PULL_SCRIPT}" "${LOOP_SCRIPT}"

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
        <string>${LOOP_SCRIPT}</string>
    </array>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PEOPLE360_PULSE_ROOT</key>
        <string>${PULSE_ROOT}</string>
        <key>PEOPLE360_ISKOLARIS_ROOT</key>
        <string>${ISKOLARIS_ROOT}</string>
        <key>SKOLARIS_REFERENCE_PULL_INITIAL_DELAY_SECONDS</key>
        <string>${INITIAL_DELAY_SECONDS}</string>
        <key>SKOLARIS_REFERENCE_PULL_INTERVAL_SECONDS</key>
        <string>${INTERVAL_SECONDS}</string>
        <key>PATH</key>
        <string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
    </dict>
    <key>KeepAlive</key>
    <true/>
    <key>RunAtLoad</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${LOG_DIR}/skolaris-reference-pull.launchd.log</string>
    <key>StandardErrorPath</key>
    <string>${LOG_DIR}/skolaris-reference-pull.launchd.log</string>
</dict>
</plist>
EOF

launchctl bootout "gui/$(id -u)/${LABEL}" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "${PLIST}"
launchctl enable "gui/$(id -u)/${LABEL}" 2>/dev/null || true

echo "Installed ${LABEL} (first pull ${INITIAL_DELAY_SECONDS}s after login, then every ${INTERVAL_SECONDS}s)."
echo "Runner copies: ${APP_SUPPORT}/"
echo "Log: ${LOG_DIR}/skolaris-reference-pull.log"
echo "Stop: launchctl bootout gui/$(id -u)/${LABEL}"
