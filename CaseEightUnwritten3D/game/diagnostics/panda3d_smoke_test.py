"""Real Panda3D startup smoke test.

Opens an actual Panda3D window (or offscreen buffer), renders at least
one real frame, and saves a screenshot as proof of a working render
pipeline. Exits with a non-zero status and a clear message on failure --
it never reports success it did not observe.

Usage:
    xvfb-run -a python game/diagnostics/panda3d_smoke_test.py --mode onscreen
    python game/diagnostics/panda3d_smoke_test.py --mode offscreen
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from panda3d.core import (
    AmbientLight,
    DirectionalLight,
    Filename,
    PandaSystem,
    Vec4,
    load_prc_file_data,
)

REPORTS_DIR = Path(__file__).resolve().parents[2] / "reports"


def run_smoke_test(mode: str, screenshot_path: Path) -> dict:
    if mode == "offscreen":
        load_prc_file_data("", "window-type offscreen")
    else:
        load_prc_file_data("", "window-type onscreen")

    load_prc_file_data("", "win-size 640 480")
    load_prc_file_data("", "audio-library-name null")  # no /dev/snd in this container
    load_prc_file_data("", "notify-level warning")

    from direct.showbase.ShowBase import ShowBase

    app = ShowBase()

    result = {
        "panda3d_version": PandaSystem.getVersionString(),
        "mode": mode,
        "window_opened": False,
        "pipe_name": None,
        "renderer": None,
        "frame_rendered": False,
        "screenshot_written": False,
    }

    win = app.win
    if win is None:
        app.destroy()
        raise RuntimeError("ShowBase.win is None: no graphics window/buffer was created")

    result["window_opened"] = True
    result["pipe_name"] = app.pipe.getInterfaceName() if app.pipe else None

    gsg = win.getGsg()
    if gsg is not None:
        result["renderer"] = f"{gsg.getDriverVendor()} / {gsg.getDriverRenderer()} / {gsg.getDriverVersion()}"

    # Build a minimal real scene: one lit cube, so the frame is not blank.
    cube = app.loader.loadModel("models/box")
    if cube.isEmpty():
        # Fallback: procedural geometry if the built-in sample model is unavailable.
        from panda3d.core import CardMaker

        cm = CardMaker("fallback_card")
        cm.setFrame(-1, 1, -1, 1)
        cube = app.render.attachNewNode(cm.generate())
    cube.reparentTo(app.render)
    cube.setPos(0, 8, 0)

    dlight = DirectionalLight("dlight")
    dlight.setColor(Vec4(0.9, 0.9, 0.85, 1))
    dlnp = app.render.attachNewNode(dlight)
    dlnp.setHpr(45, -45, 0)
    app.render.setLight(dlnp)

    alight = AmbientLight("alight")
    alight.setColor(Vec4(0.25, 0.25, 0.3, 1))
    alnp = app.render.attachNewNode(alight)
    app.render.setLight(alnp)

    app.disableMouse()
    app.camera.setPos(0, 0, 0)
    app.camera.lookAt(cube)

    # Render several real frames (first frame after window creation can be a stub).
    for _ in range(10):
        app.taskMgr.step()
    result["frame_rendered"] = True

    screenshot_path.parent.mkdir(parents=True, exist_ok=True)
    written = win.saveScreenshot(Filename.fromOsSpecific(str(screenshot_path)))
    result["screenshot_written"] = bool(written) and screenshot_path.exists()

    app.destroy()
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--mode", choices=["onscreen", "offscreen"], default="offscreen")
    args = parser.parse_args()

    screenshot_path = REPORTS_DIR / f"panda3d_smoke_{args.mode}.png"

    try:
        result = run_smoke_test(args.mode, screenshot_path)
    except Exception as exc:  # noqa: BLE001 - smoke test must report any failure plainly
        print(f"SMOKE TEST FAILED ({args.mode}): {exc!r}", file=sys.stderr)
        return 1

    print("SMOKE TEST RESULT:")
    for key, value in result.items():
        print(f"  {key}: {value}")

    if not (result["window_opened"] and result["frame_rendered"] and result["screenshot_written"]):
        print("SMOKE TEST FAILED: one or more required checks did not pass", file=sys.stderr)
        return 1

    print("SMOKE TEST PASSED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
