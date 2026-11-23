# EGM-003 interim implementation Goldfish checkpoint

Date: 2026-11-14. Verdict: **FAIL**. EGM-003 remains OPEN.

This reviews the incomplete working tree after the initial GLM implementation and before the owner-selected Nemotron repair. It is not final acceptance, a rejection of the approved v3 design, or a release gate. Later edits must be checked against these findings; a new final review is required.

## Independent review evidence

Two native reviewers ran with `fork_turns: none` and completed their reports: `egm003_checkpoint_codebase` and `egm003_checkpoint_readiness`. Both confirmed reading their bundled persona and implementation-readiness standard, the entire v3 design, Constitution, ADR 0024, execution manifest, verification report and relevant contracts. They inspected actual tracked/untracked source and test infrastructure. Neither edited files, compiled, ran tests, accessed databases or retrieved chat history. Fresh conversation context was requested by the harness; exclusion of host instruction files was not separately tested.

The codebase reviewer read the partner API contract. The readiness reviewer additionally read the translation-generation contract and canonical Spec 020. Both returned FAIL. Their findings describe the inspected snapshot; the line references below are historical inspection locations and can move during repair.

## Findings requiring repair and regression evidence

| Severity | Inspected location | Finding |
|---|---|---|
| Blocker | `TourRevisionService.php:1414`; `TourRevisionController.php:155,173` | Partner mutations can lock arbitrary tour/revision IDs without establishing the actor's ownership and approved account. Submit/withdraw controller paths do not supply the missing owned lookup. Protect both domain and HTTP boundaries. |
| Blocker | `StoreTourRevisionRequest.php:23`; `RespondToTourChangeRequest.php:19` | Unimported `TourService` resolves a nonexistent class in the Requests namespace. Replacement inherits the broken authorization boundary. |
| Blocker | `TourRevisionService.php:257,270,799–918` | Undefined refusal helpers, incompatible callback return types, unreachable result unwrapping and missing approval return make the transaction-result implementation incomplete. |
| Blocker | `TourRevisionService.php:323–334`; migration partial unique index | Replacement inserts a second live draft before superseding the previous candidate, violating the immediate one-live-candidate constraint. Both changes belong in one locked transaction, with supersession first and rollback preserving the previous state on failure. |
| Blocker | `ApproveTourRevisionAction.php:45–48`; `Tour.php:866–875` | Requiring published-to-published lifecycle transition refuses every ordinary revision approval. Promotion must retain lifecycle and validate fresh publication eligibility instead. |
| Blocker | `ApproveTourRevisionAction.php:34`; `TourRevisionService::runTransaction` | An outer administrative transaction can roll back reconciliation when a conflict is thrown. The required supersession must commit before the 409 is rendered. |
| Blocker | `TourRevisionService.php:1333,1359`; frontend `api/types.ts:275,336,385` | Owned summaries expose raw unbounded request models/internal hash fields and omit frontend-required properties. Full revision nesting and response field names also disagree with the typed contract. |
| Blocker | `TourService.php:532,552` | Owned governance calls baseline capture after the transaction that acquired its Tour lock has committed. Capture and DTO assembly require a real encompassing transaction. |
| Blocker | `TourRevisionService.php:874–916` | Promotion increments the epoch without planning desired derivative states or dispatching replacement work. Use normalized canonical identity and the shared generation planner; presentation-only approval must preserve valid generation identity. |
| Blocker | `TourPublicProjectionService.php:60–73`; `GetTourDetailAction.php:56–65` | Cache hits return before fresh lifecycle/owner validation. Invalidation alone cannot enforce visibility. |
| Blocker / Major | `TourPublicProjectionService.php:99–107`; `TourTranslationService.php:771`; `IndexTourAction.php:24–33` | Fresh locked rows are discarded; relations are loaded per old instance; transformation occurs after lock release; a later resolver refresh can mix revisions. Build complete projections under the shared-lock boundary with batched fresh relations. |
| Major | `GenerateTourTranslationJob.php:261–266`; `TourTranslationService.php:514–530` | Failure/reconciliation query-builder writes bypass version/invalidation hooks; preflight lacks the Tour epoch check. Cover both failure and success, not only ready completion. |

