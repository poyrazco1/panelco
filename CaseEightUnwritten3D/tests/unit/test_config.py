from game.core.config import load_config


def test_default_config_loads():
    config = load_config(env="production")
    assert config.get("window.title") == "CASE EIGHT: UNWRITTEN"
    assert config.get("player.move_speed_run") == 5.2


def test_development_env_overrides_merge_on_top_of_default():
    config = load_config(env="development")
    # window.title comes from default.toml (no override in development.toml)
    assert config.get("window.title") == "CASE EIGHT: UNWRITTEN"
    # dev.window_type is only defined in development.toml
    assert config.get("dev.window_type") == "offscreen"


def test_production_env_overrides_fullscreen():
    dev_config = load_config(env="development")
    prod_config = load_config(env="production")
    assert dev_config.get("window.fullscreen") is False
    assert prod_config.get("window.fullscreen") is True


def test_missing_key_returns_default():
    config = load_config(env="development")
    assert config.get("nonexistent.path", "fallback") == "fallback"


def test_jump_disabled_by_design():
    config = load_config(env="development")
    assert config.get("player.jump_speed") == 0.0
