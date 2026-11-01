"""Neutral toolpath intermediate representation (IR) for MultiCam posts."""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Literal

Units = Literal["inch", "mm"]
MoveType = Literal["line", "arc_cw", "arc_ccw"]
DialectName = Literal["classic", "a2mc"]


@dataclass(frozen=True)
class Point2D:
    x: float
    y: float


@dataclass(frozen=True)
class Move:
    """Absolute endpoint move. Arcs also carry absolute center (I/J style)."""

    type: MoveType
    x: float
    y: float
    i: float | None = None
    j: float | None = None

    def __post_init__(self) -> None:
        if self.type in ("arc_cw", "arc_ccw") and (self.i is None or self.j is None):
            raise ValueError(f"{self.type} requires absolute center i/j")


@dataclass(frozen=True)
class Contour:
    start: Point2D
    depth: float
    moves: tuple[Move, ...]


@dataclass(frozen=True)
class Operation:
    tool: int
    contours: tuple[Contour, ...]
    spindle_rpm: int | None = None
    feed_ipm: float = 120.0
    plunge_ipm: float = 60.0


@dataclass(frozen=True)
class ToolpathProgram:
    """CAM-neutral program consumed by dialect writers."""

    program_name: str
    units: Units
    operations: tuple[Operation, ...]
    spindle_rpm: int = 18000
    # Classic MultiCam Z sense: negative = above sheet, positive = into material.
    safe_z: float = -0.5
    approach_z: float = -0.1
    home_xy: Point2D = field(default_factory=lambda: Point2D(0.0, 0.0))
    comment: str = ""

    def __post_init__(self) -> None:
        if not self.operations:
            raise ValueError("program requires at least one operation")
        for op in self.operations:
            if op.tool < 1:
                raise ValueError(f"tool number must be >= 1, got {op.tool}")
            if not op.contours:
                raise ValueError(f"operation tool {op.tool} has no contours")


def _require(data: dict[str, Any], key: str) -> Any:
    if key not in data:
        raise ValueError(f"missing required field: {key}")
    return data[key]


def _parse_move(raw: dict[str, Any]) -> Move:
    move_type = _require(raw, "type")
    if move_type not in ("line", "arc_cw", "arc_ccw"):
        raise ValueError(f"unsupported move type: {move_type}")
    return Move(
        type=move_type,
        x=float(_require(raw, "x")),
        y=float(_require(raw, "y")),
        i=float(raw["i"]) if "i" in raw and raw["i"] is not None else None,
        j=float(raw["j"]) if "j" in raw and raw["j"] is not None else None,
    )


def _parse_contour(raw: dict[str, Any]) -> Contour:
    start = _require(raw, "start")
    moves = tuple(_parse_move(m) for m in _require(raw, "moves"))
    if not moves:
        raise ValueError("contour requires at least one move")
    return Contour(
        start=Point2D(float(_require(start, "x")), float(_require(start, "y"))),
        depth=float(_require(raw, "depth")),
        moves=moves,
    )


def _parse_operation(raw: dict[str, Any]) -> Operation:
    contours = tuple(_parse_contour(c) for c in _require(raw, "contours"))
    return Operation(
        tool=int(_require(raw, "tool")),
        contours=contours,
        spindle_rpm=int(raw["spindle_rpm"]) if raw.get("spindle_rpm") is not None else None,
        feed_ipm=float(raw.get("feed_ipm", 120.0)),
        plunge_ipm=float(raw.get("plunge_ipm", 60.0)),
    )


def toolpath_from_dict(data: dict[str, Any]) -> ToolpathProgram:
    units = data.get("units", "inch")
    if units not in ("inch", "mm"):
        raise ValueError("units must be 'inch' or 'mm'")
    home = data.get("home_xy") or {"x": 0.0, "y": 0.0}
    operations = tuple(_parse_operation(op) for op in _require(data, "operations"))
    return ToolpathProgram(
        program_name=str(data.get("program_name", "job")),
        units=units,
        operations=operations,
        spindle_rpm=int(data.get("spindle_rpm", 18000)),
        safe_z=float(data.get("safe_z", -0.5)),
        approach_z=float(data.get("approach_z", -0.1)),
        home_xy=Point2D(float(home.get("x", 0.0)), float(home.get("y", 0.0))),
        comment=str(data.get("comment", "")),
    )


def load_toolpath(path: str | Path) -> ToolpathProgram:
    payload = json.loads(Path(path).read_text(encoding="utf-8"))
    if not isinstance(payload, dict):
        raise ValueError("toolpath JSON must be an object")
    return toolpath_from_dict(payload)


def ipm_to_ips(ipm: float) -> float:
    """Convert inches-per-minute to inches-per-second (classic MultiCam F)."""
    return ipm / 60.0
