"""Layered TOML configuration loading.

Loads config/default.toml, then merges config/<env>.toml on top of it,
where <env> comes from the CASE_EIGHT_ENV environment variable
(default: "development"). Nothing here is hidden inside code -- all
tunable numbers live in the TOML files under config/.
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import toml

PROJECT_ROOT = Path(__file__).resolve().parents[2]
CONFIG_DIR = PROJECT_ROOT / "config"


def _deep_merge(base: dict[str, Any], override: dict[str, Any]) -> dict[str, Any]:
    merged = dict(base)
    for key, value in override.items():
        if key in merged and isinstance(merged[key], dict) and isinstance(value, dict):
            merged[key] = _deep_merge(merged[key], value)
        else:
            merged[key] = value
    return merged


@dataclass(frozen=True)
class GameConfig:
    raw: dict[str, Any] = field(default_factory=dict)

    def get(self, dotted_path: str, default: Any = None) -> Any:
        node: Any = self.raw
        for part in dotted_path.split("."):
            if not isinstance(node, dict) or part not in node:
                return default
            node = node[part]
        return node

    @property
    def window(self) -> dict[str, Any]:
        return self.raw.get("window", {})

    @property
    def player(self) -> dict[str, Any]:
        return self.raw.get("player", {})

    @property
    def physics(self) -> dict[str, Any]:
        return self.raw.get("physics", {})


def load_config(env: str | None = None) -> GameConfig:
    env = env or os.environ.get("CASE_EIGHT_ENV", "development")

    default_path = CONFIG_DIR / "default.toml"
    if not default_path.exists():
        raise FileNotFoundError(f"Missing base config: {default_path}")
    merged = toml.load(default_path)

    env_path = CONFIG_DIR / f"{env}.toml"
    if env_path.exists():
        merged = _deep_merge(merged, toml.load(env_path))

    return GameConfig(raw=merged)
