"""Blender Python (bpy) generator for a real 3D test room.

This is the first working link in the asset pipeline described in
docs/MASTER_3D_SPEC.md: architecture -> Blender Python -> GLB -> Panda3D.
It is deliberately simple (a single rectangular room with a door
opening) but every piece of geometry, the UVs, the materials, and the
exported socket points are real -- nothing here is a stub.

Run headless:
    blender --background --python tools/blender/generate_test_room.py \
        -- --seed 8 --out assets/models/maps/test_room/test_room.glb

The trailing "--" is required by Blender so argv after it is passed to
this script instead of being parsed by Blender itself.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

import bpy
import bmesh
from mathutils import Vector

PROJECT_ROOT = Path(__file__).resolve().parents[2]


def parse_args() -> argparse.Namespace:
    argv = sys.argv
    if "--" in argv:
        argv = argv[argv.index("--") + 1 :]
    else:
        argv = []
    parser = argparse.ArgumentParser()
    parser.add_argument("--seed", type=int, default=8)
    parser.add_argument(
        "--out",
        type=str,
        default="assets/models/maps/test_room/test_room.glb",
    )
    parser.add_argument(
        "--blend-out",
        type=str,
        default="assets/source/blender/test_room.blend",
    )
    parser.add_argument("--width", type=float, default=4.0, help="room X size in meters")
    parser.add_argument("--depth", type=float, default=4.0, help="room Y size in meters")
    parser.add_argument("--height", type=float, default=2.7, help="room Z size (ceiling) in meters")
    return parser.parse_args(argv)


def clear_scene() -> None:
    bpy.ops.object.select_all(action="SELECT")
    bpy.ops.object.delete(use_global=False)
    for block_collection in (bpy.data.meshes, bpy.data.materials, bpy.data.images):
        for block in list(block_collection):
            if block.users == 0:
                block_collection.remove(block)


def make_material(name: str, base_color, roughness: float) -> bpy.types.Material:
    mat = bpy.data.materials.get(name) or bpy.data.materials.new(name)
    mat.use_nodes = True
    bsdf = mat.node_tree.nodes.get("Principled BSDF")
    if bsdf is not None:
        bsdf.inputs["Base Color"].default_value = (*base_color, 1.0)
        bsdf.inputs["Roughness"].default_value = roughness
    return mat


def add_box(name: str, size: Vector, location: Vector, material: bpy.types.Material) -> bpy.types.Object:
    """Add a UV-mapped box mesh of the given world-space size at location (center)."""
    # primitive_cube_add(size=1.0) already produces a full 1.0m cube
    # (vertices at +/-0.5 on every axis), so the scale factor equals the
    # desired dimension directly -- do NOT halve it again here.
    bpy.ops.mesh.primitive_cube_add(size=1.0, location=location)
    obj = bpy.context.active_object
    obj.name = name
    obj.scale = (size.x, size.y, size.z)
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)

    bpy.ops.object.mode_set(mode="EDIT")
    bm = bmesh.from_edit_mesh(obj.data)
    bm.faces.ensure_lookup_table()
    bpy.ops.uv.smart_project(angle_limit=66.0, island_margin=0.02)
    bmesh.update_edit_mesh(obj.data)
    bpy.ops.object.mode_set(mode="OBJECT")

    obj.data.materials.append(material)
    return obj


def add_empty(name: str, location: Vector, empty_type: str = "PLAIN_AXES", extras: dict | None = None) -> bpy.types.Object:
    bpy.ops.object.empty_add(type=empty_type, location=location)
    obj = bpy.context.active_object
    obj.name = name
    obj.empty_display_size = 0.3
    if extras:
        for key, value in extras.items():
            obj[key] = value
    return obj


def build_room(args: argparse.Namespace) -> None:
    w, d, h = args.width, args.depth, args.height
    wall_t = 0.2
    door_w, door_h = 1.0, 2.1

    clear_scene()

    floor_mat = make_material("M_TestRoom_Floor", (0.28, 0.24, 0.20), 0.75)
    wall_mat = make_material("M_TestRoom_Wall", (0.55, 0.52, 0.48), 0.85)
    ceiling_mat = make_material("M_TestRoom_Ceiling", (0.20, 0.20, 0.22), 0.9)
    door_mat = make_material("M_TestRoom_DoorFrame", (0.15, 0.10, 0.08), 0.6)

    root = bpy.data.objects.new("TestRoom_Hollow_Creek_Style", None)
    bpy.context.collection.objects.link(root)

    def parent(obj: bpy.types.Object) -> bpy.types.Object:
        obj.parent = root
        return obj

    # Floor and ceiling
    parent(add_box("Floor", Vector((w, d, wall_t)), Vector((0, 0, -wall_t / 2)), floor_mat))
    parent(add_box("Ceiling", Vector((w, d, wall_t)), Vector((0, 0, h + wall_t / 2)), ceiling_mat))

    # North / East / West walls: single solid segments
    parent(add_box("Wall_North", Vector((w + wall_t * 2, wall_t, h)), Vector((0, d / 2 + wall_t / 2, h / 2)), wall_mat))
    parent(add_box("Wall_East", Vector((wall_t, d, h)), Vector((w / 2 + wall_t / 2, 0, h / 2)), wall_mat))
    parent(add_box("Wall_West", Vector((wall_t, d, h)), Vector((-(w / 2 + wall_t / 2), 0, h / 2)), wall_mat))

    # South wall with a door opening in the middle: left segment, right segment, header above door
    south_y = -(d / 2 + wall_t / 2)
    left_w = (w - door_w) / 2 + wall_t
    right_w = left_w
    left_x = -door_w / 2 - left_w / 2
    right_x = door_w / 2 + right_w / 2
    parent(add_box("Wall_South_Left", Vector((left_w, wall_t, h)), Vector((left_x, south_y, h / 2)), wall_mat))
    parent(add_box("Wall_South_Right", Vector((right_w, wall_t, h)), Vector((right_x, south_y, h / 2)), wall_mat))
    header_h = h - door_h
    parent(
        add_box(
            "Wall_South_Header",
            Vector((door_w, wall_t, header_h)),
            Vector((0, south_y, door_h + header_h / 2)),
            wall_mat,
        )
    )

    # Door frame trim (visual detail around the opening, real geometry not a placeholder)
    frame_t = 0.06
    parent(add_box("DoorFrame_Left", Vector((frame_t, wall_t * 1.4, door_h)), Vector((-door_w / 2, south_y, door_h / 2)), door_mat))
    parent(add_box("DoorFrame_Right", Vector((frame_t, wall_t * 1.4, door_h)), Vector((door_w / 2, south_y, door_h / 2)), door_mat))
    parent(add_box("DoorFrame_Top", Vector((door_w + frame_t * 2, wall_t * 1.4, frame_t)), Vector((0, south_y, door_h)), door_mat))

    # A simple piece of furniture so the room isn't a totally empty box
    table_mat = make_material("M_TestRoom_Table", (0.32, 0.20, 0.12), 0.65)
    parent(add_box("Table", Vector((1.2, 0.6, 0.05)), Vector((0, -1.2, 0.75)), table_mat))
    for lx, ly in ((-0.5, -1.45), (0.5, -1.45), (-0.5, -0.95), (0.5, -0.95)):
        parent(add_box(f"TableLeg_{lx}_{ly}", Vector((0.05, 0.05, 0.75)), Vector((lx, ly, 0.375)), table_mat))

    # Collision proxy collection: same geometry, separate objects so the
    # exporter / Panda3D loader can distinguish visual vs. collision meshes
    # by name convention ("_collision" suffix), per docs/MASTER_3D_SPEC.md.
    collision_names = [
        "Floor", "Ceiling", "Wall_North", "Wall_East", "Wall_West",
        "Wall_South_Left", "Wall_South_Right", "Wall_South_Header",
        "Table",
    ]
    for name in collision_names:
        src = bpy.data.objects[name]
        dup = src.copy()
        dup.data = src.data.copy()
        dup.name = f"{name}_collision"
        dup.data.materials.clear()
        dup["px_collision"] = True
        bpy.context.collection.objects.link(dup)
        dup.parent = root
        dup.hide_render = True

    # Socket / gameplay marker points, exported as named Empties per
    # docs/MASTER_3D_SPEC.md section 8 (evidence sockets, hiding spots,
    # entity nav points, spawn points).
    parent(add_empty("Socket_PlayerSpawn", Vector((0, 1.2, 0.0)), extras={"socket_type": "player_spawn"}))
    parent(add_empty("Socket_EvidenceSocket_01", Vector((0, -1.2, 0.8)), extras={"socket_type": "evidence_socket", "evidence_id": "TEST_EVIDENCE_01"}))
    parent(add_empty("Socket_HidingSpot_01", Vector((w / 2 - 0.4, d / 2 - 0.4, 0.0)), extras={"socket_type": "hiding_spot", "hiding_spot_kind": "closet"}))
    parent(add_empty("Socket_EntityNav_01", Vector((-w / 2 + 0.4, -d / 2 + 0.4, 0.0)), extras={"socket_type": "entity_nav"}))
    parent(add_empty("Socket_LightPoint_01", Vector((0, 0, h - 0.3)), empty_type="SPHERE", extras={"socket_type": "light_point", "light_kind": "ceiling"}))
    parent(add_empty("Socket_DoorwayExit", Vector((0, south_y, 0.0)), extras={"socket_type": "doorway_exit"}))

    bpy.context.view_layer.update()


def export_glb(out_path: Path) -> None:
    out_path.parent.mkdir(parents=True, exist_ok=True)
    bpy.ops.object.select_all(action="SELECT")
    bpy.ops.export_scene.gltf(
        filepath=str(out_path),
        export_format="GLB",
        use_selection=False,
        export_apply=True,
        export_yup=True,
        export_extras=True,
        export_cameras=False,
        export_lights=False,
    )


def save_blend(blend_path: Path) -> None:
    blend_path.parent.mkdir(parents=True, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=str(blend_path))


def main() -> None:
    args = parse_args()
    build_room(args)

    out_path = PROJECT_ROOT / args.out
    blend_path = PROJECT_ROOT / args.blend_out

    export_glb(out_path)
    save_blend(blend_path)

    mesh_objs = [o for o in bpy.data.objects if o.type == "MESH"]
    empty_objs = [o for o in bpy.data.objects if o.type == "EMPTY"]
    print("BLENDER_GENERATION_RESULT:")
    print(f"  mesh_objects: {len(mesh_objs)}")
    print(f"  empty_sockets: {len(empty_objs)}")
    print(f"  materials: {len(bpy.data.materials)}")
    print(f"  glb_out: {out_path}")
    print(f"  glb_exists: {out_path.exists()}")
    print(f"  glb_size_bytes: {out_path.stat().st_size if out_path.exists() else 0}")
    print(f"  blend_out: {blend_path}")
    print(f"  blend_exists: {blend_path.exists()}")


if __name__ == "__main__":
    main()
