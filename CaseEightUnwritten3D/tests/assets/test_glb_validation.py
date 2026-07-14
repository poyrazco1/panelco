from pathlib import Path

from tools.validators.validate_glb import validate_glb

PROJECT_ROOT = Path(__file__).resolve().parents[2]
TEST_ROOM_GLB = PROJECT_ROOT / "assets/models/maps/test_room/test_room.glb"


def test_test_room_glb_exists():
    assert TEST_ROOM_GLB.exists(), "run tools/blender/generate_test_room.py first"


def test_test_room_glb_is_valid():
    result = validate_glb(TEST_ROOM_GLB, require_collision=True, require_sockets=True)
    assert result.ok, f"errors={result.errors}"
    assert result.mesh_count > 0
    assert result.collision_node_count >= 8  # floor, ceiling, 6 wall segments (+ table)
    assert result.socket_node_count >= 6


def test_missing_file_is_reported_not_faked():
    result = validate_glb(PROJECT_ROOT / "assets/models/maps/does_not_exist.glb")
    assert not result.ok
    assert any("does not exist" in e for e in result.errors)


def test_empty_file_is_rejected():
    import tempfile

    with tempfile.NamedTemporaryFile(suffix=".glb") as f:
        result = validate_glb(Path(f.name))
        assert not result.ok
        assert any("empty" in e for e in result.errors)
