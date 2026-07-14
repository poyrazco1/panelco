# CLAUDE.md — CASE EIGHT: UNWRITTEN (3D/Python)

Read this file first in every session, then in order:
1. `docs/STATUS.md` — what's actually built right now
2. `docs/KNOWN_ISSUES.md` — what's broken or blocked
3. `reports/TEST_REPORT.md` — last real test results
4. `docs/ROADMAP.md` — what phase we're in and what's next

## What this project is

CASE EIGHT: UNWRITTEN is a 1-4 player psychological horror / paranormal
investigation game. The **binding design source** is
`docs/SOURCE_DESIGN_2D.txt` (the original 2D/Unity design document —
every exact number, name, price, level, and rule in it is binding) as
converted to 3D/Python by `docs/SOURCE_3D_CONVERSION_PROMPT.md` (the
binding conversion ruleset — every 2D/Unity-specific instruction in the
source design is superseded by this document). `docs/MASTER_3D_SPEC.md`
is a working index between the two; if it ever seems to contradict
either source document, the two source documents win.

**This is a 3D game. Not 2D. Not Unity. Not Unreal.**
Engine: Python + Panda3D + Panda3D Bullet.
Content pipeline: Blender + Blender Python (headless).
Backend: FastAPI (Python), SQLite for local dev, PostgreSQL optional
for production.

## Non-negotiable rules (binding on every future session)

- Never report a test as passed, a model as generated, a build as
  produced, or a feature as working without having actually run it in
  this session (or a prior session with results still verifiable on
  disk). Use the status tags: `DONE_AND_TESTED`,
  `DONE_NOT_FULLY_TESTED`, `IMPLEMENTED_WITH_PLACEHOLDER`,
  `BLOCKED_BY_ENVIRONMENT`, `NOT_STARTED`.
- Placeholder geometry/content is allowed during development but must
  be flagged as such (see `reports/PLACEHOLDER_REPORT.md`) and never
  presented as final.
- The exact counts from the source design are binding: **8** main maps,
  **20** paranormal entity appearances, **8** research caravans, **8**
  personal vehicles, **32** equipment items. Never add or remove from
  these counts.
- No single giant Python file — one system per module (see
  `docs/ARCHITECTURE.md`).
- No API keys or secrets committed to the repo. Use `.env` (gitignored)
  with a documented `.env.example` when secrets are needed.
- Don't ask the user to write code, model assets, or fix bugs — that's
  this project's job. Only escalate to the user for things explicitly
  requiring their action: paid service credentials, Steamworks access,
  or an actual Windows machine to build/test on.

## Environment reality (read before assuming something works)

This development container (see `reports/ENVIRONMENT_AUDIT.md` for the
full audit) has **no GPU, no display, and no audio hardware**. Every
Panda3D script must run under `xvfb-run -a` (see `scripts/test_all.sh`
and `scripts/run_game.sh`) — Panda3D's "offscreen" window type still
needs a live X display here since only `glxGraphicsPipe` is available.
Rendering uses Mesa's `llvmpipe` software rasterizer — functionally
correct but not representative of real-world performance on a player's
GPU. There is no Windows host in this container; Windows-specific
scripts (`scripts/*.ps1`) are written but unverified — see the honesty
flag comment at the top of each.

## Quick start (verified working commands)

```bash
source .venv/bin/activate
export PYTHONPATH="$(pwd)"

./scripts/test_all.sh          # run all 30 tests (Linux/Xvfb)
./scripts/run_game.sh          # run the Phase 0/1 acceptance demo
python -m uvicorn server.app.main:app --host 127.0.0.1 --port 8778   # backend
```

To regenerate the test room asset:
```bash
blender --background --python tools/blender/generate_test_room.py -- --seed 8
```

## Project structure

See `docs/ARCHITECTURE.md` for the layered breakdown of `game/`,
`server/`, and `tools/`. The directory tree itself matches the
structure mandated in `docs/SOURCE_3D_CONVERSION_PROMPT.md` §5.

## End of every session

Before ending a work session:
1. Run `./scripts/test_all.sh` and update `reports/TEST_REPORT.md` with
   the real output.
2. Update `docs/STATUS.md` to reflect what's actually true now.
3. Update `docs/KNOWN_ISSUES.md` and `reports/PLACEHOLDER_REPORT.md` if
   anything changed.
4. Log any new bugs found/fixed or non-obvious decisions in
   `docs/DECISIONS.md`.
5. Commit with a descriptive message.
