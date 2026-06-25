#!/usr/bin/env bash
# Daily code-change snapshot for pulse/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TODAY="${1:-$(date +%Y-%m-%d)}"
NEXT_DAY="$(date -j -f "%Y-%m-%d" -v+1d "$TODAY" "+%Y-%m-%d" 2>/dev/null || date -d "$TODAY + 1 day" "+%Y-%m-%d")"
LOG_FILE="docs/changelog/${TODAY}.md"

echo "=== Pulse Code Snapshot — ${TODAY} ==="
echo

if [[ -f "$LOG_FILE" ]]; then
    echo "--- Agent changelog (${LOG_FILE}) ---"
    cat "$LOG_FILE"
    echo
else
    echo "--- Agent changelog: (none for ${TODAY}) ---"
    echo
fi

echo "--- Git commits ---"
git log --since="${TODAY} 00:00:00" --until="${NEXT_DAY} 00:00:00" \
    --pretty=format:"%h | %ad | %s" --date=format:"%H:%M" 2>/dev/null || echo "(no commits)"
echo
echo

echo "--- Files modified today (source only) ---"
find . -type f \( \
    -name "*.php" -o -name "*.blade.php" -o -name "*.js" -o -name "*.css" \
    -o -name "*.json" -o -name "*.md" -o -name "*.mdc" \
\) \
    ! -path "./vendor/*" \
    ! -path "./node_modules/*" \
    ! -path "./storage/*" \
    ! -path "./bootstrap/cache/*" \
    ! -path "./public/build/*" \
    -newermt "${TODAY} 00:00:00" \
    ! -newermt "${NEXT_DAY} 00:00:00" \
    -exec stat -f "%Sm | %N" -t "%H:%M" {} \; 2>/dev/null \
    | sort || true
echo

echo "--- Uncommitted diff vs HEAD ---"
git diff --stat HEAD 2>/dev/null || echo "(not a git repo or no diff)"
echo

echo "--- Untracked file count ---"
git ls-files --others --exclude-standard 2>/dev/null | wc -l | awk '{print $1 " untracked files"}'
