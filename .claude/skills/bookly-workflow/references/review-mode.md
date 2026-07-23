# Code Review Mode — Full Specification

Triggered by the orchestrator at Step 6 (Integration) and Step 7 (Final), or on demand for any diff. Routed to `review-agent`; `debug-agent` is attached for findings at severity High or Critical.

## Review pipeline (per changed file)

```
file diff
  ├─► static analysis (Pint backend / ESLint + tsc frontend)
  ├─► PRD compliance (prd-validation-agent FR-ID trace)
  ├─► architecture rules (see table)
  └─► bug detection (review-agent + debug-agent for ≥ High)
        ▼
  structured review block (one per finding)
```

## Architecture rules (machine-checkable)

| Rule | Check |
|------|-------|
| Laravel 11 API-only | No Blade views outside Filament; controllers return JSON (Filament admin excepted) |
| Next.js 16 App Router | No Pages Router; `app/[locale]/` structure enforced |
| PostgreSQL + Redis | No SQLite in migrations; cache/queue on Redis driver |
| Stripe Payment Intents only | No `Charge::create` (FR-005.1) |
| Idempotent booking | Booking-create consumes client idempotency key (FR-004.6) |
| Immutable ledger | `financial_ledger_entries` has no UPDATE/DELETE/mass-assign path (FR-005.5) |
| Multi-language EN/ES/IT | New i18n keys present in all three locales (FR-010.5) |
| No UI before backend contract | Frontend diff references a published contract artifact |
| No skipping DB design | Persistence change references a committed migration |
| Integer cents | All money fields integer cents, no floats (§14) |

## Output format — one block per finding (exact)

```
### Issues
<concise statement; file:line>

### Severity
<Critical | High | Medium | Low>

### Root Cause
<the mechanism, not the symptom>

### Fix
<minimal corrective action for the owning agent>

### Improved Version
<code sketch of the corrected implementation>

### Prevention Tips
<guard / test / lint to stop this class recurring>
```

## Severity rubric

| Severity | Meaning |
|----------|---------|
| Critical | Financial data loss, overbooking, security breach, broken Critical FR |
| High | Failing critical-flow test, contract drift, missing i18n locale |
| Medium | NFR miss (perf/a11y), missing non-critical FR |
| Low | Style, minor reuse, doc drift |

## Disposition

- **Critical / High** → orchestrator blocks the phase; routes the finding to the owning agent; `debug-agent` co-owns the RCA.
- **Medium** → logged; owning agent fixes before final verdict.
- **Low** → batched into a cleanup task; non-blocking.
- **No findings** → `review-agent` returns `PASS` together with `prd-validation-agent`'s FR-ID coverage closure.

## Verdict payload (review-agent → dev-sup-agent)

```
{
  findings: [<blocks>],
  critical_count, high_count, medium_count, low_count,
  verdict: "PASS" | "FAIL",
  fr_ids: [...]
}
```

`FAIL` on any Critical/High. Only `PASS` + `COVERED` together advance the feature to `COMPLETE`.