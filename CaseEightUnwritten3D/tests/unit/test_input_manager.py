"""InputManager tests using InputState.force() to simulate real key
state without needing an actual window or keyboard events."""
from direct.showbase.InputStateGlobal import inputState

from game.input.input_manager import InputManager


class _FakeBase:
    win = None


def test_no_input_gives_zero_vector():
    mgr = InputManager(_FakeBase())
    assert mgr.movement_vector() == (0.0, 0.0)


def test_forward_press_gives_positive_y():
    mgr = InputManager(_FakeBase())
    token = inputState.force("move_forward:w", True, inputSource="test")
    try:
        x, y = mgr.movement_vector()
        assert x == 0.0
        assert y == 1.0
    finally:
        token.release()


def test_diagonal_movement_is_normalized():
    mgr = InputManager(_FakeBase())
    forward_token = inputState.force("move_forward:w", True, inputSource="test")
    right_token = inputState.force("move_right:d", True, inputSource="test")
    try:
        x, y = mgr.movement_vector()
        length = (x * x + y * y) ** 0.5
        assert abs(length - 1.0) < 1e-6
    finally:
        forward_token.release()
        right_token.release()


def test_opposing_keys_cancel_out():
    mgr = InputManager(_FakeBase())
    fwd = inputState.force("move_forward:w", True, inputSource="test")
    back = inputState.force("move_backward:s", True, inputSource="test")
    try:
        x, y = mgr.movement_vector()
        assert x == 0.0
        assert y == 0.0
    finally:
        fwd.release()
        back.release()
