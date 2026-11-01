# MultiCam CNC Post Processor — Plan

## Goal

Build a CAM-neutral **post processor** that turns structured toolpaths into **MultiCam-compatible `.cnc` G-code** for CNC routers/cutters.

## Why a custom post

MultiCam controllers do not accept arbitrary Fanuc posts. They expect specific program framing, shape delimiters, tool calls, and (on classic JobServer) an inverted Z sense and feed rates in inches/second.

## Architecture

```
CAM / CAD export  →  Toolpath IR (JSON)  →  Post engine  →  Dialect writer  →  .cnc
```

| Stage | Role |
|-------|------|
| IR load | Validate units, tools, contours, feeds |
| Normalize | Z convention, feed IPM↔IPS, tool numbering |
| Emit | Controller-specific header / moves / footer |
| Validate | M12 pairing, unsupported codes, dry-run report |

## Dialects (v1)

### Classic JobServer

Matches published MultiCam JobServer samples:

- Start: `M90`, `G90`, `G75`, `G97 S####`, `G00 T#`
- Shape: `M12` … moves … retract … `M22`
- Arcs: `G02`/`G03` with **absolute** `I`/`J` centers
- End: `M02`
- Z: **positive into material** (e.g. `Z-0.5` = 0.5" above sheet)
- F: typically **inches/second** (`F3.333` ≈ 200 IPM)

### A2MC / AMC (AU Toolpath)

Matches Multicam Australia AMC importer guidance:

- Non-modal `G00`/`G01`/`G02`/`G03`
- Absolute `G90` (I/J absolute when coords are absolute)
- Shape: `M12` start, `M0` end
- Tools: `T01`–`T08`
- Appliance M-codes often ignored by Toolpath importer

## IR (v1)

See `multicam_post/examples/*.json` and `multicam_post/ir.py`.

## Phases

| Phase | Scope |
|-------|--------|
| **P0** | IR + classic + a2mc writers + CLI |
| **P1** | Examples + unit tests |
| **P2** | Optional generic G-code → IR importer |
| **P3** | Machine profile YAML (table, ATC, Z sense) |

## Safety

Dry-run on the controller or simulator before cutting. Confirm tool map, Z origin, and feed-unit settings for the specific machine.
