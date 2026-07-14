# Controls

Default key bindings, from `game/input/input_manager.py`
(`DEFAULT_KEY_BINDINGS`). All are rebindable at runtime via
`InputManager.rebind()`; a full settings-menu UI for this is not yet
built (see `docs/KNOWN_ISSUES.md`).

| Action | Default key(s) |
|---|---|
| Move forward | `W` / Up arrow |
| Move backward | `S` / Down arrow |
| Move left (strafe) | `A` / Left arrow |
| Move right (strafe) | `D` / Right arrow |
| Run | `Shift` |
| Crouch | `Ctrl` / `C` |
| Hold breath | `B` |
| Interact | `E` |
| Use flashlight | `F` |
| Use phone | `T` |
| Use radio | `R` |
| Take photo | Left mouse button |
| Hide | `H` |
| Mouse look | Mouse movement (sensitivity: `player.mouse_sensitivity` in `config/default.toml`) |

## Notes

- **Jump is not bound.** Per `docs/SOURCE_3D_CONVERSION_PROMPT.md` §7
  and the source design's own training-sequence description, this is a
  horror investigation game with no stated jump requirement;
  `player.jump_speed = 0.0` in `config/default.toml`.
- Mouse look pitch is clamped to ±85° by default
  (`FirstPersonController.apply_mouse_look`'s `pitch_limit_deg`
  parameter).
- Controller/gamepad support is designed as an abstraction target
  (`InputManager` is action-based, not key-based, specifically so a
  controller binding layer can be added later) but no controller
  bindings exist yet.
