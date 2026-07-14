#!/usr/bin/env bash
# Linux/macOS dev-environment setup. This is the script actually
# exercised in this container -- see scripts/setup.ps1 for the Windows
# equivalent (target platform for shipped builds), which has not been
# run here since this container is Linux-only.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
# requirements.lock is the exact set verified working in
# reports/ENVIRONMENT_AUDIT.md; `pip install -e .` is not used here
# because this project intentionally has multiple top-level packages
# (game/, server/, tools/) and has not been tested as an installable
# distribution.
pip install -r requirements.lock

echo "Setup complete. Activate with: source .venv/bin/activate"
echo "Set PYTHONPATH to the project root before running game/server scripts, e.g.:"
echo "  export PYTHONPATH=\"$(pwd)\""
