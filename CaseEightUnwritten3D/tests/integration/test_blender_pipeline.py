"""Blender -> GLB integration test, per docs/MASTER_3D_SPEC.md section 22
("Blender -> GLB -> Panda3D" is a required integration test).

Actually invokes the real `blender --background` process and the real
generator script -- this is not a mock of the pipeline, it runs it.
Skips (does not fake a pass) if the `blender` binary isn't on PATH.
"""
from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

import pytest

from tools.validators.validate_glb import validate_glb

PROJECT_ROOT = Path(__file__).resolve().parents[2]
BLENDER_BIN = shutil.which("blender")

pytestmark = pytest.mark.skipif(BLENDER_BIN is None, reason="blender binary not found on PATH")


def test_generator_script_produces_valid_glb(tmp_path):
    # generate_test_room.py resolves --out / --blend-out via
    # PROJECT_ROOT / args.out; pathlib's `/` returns the right-hand side
    # unchanged when it is already absolute, so passing absolute tmp_path
    # locations here writes exactly there, not under PROJECT_ROOT.
    out_glb = tmp_path / "test_room_pipeline_check.glb"
    out_blend = tmp_path / "test_room_pipeline_check.blend"

    result = subprocess.run(
        [
            BLENDER_BIN,
            "--background",
            "--python",
            str(PROJECT_ROOT / "tools/blender/generate_test_room.py"),
            "--",
            "--out",
            str(out_glb),
            "--blend-out",
            str(out_blend),
        ],
        cwd=PROJECT_ROOT,
        capture_output=True,
        text=True,
        timeout=120,
    )

    assert result.returncode == 0, f"blender exited non-zero.\nstdout={result.stdout}\nstderr={result.stderr}"
    assert "BLENDER_GENERATION_RESULT" in result.stdout
    assert out_glb.exists(), f"expected GLB not found at {out_glb}"

    validation = validate_glb(out_glb, require_collision=True, require_sockets=True)
    assert validation.ok, f"generated GLB failed validation: {validation.errors}"
