# Windows dev-environment setup.
#
# NOTE (honesty flag): this script has NOT been executed. Development
# and testing so far happened in a Linux container with no Windows
# host available (see reports/ENVIRONMENT_AUDIT.md, section
# "Windows build capability"). It is written to the same steps
# scripts/setup.sh performs and verified on Linux, translated to
# PowerShell, but is unverified on real Windows until someone runs it
# there and reports/BUILD_REPORT.md is updated accordingly.

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

python -m venv .venv
. .\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.lock

Write-Host "Setup complete. Activate with: .\.venv\Scripts\Activate.ps1"
Write-Host "Set PYTHONPATH to the project root before running game/server scripts."
