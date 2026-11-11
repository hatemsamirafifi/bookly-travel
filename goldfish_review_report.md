# Goldfish Annotated Review Report

**Status:** Resolved · project owner approved Option B on 2026-11-10  
**Target:** [Supplier Portal Plan](docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md) (lines 13–20, 27, 44–54, 60–67, 96–112)  
**Constitution Check:** The plan's source-language choice and publication validation match v3.0.0. Full plan compliance and release verification are separate checks. [Adopted policy](.specify/memory/constitution.md#L244-L261)

## Resolution

Constitution v3.0.0 adopts one fixed partner-authored source locale per tour from `en`, `es`, and `it`. The platform generates the other two from the current source revision. The adopted wording retains v2.1.0's explicit requirement that the localized fallback notice be visible. The Sync Impact Report records the active documentation and verification still to be aligned before further work relies on the amendment. [Sync Impact Report](.specify/memory/constitution.md#L1-L23)

The follow-up Goldfish review records the synchronized documentation, test results, and remaining Phase A blockers. [Supplier Portal follow-up](docs/reports/supplier-portal-v3-goldfish-review.md)

## Prioritized Findings & AI Suggestions

### EGM-001 · Tour source language conflicts with Constitution v2.1.0 · Severity Blocker

- **Origin** — User-reported finding verified against repository files during EGM Resolve.
- **Affected scope** — The plan lets suppliers select `en`, `es`, or `it` as the authored source, stores the choice as `tours.source_locale`, generates the other two languages, and validates submission and approval in the selected language. [Authoring flow](docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md#L44-L54) · [Data and translation](docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md#L60-L67) · [Approval](docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md#L108-L112)
- **Implementation history** — Commit `a1e878e` introduced selectable source locales as part of supplier translation and booking-option localization. The user clarified that the source-language behavior was changed during this implementation. Constitution v2.1.0 and Spec 019 prescribed English as the authored source. [Implementation baseline](docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md#L13-L20) · [Spec 019](specs/019-tour-content-data/spec.md#L124-L131)
- **Discrepancy found under v2.1.0** — The previous Constitution required English as the canonical partner-authored source, validated the English title and description before submission and publication, and required Spanish and Italian to be generated from the current English revision. The frontend schema accepts all three as sources and admin approval reads the selected source locale, so the discrepancy reached the implementation. [Frontend schema](frontend/src/lib/validators/partner.ts#L251) · [Admin approval](backend/app/Domains/Admin/Actions/ApproveTourAction.php#L33)
- **Rationale** — A tour authored in Spanish or Italian could satisfy the plan's source-language gate while failing the v2.1.0 English-source gate. A generated English version also failed the former requirement for partner-authored English. [Governance](.specify/memory/constitution.md#L382-L396)
- **Decision** — PROPOSE FIX accepted. The project owner explicitly approved Option B under the Constitution's amendment procedure. [Amendment procedure](.specify/memory/constitution.md#L388-L396)

### Option A considered · Align the plan with Constitution v2.1.0

Replace the authoring-language choice and its acceptance criteria in the plan with this decision:

> Suppliers author traveler-facing tour content in English (`source_locale=en`). The server validates the English title and description at submission and publication. The platform generates Spanish and Italian from the current English revision. The public page uses a current derivative when available and otherwise shows the current English content with a localized notice. Derivative availability does not block publication of an otherwise eligible tour. The portal display language and live-guide languages are independent of the authored source.

Update the plan's status, scope, wizard flow, data and translation rules, and A2/A4 acceptance criteria to distinguish current behavior from the approved target. The implementation check should cover direct API attempts to author in `es` or `it`, English validation at both gates, and publication while derivatives are pending. Check the target environment for tours and drafts with non-English sources before defining any remediation. If records exist, preserve the original content and require supplier-reviewed English before promotion to the canonical source. Recheck Constitution compliance before release.

### Option B adopted · Ratify a scoped constitutional amendment

The approved amendment replaces the first four bullets in **Tour Content Source and Derived Locales** with this policy:

> Each tour MUST have one canonical partner-authored source for traveler-facing content, including structured itineraries, in English (`en`), Spanish (`es`), or Italian (`it`). The partner selects the source locale at creation and it MUST remain fixed for that tour. Submission and publication MUST validate the title and description in the canonical source locale. The platform MUST generate the two other locales from the current canonical revision. Missing, pending, stale, or failed derivatives MUST NOT block publication of an otherwise eligible tour. Superseded results MUST NOT replace current content. Generation and retries MUST be idempotent and MUST NOT delay a successful source save. Public display MUST use the current derivative or the current canonical source with a localized fallback notice. Content and itinerary MAY fall back independently. Their actual languages MUST be disclosed, and SEO descriptions and structured content MUST reflect the values displayed.

Version **3.0.0** replaces the mandatory English-source rule with an incompatible per-tour rule. The amendment affects the redesign master plan and specifications 018–020, supplier create/edit contracts, translation and fallback behavior, approval rules, and data and rollout procedures that assume English. [Versioning policy](.specify/memory/constitution.md#L398-L405) · [Master plan](docs/bookly-ui-redesign-spec-kit-master-plan.md#L315-L318) · [Redesign requirements](specs/018-bookly-ui-redesign/spec.md#L186) · [Tour-content requirements](specs/019-tour-content-data/spec.md#L124-L131) · [Tour-detail plan](specs/020-tour-detail-experience/plan.md#L25)
