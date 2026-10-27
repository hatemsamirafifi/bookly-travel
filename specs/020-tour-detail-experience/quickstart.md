# Quickstart and Acceptance Guide: Spec 020

**Status (2026-10-26 — final QA and strict performance audit closure)**: All 74 tasks complete (100% acceptance). On 2026-10-26 the rebuilt Docker runtime (`bookly-frontend` container `a8fb16cf2eee507696e84b0b8753a0497ce39177d013c89e5978003a1dd6f09e`, image `sha256:5d0871d62426995f85926382fc7e78a2c53ac15d250129bd32e0603822d1cda6`, build `AfUeSl_Iw4WVM4xW7N-hy`, including LCP root-cause fixes in `tours.ts`, `CookieConsent.tsx`, and `useAuth.test.tsx` with zero-mismatch [419-file host](evidence/final/performance/repair-20261018/lcp-qa-20261026/new419-candidate-manifest.json) and [420-file container](evidence/final/performance/repair-20261018/lcp-qa-20261026/new420-container-source.json) parity) passed all three in-container Linux source gates: Jest `857/857` tests across `72/72` suites, `tsc 0`, and `ESLint 0` ([source QA](evidence/final/performance/repair-20261018/lcp-qa-20261026/frontend-gates-result.json), [runtime parity](evidence/final/performance/repair-20261018/lcp-qa-20261026/runtime-ready.json)). The 2026-10-26 strict native LHCI audit (`attempt-02`, Lighthouse `12.8.2`) collected all 18 valid fresh reports across all six configured URLs (three runs each): `/en` passed with `0.95, 0.95, 0.96` (min `0.95`), `/en/search?q=rome` passed with `0.90, 0.96, 0.97` (min `0.90`), `/en/blog/hidden-gems-florence` passed with `0.94, 0.94, 0.95` (min `0.94`), and tour detail pages scored `0.88–0.90` (FCP `0.9s` / `1.00`, CLS `0.001` / `1.00`, SI `1.4s–1.5s` / `1.00`, TBT `110–150ms` / `0.94–0.98`), with 22/24 category assertions passing ([attempt-02 result](evidence/final/performance/repair-20261018/lcp-strict-performance-20261026/attempt-02/native-audits-result.json)). [Verification](verification.md) is the authoritative acceptance record. Source-head CI, retained-shell merge and target deployment remain separate unproven gates, and no commits were created. [Plan](plan.md), [consumer contract](contracts/tour-detail-consumer.md), [UI contract](contracts/tour-detail-ui.md).

## Prerequisites and isolation

