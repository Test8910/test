"""A2MC / AMC (AU Multicam Toolpath) dialect."""

from __future__ import annotations

from multicam_post.formatters import g_code, m_code, word
from multicam_post.ir import Contour, Move, Operation, ToolpathProgram

from .base import DialectWriter


class A2MCWriter(DialectWriter):
    """
    Emits non-modal Fanuc-style NC suited to Multicam AMC/A2MC Toolpath import.

    Shape delimiters: M12 start, M0 end.
    Tools: T01–T08 style.
    Arc I/J: absolute when program is G90 (per Multicam AU NC notes).
    Feed words keep IPM (or mm/min) as provided in the IR feed_ipm field —
    rename semantically as feed_per_min for the active units.
    """

    name = "a2mc"

    def emit(self, program: ToolpathProgram) -> str:
        lines: list[str] = []
        title = program.program_name.replace("\n", " ")
        lines.append(f"(MultiCam A2MC post - {title})")
        if program.comment:
            lines.append(f"({program.comment.replace(chr(10), ' ')})")
        lines.append(f"(units={program.units})")
        lines.append(g_code(90))
        if program.units == "inch":
            lines.append(g_code(70))
        else:
            lines.append(g_code(71))

        for op in program.operations:
            lines.extend(self._emit_operation(program, op))

        lines.append(
            f"{g_code(0)} {word('X', program.home_xy.x)} {word('Y', program.home_xy.y)} "
            f"{word('Z', program.safe_z)}"
        )
        lines.append(m_code(30))
        lines.append("")
        return "\n".join(lines)

    def _tool_word(self, tool: int) -> str:
        return f"T{tool:02d}"

    def _emit_operation(self, program: ToolpathProgram, op: Operation) -> list[str]:
        lines: list[str] = [
            f"(op tool={op.tool} plunge={op.plunge_ipm}/min cut={op.feed_ipm}/min)",
            self._tool_word(op.tool),
            f"{g_code(0)} {word('Z', program.safe_z)}",
        ]
        for contour in op.contours:
            lines.extend(self._emit_contour(program, contour, op))
        return lines

    def _emit_contour(
        self, program: ToolpathProgram, contour: Contour, op: Operation
    ) -> list[str]:
        sx, sy = contour.start.x, contour.start.y
        lines = [
            m_code(12),
            f"{g_code(0)} {word('X', sx)} {word('Y', sy)} {word('Z', program.safe_z)}",
            f"{g_code(0)} {word('X', sx)} {word('Y', sy)} {word('Z', program.approach_z)}",
            (
                f"{g_code(1)} {word('X', sx)} {word('Y', sy)} {word('Z', contour.depth)} "
                f"{word('F', op.plunge_ipm, 3)}"
            ),
        ]
        for move in contour.moves:
            lines.append(self._emit_move(move, op.feed_ipm, z=contour.depth))
        lines.append(
            f"{g_code(0)} {word('X', move_end_x(contour))} {word('Y', move_end_y(contour))} "
            f"{word('Z', program.safe_z)}"
        )
        lines.append(m_code(0))
        return lines

    def _emit_move(self, move: Move, feed: float, z: float) -> str:
        if move.type == "line":
            return (
                f"{g_code(1)} {word('X', move.x)} {word('Y', move.y)} {word('Z', z)} "
                f"{word('F', feed, 3)}"
            )
        code = g_code(2) if move.type == "arc_cw" else g_code(3)
        assert move.i is not None and move.j is not None
        return (
            f"{code} {word('X', move.x)} {word('Y', move.y)} {word('Z', z)} "
            f"{word('I', move.i)} {word('J', move.j)} {word('F', feed, 3)}"
        )

    def validate(self, program: ToolpathProgram) -> list[str]:
        warnings = super().validate(program)
        for op in program.operations:
            if op.tool > 8:
                warnings.append(
                    f"A2MC Toolpath commonly maps T01–T08; tool {op.tool} may need remapping"
                )
        return warnings


def move_end_x(contour: Contour) -> float:
    return contour.moves[-1].x


def move_end_y(contour: Contour) -> float:
    return contour.moves[-1].y
