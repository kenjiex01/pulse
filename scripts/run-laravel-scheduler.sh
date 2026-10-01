#!/usr/bin/env bash
# Run Laravel scheduled tasks (probationary emails, NTE overdue, biometric pull, etc.).
# Cron example (every minute): * * * * * /path/to/pulse/scripts/run-laravel-scheduler.sh >> /dev/null 2>&1

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

php artisan schedule:run --no-interaction
