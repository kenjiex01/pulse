#!/bin/bash
cd /Users/kentordillos/Documents/ISKOLARIS/pulse
export TMPDIR=/tmp
./tmp/make-mac-dmg.sh
if [[ -f dist/Pulse-0.1.32-arm64.dmg ]]; then
  echo ""
  echo "Done: $(pwd)/dist/Pulse-0.1.32-arm64.dmg"
  open dist
else
  echo "DMG creation failed. See output above."
fi
echo ""
read -n 1 -s -r -p "Press any key to close..."
