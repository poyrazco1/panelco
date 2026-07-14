"""Raycast-based interaction: doors, drawers, pickups.

Any collision-enabled NodePath tagged "interactable" is a candidate.
The tag value names the interaction kind ("door", "drawer", "pickup",
"evidence_socket", ...) so callers can dispatch without string-matching
node names. Server-authoritative games route the actual state change
(door open, item pickup) through the network layer; this system only
answers "what is the player looking at, and is it in range".
"""
from __future__ import annotations

from dataclasses import dataclass

from panda3d.core import BitMask32, NodePath, Vec3

from game.core.physics_world import PhysicsWorld

INTERACTABLE_MASK = BitMask32.bit(1)


@dataclass
class InteractionResult:
    node_path: NodePath | None
    distance: float
    kind: str | None
    hit_pos: Vec3 | None


class InteractionSystem:
    def __init__(self, physics_world: PhysicsWorld, max_distance: float = 2.2) -> None:
        self._physics_world = physics_world
        self.max_distance = max_distance

    def raycast_from_camera(self, camera: NodePath, render: NodePath) -> InteractionResult:
        origin = camera.get_pos(render)
        direction = render.get_relative_vector(camera, Vec3(0, 1, 0))
        direction.normalize()
        target = origin + direction * self.max_distance

        hit = self._physics_world.ray_test_closest(origin, target)
        if not hit.has_hit():
            return InteractionResult(node_path=None, distance=self.max_distance, kind=None, hit_pos=None)

        node = hit.get_node()
        np = NodePath.any_path(node)
        distance = (hit.get_hit_pos() - origin).length()
        kind = np.get_tag("interactable") if np.has_tag("interactable") else None

        return InteractionResult(node_path=np, distance=distance, kind=kind or None, hit_pos=hit.get_hit_pos())

    @staticmethod
    def mark_interactable(node_path: NodePath, kind: str) -> None:
        node_path.set_tag("interactable", kind)
        node_path.set_collide_mask(INTERACTABLE_MASK)
