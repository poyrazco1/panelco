from pathlib import Path

import pytest

from game.core.physics_world import PhysicsWorld
from game.rendering.scene_manager import SceneManager3D

PROJECT_ROOT = Path(__file__).resolve().parents[2]
TEST_ROOM_GLB = PROJECT_ROOT / "assets/models/maps/test_room/test_room.glb"

pytestmark = pytest.mark.skipif(not TEST_ROOM_GLB.exists(), reason="test_room.glb not generated")


def test_load_map_builds_real_bullet_bodies(clean_render):
    physics = PhysicsWorld()
    scene_mgr = SceneManager3D(physics)

    loaded = scene_mgr.load_map(TEST_ROOM_GLB, clean_render, name="TestRoom")

    assert not loaded.root.is_empty()
    assert len(loaded.collision_bodies) >= 8
    assert physics.bullet_world.get_num_rigid_bodies() >= 8


def test_load_map_exposes_typed_sockets(clean_render):
    physics = PhysicsWorld()
    scene_mgr = SceneManager3D(physics)

    loaded = scene_mgr.load_map(TEST_ROOM_GLB, clean_render, name="TestRoom")

    spawn_sockets = loaded.sockets_of_type("player_spawn")
    evidence_sockets = loaded.sockets_of_type("evidence_socket")
    hiding_sockets = loaded.sockets_of_type("hiding_spot")

    assert len(spawn_sockets) == 1
    assert len(evidence_sockets) == 1
    assert len(hiding_sockets) == 1
    assert evidence_sockets[0].get_tag("evidence_id") == "TEST_EVIDENCE_01"


def test_missing_glb_raises_not_faked(clean_render):
    physics = PhysicsWorld()
    scene_mgr = SceneManager3D(physics)

    with pytest.raises(FileNotFoundError):
        scene_mgr.load_map(PROJECT_ROOT / "assets/models/maps/nonexistent.glb", clean_render)
