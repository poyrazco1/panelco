"""FastAPI application entrypoint.

This is the real backend process boundary described in
docs/MASTER_3D_SPEC.md section 15: lobby, authentication, text chat,
and management run over FastAPI HTTP/WebSocket, while real-time
in-mission game state uses a separate low-level protocol (not yet
implemented -- see docs/STATUS.md).

Phase 0 scope is intentionally small: prove the process boundary works
end-to-end (starts, binds a port, answers a real HTTP request) before
building authoritative gameplay endpoints on top of it.
"""
from __future__ import annotations

from fastapi import FastAPI
from pydantic import BaseModel

from game.core.logging_setup import get_logger

logger = get_logger("server.app")

app = FastAPI(title="CASE EIGHT: UNWRITTEN Backend", version="0.1.0")


class HealthResponse(BaseModel):
    status: str
    service: str
    version: str


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse(status="ok", service="case-eight-unwritten-backend", version="0.1.0")


@app.get("/")
def root() -> dict:
    return {"name": "CASE EIGHT: UNWRITTEN Backend", "docs": "/docs"}
