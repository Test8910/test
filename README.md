# MultiCam CNC Post Processor

Python post processor that converts a CAM-neutral **toolpath JSON IR** into **MultiCam `.cnc` G-code** for:

- **classic** — MultiCam JobServer (`M90` / `M12` / `M22` / `M02`, absolute `I`/`J`, feed in in/sec)
- **a2mc** — Multicam AMC/A2MC Toolpath (`M12` / `M0`, non-modal moves, `T01`–`T08`)

Plan: [`docs/PLAN.md`](docs/PLAN.md)

## Install

```bash
pip install -e ".[dev]"
```

## Usage

```bash
# Classic JobServer dialect (default)
multicam-post multicam_post/examples/square.json -o square.cnc

# A2MC / AMC dialect
multicam-post multicam_post/examples/circle.json -d a2mc -o circle_a2mc.cnc

# Print to stdout
multicam-post multicam_post/examples/multi_tool.json --stdout
```

## Toolpath IR (JSON)

```json
{
  "program_name": "five_inch_square",
  "units": "inch",
  "spindle_rpm": 18000,
  "safe_z": -0.5,
  "approach_z": -0.1,
  "operations": [
    {
      "tool": 1,
      "plunge_ipm": 60,
      "feed_ipm": 200,
      "contours": [
        {
          "start": { "x": 0.0, "y": 0.0 },
          "depth": 0.25,
          "moves": [
            { "type": "line", "x": 5.0, "y": 0.0 },
            { "type": "line", "x": 5.0, "y": 5.0 },
            { "type": "line", "x": 0.0, "y": 5.0 },
            { "type": "line", "x": 0.0, "y": 0.0 }
          ]
        }
      ]
    }
  ]
}
```

### Classic Z / feed notes

On classic MultiCam JobServer samples:

- **Z+** is into the material; **Z-** is above the sheet (`Z-0.5` ≈ 0.5" clearance).
- Contour `depth` is a positive cut depth (e.g. `0.25`).
- `feed_ipm` / `plunge_ipm` are converted to **inches/second** in the classic dialect (`200 IPM` → `F3.333`).

Always dry-run on the controller and confirm tool mapping before cutting.

## Library API

```python
from multicam_post import load_toolpath, post_process

program = load_toolpath("multicam_post/examples/square.json")
result = post_process(program, dialect="classic")
print(result.gcode)
```

## Tests

```bash
pytest -q
```

## Examples

| File | Description |
|------|-------------|
| `multicam_post/examples/square.json` | 5" square, tool 1 |
| `multicam_post/examples/circle.json` | 5" CW circle, tool 3, absolute I/J |
| `multicam_post/examples/multi_tool.json` | Square then circle (T1 + T3) |
