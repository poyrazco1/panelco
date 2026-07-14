# MASTER 3D SPEC — CASE EIGHT: UNWRITTEN

This document translates every 2D/Unity-specific statement in
`docs/SOURCE_DESIGN_2D.txt` into its 3D/Python/Panda3D/Blender
equivalent, per the binding conversion rules in
`docs/SOURCE_3D_CONVERSION_PROMPT.md`. **All exact counts, names,
prices, levels, durations, and rules from the source document are
preserved unchanged.** Only the presentation technology changes.

For the full, authoritative conversion ruleset, read
`docs/SOURCE_3D_CONVERSION_PROMPT.md` directly — this file is a
working index/summary for engineering use, not a replacement.

## What changes vs. the source document

| Source doc (2D/Unity) | This project (3D/Python) |
|---|---|
| Unity 6 LTS | Panda3D (Python) for the game engine, Blender + Blender Python for content |
| C# | Python 3.11+ |
| 2D top-down orthographic camera | First-person 3D camera, adjustable FOV, optional head bob |
| Sprite Renderer / Tilemap / Rule Tile | Real 3D meshes (GLB), built via Blender Python scripts |
| 2D Lights / Shadow Caster 2D | Panda3D dynamic lights + shadows |
| Cinemachine 2D | Panda3D camera rig (first-person, no Cinemachine equivalent needed) |
| Netcode for GameObjects / Unity Transport / Lobby / Relay | FastAPI (HTTP/WebSocket) for lobby/chat/management + a to-be-built low-level real-time protocol for in-mission state (see docs/STATUS.md) |
| Vivox Voice and Text Chat | Abstracted `VoiceChatService` interface; text chat is the Phase-0/1 priority (see docs/SOURCE_3D_CONVERSION_PROMPT.md §12) |
| Sprite Atlas / Sorting Layers | N/A (real 3D depth, no sprite sorting) |
| ScriptableObject catalogs | Python `dataclass`/Pydantic data catalogs under `data/catalogs/` |
| 8-directional character sprites | Full 3D character rig (skeletal animation), not yet modeled — see docs/STATUS.md |
| Windows .exe via Unity Build | Panda3D-recommended packaging path targeting `builds/windows/` (not yet implemented — Phase 7) |

## What does NOT change

Every number and named entity from the source document is binding and
must appear in the final project exactly as listed there:

- **8** main maps (Hollow Creek Residence, Grey Pines Motel, Morrow
  Farmstead, Blackridge High School, Northvale Memorial Hospital,
  Bellweather Grand Hotel, Kestrel Underground Research Site, Crimson
  Crown Palace) with their listed unlock levels and room lists.
- **20** paranormal entity appearances (The Charred, The Drowned, The
  Porcelain Face, The Tall Hollow, The Shadow Body, The Decayed Noble,
  The Orderly, The Caretaker, The Veiled Figure, The Faceless, The Bone
  Frame, The Mirrored Double, The Stitched, The Bent Crawler, The Ashen
  Pilgrim, The Forgotten Guard, The Court Figure, The Mechanic, The
  Wrapped, The Translucent Echo).
- **8** research caravans (Nordmark Kestrel C1 through Specter Horizon
  Prime) with their listed levels, prices, and slot counts.
- **8** personal vehicles (Nordmark Kestrel Touring through Specter
  Horizon GT).
- **32** equipment items, with the ItemInstanceID ownership model
  (Available / InMission / Returned / Lost / Consumed /
  LockedInScanner) unchanged.
- Hunt system states (Dormant, Aware, Disturbance, Manifestation,
  Stalking, HuntPreparation, Hunt, Search, Cooldown, Enraged,
  FinalEncounter).
- Equipment Recovery Phase durations by difficulty (120s / 90s / 75s /
  60s / 45s / 30s for Beginner through Unwritten).
- Economy, leveling (max 100, Prestige 10), Challenge Mode unlock
  levels (15 / full editor at 40).
- All 12 target languages, with Turkish + English as the first fully
  complete pair.

## Current implementation status

See `docs/STATUS.md` for the authoritative, continuously-updated
answer to "what is actually built right now." As of Phase 0
completion, the game systems above are **designed and catalogued in
this document**, but only a small vertical slice (physics, first-person
movement, one procedurally-generated test room, one FastAPI health
endpoint) is actually running code. Do not read this file as a claim
that the full 68-map/entity/equipment content set already exists.
