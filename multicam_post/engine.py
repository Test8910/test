"""Post-process orchestration."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from multicam_post.dialects import get_writer
from multicam_post.ir import DialectName, ToolpathProgram, load_toolpath


@dataclass(frozen=True)
class PostResult:
    dialect: str
    gcode: str
    warnings: tuple[str, ...]
    extension: str

    def write(self, path: str | Path) -> Path:
        out = Path(path)
        out.write_text(self.gcode, encoding="utf-8")
        return out


def post_process(
    program: ToolpathProgram,
    dialect: DialectName | str = "classic",
) -> PostResult:
    writer = get_writer(str(dialect))
    warnings = tuple(writer.validate(program))
    gcode = writer.emit(program)
    return PostResult(
        dialect=writer.name,
        gcode=gcode,
        warnings=warnings,
        extension=writer.file_extension,
    )


def post_process_file(
    input_path: str | Path,
    dialect: DialectName | str = "classic",
    output_path: str | Path | None = None,
) -> PostResult:
    program = load_toolpath(input_path)
    result = post_process(program, dialect=dialect)
    if output_path is not None:
        result.write(output_path)
    return result
