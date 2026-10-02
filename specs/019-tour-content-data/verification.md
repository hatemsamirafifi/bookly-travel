# Verification Evidence: Spec 019 Tour Content Data Model and API

**Owner**: Spec 019 task T001 (Setup: baseline verification).
**Created**: 2026-10-02.
**Scope**: This file records T001 baseline/dependency facts only. It claims no
implementation, runtime, CI, merge, or release acceptance. Tasks T002-T078
remain pending and are owned by later tasks.

**Traceability**:

- Spec: [spec.md](spec.md) (Spec `019`; UI master-plan Phase 2, Tasks 2.1-2.4)
- Plan: [plan.md](plan.md)
- Tasks: [tasks.md](tasks.md) (T001-T078; all pending at time of writing)
- Design: [research.md](research.md), [data-model.md](data-model.md),
  [quickstart.md](quickstart.md)
- Contracts: [public detail](contracts/tour-detail-api.md),
  [partner content](contracts/partner-tour-content-api.md),
  [generation/recovery](contracts/translation-generation.md)
- Quality gate: [requirements.md](checklists/requirements.md) (16/16 checked,
  0 unchecked; document readiness, not implementation acceptance)
- Governance: [Constitution v2.1.0](../../.specify/memory/constitution.md)
- Predecessor baseline: [Spec 018 spec](../018-bookly-ui-redesign/spec.md),
  [Spec 018 plan](../018-bookly-ui-redesign/plan.md)
- Program mapping:
  [master plan](../../docs/bookly-ui-redesign-spec-kit-master-plan.md),
  [PRD](../../docs/PRD.md),
  [Phase 2 plan](../../docs/Phase%202%20Implementation%20Plan.md)

## 1. Branch, HEAD, and Active Pointer (checked 2026-10-02)

| Fact | Observed value | Expected | Verdict |
|---|---|---|---|
| Current branch (`git branch --show-current`) | `codex/019-tour-content-data` | `codex/019-tour-content-data` | MATCH |
| HEAD (`git rev-parse HEAD`) | `84ae4e59b49a1603d59a624ff9706f17ff02338f` | `84ae4e5` (prefix of same commit) | MATCH |
| HEAD commit message/date | `docs: mark master-plan Phase 1 implementation complete` / 2026-10-01 | Inherited baseline commit | MATCH |
| Ancestry (`git merge-base --is-ancestor 84ae4e5 HEAD`, exit `0`) | `84ae4e5` is an ancestor of HEAD (HEAD equals the baseline commit) | Baseline retained | MATCH |
| Active pointer (`.specify/feature.json`) | `{"feature_directory": "specs/019-tour-content-data"}` | Spec 019 | MATCH |
| Spec 019 directory status | Untracked (`?? specs/019-tour-content-data/`), 10 files: `spec.md`, `plan.md`, `tasks.md`, `research.md`, `data-model.md`, `quickstart.md`, `contracts/` (3 files), `checklists/requirements.md` | Pre-existing authorized work, preserved | MATCH |
| Prerequisite script (`.specify/scripts/powershell/check-prerequisites.ps1 -Json -RequireTasks -IncludeTasks`) | `FEATURE_DIR: .../specs/019-tour-content-data`; `AVAILABLE_DOCS: research.md, data-model.md, contracts/, quickstart.md, tasks.md` | Complete Spec Kit artifacts present | MATCH |
| Task inventory (`tasks.md`) | 78 checkboxes `T001`-`T078`, all `- [ ]` (pending); 0 checked | 78 pending per task-generation record | MATCH |
| Requirements gate (`checklists/requirements.md`) | 16 `[x]` checked, 0 `[ ]` unchecked | 16/16 read-only quality gate | MATCH |

The extra `- [ ]` literal match in `tasks.md` is the format-description line
("Each task uses `- [ ] Tnnn ...`"), not a 79th task.

## 2. Spec 018 Preservation (checked 2026-10-02)

`git diff --stat -- specs/018-bookly-ui-redesign` (working tree, uncommitted):

```text
specs/018-bookly-ui-redesign/plan.md | 2 ++
specs/018-bookly-ui-redesign/spec.md | 4 +++-
```

