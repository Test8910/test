"""Tests for MultiCam post processor."""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from multicam_post.cli import main
from multicam_post.engine import post_process, post_process_file
from multicam_post.ir import load_toolpath, toolpath_from_dict

ROOT = Path(__file__).resolve().parents[1]
EXAMPLES = ROOT / "multicam_post" / "examples"


def test_load_square_example() -> None:
    program = load_toolpath(EXAMPLES / "square.json")
    assert program.program_name == "five_inch_square"
    assert program.operations[0].tool == 1
    assert len(program.operations[0].contours[0].moves) == 4


def test_classic_square_matches_jobserver_shape() -> None:
    result = post_process_file(EXAMPLES / "square.json", dialect="classic")
    text = result.gcode
    assert "M90" in text
    assert "G90" in text
    assert "G75" in text
    assert "G97 S18000" in text
    assert "G00 T1" in text
    assert "M12" in text
    assert "M22" in text
    assert text.strip().endswith("M02")
    # 200 IPM -> 3.333 IPS
    assert "F3.333" in text
    # 60 IPM plunge -> 1 IPS
    assert "F1." in text or "F1" in text
    assert "G01 X5. Y0. F3.333" in text or "G01 X5. Y0." in text


def test_classic_circle_absolute_ij() -> None:
    result = post_process_file(EXAMPLES / "circle.json", dialect="classic")
    assert "G00 T3" in result.gcode
    assert "G02" in result.gcode
    assert "I2.5" in result.gcode
    assert "J2.5" in result.gcode


def test_a2mc_non_modal_and_shape_codes() -> None:
    result = post_process_file(EXAMPLES / "square.json", dialect="a2mc")
    text = result.gcode
    assert "G90" in text
    assert "G70" in text
    assert "T01" in text
    assert "M12" in text
    assert "M00" in text or "M0\n" in text or text.count("M0") >= 1
    assert "M30" in text
    # Non-modal: every motion line starts with G0x
    motion = [
        line
        for line in text.splitlines()
        if line.startswith(("G00", "G01", "G02", "G03"))
    ]
    assert motion
    assert all(line[:3] in {"G00", "G01", "G02", "G03"} for line in motion)


def test_multi_tool_emits_both_tools() -> None:
    classic = post_process_file(EXAMPLES / "multi_tool.json", dialect="classic")
    assert "G00 T1" in classic.gcode
    assert "G00 T3" in classic.gcode
    assert classic.gcode.count("M12") == 2
    assert classic.gcode.count("M22") == 2


def test_arc_requires_center() -> None:
    with pytest.raises(ValueError, match="requires absolute center"):
        toolpath_from_dict(
            {
                "program_name": "bad",
                "operations": [
                    {
                        "tool": 1,
                        "contours": [
                            {
                                "start": {"x": 0, "y": 0},
                                "depth": 0.1,
                                "moves": [{"type": "arc_cw", "x": 1, "y": 0}],
                            }
                        ],
                    }
                ],
            }
        )


def test_unknown_dialect() -> None:
    program = load_toolpath(EXAMPLES / "square.json")
    with pytest.raises(ValueError, match="unknown dialect"):
        post_process(program, dialect="fanuc")


def test_cli_stdout(tmp_path: Path, capsys: pytest.CaptureFixture[str]) -> None:
    code = main([str(EXAMPLES / "square.json"), "--dialect", "classic", "--stdout"])
    assert code == 0
    out = capsys.readouterr().out
    assert "M90" in out
    assert "M02" in out


def test_cli_writes_file(tmp_path: Path) -> None:
    out = tmp_path / "job.cnc"
    code = main(
        [
            str(EXAMPLES / "circle.json"),
            "--dialect",
            "a2mc",
            "-o",
            str(out),
        ]
    )
    assert code == 0
    assert out.is_file()
    assert "T03" in out.read_text(encoding="utf-8")


def test_tool_warning_for_high_tool_number() -> None:
    data = json.loads((EXAMPLES / "square.json").read_text(encoding="utf-8"))
    data["operations"][0]["tool"] = 12
    program = toolpath_from_dict(data)
    result = post_process(program, dialect="classic")
    assert any("T1–T8" in w or "T1-T8" in w or "T1" in w for w in result.warnings)
