"""Input abstraction layer.

Wraps Panda3D's InputState (held-key tracking) and mouse-look behind a
named-action API so gameplay code never reads a raw key name directly.
This is also the seam accessibility key-rebinding and controller
support (docs/MASTER_3D_SPEC.md section 19) hook into later without
touching FirstPersonController.
"""
from __future__ import annotations

from dataclasses import dataclass

from direct.showbase.InputStateGlobal import inputState
from panda3d.core import WindowProperties

DEFAULT_KEY_BINDINGS: dict[str, list[str]] = {
    "move_forward": ["w", "arrow_up"],
    "move_backward": ["s", "arrow_down"],
    "move_left": ["a", "arrow_left"],
    "move_right": ["d", "arrow_right"],
    "run": ["shift"],
    "crouch": ["control", "c"],
    "hold_breath": ["b"],
    "interact": ["e"],
    "use_flashlight": ["f"],
    "use_phone": ["t"],
    "use_radio": ["r"],
    "take_photo": ["mouse1"],
    "hide": ["h"],
}


@dataclass
class MouseDelta:
    dx: float = 0.0
    dy: float = 0.0


class InputManager:
    """Action-based input reader for the currently active window/app."""

    def __init__(self, base, sensitivity: float = 0.12, key_bindings: dict[str, list[str]] | None = None) -> None:
        self._base = base
        self.sensitivity = sensitivity
        self._bindings = key_bindings or DEFAULT_KEY_BINDINGS
        self._mouse_locked = False
        self._last_mouse_x = 0
        self._last_mouse_y = 0

        for action, keys in self._bindings.items():
            for key in keys:
                if key.startswith("mouse"):
                    continue
                inputState.watch_with_modifiers(f"{action}:{key}", key)

    def is_action_pressed(self, action: str) -> bool:
        keys = self._bindings.get(action, [])
        return any(
            inputState.is_set(f"{action}:{key}")
            for key in keys
            if not key.startswith("mouse")
        )

    def rebind(self, action: str, keys: list[str]) -> None:
        for key in self._bindings.get(action, []):
            if not key.startswith("mouse"):
                inputState.remove_watched_key(f"{action}:{key}", key)
        self._bindings[action] = keys
        for key in keys:
            if not key.startswith("mouse"):
                inputState.watch_with_modifiers(f"{action}:{key}", key)

    def lock_mouse(self, locked: bool) -> None:
        self._mouse_locked = locked
        props = WindowProperties()
        props.set_cursor_hidden(locked)
        props.set_mouse_mode(WindowProperties.M_relative if locked else WindowProperties.M_absolute)
        if self._base.win is not None:
            self._base.win.request_properties(props)

    def get_mouse_delta(self) -> MouseDelta:
        win = self._base.win
        if win is None or not win.has_pointer(0):
            return MouseDelta(0.0, 0.0)
        pointer = win.get_pointer(0)
        dx = (pointer.get_x() - self._last_mouse_x) if self._mouse_locked else 0.0
        dy = (pointer.get_y() - self._last_mouse_y) if self._mouse_locked else 0.0
        self._last_mouse_x = pointer.get_x()
        self._last_mouse_y = pointer.get_y()
        return MouseDelta(dx * self.sensitivity, dy * self.sensitivity)

    def movement_vector(self) -> tuple[float, float]:
        """Returns (strafe_x, forward_y) in [-1, 1] from held movement keys."""
        x = 0.0
        y = 0.0
        if self.is_action_pressed("move_forward"):
            y += 1.0
        if self.is_action_pressed("move_backward"):
            y -= 1.0
        if self.is_action_pressed("move_right"):
            x += 1.0
        if self.is_action_pressed("move_left"):
            x -= 1.0
        length = (x * x + y * y) ** 0.5
        if length > 1.0:
            x /= length
            y /= length
        return x, y
