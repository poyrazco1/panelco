# Test Report — Phase 0 / Phase 1

Date: 2026-07-14
Environment: this Linux dev container (no GPU/display — see
`reports/ENVIRONMENT_AUDIT.md`); all Panda3D-dependent tests run under
`xvfb-run` (`scripts/test_all.sh`).

## Result: 30 / 30 tests passed

```
$ ./scripts/test_all.sh
============================= test session starts ==============================
platform linux -- Python 3.11.15, pytest-9.1.1, pluggy-1.6.0
collected 30 items

tests/assets/test_glb_validation.py::test_test_room_glb_exists PASSED
tests/assets/test_glb_validation.py::test_test_room_glb_is_valid PASSED
tests/assets/test_glb_validation.py::test_missing_file_is_reported_not_faked PASSED
tests/assets/test_glb_validation.py::test_empty_file_is_rejected PASSED
tests/integration/test_blender_pipeline.py::test_generator_script_produces_valid_glb PASSED
tests/integration/test_first_person_controller.py::test_character_settles_onto_floor PASSED
tests/integration/test_first_person_controller.py::test_wall_collision_stops_forward_movement PASSED
tests/integration/test_first_person_controller.py::test_interaction_raycast_hits_tagged_collision_body PASSED
tests/integration/test_first_person_controller.py::test_interaction_raycast_returns_none_kind_for_untagged_wall PASSED
tests/integration/test_scene_manager.py::test_load_map_builds_real_bullet_bodies PASSED
tests/integration/test_scene_manager.py::test_load_map_exposes_typed_sockets PASSED
tests/integration/test_scene_manager.py::test_missing_glb_raises_not_faked PASSED
tests/unit/test_config.py::test_default_config_loads PASSED
tests/unit/test_config.py::test_development_env_overrides_merge_on_top_of_default PASSED
tests/unit/test_config.py::test_production_env_overrides_fullscreen PASSED
tests/unit/test_config.py::test_missing_key_returns_default PASSED
tests/unit/test_config.py::test_jump_disabled_by_design PASSED
tests/unit/test_first_person_controller_config.py::test_config_derives_from_game_config PASSED
tests/unit/test_first_person_controller_config.py::test_run_speed_is_faster_than_walk_and_crouch PASSED
tests/unit/test_first_person_controller_config.py::test_capsule_dimensions_are_positive PASSED
tests/unit/test_input_manager.py::test_no_input_gives_zero_vector PASSED
tests/unit/test_input_manager.py::test_forward_press_gives_positive_y PASSED
tests/unit/test_input_manager.py::test_diagonal_movement_is_normalized PASSED
tests/unit/test_input_manager.py::test_opposing_keys_cancel_out PASSED
tests/unit/test_physics_world.py::test_physics_world_applies_configured_gravity PASSED
tests/unit/test_physics_world.py::test_physics_world_step_advances_simulation PASSED
tests/unit/test_physics_world.py::test_falling_rigid_body_accelerates_downward PASSED
server/tests/test_health.py::test_health_endpoint_returns_ok PASSED
server/tests/test_health.py::test_root_endpoint PASSED
server/tests/test_health.py::test_openapi_docs_available PASSED

30 passed, 1 warning in ~1.3s
```

(The one warning is a third-party `StarletteDeprecationWarning` about
`httpx` vs. a future `httpx2` package in FastAPI's `TestClient` —
harmless, not a test failure.)

## What these tests actually verify (not just "pass")

- **`test_generator_script_produces_valid_glb`** spawns a real
  `blender --background` subprocess, regenerates the test room from
  scratch into a temp directory, and validates the output — this is
  the full Blender→GLB pipeline, not a cached fixture.
- **`test_wall_collision_stops_forward_movement`** runs 240 real
  physics steps (4 simulated seconds) of a character running toward a
  wall and asserts the *actual* Bullet collision response keeps it
  inside the room (`y < 2.2`) instead of the ~20m it would travel
  uncollided at the configured run speed.
- **`test_interaction_raycast_hits_tagged_collision_body`** fires a
  real Bullet raycast from a real camera transform and asserts it
  resolves to the tagged table body — this test only started passing
  after fixing three separate real bugs (see `docs/DECISIONS.md`).
- **`server/tests/test_health.py`** exercises the real FastAPI app
  object (via `TestClient`, which drives the real ASGI app) — this was
  additionally verified with a real `uvicorn` process bound to
  `127.0.0.1:8778` answering real `curl` requests (see
  `reports/ENVIRONMENT_AUDIT.md`).

## Manual (non-pytest) verification also performed this session

| Check | Command | Result |
|---|---|---|
| Panda3D onscreen window | `xvfb-run -a python game/diagnostics/panda3d_smoke_test.py --mode onscreen` | PASSED, screenshot saved to `reports/panda3d_smoke_onscreen.png` |
| Panda3D offscreen buffer | `xvfb-run -a python game/diagnostics/panda3d_smoke_test.py --mode offscreen` | PASSED, screenshot saved to `reports/panda3d_smoke_offscreen.png` |
| GLB loads into Panda3D | `xvfb-run -a python game/diagnostics/load_glb_test.py` | PASSED, 24 geometry nodes, 6 sockets, screenshot at `reports/glb_load_test.png` |
| Full FPS-controller + collision + interaction demo | `./scripts/run_game.sh` (`game/bootstrap/run_test_room.py`) | PASSED, screenshot at `reports/fps_controller_test.png` |
| FastAPI real HTTP round-trip | live `uvicorn` + `curl` to `/health`, `/`, `/docs` | All HTTP 200 |

## What was NOT tested (explicitly)

- Multiplayer (2+ real client processes) — no networking protocol
  exists yet beyond the single health endpoint.
- Any content beyond the one generic test room (no named maps,
  entities, equipment, caravans, or vehicles exist yet).
- Windows build — no Windows host available.
- Audio — no audio hardware available.
- Real-GPU performance/frame-rate numbers — this container has no GPU.
