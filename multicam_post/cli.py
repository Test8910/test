"""Command-line interface for the MultiCam post processor."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from multicam_post import __version__
from multicam_post.dialects import DIALECTS
from multicam_post.engine import post_process_file


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="multicam-post",
        description=(
            "Post-process CAM toolpath JSON into MultiCam .cnc G-code "
            "(classic JobServer or A2MC/AMC)."
        ),
    )
    parser.add_argument(
        "input",
        type=Path,
        help="Path to toolpath IR JSON",
    )
    parser.add_argument(
        "-d",
        "--dialect",
        choices=sorted(DIALECTS.keys()),
        default="classic",
        help="Controller dialect (default: classic)",
    )
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        default=None,
        help="Output .cnc path (default: <input_stem>_<dialect>.cnc)",
    )
    parser.add_argument(
        "--stdout",
        action="store_true",
        help="Print G-code to stdout instead of writing a file",
    )
    parser.add_argument(
        "--strict",
        action="store_true",
        help="Treat validation warnings as errors",
    )
    parser.add_argument(
        "-V",
        "--version",
        action="version",
        version=f"%(prog)s {__version__}",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    if not args.input.is_file():
        print(f"error: input not found: {args.input}", file=sys.stderr)
        return 2

    try:
        result = post_process_file(args.input, dialect=args.dialect)
    except Exception as exc:  # noqa: BLE001 - CLI boundary
        print(f"error: {exc}", file=sys.stderr)
        return 1

    for warning in result.warnings:
        print(f"warning: {warning}", file=sys.stderr)
    if args.strict and result.warnings:
        print("error: strict mode — aborting due to warnings", file=sys.stderr)
        return 3

    if args.stdout:
        sys.stdout.write(result.gcode)
        return 0

    out = args.output
    if out is None:
        out = args.input.with_name(f"{args.input.stem}_{result.dialect}{result.extension}")
    result.write(out)
    print(f"wrote {out} ({result.dialect})", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
