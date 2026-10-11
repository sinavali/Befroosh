---
id: product:befroosh/agent
scope: befroosh
level: product
kind: agent
is_org_root: true
skill: docs
excluded_paths: [.git/, node_modules/, dist/, build/, .cache/, coverage/, .agents/]
included_paths: []
---

# Befroosh — Agent Router

`README.md` is human-facing and is not normative for agents.

## Skill (MUST)
Invoke the `docs` skill before reading or writing any doc.
Modes: read, index, validate, extend, init, repair.

## Upward reading order (MUST)
1. This file
2. docs/INDEX.md
3. docs/002-blueprint/INDEX.md

## Doc map
| Purpose | Path |
|---|---|
| Global Map | docs/INDEX.md |
| Product Blueprint | docs/002-blueprint/INDEX.md |
| Architecture Decisions | docs/003-decisions/INDEX.md |
| API & Service Contracts | docs/004-contracts/INDEX.md |
| Cross-Cutting Concerns | docs/005-cross-cutting/INDEX.md |
| Operations & Runtime | docs/006-operational/INDEX.md |
| User & System Flows | docs/007-flows/INDEX.md |

## Index protocol (MUST)
Build an index of doc **paths**, not contents. Load contents only when a task requires them.

## File-level docs (MUST)
When modifying any source file, check for `<filename>.md` in the same directory.

## Excluded paths
Tool-generated, VCS, and build paths. Do not create docs inside them:
`.git/`, `node_modules/`, `dist/`, `build/`, `.cache/`, `coverage/`, `.agents/`.

## Directory map
| Path | Purpose | Rules |
|---|---|---|
| docs/002-blueprint/ | Product intent, user personas, non-goals, and core invariants | Edit in place |
| docs/003-decisions/ | Immutable Architectural Decision Records (ADRs) | Append-only |
| docs/004-contracts/ | Formal API, schema, and interface contracts | Strict compatibility |
| docs/005-cross-cutting/ | Cross-cutting policies (Security, Multi-tenancy, Quotas) | Broad applicability |
| docs/006-operational/ | Environment setup, background workers, and deployments | Runbook instructions |
| docs/007-flows/ | Cross-domain user journey workflows | Flow participation |

---

## Mandatory Impact Workflow

For any non-trivial code change, this repository requires the following sequence:

1. Determine the affected flow or domain.
2. Consult the documentation graph (`docs/INDEX.md`, then `modes/index.md` metadata).
3. Follow documentation relationships until the impact surface is closed.
4. Resolve affected `code_paths` and `test_paths`.
5. Produce a **Change Surface** (`templates/change-surface.md`).
6. Do not implement before the Change Surface is complete.
7. The reviewer MUST verify coverage against the Change Surface.

### Before Modifying Any Source File

1. Check for a sibling `<filename>.md` file-level doc. Read it first.
2. Confirm the file is inside a `code_paths` glob declared by a doc in the Change Surface. If not, the surface is incomplete.
