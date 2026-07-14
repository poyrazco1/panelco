# Decisions and debugging log

Append-only. Each entry is a real decision or a real bug found and
fixed during development, kept so future sessions don't rediscover the
same thing from scratch.

## 2026-07-14 — Phase 0 / Phase 1 session

### Toolchain choices

- **Blender 4.0.2 via `apt`**, not a newer manually-downloaded build.
  This container's `apt` repo only offers 4.0.2; that's what's
  installed and tested. If a newer Blender is needed later, install it
  explicitly and re-verify the whole `tools/blender/` pipeline (the
  glTF exporter add-on's dependencies, in particular, are version- and
  system-Python-specific — see below).
- **`panda3d-gltf`'s import name is `gltf`**, not `panda3d_gltf` (the
  PyPI package name). Documented in `game/rendering/scene_manager.py`
  and `requirements.lock` usage notes so this isn't rediscovered by
  trial and error again.
- **SQLite for Phase 0/1**, PostgreSQL deferred. Matches
  `docs/SOURCE_3D_CONVERSION_PROMPT.md` §20 ("basitleştirilmiş yerel
  sürümde SQLite").
- **Jump disabled by default** (`player.jump_speed = 0.0` in
  `config/default.toml`) — per source doc §7.1, this is a horror
  investigation game with no stated jump requirement, and
  `docs/SOURCE_3D_CONVERSION_PROMPT.md` §7 explicitly calls jumping
  optional/skippable.

### Real bugs found and fixed this session (not silently patched — recorded here)

1. **Blender's system Python had no numpy.** `blender --background
   --python tools/blender/generate_test_room.py` failed with
   `ModuleNotFoundError: No module named 'numpy'` inside the bundled
   `io_scene_gltf2` add-on. Root cause: the Ubuntu `blender` package
   uses the *system* `python3.12`, completely separate from this
   project's `.venv` (Python 3.11). Fix: `apt-get install -y
   python3-numpy`.

2. **Box scale bug in `generate_test_room.py`.** `add_box()` called
   `bpy.ops.mesh.primitive_cube_add(size=1.0)` (which already produces
   a full 1.0m cube, vertices at ±0.5) and then set
   `obj.scale = (size.x / 2.0, ...)`, silently halving every dimension
   in the room. Found by a minimal repro (a Bullet character falling
   onto a hand-built floor worked; falling onto the *GLB-loaded* floor
   didn't) and confirmed by comparing the collision mesh's actual
   bounding-sphere radius (1.4151) against the expected radius for the
   intended floor size (2.828 — exactly double). Fixed by setting
   `obj.scale = (size.x, size.y, size.z)` directly.

3. **Collision-node hierarchy misunderstanding in `scene_manager.py`.**
   A GLB node named `Floor_collision` is a plain transform `PandaNode`
   wrapper; the actual `GeomNode` with real geometry is a *child* with
   a different name (the Blender mesh-data name, e.g. `Cube.016`).
   `_build_collision()` originally checked `np.node().is_geom_node()`
   on the `_collision`-named node itself and found nothing (0
   collision bodies built, and the character fell through the floor).
   Fixed by searching `_collision`-named nodes' descendants for
   `GeomNode`s.

4. **`BulletTriangleMesh.add_geom()` argument type.** Passed a raw
   `LMatrix4f` (from `NodePath.get_net_transform().get_mat()`) where
   the API wants a `TransformState`. Fixed by passing
   `get_net_transform()` directly (it already returns a
   `TransformState`).

5. **Eye-height double-counting.** `BulletCharacterControllerNode`'s
   `NodePath` origin coincides with the **capsule shape's center**, not
   the character's feet (confirmed empirically: a character resting on
   a floor at z=0 settles with `node_path.z ≈ capsule_height / 2`, not
   0). The first version of `FirstPersonController` set the camera's
   local Z directly to `eye_height_standing` (measured from the feet),
   producing a camera roughly `capsule_height / 2` too high (~2.45m
   total instead of a realistic ~1.58m). Fixed by subtracting
   `capsule_height / 2` from the configured eye heights before using
   them as the camera's local offset.

6. **Demo/test bugs, not engine bugs**, found while writing
   `game/bootstrap/run_test_room.py`:
   - Spawning the character with its origin *at* floor level embeds
     the capsule inside the floor mesh (its center should rest ~0.84m
     above a floor at z=0 for this capsule size), causing an
     unpredictable Bullet depenetration response. Fixed by spawning
     ~0.9m above the target socket and adding a short physics-settle
     phase before measuring anything.
   - A level (non-pitched) raycast from standing eye height
     (~1.58m) will never intersect a tabletop at ~0.75m — this is
     correct physical behavior, not a bug, and mirrors how a real
     player has to look down to interact with a low table. Fixed the
     test to pitch the camera down before the raycast, instead of
     "fixing" the geometry to make a level ray work.

### Why these are recorded in this much detail

The project's binding instructions explicitly forbid presenting
untested or fabricated results as working. Every one of the six items
above was a genuine failure observed in this container, not a
hypothetical — this log exists so nobody (human or AI) re-derives them
from scratch or, worse, assumes they were never bugs at all.
