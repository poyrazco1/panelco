# Architecture

## Process boundaries

- **`game/`** — the Panda3D client. Owns rendering, local input,
  client-side prediction (future), and talks to `server/` over the
  network for anything authoritative.
- **`server/`** — the FastAPI backend. Owns lobby/session management,
  authentication (future), text chat, and (per
  `docs/MASTER_3D_SPEC.md`) will own the authoritative gameplay state
  for multiplayer sessions: economy, XP, equipment ownership, entity
  AI, Hunt, evidence, doors, death, mission outcome, and equipment
  recovery. A separate low-level real-time protocol for in-mission
  state (player positions, door states, etc. at network tick rate) is
  designed but **not yet implemented** — see `docs/STATUS.md`.
- **`tools/`** — offline content pipeline (Blender generators, asset
  validators, packaging). Never imported by `game/` or `server/` at
  runtime.

## Layering inside `game/`

```
game/core/          -- config, logging, PhysicsWorld (no rendering, no gameplay rules)
game/rendering/      -- SceneManager3D: GLB -> scene graph + Bullet collision + sockets
game/input/          -- InputManager: named actions, not raw key codes
game/player/         -- FirstPersonController: Bullet character controller
game/interaction/    -- InteractionSystem: raycast against Bullet world
game/{inventory,equipment,evidence,cases,narrator8,entities,hunt,
      hiding,phone,voice,networking,lobby,vehicles,caravans,economy,
      progression,saving,localization,accessibility,audio,ui}/
                      -- one system per gameplay domain (see docs/STATUS.md
                         for what's implemented vs. scaffolded-only)
game/diagnostics/     -- smoke tests / manual verification scripts
game/bootstrap/       -- entrypoints that wire the above together
```

Rule: gameplay logic never imports Panda3D rendering calls directly
where avoidable, and rendering code never encodes gameplay rules
(e.g. `SceneManager3D` knows how to turn a `_collision`-suffixed GLB
node into a Bullet body; it has no idea what a "door" or "evidence" is
— that's `game/interaction` and `game/evidence`'s job, dispatching on
the `interactable` tag string).

## Data flow: Blender -> GLB -> Panda3D (verified working, Phase 0)

1. `tools/blender/generate_test_room.py` runs under
   `blender --background --python`. It is deterministic Python (`bpy`),
   not manual Blender editing.
2. It exports `.glb` (runtime asset) *and* saves the source `.blend`
   (kept under `assets/source/blender/`, never deleted, per
   `docs/SOURCE_3D_CONVERSION_PROMPT.md` §18.1).
3. Convention: any object named `<name>_collision` becomes a static
   Bullet `BulletTriangleMeshShape` body when loaded; any Empty named
   `Socket_<Type>` becomes a tagged gameplay marker NodePath
   (`game/rendering/scene_manager.py`). glTF `extras` on the Blender
   Empty survive as Panda3D node tags (verified empirically).
4. `game.rendering.scene_manager.SceneManager3D.load_map()` loads the
   GLB via `gltf.load_model` (the `panda3d-gltf` package; note the
   importable module name is `gltf`, not `panda3d_gltf`), builds
   collision bodies, and exposes `LoadedMap.sockets_of_type(...)`.
5. `tools/validators/validate_glb.py` checks every `.glb` before it's
   trusted: parses, checks for missing materials, NaN/degenerate
   transforms, presence of `_collision` and `Socket_` nodes.

This pipeline is exercised end-to-end by
`tests/integration/test_blender_pipeline.py` (spawns a real `blender`
subprocess),
`tests/integration/test_scene_manager.py`, and
`tests/integration/test_first_person_controller.py` — not mocked.

## Physics

`game/core/physics_world.py` wraps a single `panda3d.bullet.BulletWorld`
per running game instance. Two load-bearing facts discovered
empirically this session (see `docs/DECISIONS.md` for the debugging
narrative):

- A `BulletCharacterControllerNode`'s `NodePath` origin coincides with
  the **capsule shape's own center**, not the character's feet. Camera
  eye-height offsets must subtract `capsule_height / 2` from the
  configured eye height (measured from the feet) or the camera ends up
  roughly half a body-height too tall.
- glTF nodes named `<name>_collision` export as a transform-only
  `PandaNode` wrapping a differently-named `GeomNode` child (the
  Blender mesh-data name, e.g. `Cube.016`) — collision-mesh extraction
  must search descendants for `GeomNode`s, not assume the named node
  itself is one.

## Networking (designed, not yet implemented beyond the FastAPI skeleton)

- FastAPI HTTP/WebSocket (`server/app`) — lobby, auth, chat, management.
  A working `/health` endpoint is live as of Phase 0 (see
  `reports/TEST_REPORT.md`).
- Real-time in-mission protocol — planned as a versioned, sequenced,
  rate-limited Pydantic-schema-validated protocol per
  `docs/SOURCE_3D_CONVERSION_PROMPT.md` §15. **Not started.**

## Configuration

Layered TOML: `config/default.toml` merged with
`config/{development,production}.toml` selected by `CASE_EIGHT_ENV`
(default `development`). No tunable gameplay numbers are hardcoded in
Python — see `game/core/config.py`.
