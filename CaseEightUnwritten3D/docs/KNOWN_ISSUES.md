# Known Issues

Status tags: `BLOCKED_BY_ENVIRONMENT`, `IMPLEMENTED_WITH_PLACEHOLDER`,
`NOT_STARTED`, `MINOR`.

## Environment-blocked (cannot be fixed from inside this container)

- **No GPU, no display hardware.** All rendering in this container
  uses Mesa's `llvmpipe` software rasterizer under `Xvfb`. Functionally
  correct (real geometry, real lighting, real screenshots), but not
  representative of real-world performance. Never report FPS numbers
  measured here as shipping performance. — `BLOCKED_BY_ENVIRONMENT`
- **No audio hardware** (`/dev/snd` absent). Panda3D is configured with
  `audio-library-name null`. No audio playback has been implemented or
  tested; it can be written and unit-tested for logic (e.g. volume
  math) without hardware, but actual sound output cannot be verified
  here. — `BLOCKED_BY_ENVIRONMENT`
- **No Windows host.** `scripts/build_windows.ps1` is a deliberate
  stub that exits with an error rather than claim a build exists.
  `scripts/setup.ps1`, `run_game.ps1`, `run_server.ps1`, `test_all.ps1`
  are written (mirroring the verified Linux/`.sh` scripts) but
  unverified — see the "NOTE (honesty flag)" comment at the top of
  each. — `BLOCKED_BY_ENVIRONMENT`
- **No Steamworks SDK / App ID / account.** No Steam integration
  attempted; `PlatformService` abstraction referenced in
  `docs/SOURCE_3D_CONVERSION_PROMPT.md` §24 is not yet even
  scaffolded. — `BLOCKED_BY_ENVIRONMENT` + `NOT_STARTED`
- **Multi-process multiplayer test (2-4 real clients)** — not
  attempted; there is currently no real-time network protocol to test
  (only a single FastAPI health endpoint exists). —
  `NOT_STARTED`

## Implementation gaps (not environment-blocked, just not built yet)

- `game/inventory/`, `game/saving/`, `game/equipment/`,
  `game/evidence/`, `game/hunt/`, `game/hiding/`, `game/entities/`,
  `game/narrator8/`, `game/phone/`, `game/voice/`, `game/networking/`,
  `game/lobby/`, `game/vehicles/`, `game/caravans/`, `game/economy/`,
  `game/progression/`, `game/localization/`, `game/accessibility/`,
  `game/audio/`, `game/ui/`, `game/characters/`, `game/shaders/` are
  all empty packages (directory + `__init__.py` only). —
  `NOT_STARTED`
- No door/drawer open-close behavior yet: `InteractionSystem` can
  detect a tagged interactable and report its `kind`, but nothing
  consumes that to actually open a door. — `NOT_STARTED`
- No `game/main.py`. The only real entrypoint is
  `game/bootstrap/run_test_room.py`, a diagnostic/acceptance demo, not
  a menu-to-mission game loop. — `NOT_STARTED`

## Infrastructure

- **Git LFS push is blocked in this environment.** `git lfs install
  --local` and `git lfs track` work fine, but `git push` fails with
  `403 Forbidden` from this container's outbound proxy when uploading
  LFS objects to GitHub. Verified this session. Binary assets are
  currently committed as regular git blobs instead (all small so far —
  see `.gitattributes` for the full explanation). Re-enable LFS only
  after confirming a push actually succeeds in whatever environment
  does it. — `BLOCKED_BY_ENVIRONMENT`

## Minor / cosmetic

- `tools/blender/generate_test_room.py` exports collision-proxy
  meshes (`*_collision`) in the same GLB as the visual meshes, and
  they currently have no material assigned, producing
  `Warning: mesh Cube.0NN has a primitive with no material` from
  Panda3D on load. Harmless (the collision meshes are hidden after
  load in `SceneManager3D._build_collision`), but a real production
  pipeline should export collision as a separate pass/file to avoid
  bloating the visual GLB and the warning noise. — `MINOR`
- `panda3d-gltf`'s import name (`gltf`) does not match its PyPI
  package name (`panda3d-gltf`) — easy to trip over; documented in
  `docs/DECISIONS.md` and inline comments. — `MINOR`, documented not
  "fixed" (it's a third-party package's own naming).
