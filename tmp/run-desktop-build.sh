#!/usr/bin/env bash
set -euo pipefail
cd /Users/kentordillos/Documents/ISKOLARIS/pulse
export TMPDIR=/tmp
export COMPOSER_PROCESS_TIMEOUT=0
LOG=/Users/kentordillos/Documents/ISKOLARIS/pulse/tmp/desktop-build-0.1.30.log
echo "Starting Pulse 0.1.30 desktop build at $(date)" | tee "$LOG"
./scripts/build-desktop.sh all 2>&1 | tee -a "$LOG"
echo "BUILD_EXIT:${PIPESTATUS[0]}" | tee -a "$LOG"
