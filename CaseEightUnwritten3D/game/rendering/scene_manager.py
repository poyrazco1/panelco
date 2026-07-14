"""Loads GLB maps into the Panda3D scene graph and builds real Bullet
collision + gameplay socket data from them.

Convention produced by tools/blender/generate_test_room.py (and meant
to be followed by every future map/prop generator in the pipeline):

- Any mesh node named "<name>_collision" becomes a static Bullet
  triangle-mesh collider and is hidden from the visible render.
- Any Empty node named "Socket_<Something>" carries a "socket_type"
  tag (from glTF extras) and is exposed as a gameplay socket, per
  docs/MASTER_3D_SPEC.md section 8 (evidence sockets, hiding spots,
  entity nav points, spawn points, light points).
"""
from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path

import gltf
from panda3d.bullet import BulletRigidBodyNode, BulletTriangleMesh, BulletTriangleMeshShape
from panda3d.core import Filename, NodePath

from game.core.logging_setup import get_logger
from game.core.physics_world import PhysicsWorld

logger = get_logger("rendering.scene_manager")


@dataclass
class LoadedMap:
    root: NodePath
    collision_bodies: list[NodePath] = field(default_factory=list)
    sockets: dict[str, NodePath] = field(default_factory=dict)

    def sockets_of_type(self, socket_type: str) -> list[NodePath]:
        return [np for np in self.sockets.values() if np.get_tag("socket_type") == socket_type]


class SceneManager3D:
    """AssetManager-facing helper for loading a map GLB into the world."""

    def __init__(self, physics_world: PhysicsWorld) -> None:
        self._physics_world = physics_world

    def load_map(self, glb_path: Path, parent: NodePath, name: str | None = None) -> LoadedMap:
        glb_path = Path(glb_path)
        if not glb_path.exists():
            raise FileNotFoundError(f"Map GLB not found: {glb_path}")

        model_root = gltf.load_model(Filename.from_os_specific(str(glb_path)))
        if model_root is None:
            raise RuntimeError(f"gltf.load_model returned None for {glb_path}")

        root = parent.attach_new_node(model_root)
        if name:
            root.set_name(name)

        loaded = LoadedMap(root=root)
        self._build_collision(root, loaded)
        self._collect_sockets(root, loaded)

        logger.info(
            "Loaded map %s: %d collision bodies, %d sockets",
            glb_path.name,
            len(loaded.collision_bodies),
            len(loaded.sockets),
        )
        return loaded

    def _build_collision(self, root: NodePath, loaded: LoadedMap) -> None:
        for collision_np in root.find_all_matches("**"):
            if not collision_np.name.endswith("_collision"):
                continue

            # The "<name>_collision" node exported by Blender is a plain
            # transform wrapper; the actual renderable geometry lives on
            # a differently-named GeomNode child (the Blender mesh-data
            # name, e.g. "Cube.016"). Collect every GeomNode descendant.
            geom_nps = [n for n in collision_np.find_all_matches("**") if n.node().is_geom_node()]
            if not geom_nps:
                continue

            mesh = BulletTriangleMesh()
            for geom_np in geom_nps:
                geom_node = geom_np.node()
                world_transform = geom_np.get_net_transform()
                for i in range(geom_node.get_num_geoms()):
                    geom = geom_node.get_geom(i)
                    mesh.add_geom(geom, True, world_transform)

            shape = BulletTriangleMeshShape(mesh, dynamic=False)
            body = BulletRigidBodyNode(collision_np.name)
            body.add_shape(shape)
            body.set_mass(0.0)
            body.set_static(True)
            body_np = root.get_parent().attach_new_node(body)
            self._physics_world.attach(body)

            collision_np.hide()
            loaded.collision_bodies.append(body_np)

    def _collect_sockets(self, root: NodePath, loaded: LoadedMap) -> None:
        for np in root.find_all_matches("**"):
            if np.name.startswith("Socket_"):
                loaded.sockets[np.name] = np
