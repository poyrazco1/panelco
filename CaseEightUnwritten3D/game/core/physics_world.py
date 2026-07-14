"""Bullet physics world wrapper.

A thin, testable layer over panda3d.bullet.BulletWorld: fixed-timestep
stepping, gravity from config, and a place to hang debug rendering.
Gameplay code (FirstPersonController, InteractionSystem, ...) talks to
this class, never to BulletWorld directly, so the physics backend can
be swapped or mocked in unit tests.
"""
from __future__ import annotations

from panda3d.bullet import BulletDebugNode, BulletWorld
from panda3d.core import NodePath, Vec3

from game.core.logging_setup import get_logger

logger = get_logger("core.physics_world")


class PhysicsWorld:
    def __init__(self, gravity: float = -9.81) -> None:
        self._world = BulletWorld()
        self._world.set_gravity(Vec3(0, 0, gravity))
        self._debug_np: NodePath | None = None
        logger.info("PhysicsWorld created with gravity=%s", gravity)

    @property
    def bullet_world(self) -> BulletWorld:
        return self._world

    def step(self, dt: float, max_sub_steps: int = 10, fixed_timestep: float = 1.0 / 60.0) -> int:
        return self._world.do_physics(dt, max_sub_steps, fixed_timestep)

    def attach(self, node) -> None:
        self._world.attach(node)

    def remove(self, node) -> None:
        self._world.remove(node)

    def ray_test_closest(self, origin: Vec3, target: Vec3, mask=None):
        if mask is None:
            return self._world.ray_test_closest(origin, target)
        return self._world.ray_test_closest(origin, target, mask)

    def enable_debug(self, parent: NodePath) -> NodePath:
        if self._debug_np is not None:
            return self._debug_np
        debug_node = BulletDebugNode("bullet_debug")
        debug_node.show_wireframe(True)
        debug_node.show_constraints(True)
        debug_node.show_bounding_boxes(False)
        debug_node.show_normals(False)
        self._debug_np = parent.attach_new_node(debug_node)
        self._world.set_debug_node(debug_node)
        return self._debug_np
