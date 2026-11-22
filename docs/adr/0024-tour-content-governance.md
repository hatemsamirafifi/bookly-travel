# EGM-003: Tour content and translation governance

Date: 2026-11-14.
Status: Owner policy approved; v3 technical design passed independent review; implementation acceptance pending.
Governance: Constitution v3.0.0. Existing requirement and specification numbers remain unchanged.

## Owner decision

1. Partners own and edit canonical tour content.
2. Administrators request changes with a mandatory reason and specific field references.
3. Gemini-generated translations must not be manually edited.
4. Edits to a published tour create a separate reviewable revision. The approved public version stays unchanged until a proposed revision is approved.
5. Exceptional administrative corrections use a dedicated authorized and audited domain Action. The general Filament form cannot make those corrections.

The owner approved these rules in the implementation request on this date. They replace the unresolved policy questions recorded under EGM-003; approval of the policy does not establish that the implementation meets it.

## Selected correction workflow

Exceptional corrections require an explicit correction permission, a reason, validation, a separate proposed revision, and a governance audit event. They pass ordinary administrative review before publication. Creating a correction never approves or publishes it. No emergency approval or automatic publication path is introduced. This is the implementation choice for the owner's permitted correction mechanism.

## Constraints and acceptance

- Keep one immutable source locale per tour from `en`, `es`, `it`.
- Preserve current approved public content, search and SEO while proposals are edited or reviewed.
- Identify the exact submitted revision at approval; refuse superseded review requests.
- Bind translation work to the source revision and protect current derivatives from stale completion. Missing or unfinished derivatives do not block eligible publication; public fallback remains the current approved source with a localized notice.
- Keep guide languages independent from content locales and preserve purchased booking snapshots.
- Enforce ownership and permissions on Laravel; read-only controls alone are insufficient.
- Review the technical design against the actual schema before changing schema or workflow.
- Verify with real PostgreSQL transactions and concurrency tests, Filament Livewire, relevant frontend/browser/accessibility tests, and configured static checks. Keep EGM-003 open until documented acceptance passes.
- Preserve EGM-002 and EGM-004 protections and historical evidence. Do not implement A3, A5, Phase B or Phase C or declare Phase A release-ready.

## Evidence status

Before this change, the focused backend baseline passed 102 tests with 726 assertions. The [v3 technical design](../designs/supplier-portal-egm003-governance-v3.md), execution manifest and synchronized contracts passed independent review before schema or workflow changes began. Implementation is in progress and has not been accepted. The pre-existing translation hash/state mechanism and CMS content history table did not provide immutable published-tour revisions.

## Owner decision: public owner eligibility

On 2026-11-14 the owner explicitly selected HTTP 404 for the inconsistent `published` tour whose owning partner is no longer eligible. This is a narrow exception to the existing public-detail availability contract. Reuse the existing public operator eligibility definition: the partner exists, is `approved`, and is active.

- Check fresh partner eligibility on the server before any cached tour content is served. An ineligible owner produces 404 without tour content or operator information.
- Keep 200 with the existing unavailable state for a published tour with an eligible owner but temporarily unavailable pricing/inventory/schedule.
- Reject new bookings for an ineligible owner independently of page visibility. Preserve existing purchased booking snapshots.
- Apply the same restriction to search, public APIs, SSR, SEO and caches, and invalidate affected representations when eligibility changes.
- Preserve suspension's existing published-to-draft workflow. Restoring eligibility never republishes a tour automatically.
- Add real regressions for stale caches and the inconsistent published/ineligible state. This decision does not establish that those checks pass or change the source-locale policy.
