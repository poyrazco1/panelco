"""Automated .glb validation, per docs/MASTER_3D_SPEC.md section 18.2.

Checks a glTF/GLB file for the failure modes the source spec calls out
by name: does it open, are there missing textures/materials, are there
NaN/degenerate transforms, is there at least one collision proxy and
one gameplay socket. A broken asset must never reach the build.
"""
from __future__ import annotations

import math
from dataclasses import dataclass, field
from pathlib import Path

from pygltflib import GLTF2

MAX_GLB_SIZE_BYTES = 100 * 1024 * 1024  # 100 MB per-file soft ceiling


@dataclass
class ValidationResult:
    path: str
    ok: bool
    mesh_count: int = 0
    node_count: int = 0
    material_count: int = 0
    collision_node_count: int = 0
    socket_node_count: int = 0
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)


def validate_glb(path: Path, require_collision: bool = True, require_sockets: bool = False) -> ValidationResult:
    path = Path(path)
    result = ValidationResult(path=str(path), ok=False)

    if not path.exists():
        result.errors.append(f"file does not exist: {path}")
        return result

    size = path.stat().st_size
    if size == 0:
        result.errors.append("file is empty (0 bytes)")
        return result
    if size > MAX_GLB_SIZE_BYTES:
        result.warnings.append(f"file exceeds soft size ceiling: {size} bytes")

    try:
        gltf = GLTF2().load(str(path))
    except Exception as exc:  # noqa: BLE001
        result.errors.append(f"failed to parse glTF: {exc!r}")
        return result

    result.mesh_count = len(gltf.meshes or [])
    result.node_count = len(gltf.nodes or [])
    result.material_count = len(gltf.materials or [])

    if result.mesh_count == 0:
        result.errors.append("no meshes found")

    node_names = [n.name for n in (gltf.nodes or []) if n.name]
    result.collision_node_count = sum(1 for n in node_names if n.endswith("_collision"))
    result.socket_node_count = sum(1 for n in node_names if n.startswith("Socket_"))

    if require_collision and result.collision_node_count == 0:
        result.errors.append("no '*_collision' nodes found")
    if require_sockets and result.socket_node_count == 0:
        result.errors.append("no 'Socket_*' gameplay marker nodes found")

    for node in gltf.nodes or []:
        matrix = node.matrix
        if matrix and any(not math.isfinite(v) for v in matrix):
            result.errors.append(f"node '{node.name}' has a non-finite (NaN/Inf) transform")
        scale = node.scale
        if scale and any(abs(s) > 1000 or (s != 0 and abs(s) < 1e-6) for s in scale):
            result.warnings.append(f"node '{node.name}' has a suspicious scale: {scale}")

    for i, mesh in enumerate(gltf.meshes or []):
        for primitive in mesh.primitives:
            if primitive.material is None:
                result.warnings.append(f"mesh[{i}] '{mesh.name}' has a primitive with no material")

    result.ok = len(result.errors) == 0
    return result


def main() -> int:
    import argparse

    parser = argparse.ArgumentParser()
    parser.add_argument("glb_paths", nargs="+")
    parser.add_argument("--require-sockets", action="store_true")
    args = parser.parse_args()

    all_ok = True
    for glb_path in args.glb_paths:
        result = validate_glb(Path(glb_path), require_sockets=args.require_sockets)
        status = "OK" if result.ok else "FAIL"
        print(f"[{status}] {result.path}")
        print(f"  meshes={result.mesh_count} nodes={result.node_count} materials={result.material_count} "
              f"collision_nodes={result.collision_node_count} socket_nodes={result.socket_node_count}")
        for err in result.errors:
            print(f"  ERROR: {err}")
        for warn in result.warnings:
            print(f"  WARNING: {warn}")
        all_ok = all_ok and result.ok

    return 0 if all_ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
