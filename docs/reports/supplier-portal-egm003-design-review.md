# EGM-003 independent design review

Date: 2026-11-14. Status: v3 technical design, execution manifest and corrected companion SYNC pass both independent reviewers. Implementation acceptance remains pending.

Target: [initial design](../designs/supplier-portal-egm003-revision-governance.md).
Policy: [ADR 0024](../adr/0024-tour-content-governance.md).

Two fresh-context Goldfish reviewers read their bundled Codebase Details / Implementation Readiness personas and the implementation-readiness standard. They inspected the design, owner decision and relevant actual code. Their reviews were read-only; neither executed tests. Neither claimed to read every linked historical file or migration. The findings below are conclusions from inspected code, not test results.

| Finding | Required correction before implementation |
|---|---|
| Direct published-content writes remain | Cover the main image, all source aliases, public presentation metadata and general Filament writes; define a complete field/writer matrix. |
| Text-only option promotion would overwrite commerce | Do not pass text-only snapshots to the existing full `syncOptions`. Define option identity/order/status and commercial-drift handling. Preserve current booking snapshots and live commercial values. |
| Published option saves lack a compatible contract | Existing rows require title with commercial values; distinguish unchanged round-tripped text from actual source edits. Explicitly define reviewed addition/retirement or a bounded refusal. |
| Pointer drift during deployment / republishing | Remove the unsafe activation window. Reconcile first publication, unpublish/edit/republish and lazy/batch baseline under locks; never reactivate stale pointers after legacy writes. |
| Missing translation epoch and legacy completion fences | Reject null-identity legacy jobs after adoption. Handle unchanged hashes and A-to-B-to-A, desired-state identity, completion, failure and reconciliation. Retire the legacy field engine with an in-flight completion check and worker restart instructions. |
| Public read/cache coherence unsupported | Multi-query reads can cross a commit; precommit cache eviction can refill old content. Specify a coherent read boundary, versioned cache or equivalent, after-commit invalidation and final-state indexing. Qualify asynchronous search lag. |
| Candidate cannot be restored through the API | Add exact owned payload retrieval, mandatory observed version/CAS, replacement-patch semantics and mixed-save failure recovery. Show the compared exact revision in admin review. |
| Contradictory states and action contracts | Supply executable transitions and linked-request effects for submission, response, replacement, withdrawal, rejection, approval and replay. Correct identifier types and supersession arguments. Changed paths must be a subset of declared correction fields. |
| Backfill / rollback under-specified | Define faithful legacy capture, locking, idempotence, invalid legacy data treatment and safe fail-closed rollback. Do not normalize historical data into invented approved content. |
| Acceptance commands miss promised tests | Use the actual partner accessibility project matching, explicit auth preparation, admin-partner-public browser flow, and complete owning file/test matrix. Preserve current baseline facts separately from older evidence. |

Ordinary correction review is an implementation choice under the owner-approved correction policy, not a separately quoted owner mandate. No default grant of the correction permission to ordinary administrators is assumed.

Current independently executed baseline: backend 102 tests / 726 assertions; frontend four Jest suites / 68 tests; PHPStan zero errors. The prepared PHP 8.3.36 image has JPEG support. The older 21-error/JPEG results remain historical evidence in their original report.

Next gate: revise the technical design and synchronized contracts, then obtain completed independent re-review. EGM-003 remains open. No schema or production workflow change has been implemented in this round.

## Round 2

Both independent reviewers completed re-review of v2 and still found blockers. The new trigger/GUC scheme did not cover derivative, option-translation or structural-option writes despite claiming it did. Immutable candidate replacement still lacked a new-row identity and request effects; archive/suspension lifecycle invalidation was incomplete. Versioned caching abandoned some existing operational/state invalidations, and the runbook incorrectly claimed stopped workers would drain a queue. First-use base/epoch exposure and mixed dirty editor saves also remained incomplete. The `IndexTourAction::dispatch(...)->afterCommit()` API was independently verified as valid; FIFO completion was not.

The next approach is the [self-contained v3 design](../designs/supplier-portal-egm003-governance-v3.md), authored by the orchestrator after the two failures. It removes speculative database triggers, requires coordinated old-worker/request quiescence, and defines explicit application writer fences, immutable replacement, request/lifecycle effects, preserved cache invalidation and separate mixed saves. This is a change of approach, not a claim that v2 passed. Active companion documents still require synchronization after v3 review and before implementation. No production/schema work is authorized yet.

Additional baseline evidence: JPEG-capable runner passed 10 demo/fixture cases and 41 assertions. Partner authentication passed on a warmed disposable stack. Existing A2 browser acceptance passed 8 cases in 43.0s after correcting the broad Next-button test selector to the exact wizard button inside main; no assertions or timeouts were weakened. The initial 7-pass/1-failure result and cold-start timeout remain in local logs.

## Round 3: technical design passed

Both reviewers completed a full read of v3 and found no remaining substantive technical-design blocker against the inspected repository. This is an architecture verdict, not an implementation/test acceptance. Their read limits include not executing application tests and not certifying the then-pending companion synchronization.

Both separately read the [execution manifest](../designs/supplier-portal-egm003-execution-plan.md), validated its Create/Modify paths against the real filesystem, checked actual generation-job constructor/dispatch call sites and test ownership, and passed the exact-file planning gate. Existing source-hash tests, homepage invalidation and actual Playwright project matching are explicitly preserved. Their small clarification about active correction replacement was applied; S1/S2 test sequencing is clarified in the manifest.

Only companion synchronization remains before schema/workflow implementation. No production/schema feature code has been written or accepted at this checkpoint. The policy decision remains approved, EGM-003 remains open, and no Phase A/release gate is implied.

## Final pre-implementation gate

Both reviewers re-read the nine active companions and their corrected residual lines, then issued PASS before schema/workflow implementation. The final corrections include epoch identity in the original job request boundary and failure rules, removal of a nonexistent per-revision generation-epoch field requirement, explicit never-public initial-review response semantics, and source-only exceptional correction scope. All earlier requirement IDs remain present. Local-link validation checked 221 references with zero broken file targets; `git diff --check` passed with line-ending warnings only.

The complete frontend baseline also passed 84 Jest suites / 928 tests, and backend Pint passed 749 files. TypeScript passed. These checks are preparation evidence and do not certify the still-unimplemented revision feature. EGM-003 remains open. The implementation may now proceed within the reviewed manifest.
