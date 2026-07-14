"""Shared pytest fixtures.

Panda3D/Bullet integration tests need a real ShowBase (offscreen
graphics buffer). This container has no GPU, so those tests must be
run under Xvfb (see scripts/test_all.sh /
reports/ENVIRONMENT_AUDIT.md): only one glxGraphicsPipe is available
and it requires a live X display, even for an "offscreen" buffer.

ShowBase is a process-wide singleton in Panda3D, so it is created once
per test session and shared by every integration test that needs it.
"""
from __future__ import annotations

import pytest
from panda3d.core import load_prc_file_data


@pytest.fixture(scope="session")
def showbase():
    load_prc_file_data("", "window-type offscreen")
    load_prc_file_data("", "win-size 640 480")
    load_prc_file_data("", "audio-library-name null")
    load_prc_file_data("", "notify-level fatal")

    from direct.showbase.ShowBase import ShowBase

    app = ShowBase()
    if app.win is None:
        pytest.skip("No graphics window/buffer available (no display, not running under Xvfb)")
    yield app
    app.destroy()


@pytest.fixture
def clean_render(showbase):
    """A fresh child node under render, cleaned up after each test, so
    tests that load models don't leak nodes into each other."""
    node = showbase.render.attach_new_node("test_root")
    yield node
    node.remove_node()
