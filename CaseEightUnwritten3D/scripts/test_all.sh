#!/usr/bin/env bash
# Linux dev convenience runner (actually used/verified in this
# container). This is what reports/TEST_REPORT.md's results came from.
#
# This container has no GPU/display; Panda3D's "offscreen" window type
# still needs a live X display for its only available pipe
# (glxGraphicsPipe), so integration tests run under xvfb-run. On a
# real Windows machine with a GPU, scripts/test_all.ps1 runs pytest
# directly with no virtual display needed.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

source .venv/bin/activate
export PYTHONPATH="$(pwd)"

xvfb-run -a --server-args="-screen 0 1280x800x24" python -m pytest -v "$@"
