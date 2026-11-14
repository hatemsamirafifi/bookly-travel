# EGM-003: Tour content and translation governance

Date: 2026-11-14.
Status: Owner policy approved; technical design and implementation acceptance pending.
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

Before this change, the focused backend baseline passed 102 tests with 726 assertions. The immutable tour revision workflow has not yet been implemented or accepted. The current translation hash/state mechanism and the CMS content history table must not be described as an existing immutable published-tour revision system.
