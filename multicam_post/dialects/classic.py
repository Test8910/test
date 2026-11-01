"""Classic MultiCam JobServer dialect (M90/M12/M22/M02)."""

from __future__ import annotations

from multicam_post.formatters import g_code, m_code, word
from multicam_post.ir import Contour, Move, Operation, ToolpathProgram, ipm_to_ips

from .base import DialectWriter


class ClassicWriter(DialectWriter):
    """
    Emits JobServer-style MultiCam NC.

    Z sense matches published samples: negative Z is above the sheet,
    positive Z is cut depth into the material. Feed words are inches/second.
    Arc I/J are absolute centers.
    """

    name = "classic"

    def emit(self, program: ToolpathProgram) -> str:
        lines: list[str] = []
        title = program.program_name.replace("\n", " ")
        lines.append(f"; MultiCam classic post — {title}")
        if program.comment:
            lines.append(f"; {program.comment.replace(chr(10), ' ')}")
        lines.append(f"; units={program.units}")
        lines.append(m_code(90))
        lines.append(g_code(90))
        lines.append(g_code(75))

        for op in program.operations:
            lines.extend(self._emit_operation(program, op))

        lines.append(
            f"{g_code(0)} {word('X', program.home_xy.x)} {word('Y', program.home_xy.y)}"
        )
        lines.append(m_code(2))
        lines.append("")
        return "\n".join(lines)

    def _emit_operation(self, program: ToolpathProgram, op: Operation) -> list[str]:
        rpm = op.spindle_rpm if op.spindle_rpm is not None else program.spindle_rpm
        plunge_f = ipm_to_ips(op.plunge_ipm)
        cut_f = ipm_to_ips(op.feed_ipm)
        lines: list[str] = [
            f"; op tool={op.tool} spindle={rpm} plunge={op.plunge_ipm}ipm cut={op.feed_ipm}ipm",
            f"{g_code(97)} S{int(rpm)}",
            f"{g_code(0)} T{op.tool}",
            f"{g_code(0)} {word('Z', program.safe_z)}",
        ]
        for contour in op.contours:
            lines.extend(self._emit_contour(program, contour, plunge_f, cut_f))
        return lines

    def _emit_contour(
        self,
        program: ToolpathProgram,
        contour: Contour,
        plunge_f: float,
        cut_f: float,
    ) -> list[str]:
        sx, sy = contour.start.x, contour.start.y
        lines = [
            f"{g_code(0)} {word('X', sx)} {word('Y', sy)}",
            m_code(12),
            f"{g_code(0)} {word('Z', program.approach_z)}",
            f"{g_code(1)} {word('Z', contour.depth)} {word('F', plunge_f, 3)}",
        ]
        for move in contour.moves:
            lines.append(self._emit_move(move, cut_f))
        lines.append(f"{g_code(0)} {word('Z', program.safe_z)}")
        lines.append(m_code(22))
        return lines

    def _emit_move(self, move: Move, cut_f: float) -> str:
        if move.type == "line":
            return (
                f"{g_code(1)} {word('X', move.x)} {word('Y', move.y)} "
                f"{word('F', cut_f, 3)}"
            )
        code = g_code(2) if move.type == "arc_cw" else g_code(3)
        assert move.i is not None and move.j is not None
        # Full circles may omit X/Y when endpoint == start; still emit XY for clarity.
        return (
            f"{code} {word('X', move.x)} {word('Y', move.y)} "
            f"{word('I', move.i)} {word('J', move.j)} {word('F', cut_f, 3)}"
        )

    def validate(self, program: ToolpathProgram) -> list[str]:
        warnings = super().validate(program)
        if program.units == "mm":
            warnings.append(
                "classic dialect samples are inch-based; confirm controller units "
                f"(program units={program.units})"
            )
        return warnings
