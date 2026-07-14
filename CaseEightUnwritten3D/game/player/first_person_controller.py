"""Bullet-backed first-person character controller.

Uses Panda3D's BulletCharacterControllerNode (a kinematic capsule
controller wrapping Bullet's btKinematicCharacterController) for real
collision-aware movement: step height, slope limiting, and gravity are
handled by Bullet, not hand-rolled raycasting.

Per docs/SOURCE_DESIGN_2D.txt section 7.1, this is a horror
investigation game with no combat jump requirement, so jumping is
disabled by default via config/default.toml (player.jump_speed = 0).
"""
from __future__ import annotations

from dataclasses import dataclass
from enum import Enum, auto

from panda3d.bullet import BulletCapsuleShape, BulletCharacterControllerNode, ZUp
from panda3d.core import NodePath, Vec3

from game.core.logging_setup import get_logger
from game.core.physics_world import PhysicsWorld

logger = get_logger("player.first_person_controller")


class MoveState(Enum):
    IDLE = auto()
    WALK = auto()
    RUN = auto()
    CROUCH = auto()
    CROUCH_WALK = auto()


@dataclass
class FirstPersonControllerConfig:
    capsule_radius: float = 0.35
    capsule_height: float = 1.75
    crouch_height: float = 1.0
    step_height: float = 0.35
    walk_speed: float = 2.6
    run_speed: float = 5.2
    crouch_speed: float = 1.3
    max_slope_deg: float = 45.0
    jump_speed: float = 0.0
    fall_speed: float = 55.0
    gravity: float = 9.81
    eye_height_standing: float = 1.62
    eye_height_crouching: float = 0.95

    @classmethod
    def from_game_config(cls, game_config) -> "FirstPersonControllerConfig":
        p = game_config.player
        return cls(
            capsule_radius=p.get("capsule_radius", 0.35),
            capsule_height=p.get("capsule_height", 1.75),
            crouch_height=p.get("crouch_height", 1.0),
            step_height=p.get("step_height", 0.35),
            walk_speed=p.get("move_speed_walk", 2.6),
            run_speed=p.get("move_speed_run", 5.2),
            crouch_speed=p.get("move_speed_crouch", 1.3),
            max_slope_deg=p.get("max_slope_deg", 45.0),
            jump_speed=p.get("jump_speed", 0.0),
            fall_speed=p.get("fall_speed", 55.0),
            gravity=p.get("gravity", 9.81),
        )


class FirstPersonController:
    def __init__(
        self,
        physics_world: PhysicsWorld,
        render: NodePath,
        camera: NodePath,
        config: FirstPersonControllerConfig,
        spawn_pos: Vec3,
    ) -> None:
        self._physics_world = physics_world
        self._config = config
        self._camera = camera
        self._pitch = 0.0
        self.move_state = MoveState.IDLE
        self.is_crouching = False
        self.is_holding_breath = False
        self.is_hidden = False

        shape = BulletCapsuleShape(config.capsule_radius, config.capsule_height - 2 * config.capsule_radius, ZUp)
        self._char_node = BulletCharacterControllerNode(shape, config.step_height, "Player")
        self._char_node.set_max_slope(config.max_slope_deg * (3.14159265 / 180.0))
        self._char_node.set_gravity(config.gravity)
        self._char_node.set_fall_speed(config.fall_speed)
        self._char_node.set_jump_speed(config.jump_speed)

        self.node_path = render.attach_new_node(self._char_node)
        self.node_path.set_pos(spawn_pos)
        self.node_path.set_collide_mask(0x0F)

        physics_world.bullet_world.attach_character(self._char_node)

        # BulletCharacterControllerNode's NodePath origin coincides with
        # the capsule shape's own center (verified empirically: a
        # character resting on a floor at z=0 settles with node_path.z
        # equal to ~half the capsule height, not zero) -- it is NOT the
        # feet position. eye_height_* in config is specified relative to
        # the feet, so the camera's local offset must subtract the
        # capsule's half-height, or the camera ends up roughly a half
        # body-height too tall.
        self._capsule_half_height = config.capsule_height / 2.0
        self._eye_z_standing = config.eye_height_standing - self._capsule_half_height
        self._eye_z_crouching = config.eye_height_crouching - self._capsule_half_height

        self._camera.reparent_to(self.node_path)
        self._camera.set_pos(0, 0, self._eye_z_standing)

        logger.info("FirstPersonController spawned at %s", spawn_pos)

    # -- Mouse look ---------------------------------------------------

    def apply_mouse_look(self, dx: float, dy: float, pitch_limit_deg: float = 85.0) -> None:
        heading = self.node_path.get_h() - dx
        self.node_path.set_h(heading)

        self._pitch = max(-pitch_limit_deg, min(pitch_limit_deg, self._pitch - dy))
        self._camera.set_p(self._pitch)

    # -- Movement -------------------------------------------------------

    def update(self, dt: float, strafe_x: float, forward_y: float, run: bool, crouch: bool) -> None:
        self.is_crouching = crouch
        speed = self._select_speed(strafe_x, forward_y, run, crouch)

        movement = Vec3(strafe_x, forward_y, 0)
        if movement.length_squared() > 0:
            movement.normalize()
        movement *= speed

        self._char_node.set_linear_movement(movement, True)

        eye_z = self._eye_z_crouching if crouch else self._eye_z_standing
        current_z = self._camera.get_z()
        self._camera.set_z(current_z + (eye_z - current_z) * min(1.0, dt * 8.0))

        self.move_state = self._resolve_move_state(strafe_x, forward_y, run, crouch)

    def _select_speed(self, strafe_x: float, forward_y: float, run: bool, crouch: bool) -> float:
        if strafe_x == 0 and forward_y == 0:
            return 0.0
        if crouch:
            return self._config.crouch_speed
        if run:
            return self._config.run_speed
        return self._config.walk_speed

    def _resolve_move_state(self, strafe_x: float, forward_y: float, run: bool, crouch: bool) -> MoveState:
        moving = strafe_x != 0 or forward_y != 0
        if crouch:
            return MoveState.CROUCH_WALK if moving else MoveState.CROUCH
        if not moving:
            return MoveState.IDLE
        return MoveState.RUN if run else MoveState.WALK

    def is_on_ground(self) -> bool:
        return self._char_node.is_on_ground()

    def get_position(self) -> Vec3:
        return self.node_path.get_pos()
