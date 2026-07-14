from fastapi.testclient import TestClient

from server.app.main import app

client = TestClient(app)


def test_health_endpoint_returns_ok():
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["service"] == "case-eight-unwritten-backend"


def test_root_endpoint():
    response = client.get("/")
    assert response.status_code == 200


def test_openapi_docs_available():
    response = client.get("/docs")
    assert response.status_code == 200
