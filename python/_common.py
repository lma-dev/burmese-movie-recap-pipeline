"""
Shared utilities for the Burmese Movie Recap Pipeline python scripts.

Every script reads its config from a JSON payload passed on stdin so the
Laravel side has one consistent way to talk to Python. Every script
writes a single JSON result line to stdout on success, or exits non-zero
with an error message on stderr.
"""
from __future__ import annotations

import json
import os
import sys
from typing import Any


def read_payload() -> dict[str, Any]:
    """Read the JSON payload Laravel writes to stdin."""
    raw = sys.stdin.read()
    if not raw.strip():
        die("empty stdin payload")
    try:
        return json.loads(raw)
    except json.JSONDecodeError as exc:
        die(f"invalid JSON payload on stdin: {exc}")


def write_result(payload: dict[str, Any]) -> None:
    """Write a single JSON line to stdout (the success channel)."""
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    sys.stdout.write("\n")
    sys.stdout.flush()


def progress(stage: str, value: int, **extra: Any) -> None:
    """
    Emit a progress event Laravel can tail. Each progress line is a
    self-contained JSON object on its own line, prefixed with `@@PROGRESS@@`
    so the orchestrator can distinguish progress lines from the final
    result line.
    """
    line = {"stage": stage, "value": max(0, min(100, int(value))), **extra}
    sys.stderr.write("@@PROGRESS@@" + json.dumps(line, ensure_ascii=False) + "\n")
    sys.stderr.flush()


def die(message: str, code: int = 1) -> None:
    sys.stderr.write(f"ERROR: {message}\n")
    sys.exit(code)


def env(key: str, default: str | None = None) -> str | None:
    """Convenience wrapper around os.environ.get."""
    val = os.environ.get(key)
    return val if val not in (None, "") else default


def require_env(key: str) -> str:
    val = env(key)
    if not val:
        die(f"missing required env var: {key}")
    return val  # type: ignore[return-value]


def ensure_dir(path: str) -> str:
    os.makedirs(path, exist_ok=True)
    return path