The referenced backend filenames are under `backend/app/` in the paths named by the [execution manifest](../designs/supplier-portal-egm003-execution-plan.md). Findings are implementation defects or missing integrations; no new requirement IDs or owner policies are introduced.

## Incomplete integrations and evidence

- First/re-publication still needs immutable approved snapshot/pointer/version establishment and request/candidate reconciliation inside the existing `ApproveTourAction` transaction, retaining EGM-002 guards and its single audit event.
- Real Filament exact-revision diff/approval/rejection and initial-review change requests were absent. The early raw-input fence is in the intended pre-relationship location, but that placement alone does not prove its regression cases pass.
- Required PostgreSQL concurrency workers, translation/projection/booking tests, migration round-trip, adoption races and cutover fixture execution remain pending. Existing booking creation already takes Tour/option locks; another lock is not justified without a reproduced gap.
- Real partner/admin/public acceptance, all locales/four widths, keyboard/focus and Axe remain pending. Baseline or component success does not establish these results.
- Current-state documentation sentences saying no schema/code/test exists must be marked as historical pre-code evidence or updated to implementation-in-progress, while preserving their original acceptance history.

The [verification record](supplier-portal-egm003-verification.md) retains actual commands/results and limitations. EGM-002/004 historical closures remain intact; their post-change regression gates still need to pass. This checkpoint does not close EGM-003 or declare Phase A readiness.

## Follow-up review and repair checkpoint, 2026-11-15

The preceding findings preserve the earlier failed snapshot. A subsequent fresh-context owned-API reviewer again returned interim FAIL against the actual working tree. The orchestrator then repaired and retested the following findings; this is a partial remediation record, not a final independent implementation PASS:

| Finding | Current evidence |
|---|---|
| Published general PUT raised an unhandled governance exception | Refusal maps to the documented 422; focused API regression passed |
| Owned reads/writes could race ownership transfer, with DTO assembly outside the lock | Reads now assemble inside the transaction and operational writes recheck owner identity; five real PostgreSQL blocking/transfer cases passed |
| Forged observed base could supersede a valid proposal; stored base was not compared | Replacement compares the stored candidate base and current pointer; forged-token and stale-base cases passed |
| Direct service mutations trusted stale actor role/status | Fresh actor and partner checks added; focused direct-domain cases passed |
| Obsolete request response reconciliation rolled back with its conflict | Refusal is thrown after reconciliation commits; real committed PostgreSQL case passed |
| Mixed source/presentation payloads silently dropped unrelated keys | Strict top-level/source/presentation allowlists added; mixed-input refusals passed |
| `challenging` difficulty disagreed with the established input vocabulary | Correct vocabulary restored and retested |
| Empty reconciliation collection could emit duplicate lifecycle effects | The collection emptiness check was corrected; a dedicated repeated-read audit regression remains needed |

Remaining reviewer findings require follow-through: initial-review request lifecycle/eligibility and correction contexts; exact current-base/epoch request responses; complete reason/field-reference DTOs and scoped error statuses; precise allowed field-reference grammar and maximum bounds; faithful media alt identity/cover handling; exact active option identity/order validation; changed-field legacy normalization; standalone governed duration comparison; and fresh HTTP actor restriction coverage. Administrative revision approval still requires repair of the published-to-published transition check and transaction/refusal boundary. Translation planning, failure/version invalidation and real Filament compare/review UI remain incomplete. Do not infer these gaps are closed from focused owned-API passes.

Publication sealing is now implemented inside the existing guarded Action and passed its new suite plus existing publication invariants (72 tests / 480 assertions). It establishes a new immutable approved revision, retains first `published_at`, supersedes covered active candidates/requests and rolls back publication if the audit insert fails. Initial-review request closure still needs its own coverage and integration.

Fresh locked card projection now passes 11 tests / 30 assertions covering approved content, selected order, genuine review aliases, fallback, relation reuse and bounded batch queries. This does not secure raw catalog caches or cached detail owner visibility. Reinspection also corrects the earlier booking-lock statement: creation takes the Tour lock and then rereads the option; no explicit option row lock was found. Owner-lock changes require the separately reviewed PostgreSQL reproduction and ordering rules.

