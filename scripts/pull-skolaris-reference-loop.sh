#!/usr/bin/env bash
# First pull 10 minutes after LaunchAgent start (login), then every 3 minutes.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PULL_SCRIPT="${SCRIPT_DIR}/pull-skolaris-reference.sh"
INITIAL_DELAY_SECONDS="${SKOLARIS_REFERENCE_PULL_INITIAL_DELAY_SECONDS:-600}"
INTERVAL_SECONDS="${SKOLARIS_REFERENCE_PULL_INTERVAL_SECONDS:-180}"

sleep "${INITIAL_DELAY_SECONDS}"

while true; do
    /bin/bash "${PULL_SCRIPT}" || true
    sleep "${INTERVAL_SECONDS}"
done
