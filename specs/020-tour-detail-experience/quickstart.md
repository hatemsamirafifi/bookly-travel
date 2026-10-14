# Quickstart and Acceptance Guide: Spec 020

**Status**: Commands/scenarios for future implementation acceptance; no runtime gate below was executed during planning. [Plan](plan.md), [consumer contract](contracts/tour-detail-consumer.md), [UI contract](contracts/tour-detail-ui.md).

## Prerequisites and isolation

- Confirm feature pointer resolves to020 and preserve018/019. Retain the documented shell baseline or resolve its merge dependency before release. Generate the execution ledger before implementation.
- Use existing Node22/npm10 environment, frontend dependencies and built Next application. Existing proxy/API must be running: native browser base is http://localhost:8080; containers use http://nginx. Playwright does not start the application.
- Use only owned disposable/seeded tour/account fixtures. Integrated browser setup needs seeded traveler and partner authentication; no production credentials or real payment/translation-provider operations. Do not repeatedly consume shared seeded inventory without ownership/cleanup.
- SSR fixture harness requires a production build and actual base response from configured API; it starts an isolated local API fixture and Next process. Browser route interception alone does not exercise SSR.
- Backend suites use explicit phpunit.pgsql.xml/test database and a single suite owner. Existing laravel application service must already run before docker compose exec; starting test services alone does not start the app.
- Freeze/inject UTC clock for fixed-date tests, include future/non-default dates and midnight. Existing static date fixtures must not become accidental expired-date failures.

## Frontend gates

From `frontend/` in the repository, using PowerShell:

```powershell
npm run lint
npm run typecheck
npm test -- --runInBand
$env:API_INTERNAL_URL = 'http://127.0.0.1:8080'
npm run build
npm run test:e2e -- tests/e2e/tour-detail.spec.ts --project=chromium --project=mobile --workers=1
npm run test:e2e -- tests/e2e/booking.spec.ts tests/e2e/checkout.spec.ts tests/e2e/payment.spec.ts tests/e2e/wishlist.spec.ts --project=chromium-authed --project=mobile-authed --workers=1
npm run test:e2e -- tests/e2e/a11y/tour-detail-a11y.spec.ts --project=a11y --workers=1
```

API_INTERNAL_URL above is for native server execution through the existing proxy; retain the existing internal URL inside Docker. No secret belongs in NEXT_PUBLIC variables. Add new semantic tests in existing tour/booking/page/schema suites; update brittle old CSS selectors without dropping behavior assertions. Extend public tour-detail tests for signed-out continuation and authenticated booking tests for final handoff. If using new authenticated filenames, update Playwright's project matching explicitly.

## Actual backend regression

From the repository root, with the existing app service running and disposable test configuration verified:

```powershell
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d test-postgres test-meilisearch
docker compose exec -T laravel php -d memory_limit=512M vendor/bin/pest --configuration=phpunit.pgsql.xml tests/Feature/Search/TourDetailTest.php tests/Feature/Search/TourAvailabilityLogicTest.php tests/Feature/Search/AvailabilityResolutionRegressionTest.php tests/Feature/Booking/CreateBookingTest.php tests/Feature/Payment
```

These existing suites do not alone prove the new presentation; supplement with actual endpoint → detail → checkout evidence and session recovery. Run full applicable backend/authorization regressions if code/contracts change; no backend change is currently planned.

## Acceptance scenarios

| Case | Procedure / expected evidence | Requirements / outcome |
|---|---|---|
| Complete/sparse hierarchy | Real full and sparse tours plus SSR controlled states; verify order, descriptions, practical information, optional visibility and no dangling entries. | FR-002–008; SC-001 |
| Selection and money | Non-default date/count, min=max, >30 dates, fractional major-unit base, malformed/unsafe amount/count/currency and resize; one estimate/query, invalid actions disabled. | FR-015–022; SC-002/003 |
| UTC date boundary | Offered today/past/malformed values and crossing UTC midnight; displayed date unchanged, today rejected, stale selection explained and not silently replaced. | FR-017–021; SC-002/003 |
| Logged-out/session recovery | Continue through login and registration preserving full query; trigger401 at checkout, return safely without auto-submission; verify unchanged idempotency/price drift. | FR-020/022/031; SC-002/009 |
| Gallery | Zero/one/four/>five and broken photos; actual order/count, swipe/buttons, all photo reachability, focus trap/inertness/Escape/return, no refocus on image change or layout jump. | FR-009–012/029; SC-004/007 |
| Navigation/sticky | Click and manual scroll full/sparse page, resize/long labels; active cue, visible focused heading, no focus theft/whole-page overflow/bar obstruction. | FR-013–015; SC-005/007 |
| Trust/discovery/wishlist | Real review counts/zero, absent/verified operator, related eligibility/current locale, actual saved/failed/denied outcomes; no private data. | FR-023–026; SC-006/009 |
| Locale/source/SEO | Current derivatives, independent English fallback, intentional empty itinerary and reload; notices/lang, metadata/schema/visible facts agree. | FR-001/027–028/032; SC-006/009 |
| Route/states | Actual404/410 API plus rendered not-found/withdrawn, loading/unavailable/network/review/media errors and denied actions; localized retry/noindex/no fake success. | FR-001/031; SC-006/009 |
| Twelve visual cases | EN/ES/IT ×390/768/1024/1440 screenshots with loaded reviews, viewer/mobile selectors/wishlist prompt as applicable; keyboard/reduced motion/manual focus/contrast. | FR-029–030/033; SC-005/007 |
| Performance/regression | Strict production audits and existing critical payment/auth/review/public-contract suites; retain every report/result. | FR-001/022–034; SC-008/009 |

## Performance gate

Extend `frontend/lighthouserc.js` with `/es/tours/hidden-gems-rome-walking-tour` and `/it/tours/hidden-gems-rome-walking-tour`, retaining its four existing EN URLs, three runs and pessimistic ≥0.9 assertion. This is a planned implementation change; the current EN-only config does not prove SC-008.

From `frontend/`, against a production proxy/runtime with fixtures and Chrome ready:

```powershell
$env:LIGHTHOUSE_BASE_URL = 'http://localhost:8080'
npm run lhci -- --config=lighthouserc.js
```

The existing JS config uses installed Chrome/CHROME_PATH and browser-readiness hook; use verified CI/Linux or native host Chrome. Do not use weaker legacy lighthouserc.cjs. Serial audits must not compete with builds/Jest/Playwright. Retain18 expected reports (six URLs ×three runs), settings, source commit, environment/resource conditions and traces; failed/missing reports block acceptance. The Windows Invoke-PerformanceAudit wrapper currently covers only EN and must be extended or supplemented before claiming this locale gate.

## Evidence and release record

Record commands, exit/results, exact source commit, real versus controlled fixture provenance, locale/width, UTC clock and retained output/screenshot/report paths. Keep implementation, runtime verification, CI, merge and target-environment deployment statuses separate. Document deliberate skips rather than implying passes. Rerun the Constitution Check and resolve shell dependency/release readiness before shipping; frontend rollback preserves Spec019 stored content and requires no schema rollback.
