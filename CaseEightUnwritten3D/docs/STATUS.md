# Status

Last updated: 2026-07-14, end of the Phase 0 + partial Phase 1 session.
Read this file first in any new session, along with `CLAUDE.md`,
`docs/KNOWN_ISSUES.md`, and `reports/TEST_REPORT.md`.

## Headline

Phase 0 (environment + toolchain) is complete and genuinely tested.
Phase 1 (3D base prototype) is partially done: a real Bullet-collision
first-person controller runs inside a real Blender-generated,
GLB-loaded test room, verified by 30 passing automated tests. No game
content (maps, entities, equipment, caravans, vehicles) beyond one
procedural test room exists yet. There is no playable "game" in the
sense of a menu-to-mission loop — only the underlying engine
primitives, proven working.

## What is real and working right now

- `game/core/config.py` — layered TOML config loader.
- `game/core/physics_world.py` — Bullet world wrapper.
- `game/rendering/scene_manager.py` — GLB → scene graph + Bullet
  collision + gameplay socket extraction. Tested against a real
  generated GLB.
- `game/input/input_manager.py` — named-action input, key rebinding
  hook, tested via `InputState.force`.
- `game/player/first_person_controller.py` — Bullet capsule character
  controller: walk/run/crouch speeds from config, gravity, slope
  limit, mouse look, correct eye-height math (see
  `docs/DECISIONS.md` for the bug that was found and fixed here).
- `game/interaction/interaction_system.py` — Bullet raycast +
  tag-based interactable dispatch.
- `tools/blender/generate_test_room.py` — real, deterministic,
  re-runnable Blender Python generator (walls, floor, ceiling, door
  frame + trim, a table, 6 collision bodies + 1 more for the table = 9,
  6 gameplay sockets). Exports both `.glb` and source `.blend`.
- `tools/validators/validate_glb.py` — real glTF structural validator
  (missing meshes, no-material primitives, NaN transforms, required
  collision/socket presence).
- `server/app/main.py` — minimal FastAPI app with a real `/health`
  endpoint. Verified with both a live `uvicorn` process (real HTTP
  requests) and `TestClient`.
- 30 automated tests, all passing (`reports/TEST_REPORT.md`):
  unit tests (config, physics, input, controller config) that need no
  display, plus integration tests (scene loading, character collision,
  interaction raycasts, the full Blender→GLB pipeline, the FastAPI
  app) that run under Xvfb in this container.

## What is scaffolded only (empty packages, no logic)

`game/{characters,inventory,equipment,evidence,cases,narrator8,
entities,hunt,hiding,phone,voice,networking,lobby,vehicles,caravans,
economy,progression,saving,localization,accessibility,audio,ui,
shaders}/` — directories and `__init__.py` exist per the mandated
project structure; no implementation yet. Same for
`server/{api,websocket,authoritative,persistence,migrations}/`.

## What is explicitly NOT done (do not imply otherwise)

- No door/drawer open-close behavior, no inventory pickup/drop, no
  save/load, no settings UI, no audio playback.
- No `game/main.py` full entrypoint — only
  `game/bootstrap/run_test_room.py`, a diagnostic/acceptance demo.
- Zero of the 8 named maps, 20 named entities, 32 named equipment
  items, 8 caravans, or 8 vehicles from the source design have been
  modeled. Only one generic, unnamed "test room" exists.
- No multiplayer networking beyond a single FastAPI health endpoint —
  no lobby, no real-time protocol, no authoritative gameplay state.
- No Windows build has been produced (no Windows host available in
  this container — see `reports/ENVIRONMENT_AUDIT.md`).
- No Steam integration attempted.
- No audio content (no audio hardware in this container to author/test
  against).

## Known-good commands (verified this session)

```bash
source .venv/bin/activate
export PYTHONPATH="$(pwd)"

# Regenerate the test room
blender --background --python tools/blender/generate_test_room.py -- --seed 8

# Run the full test suite (Linux/Xvfb dev path)
./scripts/test_all.sh

# Run the Phase 0/1 acceptance demo
./scripts/run_game.sh

# Run the backend
python -m uvicorn server.app.main:app --host 127.0.0.1 --port 8778
```
