#!/usr/bin/env bash
# Pull skolaris-be and skolaris-fe (reference repos) for People360 / checker parity.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PULSE_ROOT="${PEOPLE360_PULSE_ROOT:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
ISKOLARIS_ROOT="${PEOPLE360_ISKOLARIS_ROOT:-$(cd "${PULSE_ROOT}/.." && pwd)}"
LOG_DIR="${PULSE_ROOT}/storage/logs"
LOG_FILE="${LOG_DIR}/skolaris-reference-pull.log"
BE_DIR="${ISKOLARIS_ROOT}/skolaris-be"
FE_DIR="${ISKOLARIS_ROOT}/skolaris-fe"

mkdir -p "${LOG_DIR}"

log() {
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$*" | tee -a "${LOG_FILE}"
}

pull_repo() {
    local name="$1"
    local dir="$2"

    if [[ ! -d "${dir}/.git" ]]; then
        log "[${name}] SKIP — not a git repo: ${dir}"

        return 0
    fi

    log "[${name}] pull start (${dir})"

    (
        cd "${dir}"
        local branch
        branch="$(git branch --show-current 2>/dev/null || echo master)"

        git fetch origin --quiet 2>&1 || {
            log "[${name}] fetch failed"
            exit 1
        }

        local stashed=0
        if ! git diff --quiet -- .env.example .env .env.dev .env.prod 2>/dev/null \
            || [[ -n "$(git status --porcelain -- .env.example .env .env.dev .env.prod 2>/dev/null)" ]]; then
            if git stash push -m "auto-pull $(date '+%Y-%m-%dT%H:%M:%S')" -- .env.example .env .env.dev .env.prod >>"${LOG_FILE}" 2>&1; then
                stashed=1
            fi
        fi

        if git pull --ff-only origin "${branch}" >>"${LOG_FILE}" 2>&1; then
            log "[${name}] OK — ${branch} @ $(git rev-parse --short HEAD)"
        else
            log "[${name}] pull failed (see log)"
            exit 1
        fi

        if [[ "${stashed}" -eq 1 ]]; then
            if ! git stash pop >>"${LOG_FILE}" 2>&1; then
                log "[${name}] WARN — stash pop conflict; run 'git stash list' in ${dir}"
            fi
        fi
    ) || log "[${name}] finished with errors"
}

log "=== skolaris reference pull ==="
pull_repo "skolaris-be" "${BE_DIR}"
pull_repo "skolaris-fe" "${FE_DIR}"
log "=== done ==="
