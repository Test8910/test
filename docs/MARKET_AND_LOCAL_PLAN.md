# MultiCam Post Processor — Market Survey & Local-First Plan

**Constraint:** local only for now (offline CLI / desktop; no cloud posting).

## Market: what exists today

MultiCam does **not** ship a standalone universal post. Shops get G-code from **CAM + a machine-specific post**.

| Option | MultiCam path | Local? | Notes |
|--------|---------------|--------|-------|
| **EnRoute** | Custom `.pst` / `.cnf` | Yes | Often the shop “golden” reference |
| **Fusion / Inventor CAM** | Autodesk `.cps` (MultiCam ISO, HPGL, Aus variants) | Yes | Frequently needs header/footer/IJK edits |
| **Mastercam / Alphacam / CamWorks** | Dealer / UPG posts | Yes | Production; posts often opaque |
| **MecSoft** | Downloadable `.spm` (MultiCam, A2MC, knife) | Yes | Tied to MecSoft CAM |
| **Mozaik / Planit / KitchenCAD** | Vertical cabinet posts | Yes | Tool mapping critical |
| **Coreo / ProNest / SigmaNEST** | Via MultiCam software channel | Yes | Nesting / profile focused |
| **Open-source CAM** (DerpCAM, ivaCAM) | GRBL/LinuxCNC; ivaCAM can run `.cps` | Yes | No trusted MultiCam dialect built-in |
| **Controller apps** | JobServer / A2MC Toolpath / File Manager | On machine PC | **Consume** NC; they are not posts |

### Controller dialects that matter

1. **Classic JobServer** — `M90`, `G75`, `G97 S`, `G00 T#`, `M12`/`M22`, absolute `I`/`J`, often Z+ into material, F in in/sec, `.cnc`
2. **A2MC / AMC** — non-modal Fanuc-ish; `M12`/`M0`; `T01`–`T08`; Toolpath required to send to machine; jobs as `.nc` on network share
3. **Knife / HPGL** — separate appliance dialects (out of scope for v1)

**Market gap:** there is no widely trusted open MultiCam-faithful post. Teams reverse-engineer from EnRoute goldens or tweak Fusion `.cps`.

## What is possible (local)

| Approach | Local? | Effort | Recommendation |
|----------|--------|--------|----------------|
| **A. Own open post** (IR → MultiCam NC) | Yes | Medium | **Do this** — full control |
| **B. Edit Fusion `.cps`** | Yes | Low–med | Parallel if shop already on Fusion |
| **C. Buy EnRoute + vendor post** | Yes | $ / low tech | Fastest cut path; closed |
| **D. Import shop G-code → re-post** | Yes | Med–high | Migration + golden tests |
| **E. Full CAM inside our tool** | Partial | High | **Defer** |
| **F. Cloud / SaaS post** | Later | — | **Out of scope now** |

**Strategy:** keep CAM elsewhere; own the last mile locally: toolpath IR → validated `.cnc`/`.nc` → USB or A2MC `jobs` folder.

## Local-first plan (build order)

| Phase | Deliverable | Done when |
|-------|-------------|-----------|
| **0** | Machine profile (controller, units, ATC, Z sense, feeds, extension) | One checked-in YAML |
| **1** | Golden files from EnRoute/current CAM (square, arc, tool change, multi-contour) | Diff/structure harness |
| **2** | Harden classic + a2mc writers vs goldens | Semantic match on goldens |
| **3** | Local CLI UX (`pip install`, `--dry-run`, offline) | Works with network off |
| **4** | Importers: JSON first; optional generic G-code / simple DXF | Post without cloud |
| **5** | Profiles: `jobserver-inch`, `a2mc-mm`, tools 1–8 | `-p profile` switch |
| **6** | Shop air-cut / Toolpath sim sign-off | Operator checklist |

### Non-goals now

- Cloud posting, accounts, telemetry  
- Full CAM / nesting / pocket strategies  
- Knife / plasma / HPGL until router dialect is locked  
- “Official MultiCam certified” claims without golden-file proof  

## Decisions before more code

1. Controller: JobServer vs A2MC (or both, one primary)?  
2. Units: inch or mm?  
3. ATC vs manual tool change?  
4. Can you supply a golden EnRoute (or shop) NC file?  
5. CAM source for IR: hand JSON vs Fusion/generic G-code import?

## Relation to current repo

Scaffold already exists (`multicam_post`: JSON IR → classic/a2mc CLI). Next work should **not** add cloud — freeze a machine profile and harden against goldens.
