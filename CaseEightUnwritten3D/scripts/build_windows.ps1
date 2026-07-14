# Builds a self-contained Windows distribution of the game.
#
# STATUS: NOT_STARTED. This is a placeholder for Phase 7 (see
# docs/ROADMAP.md) -- packaging has not been implemented or tested.
# Do not run this expecting a working .exe; it will fail loudly rather
# than silently claim success, per the project's no-fake-success rule
# (docs/DECISIONS.md).
#
# Planned approach (not yet implemented):
#   1. Use Panda3D's recommended packaging path (setuptools integration
#      via panda3d.tools / the `bdist_apps` command from a proper
#      setup.py, which resolves the correct Panda3D runtime + plugins
#      for a Windows target) to produce builds/windows/.
#   2. Verify the produced .exe launches on a clean Windows machine
#      with no Python/Panda3D preinstalled.
#   3. Record real results in reports/BUILD_REPORT.md -- only after
#      an actual Windows build has actually been produced and run.

$ErrorActionPreference = "Stop"
Write-Error "build_windows.ps1 is not implemented yet (Phase 7). See docs/ROADMAP.md and reports/BUILD_REPORT.md."
exit 1
