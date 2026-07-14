# CASE EIGHT: UNWRITTEN (3D)

A 1-4 player psychological horror / paranormal investigation game,
being built in **Python + Panda3D** (engine) with **Blender + Blender
Python** (3D content pipeline) and **FastAPI** (backend), per
`docs/SOURCE_3D_CONVERSION_PROMPT.md`'s conversion of the original 2D
design in `docs/SOURCE_DESIGN_2D.txt`.

For agents/AI assistants working on this repo: **read `CLAUDE.md`
first.**

## Current state

Phase 0 (environment + toolchain) complete and tested. Phase 1 (3D
base prototype) partially complete: a real Bullet-collision
first-person controller runs inside a real Blender-generated,
GLB-loaded test room. See `docs/STATUS.md` for the authoritative,
up-to-date breakdown, and `reports/TEST_REPORT.md` for the last real
test run (30/30 passing).

## Requirements

- Python 3.11+
- Blender 4.0+ (for regenerating 3D content)
- Git LFS (for binary assets — see `.gitattributes`)

## Setup

```bash
./scripts/setup.sh          # Linux/macOS
# scripts/setup.ps1 on Windows (see docs/KNOWN_ISSUES.md - unverified there)
```

## Running

```bash
source .venv/bin/activate
export PYTHONPATH="$(pwd)"

./scripts/test_all.sh                 # run the test suite
./scripts/run_game.sh                 # run the current acceptance demo
python -m uvicorn server.app.main:app --host 127.0.0.1 --port 8778   # backend
```

## Documentation

- `CLAUDE.md` — start here
- `docs/SOURCE_DESIGN_2D.txt` — binding original design document
- `docs/SOURCE_3D_CONVERSION_PROMPT.md` — binding 3D/Python conversion rules
- `docs/MASTER_3D_SPEC.md` — working index between the two
- `docs/ARCHITECTURE.md`, `docs/ROADMAP.md`, `docs/STATUS.md`,
  `docs/DECISIONS.md`, `docs/KNOWN_ISSUES.md`, `docs/CONTROLS.md`,
  `docs/ASSET_LICENSES.md`
- `reports/` — environment audit, test report, build report, placeholder report
