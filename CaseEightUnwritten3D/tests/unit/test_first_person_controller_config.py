from game.core.config import load_config
from game.player.first_person_controller import FirstPersonControllerConfig


def test_config_derives_from_game_config():
    game_config = load_config(env="development")
    fp_config = FirstPersonControllerConfig.from_game_config(game_config)

    assert fp_config.walk_speed == 2.6
    assert fp_config.run_speed == 5.2
    assert fp_config.crouch_speed == 1.3
    assert fp_config.jump_speed == 0.0


def test_run_speed_is_faster_than_walk_and_crouch():
    game_config = load_config(env="development")
    fp_config = FirstPersonControllerConfig.from_game_config(game_config)

    assert fp_config.run_speed > fp_config.walk_speed > fp_config.crouch_speed


def test_capsule_dimensions_are_positive():
    fp_config = FirstPersonControllerConfig()
    assert fp_config.capsule_radius > 0
    assert fp_config.capsule_height > fp_config.capsule_radius * 2
