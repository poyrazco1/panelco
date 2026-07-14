#!/usr/bin/env bash
# Linux dev convenience runner for the current Phase 0/1 acceptance
# demo (game/bootstrap/run_test_room.py). There is no full game/main.py
# entrypoint yet -- see docs/STATUS.md.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

source .venv/bin/activate
export PYTHONPATH="$(pwd)"
export CASE_EIGHT_ENV="${CASE_EIGHT_ENV:-development}"

xvfb-run -a --server-args="-screen 0 1280x800x24" python game/bootstrap/run_test_room.py
