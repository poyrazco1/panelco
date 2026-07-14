"""Phase 0/1 acceptance demo: spawn a Bullet-collision first-person
controller inside the Blender-generated test room, drive it with
scripted input, and prove real collision + interaction raycasting work.

This is not a mock -- it is the same FirstPersonController,
PhysicsWorld, and SceneManager3D classes the real game will use,
running against the real exported GLB.

Usage:
    xvfb-run -a python game/bootstrap/run_test_room.py
"""
from __future__ import annotations

import sys
from pathlib import Path

from panda3d.core import AmbientLight, DirectionalLight, Vec3, Vec4, load_prc_file_data

PROJECT_ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    load_prc_file_data("", "window-type offscreen")
    load_prc_file_data("", "win-size 960 540")
    load_prc_file_data("", "audio-library-name null")
    load_prc_file_data("", "notify-level warning")

    from direct.showbase.ShowBase import ShowBase

    from game.core.config import load_config
    from game.core.physics_world import PhysicsWorld
    from game.interaction.interaction_system import InteractionSystem
    from game.player.first_person_controller import FirstPersonController, FirstPersonControllerConfig
    from game.rendering.scene_manager import SceneManager3D

    app = ShowBase()
    if app.win is None:
        print("RUN_TEST_ROOM FAILED: no graphics buffer created", file=sys.stderr)
        return 1

    game_config = load_config()
    physics = PhysicsWorld(gravity=game_config.physics.get("gravity", -9.81))

    scene_mgr = SceneManager3D(physics)
    glb_path = PROJECT_ROOT / "assets/models/maps/test_room/test_room.glb"
    loaded_map = scene_mgr.load_map(glb_path, app.render, name="TestRoom")

    dlight = DirectionalLight("dlight")
    dlight.setColor(Vec4(1.0, 0.97, 0.9, 1))
    dlnp = app.render.attach_new_node(dlight)
    dlnp.set_hpr(35, -55, 0)
    app.render.set_light(dlnp)
    alight = AmbientLight("alight")
    alight.setColor(Vec4(0.35, 0.35, 0.4, 1))
    alnp = app.render.attach_new_node(alight)
    app.render.set_light(alnp)

    spawn_sockets = loaded_map.sockets_of_type("player_spawn")
    if not spawn_sockets:
        print("RUN_TEST_ROOM FAILED: no player_spawn socket found in map", file=sys.stderr)
        return 1
    # Spawn a comfortable drop-height above the socket (not embedded in
    # the floor mesh) and let a short settle phase resolve initial
    # contact before any behavior is measured -- spawning a capsule with
    # its center already inside the floor produces an undefined,
    # violent depenetration response from Bullet.
    spawn_pos = spawn_sockets[0].get_pos(app.render) + Vec3(0, 0, 0.9)

    fp_config = FirstPersonControllerConfig.from_game_config(game_config)
    controller = FirstPersonController(physics, app.render, app.camera, fp_config, spawn_pos)

    interaction = InteractionSystem(physics)
    # Only Bullet-attached collision bodies can ever be hit by a Bullet
    # raycast -- tag the real physics body for the table, not the
    # visual-only mesh (which has no shape in the Bullet world).
    table_body_np = next((np for np in loaded_map.collision_bodies if np.name == "Table_collision"), None)
    if table_body_np is not None:
        InteractionSystem.mark_interactable(table_body_np, "pickup_table")

    dt = 1.0 / 60.0
    positions = []

    def settle(frames: int) -> None:
        for _ in range(frames):
            physics.step(dt)
            controller.update(dt, strafe_x=0.0, forward_y=0.0, run=False, crouch=False)

    settle(30)  # let the character land on the floor before measuring anything

    # Phase A: walk toward the north wall for 4 seconds of simulated time.
    # The room interior spans roughly Y in [-2.15, 2.15]; the north wall
    # collision sits at Y ~= 2.15. A working capsule controller must stop
    # the player before penetrating it.
    for _ in range(240):
        physics.step(dt)
        controller.update(dt, strafe_x=0.0, forward_y=1.0, run=True, crouch=False)
        positions.append(controller.get_position())

    final_pos = controller.get_position()
    max_y_reached = max(p.y for p in positions)

    # Phase B: interaction raycast toward the table (player is far from it
    # after phase A, so first confirm the raycast API works with no hit,
    # then move back near the table and confirm it detects the tagged prop).
    far_result = interaction.raycast_from_camera(app.camera, app.render)

    controller.node_path.set_pos(0, -2.0, 0.9)
    controller.node_path.set_h(0)
    settle(30)
    # The table (Y=-1.2, top at Z~0.75) sits well below standing eye
    # height (~1.58m), same as a real table would relative to a real
    # player -- looking at it requires pitching the camera down, exactly
    # like a player would tilt their view to look at a tabletop instead
    # of firing a perfectly level ray that sails over it.
    controller.apply_mouse_look(dx=0.0, dy=55.0)
    near_result = interaction.raycast_from_camera(app.camera, app.render)

    # All behavioral results above are already captured; detach the
    # camera from the character for a readable overview render (the
    # in-character pitched-down interaction-test pose is correct for
    # the raycast math but too close/steep to make a legible screenshot).
    app.camera.reparent_to(app.render)
    app.camera.set_pos(3.2, -5.5, 2.3)
    app.camera.look_at(0, 0, 1.0)
    for _ in range(5):
        app.task_mgr.step()

    screenshot_path = PROJECT_ROOT / "reports/fps_controller_test.png"
    screenshot_path.parent.mkdir(parents=True, exist_ok=True)
    app.win.save_screenshot(str(screenshot_path))

    print("RUN_TEST_ROOM RESULT:")
    print(f"  spawn_pos: {spawn_pos}")
    print(f"  final_pos_after_walking_into_wall: {final_pos}")
    print(f"  max_y_reached: {max_y_reached:.4f} (wall collision expected to stop this below ~2.15)")
    print(f"  is_on_ground: {controller.is_on_ground()}")
    print(f"  interaction_far_hit: {far_result.node_path}")
    print(f"  interaction_near_hit_kind: {near_result.kind}")
    print(f"  screenshot_written: {screenshot_path.exists()}")

    app.destroy()

    collision_worked = max_y_reached < 2.2  # would reach ~4.8+ in 4s at run speed if uncollided
    interaction_worked = near_result.kind == "pickup_table"

    print(f"  collision_stopped_player: {collision_worked}")
    print(f"  interaction_detected_table: {interaction_worked}")

    if not (collision_worked and interaction_worked and screenshot_path.exists()):
        print("RUN_TEST_ROOM FAILED: one or more real behavior checks did not pass", file=sys.stderr)
        return 1

    print("RUN_TEST_ROOM PASSED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