Full diff consists of two additive annotation insertions and no deletions of
substantive content:

1. `specs/018-bookly-ui-redesign/plan.md`: +2 lines, a "**Continuation
   governance**" paragraph stating the recorded checks remain historical
   v2.0.0 design evidence, Spec 018 retains the original program plan and
   completed Phase 1 foundations, and active Phase 2 planning belongs to
   Spec 019 with its dependent branch/PR facts.
2. `specs/018-bookly-ui-redesign/spec.md`: governance line extended (+3/-1):
   original baseline pinned to Constitution v2.0.0 with active continuation
   under v2.1.0; plus a "**Spec ID / phase ownership**" paragraph keeping
   `018` as bootstrap/Phase 1 foundations and pointing Phase 2 requirements
   and revised contracts at Spec 019.

No Spec 018 file was deleted, renumbered, or rewritten; research, contracts,
data model, quickstart, tasks, checklists, and `phase-1-verification.md`
under `specs/018-bookly-ui-redesign/` show no diff. Verdict: **Spec 018
preserved** (annotation-only change, consistent with Constitution v2.1.0
items 6-7). These modifications are pre-existing authorized work and were
not made or altered by T001.

## 3. Predecessor / Prerequisite PR Facts (checked 2026-10-02 via `gh`)

Read-only lookup only: `gh pr view 27` / `gh pr view 28` with structured
JSON fields (`state`, `isDraft`, `baseRefName`, `headRefName`, `mergedAt`,
`url`). No comments, messages, merges, or other writes were issued.

| PR | Title | State | isDraft | base | head | mergedAt (UTC) | URL |
|---|---|---|---|---|---|---|---|
| #27 | Apply Bookly UI redesign and supporting tour workflows | **MERGED** | false | `main` | `codex/bookly-ui-redesign-all-work` | **2026-10-02T18:17:25Z** | <https://github.com/hatemsamirafifi/bookly-travel/pull/27> |
| #28 | Implement Phase 1 design foundations and shared shells | **OPEN** | false | `main` | `codex/bookly-ui-phase-1` | null (not merged) | <https://github.com/hatemsamirafifi/bookly-travel/pull/28> |

**Deviating finding (T001 -> orchestrator)**: [spec.md](spec.md) and
[plan.md](plan.md) record PR #27 and Phase 1 PR #28 as "pending merge" /
"remain unmerged" as of 2026-10-01/02. As of this check, **PR #27 is
MERGED** (2026-10-02T18:17:25Z) while **PR #28 remains OPEN**. The plan's
"confirm the predecessor/base still matches before implementation" step and
the plan's compatibility/rollout item 1 ("pending all-work/Phase 1 PRs must
be resolved or carried as an explicit stacked dependency before merge")
therefore need reconciliation before T002+ implementation begins. T001 does
not rebase, merge, or reinterpret the baseline; HEAD remains `84ae4e5` and
no merge readiness is claimed from either PR's state. Local presence of code
was not treated as merge evidence, per task instructions.

## 4. Working-Tree Status (checked 2026-10-02, read-only)

`git status --short`:

```text
M .specify/feature.json
M .specify/memory/constitution.md
M .specify/templates/plan-template.md
M .specify/templates/spec-template.md
M .specify/templates/tasks-template.md
M docs/PRD.md
M "docs/Phase 2 Implementation Plan.md"
M docs/bookly-ui-redesign-spec-kit-master-plan.md
M specs/018-bookly-ui-redesign/plan.md
M specs/018-bookly-ui-redesign/spec.md
?? specs/019-tour-content-data/
```

All modifications are pre-existing authorized specification/governance work
(Constitution v2.1.0 propagation, master-plan/PRD/roadmap renumbering,
Spec 018 annotations, feature pointer, templates) plus untracked Spec 019
artifacts. T001 created only this file and did not overwrite, revert, or
discard any of them.

`git diff --check`: exit `0`, no whitespace errors (only LF/CRLF advisory
warnings from Git on already-modified files).

## 5. Separate Acceptance Statuses

Per Constitution v2.1.0 Compliance Review, these are recorded separately.
No phase is marked implemented and no gate is implied passed.

