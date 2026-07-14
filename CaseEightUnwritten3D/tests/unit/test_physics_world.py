"""PhysicsWorld tests. Bullet is pure CPU physics -- no GPU/window
needed, so these run without Xvfb."""
from panda3d.core import Vec3

from game.core.physics_world import PhysicsWorld


def test_physics_world_applies_configured_gravity():
    world = PhysicsWorld(gravity=-9.81)
    assert world.bullet_world.get_gravity() == Vec3(0, 0, -9.81)


def test_physics_world_step_advances_simulation():
    world = PhysicsWorld(gravity=-9.81)
    steps = world.step(1.0 / 60.0)
    assert steps >= 1


def test_falling_rigid_body_accelerates_downward():
    from panda3d.bullet import BulletRigidBodyNode, BulletSphereShape
    from panda3d.core import NodePath, PandaNode

    world = PhysicsWorld(gravity=-9.81)
    root = NodePath(PandaNode("root"))

    body = BulletRigidBodyNode("ball")
    body.add_shape(BulletSphereShape(0.5))
    body.set_mass(1.0)
    body_np = root.attach_new_node(body)
    body_np.set_pos(0, 0, 10)
    world.attach(body)

    z_positions = []
    for _ in range(30):
        world.step(1.0 / 60.0)
        z_positions.append(body_np.get_z())

    assert z_positions[-1] < z_positions[0], "body should have fallen under gravity"
    # Roughly monotonic descent (allow tiny numerical noise).
    assert z_positions[-1] < z_positions[len(z_positions) // 2]
