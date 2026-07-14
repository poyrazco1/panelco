# Launches the game client on Windows.
#
# NOTE (honesty flag): unverified on real Windows -- see scripts/setup.ps1.
# The Linux equivalent path is exercised via
# game/bootstrap/run_test_room.py under Xvfb (see reports/TEST_REPORT.md);
# there is no Windows machine in this development container to run this
# script against.

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
. .\.venv\Scripts\Activate.ps1
$env:PYTHONPATH = $root
$env:CASE_EIGHT_ENV = "development"

python game\bootstrap\run_test_room.py
