#!/bin/bash
# Double-click in Finder to start People360 dev server and open Safari.
cd "$(dirname "$0")/.." || exit 1

if lsof -i :8000 -sTCP:LISTEN >/dev/null 2>&1; then
  open "http://127.0.0.1:8000/login"
  osascript -e 'display notification "People360 already running on port 8000" with title "People360 Dev"'
  exit 0
fi

chmod +x ./scripts/serve-dev-watchdog.sh 2>/dev/null || true
nohup ./scripts/serve-dev-watchdog.sh >> storage/logs/pulse-dev-serve.log 2>&1 &
sleep 2

if curl -s -o /dev/null --connect-timeout 3 http://127.0.0.1:8000/login; then
  open "http://127.0.0.1:8000/login"
  osascript -e 'display notification "Server started — keep this Mac awake or leave Terminal open" with title "People360 Dev"'
else
  osascript -e 'display dialog "Could not start People360 on http://127.0.0.1:8000. Open Terminal and run: cd pulse && ./scripts/serve-dev.sh" buttons {"OK"} default button 1 with title "People360 Dev" with icon caution'
fi