- Confirm feature pointer resolves to020 and preserve018/019. Retain the documented shell baseline or resolve its merge dependency before release. Generate the execution ledger before implementation.
- Retain installed dependencies. Verified native gates used Node24.14.1/npm11.12.1; Docker uses Node20 and CI requests Node22. These environments are distinct; no runtime upgrade was made for020. Existing proxy/API must be running: native browser base is http://localhost:8080; containers use http://nginx. Playwright does not start the application.
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
@'
const {spawnSync}=require("node:child_process");
const run=spawnSync(process.execPath,["node_modules/next/dist/bin/next","build"],{stdio:"inherit",env:{...process.env,NEXT_PUBLIC_API_URL:"",API_INTERNAL_URL:"http://127.0.0.1:8080"}});
process.exit(run.status ?? 1)
'@ | node
npm run test:e2e -- tests/e2e/tour-detail.spec.ts --project=chromium --project=mobile --workers=1
npm run test:e2e -- tests/e2e/booking.spec.ts tests/e2e/checkout.spec.ts tests/e2e/payment.spec.ts tests/e2e/wishlist.spec.ts --project=chromium-authed --project=mobile-authed --workers=1
npm run test:e2e -- tests/e2e/a11y/tour-detail-a11y.spec.ts --project=a11y --workers=1
```

The build's explicit empty NEXT_PUBLIC_API_URL keeps browser requests same-origin; Windows PowerShell5 removes an environment variable assigned an empty string, allowing an absolute .env.local value to be restored. The Node child environment above preserves the intended empty value. API_INTERNAL_URL is for native server execution through the existing proxy; retain the existing internal URL inside Docker. No secret belongs in NEXT_PUBLIC variables. The authenticated project matcher includes booking/checkout/payment/wishlist filenames.

Docker frontend source is baked into the image. Rebuild before accepting browser/image-optimizer evidence against changed source, then await the production server and proxy readiness. After a Docker engine resume, the existing services auto-start through their own restart policies and the frontend entrypoint may legitimately rebuild only its generated output, yielding a NEW BUILD_ID from the same image; that resumption cannot silently substitute provenance — verify container/host source parity, runtime versions and readiness first ([engine-resume evidence](evidence/final/runtime/opencode-engine-resume-20261017/opencode-runtime-ready.json) did exactly this with zero mismatches):

```powershell
docker compose build nextjs
docker compose up -d --no-deps --force-recreate --renew-anon-volumes nextjs
docker compose logs --tail=100 nextjs
docker compose exec -T nextjs node node_modules/@playwright/test/cli.js test tests/e2e/tour-detail.spec.ts --project=chromium --project=mobile --workers=1 --reporter=line
docker compose exec -T nextjs node node_modules/@playwright/test/cli.js test tests/e2e/booking.spec.ts tests/e2e/checkout.spec.ts tests/e2e/payment.spec.ts tests/e2e/wishlist.spec.ts --project=chromium-authed --project=mobile-authed --workers=1 --reporter=line
docker compose exec -T nextjs node node_modules/@playwright/test/cli.js test tests/e2e/a11y/tour-detail-a11y.spec.ts tests/e2e/a11y/shared-shell-a11y.spec.ts tests/e2e/a11y/checkout-a11y.spec.ts tests/e2e/a11y/wishlist-a11y.spec.ts tests/e2e/a11y/auth-nav-a11y.spec.ts --project=a11y --project=a11y-authed --workers=1 --reporter=line
```

Those commands run the setup dependency on the same origin and create fresh container-local auth state. The `npm run test:e2e` line with only `tour-detail-a11y.spec.ts` above is a narrower focused scan; the actual final integrated accessibility acceptance used the last Docker command above — five a11y spec files with both `a11y` and `a11y-authed` projects (34/34, [result](evidence/final/a11y/root-result.json)). Do not copy auth tokens into evidence. Docker's existing private-CDN optimizer setting resolves owned cdn.bookly.test assets; leave native media adapters unset for final acceptance. Reload nginx DNS only if the recreated service is directly ready but the proxy returns502.

## Actual backend regression

From the repository root, with the existing app service running and disposable test configuration verified:

```powershell
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d test-postgres test-meilisearch
docker compose -f docker-compose.yml -f docker-compose.test.yml exec -T test-postgres pg_isready -U bookly_test -d bookly_test
docker compose exec -T laravel php -d memory_limit=512M vendor/bin/pest --configuration=phpunit.pgsql.xml tests/Feature/Search/TourDetailTest.php tests/Feature/Search/TourAvailabilityLogicTest.php tests/Feature/Search/AvailabilityResolutionRegressionTest.php tests/Feature/Booking/CreateBookingTest.php tests/Feature/Payment/ tests/Feature/Auth/ tests/Feature/Reviews/
docker compose -f docker-compose.yml -f docker-compose.test.yml stop test-postgres test-meilisearch
```

Wait for pg_isready success before Pest; the root runner retains a bounded30second readiness wait. These suites passed229tests/1715assertions in the retained final backend output and do not alone prove presentation; supplement with endpoint → detail → checkout/session-recovery evidence. Stop only the disposable test services after their owner finishes. No backend implementation change was made for020.

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

`frontend/lighthouserc.js` includes `/es/tours/hidden-gems-rome-walking-tour` and `/it/tours/hidden-gems-rome-walking-tour`, retaining its four existing EN URLs, three runs and pessimistic ≥0.9 assertion. Configuration alone does not prove SC-008.

From `frontend/`, against a production proxy/runtime with fixtures and Chrome ready:

```powershell
$env:LIGHTHOUSE_BASE_URL = 'http://localhost:8080'
node --require "../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs" "node_modules/@lhci/cli/src/cli.js" collect --config=lighthouserc.js
node --require "../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs" "node_modules/@lhci/cli/src/cli.js" assert --config=lighthouserc.js --includePassedAssertions
```

The JS config uses installed Chrome/CHROME_PATH and the about:blank CPU-readiness hook; use verified CI/Linux or native host Chrome. Run collect/assert through the retained fail-closed [engine selector](evidence/final/tooling/lighthouse-engine-selector.cjs) as shown: a plain `npx lhci` may resolve the nested @lhci engine copy (12.1.0) instead of the installed root Lighthouse 12.8.2 that this config's traces require, while the selector forcibly resolves bare `lighthouse` to the root engine for LHCI's collect node-runner and exits1 on any precondition violation. Both lines above are the same verified native Node-CLI form executed by the 2026-10-20 strict attempt ([Oct-20 result](evidence/final/performance/repair-20261018/final-three-strict-performance/native-audits-result.json)) and the 2026-10-24 `attempt-03` audit ([Oct-24 attempt-03 result](evidence/final/performance/repair-20261018/loading-strict-performance-20261024/attempt-03/native-audits-result.json)). The assert `--includePassedAssertions` flag is supported by the installed @lhci CLI and retains the passed assertions in the result alongside failures; no assertion is added, removed or weakened. The config itself is unchanged: six URLs (EN home/search/detail/blog plus ES/IT detail), three runs each, default Lighthouse mobile throttling, saved assets and pessimistic performance ≥0.9. Do not use weaker legacy lighthouserc.cjs. Collect/assert avoids the autorun upload step; local evidence requires no public report upload. Serial audits must not compete with builds/Jest/Playwright. Retain18 expected reports (six URLs ×three runs), settings, source commit, environment/resource conditions and traces; failed/missing reports block acceptance. The Windows Invoke-PerformanceAudit wrapper covers only EN and cannot substitute for this six-route collector.

**Historical delegated execution record (2026-10-17; superseded 2026-10-21 — the 2026-10-20 strict attempt below in evidence/final/performance/repair-20261018/ completed collect/assert and measured the first fresh reports, all performance minimums below 0.9)** — the gate has not been met, and nothing weaker is authorized:

- No delegated attempt against this same strict config (human-approved `opencode/glm-5.3-flash-free --variant max`, root reviews only) has produced a valid current-source page report. Earlier Docker-mode and native attempts are retained as recorded failures in [performance evidence](evidence/final/performance/): the first Docker attempt's standalone CPU preflight passed (1623/1533), then `lhci collect` ran and failed its own unchanged readiness hook (collect exit1) while `lhci assert` completed vacuously with zero reports ([result](evidence/final/performance/opencode-after-load-reduction/root-audits-result.json)); one delegated native attempt stopped during preflight-tests with no final result and an unproven cause ([attempt directory](evidence/final/performance/opencode-native-after-load-reduction/)); a resumed native attempt failed earlier still at the runtime-identity `docker exec` because the Docker engine was stopped ([result](evidence/final/performance/opencode-native-resumed-20261017/native-audits-result.json)). The latest native attempt got no further than the readiness hook: preflight tests4/4 exit0, then the hook exit1 without timeout with samples959, 1160.5, 1263, 1493, 1105.5, so collect/assert did not run ([latest structured result](evidence/final/performance/opencode-native-engine-resumed-20261017/native-audits-result.json)). The unchanged CPU rule requires BOTH conditions: the last two internal samples each≥1500 AND their max/min ratio within1.2.
- The Docker app must be the verified runtime before audits: after an engine resume, recheck startup logs, direct3001/proxy8080 readiness, container/host parity and runtime identity ([procedure + fresh example](evidence/final/runtime/opencode-engine-resume-20261017/opencode-runtime-ready.json); BUILD_ID may legitimately change on restart while source stays identical, so never accept an un-verified build).
- Native conditions used by the latest attempt: installed Google Chrome `C:/Program Files/Google/Chrome/Application/chrome.exe` via CHROME_PATH, native host Node v24 spawning installed `frontend/node_modules` dependencies ([host node record](evidence/final/performance/opencode-native-engine-resumed-20261017/opencode-host-node-version.txt)), cwd `frontend`, `LIGHTHOUSE_BASE_URL=http://localhost:8080` (nested stage env in [result JSON](evidence/final/performance/opencode-native-engine-resumed-20261017/native-audits-result.json)), hidden single serial run with PID metadata, no cache warming; exact runner copies and hashes are retained under [tooling](evidence/final/tooling/opencode-executor-tools-manifest.json).
- Before any rerun dispatch, recheck readiness with the same unchanged hook/config; a failed/partial readiness or collection is a recorded failure, cannot be replaced by the12 retained historical reports (pre-T071 four-URL config), and the18-fresh-report + ≥18 unique NEW traces/logs + ≥18 unique NEW devtoolslogs requirement stands. No unchanged-condition retries, threshold/hook/throttling/route/run-count changes or weaker collectors are permitted; [aggregated attempts](evidence/final/performance/delegation-review/opencode-execution-history.json) document that readiness attribution remains unproven (do not infer it from process tables).

## Evidence and release record

Record commands, exit/results, exact source commit, real versus controlled fixture provenance, locale/width, UTC clock and retained output/screenshot/report paths. Keep implementation, runtime verification, CI, merge and target-environment deployment statuses separate. Document deliberate skips rather than implying passes. Rerun the Constitution Check and resolve shell dependency/release readiness before shipping; frontend rollback preserves Spec019 stored content and requires no schema rollback.
