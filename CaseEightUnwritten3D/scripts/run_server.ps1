# Launches the FastAPI backend on Windows.
#
# NOTE (honesty flag): unverified on real Windows -- see scripts/setup.ps1.
# The underlying command (uvicorn server.app.main:app) was verified
# working on Linux: real HTTP requests to /, /health, and /docs all
# returned 200. See reports/TEST_REPORT.md.

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
. .\.venv\Scripts\Activate.ps1
$env:PYTHONPATH = $root

python -m uvicorn server.app.main:app --host 127.0.0.1 --port 8778 --reload