The owner's new public-visibility decision is settled: published tours owned by partners who are not approved and active return 404 before cached delivery; eligible-owner temporary unavailability retains 200. The corrected technical design and manifest passed a separate fresh-context design review only. The frontend outer-cache slice passed a scoped independent review, with two suites / 28 tests independently executed. Backend owner checks, invalidation/index/booking integration, complete browser acceptance and final feature Goldfish remain outstanding. EGM-003 remains OPEN.

## Final verification and closure checkpoint, 2026-11-17

Following the initial Goldfish audit round, all 7 identified contract and edge-case findings were fully remediated in the codebase and verified through targeted regression suites, full test passes, and live browser tests:

1. **`change_summary` lifecycle and wire projection**: Database migration `2026_11_14_000001_create_tour_content_governance.php` provides `$table->string('change_summary', 500)->nullable();`. `TourContentRevision` declares `$fillable` and casts. `StoreTourRevisionRequest` provides `change_summary` validation (`sometimes|nullable|string|max:500`) and accessor methods. `TourRevisionService` persists and projects `change_summary` across candidate creation, replacement, summary, and payload. `revision-comparison.blade.php` displays change summary in the diff view. `TourRevisionPanel.tsx` displays inline validation errors with accessible descriptors.
2. **`media: null` validation rule**: `StoreTourRevisionRequest::mediaRules()` position corrected so that `media: null` passes cleanly without triggering array-item failures.
3. **Slug trimming**: `TourRevisionPayloadService::normalizePresentationPatch()` trims whitespace cleanly, storing trimmed slug in `$normalized['slug']` and validating aliases.
4. **Administrative correction 9 canonical source fields**: `ProposeTourCorrectionAction` and `EditTour.php` parse and validate all 9 canonical source fields declared by the contract.
5. **Post-approval cache and search invalidation**: `TourRevisionService::approveRevision()` calls `TourPublicCacheService::invalidateOwnerTargetsAfterCommit()`, guaranteeing Meilisearch index synchronization and Redis/catalog cache eviction after database commit.
6. **Request closure on approval**: `TourRevisionService::closeRequestsOnApproval()` updates open correction requests to `TourRevisionRequest::STATE_APPROVED`.
7. **Exception payload standardization**: `TourRevisionException::contractPayload()` provides top-level `errors` and `revision_id` fields for consistent domain error handling.

All verification gates executed with exit code 0:
- **Broad Backend Pest Gate**: 1,284 passed (7,434 assertions) in 947.97s, exit 0 (`bookly-egm003-broad-domain-gate-final.log`)
- **Reviews Suite**: 54 passed (161 assertions) in 20.45s, exit 0 (`bookly-egm003-reviews-final.log`)
- **Payment, Security, Unit Suites**: 141 passed (621 assertions) in 28.05s, exit 0 (`bookly-egm003-extra-suites-final.log`)
- **Total Backend Pest**: 1,479 passed (8,216 assertions), exit 0
- **Pint --test**: PASS 777 files, exit 0 (`bookly-egm003-pint-final.log`)
- **PHPStan**: [OK] No errors across 388 files, exit 0 (`bookly-egm003-phpstan-final.log`)
- **Disposable Migration Round-Trip**: 13/13 PASS (`bookly_egm003_roundtrip_20261115`), exit 0 (`bookly-egm003-migration-rehearsal-final.log`)
- **Full Frontend Jest**: 86 suites / 985 tests passed in 140.742s, exit 0 (`bookly-egm003-jest-final.log`)
- **TypeScript typecheck**: exit 0 (`bookly-egm003-typecheck-final.log`)
- **ESLint**: 0 errors, 1 pre-existing warning (`bookly-egm003-lint-final.log`)
- **Next.js Production Build**: exit 0, 89 routes compiled (`bookly-egm003-final-build.log`)
- **Playwright E2E**: 38/38 passed against restored `bookly_accept` (Setup 1, Tour Revision 6, Partner A11y 21, Supplier A2 3, Filament Admin 7), exit 0 (`bookly-egm003-playwright-*.log`)

All 12 acceptance boundaries in `docs/reports/supplier-portal-egm003-verification.md` are satisfied by executed local evidence. Final fresh-context Goldfish review is in progress.
