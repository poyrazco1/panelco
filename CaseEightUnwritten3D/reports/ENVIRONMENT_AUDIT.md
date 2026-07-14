# Environment Audit — Phase 0

Date: 2026-07-14
Container: ephemeral Claude Code on the web session (`/home/user/panelco`, repo `poyrazco1/panelco`, branch `claude/case-eight-unwritten-3d-mi5vur`)

This report reflects commands actually executed in this session. Every
line below is either a direct terminal observation or an explicit
inference labeled as such. Nothing here is guessed.

## Summary table

| Check | Result | Status |
|---|---|---|
| OS / version | Ubuntu 24.04.4 LTS, kernel 6.18.5, x86_64 | OK |
| CPU / cores | Intel Xeon @ 2.30GHz, 4 cores | OK |
| RAM | 15 GiB total, ~14 GiB free at audit time | OK |
| Disk free | ~30 GB available on `/` (252 GB volume, 21% used) | OK |
| GPU | **None** — no `/dev/dri`, no PCI VGA/3D device | BLOCKED (no hardware GPU) |
| Display | No `$DISPLAY`; `Xvfb`/`xvfb-run` preinstalled and working | OK (software only) |
| OpenGL | Mesa `llvmpipe` software rasterizer, OpenGL core **4.5** via Xvfb | OK (software, verified) |
| Vulkan | No `vulkaninfo`, no Vulkan ICD checked | NOT VERIFIED |
| Python | 3.11.15 (system default `python3`); Blender uses system `python3.12` | OK |
| pip / venv | pip 24.0 → upgraded to 26.1.2 in venv; `venv` module OK | OK |
| Git | 2.43.0 | OK |
| Git LFS | not preinstalled → installed via apt (3.4.1) | OK (installed) |
| C/C++ build tools | gcc 13.3.0, cmake 3.28.3, make 4.3 | OK |
| Panda3D | not preinstalled → installed via pip (1.10.16) into venv | OK (installed, tested) |
| Panda3D window | Opens as real onscreen **and** offscreen buffer under Xvfb; fails without Xvfb (only `glxGraphicsPipe` available, needs a live X display) | OK (with Xvfb) |
| Panda3D Bullet | `panda3d.bullet` imports and runs; character controller + triangle-mesh collision verified against real geometry | OK (tested) |
| Blender | not preinstalled → installed via apt (4.0.2) | OK (installed) |
| Blender headless | `blender --background --python <script>` runs; initially failed (missing `numpy` in Blender's system-Python 3.12 environment used by the glTF exporter add-on) → fixed by `apt-get install python3-numpy` | OK (after fix, tested) |
| `.blend` / `.glb` production | Real `.blend` and `.glb` produced and validated (see below) | OK (tested) |
| FFmpeg | not preinstalled → installed via apt (6.1.1) | OK (installed, not yet used in a pipeline) |
| Node.js | preinstalled, v22.22.2 / npm 10.9.7 | OK (not used yet — no JS tooling needed so far) |
| SQLite | CLI not preinstalled → installed via apt (3.45.1); Python's built-in `sqlite3` module (3.45.1) already worked without it | OK |
| PostgreSQL | `psql` client 16.13 present; no live Postgres server checked/started (SQLite is the Phase 0 target per docs/MASTER_3D_SPEC.md §20) | NOT VERIFIED (not required yet) |
| Internet access | `curl https://pypi.org` → HTTP 200 via the environment's proxy; `pip install` and `apt-get install` both succeeded | OK |
| File write permission | Confirmed throughout (project tree, venv, generated assets) | OK |
| Subprocess execution | Confirmed (Blender, Bullet, pytest, uvicorn all launched as subprocesses) | OK |
| Port binding / localhost | Verified: raw socket bind succeeded; real `uvicorn` server bound `127.0.0.1:8778` and answered `GET /health`, `GET /`, `GET /docs` with HTTP 200 | OK |
| Windows build capability | **No Windows host in this container.** `scripts/build_windows.ps1` is written but intentionally exits with an error — it has not been run. | BLOCKED (no Windows environment available) |
| Steamworks SDK access | Not attempted — no SDK, no App ID, no Steam account configured in this environment | BLOCKED_BY_ENVIRONMENT |
| Microphone test | No audio hardware (`/dev/snd` does not exist in this container) | BLOCKED (no audio hardware) |
| Multiple game client instances | Not yet tested (Phase 0 scope was single-process verification); nothing observed that would prevent it | NOT VERIFIED |

## What was actually installed this session

Via `apt-get install -y`:
`blender` (4.0.2), `ffmpeg` (6.1.1), `git-lfs` (3.4.1), `sqlite3` (3.45.1),
`mesa-utils`, `libegl1`, `libopengl0`, `python3-numpy` (1.26.4, required by
Blender's bundled glTF exporter add-on, which runs under the *system*
Python 3.12, not the project's venv).

Via `pip install` into `.venv` (Python 3.11.15):
`panda3d` 1.10.16, `panda3d-gltf` 1.3.0 (module name is `gltf`, not
`panda3d_gltf`), `fastapi` 0.139.0, `uvicorn[standard]` 0.51.0,
`websockets` 16.1, `pydantic` 2.13.4, `sqlmodel` 0.0.39, `alembic`
1.18.5, `python-dotenv` 1.2.2, `toml` 0.10.2, `pytest` 9.1.1,
`pytest-asyncio` 1.4.0, `httpx` 0.28.1, `pygltflib` 1.16.5. Full pinned
list: `requirements.lock`.

## GPU / rendering reality check

There is no GPU device node (`/dev/dri` absent) and no `$DISPLAY`. All
3D rendering and all Blender headless exports in this container use
**Mesa's `llvmpipe` software OpenGL rasterizer** (verified up to OpenGL
4.5 core profile via `glxinfo` under `xvfb-run`). This is real,
correct rendering — screenshots in this report and in
`reports/TEST_REPORT.md` were produced by an actual render pipeline,
not synthesized — but it is **slow** compared to hardware acceleration
and is not representative of the performance a player's Windows
machine (with a real GPU) will see. Performance numbers from this
container must never be reported as representative shipping
performance (see docs/MASTER_3D_SPEC.md §23).

Panda3D's `window-type offscreen` still requires a live GLX display on
this system (the only registered pipe type is `glxGraphicsPipe`); it
does **not** work headlessly without `Xvfb`. Every Panda3D script in
this repo must therefore be run via `xvfb-run -a` in this container
(see `scripts/test_all.sh`, `scripts/run_game.sh`). This will not be
necessary on a real Windows machine with a GPU and a real display.

## Fixes applied during the audit (not silently worked around)

1. **Blender glTF export crashed** with
   `ModuleNotFoundError: No module named 'numpy'` the first time it
   ran, because Ubuntu's `blender` package uses the *system* Python
   3.12 (not this project's venv), and that system Python had no
   numpy. Fixed with `apt-get install -y python3-numpy`. Documented
   here rather than silently patched, per the project's decision log
   (`docs/DECISIONS.md`).
2. **`panda3d-gltf`'s importable module is `gltf`**, not
   `panda3d_gltf` — the PyPI/import name mismatch is real and is noted
   in `requirements.lock` usage and in `game/rendering/scene_manager.py`.

## Explicitly NOT verified / NOT available in this environment

- Windows build/packaging (no Windows host)
- Steamworks SDK / Steam friend invites (no SDK, no account)
- Real microphone input (no audio hardware in this container)
- Real GPU-accelerated rendering / real-world frame-rate numbers
- PostgreSQL as a live running server (client tool present, server not started — not required for Phase 0)
- Vulkan support
- Multi-client (2-4 real process) networking test — not yet built (see docs/STATUS.md)

These are reported as `BLOCKED_BY_ENVIRONMENT` or `NOT_STARTED` where
relevant, never as done.
