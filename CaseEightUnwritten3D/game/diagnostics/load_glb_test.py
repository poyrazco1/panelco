"""Real GLB -> Panda3D load verification.

Loads a glTF/GLB file produced by tools/blender/generate_test_room.py
into an actual Panda3D scene graph using panda3d-gltf, counts the real
geometry/socket nodes that came through, positions a camera to look at
the room, and saves a screenshot as proof.

Usage:
    xvfb-run -a python game/diagnostics/load_glb_test.py \
        --glb assets/models/maps/test_room/test_room.glb
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from panda3d.core import (
    AmbientLight,
    DirectionalLight,
    Filename,
    Vec4,
    load_prc_file_data,
)

PROJECT_ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--glb", default="assets/models/maps/test_room/test_room.glb")
    parser.add_argument(
        "--screenshot",
        default="reports/glb_load_test.png",
    )
    args = parser.parse_args()

    glb_path = PROJECT_ROOT / args.glb
    screenshot_path = PROJECT_ROOT / args.screenshot

    if not glb_path.exists():
        print(f"GLB LOAD TEST FAILED: file does not exist: {glb_path}", file=sys.stderr)
        return 1

    load_prc_file_data("", "window-type offscreen")
    load_prc_file_data("", "win-size 960 540")
    load_prc_file_data("", "audio-library-name null")
    load_prc_file_data("", "notify-level warning")

    from direct.showbase.ShowBase import ShowBase
    import gltf

    app = ShowBase()

    if app.win is None:
        print("GLB LOAD TEST FAILED: no graphics buffer/window created", file=sys.stderr)
        app.destroy()
        return 1

    try:
        model_root = gltf.load_model(Filename.fromOsSpecific(str(glb_path)))
    except Exception as exc:  # noqa: BLE001
        print(f"GLB LOAD TEST FAILED: gltf.load_model raised {exc!r}", file=sys.stderr)
        app.destroy()
        return 1

    if model_root is None:
        print("GLB LOAD TEST FAILED: gltf.load_model returned None", file=sys.stderr)
        app.destroy()
        return 1

    room_np = app.render.attach_new_node(model_root)

    all_children = room_np.find_all_matches("**")
    mesh_nodes = [n for n in all_children if not n.name.startswith("Socket_") and n.node().is_geom_node()]
    socket_nodes = [n for n in all_children if n.name.startswith("Socket_")]
    collision_nodes = [n for n in all_children if n.name.endswith("_collision")]

    bounds = room_np.get_tight_bounds()

    dlight = DirectionalLight("dlight")
    dlight.setColor(Vec4(1.0, 0.97, 0.9, 1))
    dlnp = app.render.attach_new_node(dlight)
    dlnp.set_hpr(35, -55, 0)
    app.render.set_light(dlnp)

    alight = AmbientLight("alight")
    alight.setColor(Vec4(0.3, 0.3, 0.35, 1))
    alnp = app.render.attach_new_node(alight)
    app.render.set_light(alnp)

    app.disable_mouse()
    app.camera.set_pos(3.5, -6.0, 2.2)
    app.camera.look_at(0, 0, 1.2)

    for _ in range(10):
        app.task_mgr.step()

    screenshot_path.parent.mkdir(parents=True, exist_ok=True)
    written = app.win.save_screenshot(Filename.fromOsSpecific(str(screenshot_path)))

    print("GLB LOAD TEST RESULT:")
    print(f"  glb_path: {glb_path}")
    print(f"  geometry_nodes_found: {len(mesh_nodes)}")
    print(f"  collision_nodes_found: {len(collision_nodes)}")
    print(f"  socket_nodes_found: {len(socket_nodes)}")
    print(f"  socket_names: {[n.name for n in socket_nodes]}")
    print(f"  bounds: {bounds}")
    print(f"  screenshot_written: {bool(written) and screenshot_path.exists()}")

    app.destroy()

    ok = len(mesh_nodes) > 0 and len(socket_nodes) >= 6 and screenshot_path.exists()
    if not ok:
        print("GLB LOAD TEST FAILED: expected geometry/socket counts not met", file=sys.stderr)
        return 1

    print("GLB LOAD TEST PASSED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
