# Placeholder Report

Date: 2026-07-14

Per `docs/SOURCE_3D_CONVERSION_PROMPT.md` §2, temporary/placeholder
geometry is allowed during development but must be explicitly flagged,
never presented as final content, and counted here.

## Placeholder content currently in the repository

| Item | Location | Why it's a placeholder | Replace by |
|---|---|---|---|
| "Test room" | `assets/models/maps/test_room/`, `tools/blender/generate_test_room.py` | A generic 4x4m box room with a door and a table — proves the Blender→GLB→Panda3D→Bullet pipeline works end-to-end, but is not any of the 8 named maps from the source design and was never intended to be. | Phase 3+ (real Hollow Creek Residence and the other 7 named maps) |
| Materials on the test room | Same generator, `M_TestRoom_*` materials | Flat Principled BSDF colors, no textures, no PBR maps (roughness/normal/AO). Sufficient to prove lighting and collision work; not representative final art quality (`docs/SOURCE_3D_CONVERSION_PROMPT.md` §2's "stylize gerçekçi" bar is not met). | Phase 5 content pipeline |

## Zero-count check (per the source design's exact-count requirements)

These are reported honestly as zero, not omitted:

- Named maps modeled: **0 / 8**
- Named paranormal entities modeled: **0 / 20**
- Named equipment items modeled: **0 / 32**
- Named research caravans modeled: **0 / 8**
- Named personal vehicles modeled: **0 / 8**
- Character models/rigs: **0**

## What is NOT a placeholder (real, finished-for-its-scope content)

- The test room's **collision geometry** — real Bullet triangle-mesh
  shapes generated from the actual visual geometry, not a simplified
  stand-in.
- The test room's **gameplay sockets** (`Socket_PlayerSpawn`,
  `Socket_EvidenceSocket_01`, `Socket_HidingSpot_01`,
  `Socket_EntityNav_01`, `Socket_LightPoint_01`,
  `Socket_DoorwayExit`) — real, tagged, loaded via real glTF extras,
  not stubbed.
- `game/core`, `game/rendering`, `game/input`, `game/player`,
  `game/interaction` — real, tested game logic, not scaffolding.
- `server/app/main.py`'s `/health` endpoint — real, tested.

## Minor cosmetic issue (not a placeholder, but related)

Collision-proxy meshes in the test room GLB are exported without a
material (see `docs/KNOWN_ISSUES.md`), which is why Panda3D logs
`Warning: mesh Cube.0NN has a primitive with no material` on load.
This is harmless (those meshes are hidden immediately after collision
extraction) and is not itself a placeholder — it's a minor export
hygiene issue to clean up in Phase 5.
