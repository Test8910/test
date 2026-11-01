"""Shared numeric formatting for MultiCam G-code lines."""

from __future__ import annotations


def fmt_num(value: float, decimals: int = 4) -> str:
    """Format a float without trailing zeros, keeping a decimal point for integers."""
    text = f"{value:.{decimals}f}".rstrip("0").rstrip(".")
    if "." not in text:
        text += "."
    return text


def word(letter: str, value: float, decimals: int = 4) -> str:
    return f"{letter}{fmt_num(value, decimals)}"


def g_code(n: int) -> str:
    return f"G{n:02d}"


def m_code(n: int) -> str:
    return f"M{n:02d}" if n < 100 else f"M{n}"
