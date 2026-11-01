"""Base dialect writer interface."""

from __future__ import annotations

from abc import ABC, abstractmethod

from multicam_post.ir import ToolpathProgram


class DialectWriter(ABC):
    name: str
    file_extension: str = ".cnc"

    @abstractmethod
    def emit(self, program: ToolpathProgram) -> str:
        """Return full NC program text including newlines."""

    def validate(self, program: ToolpathProgram) -> list[str]:
        """Return human-readable warnings (non-fatal)."""
        warnings: list[str] = []
        for op in program.operations:
            if op.tool > 8:
                warnings.append(
                    f"tool {op.tool} exceeds common MultiCam map T1–T8; confirm machine mapping"
                )
            if op.feed_ipm <= 0 or op.plunge_ipm <= 0:
                warnings.append(f"tool {op.tool} has non-positive feed/plunge")
            for idx, contour in enumerate(op.contours, start=1):
                if contour.depth < 0:
                    warnings.append(
                        f"tool {op.tool} contour {idx}: negative depth "
                        f"({contour.depth}); classic MultiCam expects positive into material"
                    )
        if program.safe_z > program.approach_z:
            warnings.append(
                "safe_z is deeper than approach_z under classic Z sense "
                "(more positive = deeper into material)"
            )
        return warnings
