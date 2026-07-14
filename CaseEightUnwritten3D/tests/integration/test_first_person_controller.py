"""End-to-end: real Bullet collision stops a real FirstPersonController
inside the real Blender-generated test room. This is the automated
form of game/bootstrap/run_test_room.py.
"""
from pathlib import Path

import pytest
from panda3d.core import Vec3

from game.core.config import load_config
from game.core.physics_world import PhysicsWorld
from game.interaction.interaction_system import InteractionSystem
from game.player.first_person_controller import FirstPersonController, FirstPersonControllerConfig, MoveState
from game.rendering.scene_manager import SceneManager3D

PROJECT_ROOT = Path(__file__).resolve().parents[2]
TEST_ROOM_GLB = PROJECT_ROOT / "assets/models/maps/test_room/test_room.glb"

pytestmark = pytest.mark.skipif(not TEST_ROOM_GLB.exists(), reason="test_room.glb not generated")


@pytest.fixture
def room_and_controller(showbase, clean_render):
    physics = PhysicsWorld(gravity=-9.81)
    scene_mgr = SceneManager3D(physics)
    loaded = scene_mgr.load_map(TEST_ROOM_GLB, clean_render, name="TestRoom")

    fp_config = FirstPersonControllerConfig.from_game_config(load_config())
    spawn = loaded.sockets_of_type("player_spawn")[0].get_pos(clean_render) + Vec3(0, 0, 0.9)
    camera = showbase.render.attach_new_node("test_camera")
    controller = FirstPersonController(physics, clean_render, camera, fp_config, spawn)

    yield physics, loaded, controller, camera
    camera.remove_node()


def _settle(physics, controller, frames=30, dt=1.0 / 60.0):
    for _ in range(frames):
        physics.step(dt)
        controller.update(dt, 0.0, 0.0, False, False)


def test_character_settles_onto_floor(room_and_controller):
    physics, _loaded, controller, _camera = room_and_controller
    _settle(physics, controller, frames=60)
    assert controller.is_on_ground()
    # Resting height should be within a plausible band above floor
    # level (z=0), not embedded in or falling through it.
    assert 0.5 < controller.get_position().z < 1.2


def test_wall_collision_stops_forward_movement(room_and_controller):
    physics, _loaded, controller, _camera = room_and_controller
    _settle(physics, controller, frames=30)

    dt = 1.0 / 60.0
    for _ in range(240):
        physics.step(dt)
        controller.update(dt, strafe_x=0.0, forward_y=1.0, run=True, crouch=False)

    # Room interior extends to roughly y=2.15 at the north wall; an
    # uncollided run at 5.2 m/s for 4s would travel ~20m.
    assert controller.get_position().y < 2.2
    assert controller.move_state == MoveState.RUN


def test_interaction_raycast_hits_tagged_collision_body(room_and_controller):
    physics, loaded, controller, camera = room_and_controller
    table_body = next(np for np in loaded.collision_bodies if np.name == "Table_collision")
    InteractionSystem.mark_interactable(table_body, "pickup_table")

    controller.node_path.set_pos(0, -2.0, 0.9)
    controller.node_path.set_h(0)
    _settle(physics, controller, frames=30)
    controller.apply_mouse_look(dx=0.0, dy=55.0)  # look down at the low tabletop

    interaction = InteractionSystem(physics)
    result = interaction.raycast_from_camera(camera, controller.node_path.get_parent())

    assert result.kind == "pickup_table"


def test_interaction_raycast_returns_none_kind_for_untagged_wall(room_and_controller):
    physics, _loaded, controller, camera = room_and_controller
    _settle(physics, controller, frames=30)
    controller.node_path.set_h(0)

    interaction = InteractionSystem(physics)
    result = interaction.raycast_from_camera(camera, controller.node_path.get_parent())

    # The wall is a real collision body (so node_path is not None) but
    # was never tagged "interactable".
    assert result.node_path is not None
    assert result.kind is None
