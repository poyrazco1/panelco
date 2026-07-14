# Roadmap

Phases match `docs/SOURCE_3D_CONVERSION_PROMPT.md` §21. Status legend:
`DONE_AND_TESTED`, `DONE_NOT_FULLY_TESTED`,
`IMPLEMENTED_WITH_PLACEHOLDER`, `BLOCKED_BY_ENVIRONMENT`,
`NOT_STARTED`.

## Phase 0 — Environment and toolchain — `DONE_AND_TESTED`

- [x] Real environment audit (`reports/ENVIRONMENT_AUDIT.md`)
- [x] Git repository (this repo, branch `claude/case-eight-unwritten-3d-mi5vur`)
- [x] Python virtual environment + locked dependencies (`requirements.lock`)
- [x] Panda3D window test (onscreen and offscreen, under Xvfb)
- [x] Bullet test (falling body, resting character controller)
- [x] Blender headless test (real subprocess, fixed a real numpy-missing bug)
- [x] GLB production and loading into Panda3D (real geometry, real sockets, real collision)
- [x] FastAPI localhost test (real HTTP round-trip to `/health`, `/`, `/docs`)
- [x] Test infrastructure (pytest, 30 real tests, all passing — `reports/TEST_REPORT.md`)
- [x] Status files (this set of documents)

## Phase 1 — 3D base prototype — `DONE_NOT_FULLY_TESTED` (partial)

Source requirement: FirstPersonController, input, camera, Bullet
collision, interaction raycast, door, drawer, item pickup/drop, basic
inventory, sound, settings, save, test room.

Done and tested this session:
- [x] `FirstPersonController` (Bullet capsule character controller: walk/run/crouch speeds, gravity, slope limit, mouse look)
- [x] `InputManager` (named actions, key rebinding hook, tested with `InputState.force`)
- [x] `InteractionSystem` (raycast against the real Bullet world, tag-based dispatch)
- [x] `SceneManager3D` (GLB loading + collision + socket extraction)
- [x] One generated test room (Blender Python, real geometry, real collision, real sockets)

Not yet done:
- [ ] Door open/close interaction (raycast can detect a tagged door; no door *behavior* implemented yet)
- [ ] Drawer interaction
- [ ] Inventory (pickup/drop) — `game/inventory/` is scaffolded (empty package) only
- [ ] Audio playback (no audio hardware in this dev container — see `docs/KNOWN_ISSUES.md`; code can still be written and unit-tested without hardware, just not done yet)
- [ ] Settings UI
- [ ] Save/load system — `game/saving/` scaffolded only
- [ ] `game/main.py` (there is no full game entrypoint yet, only `game/bootstrap/run_test_room.py`)

## Phase 2 — Training vertical slice — `NOT_STARTED`

## Phase 3 — First full case (Hollow Creek Residence) — `NOT_STARTED`

## Phase 4 — Multiplayer vertical slice — `NOT_STARTED`

## Phase 5 — Content production pipeline (8 maps / 20 entities / 32 equipment / 8 caravans / 8 vehicles) — `NOT_STARTED`

The Blender generation pipeline proven in Phase 0 (parametric room
generation, collision extraction, socket export, GLB validation) is
the foundation this phase will scale up, but none of the 8 named maps,
20 named entities, 32 named equipment items, 8 caravans, or 8 vehicles
have been modeled yet.

## Phase 6 — Progression and social systems — `NOT_STARTED`

## Phase 7 — Audio, localization, optimization, and build — `NOT_STARTED`

`scripts/build_windows.ps1` exists but deliberately exits with an
error — there is no Windows host in this development container to
produce or test a real `.exe` against (see
`reports/ENVIRONMENT_AUDIT.md`).

## Immediate next steps

1. Door/drawer interaction behavior + save/load, to close out Phase 1 honestly.
2. `game/main.py` as a real entrypoint (currently only a diagnostic bootstrap exists).
3. Expand the Blender pipeline from "one test room" to the real Hollow Creek Residence layout (Phase 3 prerequisite).