| Gate | Status | Evidence / note |
|---|---|---|
| Implementation (Spec 019 code/tests) | **NOT STARTED** | T001 is documentation-only; T002-T078 pending, 0/78 complete |
| Runtime verification (backend/frontend suites, E2E, adoption/recovery rehearsal) | **NOT RUN** | No test, build, lint, typecheck, or Lighthouse command executed for T001; none required for this documentation-only task |
| CI (remote pipelines) | **NOT RUN / UNKNOWN** | No CI triggered or inspected |
| Merge | **PENDING / BLOCKED on reconciliation** | PR #27 MERGED, PR #28 OPEN (see Section 3); predecessor/base must be reconfirmed per [plan.md](plan.md) before implementation |
| Staging / release | **NOT STARTED** | No deployment, migration, or live-provider activity; explicitly out of scope |

## 6. Commands Executed (all read-only) and Outcomes

| # | Command | Outcome |
|---|---|---|
| 1 | `git branch --show-current` | `codex/019-tour-content-data` |
| 2 | `git rev-parse HEAD` | `84ae4e59b49a1603d59a624ff9706f17ff02338f` |
| 3 | `git merge-base --is-ancestor 84ae4e5 HEAD` | exit `0` — baseline is ancestor (HEAD equals it) |
| 4 | `git log --oneline -8` | HEAD `84ae4e5`; parent chain via `8ff6ba3`, `f3e8e25`, PR #26 merge — no unexpected divergence observed |
| 5 | `git status --short` (+ `--stat`, untracked listing) | 10 modified + `?? specs/019-tour-content-data/` (10 files), as in Section 4 |
| 6 | `git diff -- specs/018-bookly-ui-redesign` | Additive 2-line plan note + 4-line spec governance/ownership note; no deletions of substance |
| 7 | `Get-Content .specify/feature.json -Raw` | `specs/019-tour-content-data` |
| 8 | `.specify/scripts/powershell/check-prerequisites.ps1 -Json -RequireTasks -IncludeTasks` | `FEATURE_DIR` resolves to Spec 019; all design docs + `tasks.md` available |
| 9 | `gh pr view 27 --json state,isDraft,baseRefName,headRefName,mergedAt,url,title` | MERGED, mergedAt `2026-10-02T18:17:25Z`, base `main`, head `codex/bookly-ui-redesign-all-work` |
| 10 | `gh pr view 28 --json state,isDraft,baseRefName,headRefName,mergedAt,url,title` | OPEN, mergedAt null, base `main`, head `codex/bookly-ui-phase-1` |
| 11 | `git diff --check` | exit `0`, clean |
| 12 | Checkbox counts via `Select-String` on `tasks.md` / `requirements.md` | tasks: 78 `T001`-`T078` pending / 0 checked; requirements: 16 checked / 0 unchecked |
| 13 | `Test-Path` on `docs/bookly-ui-redesign-spec-kit-master-plan.md`, `docs/PRD.md`, `docs/Phase 2 Implementation Plan.md`, prerequisite script | All `True`; every relative link in this file targets a verified-existing path |
| — | Runtime gates (`php artisan test`, `npm test/lint/build`, Playwright, Lighthouse) | **Explicitly not run** — documentation-only task, no code changed |

## 7. Unknowns and Follow-ups for the Orchestrator

1. **PR #27 merged after plan text**: plan/spec say both PRs unmerged; #27
   merged 2026-10-02T18:17:25Z. Whether the baseline moves off `84ae4e5`,
   restacks onto `main`, or stays with an explicit stacked dependency on
   open PR #28 is an orchestrator decision — T001 takes no position beyond
   recording the facts.
2. **PR #28 still open**: Phase 1 worktree remains separate and outside this
   task; its merge/removal effect on Spec 019's predecessor record is
   unresolved.
3. **T002-T004 next**: disposable PostgreSQL/Meilisearch isolation,
   frontend command/SSR-fixture verification, and schema/consumer inventory
   are unverified by T001 and must precede foundations (per the task graph
   T001 -> T002/T003, then T004).
4. **No external lookup failure occurred**: `gh` was authenticated and
   returned structured JSON for both PRs, so no uncertainty needs preserving
   on PR state; the only open item is the reconciliation decision above.
