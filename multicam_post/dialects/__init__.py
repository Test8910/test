"""MultiCam controller dialect writers."""

from .a2mc import A2MCWriter
from .base import DialectWriter
from .classic import ClassicWriter

DIALECTS: dict[str, type[DialectWriter]] = {
    "classic": ClassicWriter,
    "a2mc": A2MCWriter,
}


def get_writer(name: str) -> DialectWriter:
    key = name.lower().strip()
    if key not in DIALECTS:
        supported = ", ".join(sorted(DIALECTS))
        raise ValueError(f"unknown dialect '{name}'; expected one of: {supported}")
    return DIALECTS[key]()


__all__ = ["A2MCWriter", "ClassicWriter", "DialectWriter", "DIALECTS", "get_writer"]
