# Runs the full automated test suite on Windows.
#
# NOTE (honesty flag): unverified on real Windows -- see scripts/setup.ps1.
# On Windows, a real GPU/display is normally present so Panda3D's
# integration tests should run directly (no Xvfb needed, unlike this
# Linux dev container -- see scripts/test_all.sh and
# reports/ENVIRONMENT_AUDIT.md for why Xvfb is required here).

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
. .\.venv\Scripts\Activate.ps1
$env:PYTHONPATH = $root

python -m pytest -v
