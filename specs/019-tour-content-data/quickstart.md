# Quickstart and Acceptance Guide: Spec 019

**Status**: Instructions for subsequent implementation; no runtime gates in this guide were executed during planning.
**Contracts**: [public](contracts/tour-detail-api.md), [partner](contracts/partner-tour-content-api.md), [generation](contracts/translation-generation.md).
**Model**: [data-model.md](data-model.md).

## Prerequisites and Isolation

- Keep branch `codex/019-tour-content-data`, predecessor `84ae4e5`, Spec 018 and documented pending PR dependencies. Follow the generated [tasks.md](tasks.md) before implementation.
- Install backend/frontend dependencies and Docker tooling. Run feature suites against disposable PostgreSQL: `phpunit.pgsql.xml` and `tests/bootstrap.php` select `bookly_test`, never development/production data.
- Only one backend suite may own the disposable database. Controlled multi-connection race tests belong inside that suite and clean their own fixtures.
- Bind fake TourContentTranslator and fake HTTP/queue in automated tests; prevent real provider requests. Integrated browser writes use seeded non-production accounts/tours and controlled translation results.
- Existing detail browser tests start a local API fixture and isolated built Next process; their base fixture comes from the configured API. Browser route mocks alone cannot intercept SSR.
- Browser config expects the app/proxy already running: native `http://localhost:8080`, Docker `http://nginx`. Partner projects depend on seeded authentication setup; config does not start the app.
- Build/SSR needs API_INTERNAL_URL or NEXT_PUBLIC_API_URL. For native execution using the existing proxy, API_INTERNAL_URL can be `http://127.0.0.1:8080`. Container execution uses existing internal proxy configuration. No secret belongs in a NEXT_PUBLIC variable.

## Existing Focused Backend Checks

From repository root, start disposable test services, then run existing suites with explicit PostgreSQL configuration:

```powershell
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d test-postgres test-meilisearch
docker compose exec -T laravel php -d memory_limit=512M vendor/bin/pest --configuration=phpunit.pgsql.xml tests/Feature/Partner/TourCreateTest.php tests/Feature/Partner/TourDraftTest.php tests/Feature/Partner/TourTranslationTest.php tests/Feature/Search/TourDetailTest.php tests/Feature/Admin/TourModerationTest.php
```

The laravel service must already run through existing application setup. Verify test-specific configuration before execution. Starting test services does not authorize clearing development tours or another shared database. Extend these suites with the scenarios below; task generation assigns new race/adoption tests. Running only current tests does not prove the new requirements.

## Existing Focused Frontend Checks

```powershell
Set-Location -LiteralPath 'F:/Travel Website/bookly travel/frontend'
npm run lint
npm run typecheck
npm test -- --runInBand --runTestsByPath src/lib/validators/__tests__/partner.test.ts src/components/tour/__tests__/TourDetail.test.tsx src/components/tour/__tests__/ImageGallery.test.tsx src/components/partner/tours/__tests__/ImageUploader.test.tsx src/components/seo/__tests__/StructuredData.test.tsx 'src/app/[locale]/(public)/tours/[slug]/__tests__/page.test.tsx'
$env:API_INTERNAL_URL = 'http://127.0.0.1:8080'
npm run build
npm run test:e2e -- tests/e2e/tour-detail.spec.ts --project=chromium
npm run test:e2e -- tests/e2e/partner/tour-create.spec.ts tests/e2e/partner/tour-edit.spec.ts --project=chromium-partner --project=mobile-partner
npm run test:e2e -- tests/e2e/a11y/tour-detail-a11y.spec.ts --project=a11y
```

The API_INTERNAL_URL above applies to native host execution. Keep tests serialized where they share mutable seeded tours. Add tests for new wizard/source/duration fields and OperatorSummary. Fixture-driven SSR acceptance supplements an integrated partner-write/public-read test; it does not replace one.

## Acceptance Scenarios and Expected Outcomes

| Scenario | Procedure / expected result | Trace |
|---|---|---|
| Source round trip | Create owned EN tour with every source field; update one, omit one, clear nullable text/lists; reopen source and draft. Accepted values/order match; omitted data/media retained | US1; FR-001-009; SC-001/005 |
| Ownership and state forgery | Use partner IDs different from user IDs; try another tour and ES/IT/state/hash writes. Denial/422, no modified source/state or leaked fields | FR-002/011/023/027 |
| Itinerary bounds | Test 0/30/31 days, 0/20/21 stops, 160/161 titles, 2000/2001 descriptions, fractional/out-of-range day/duration, null/1/1440/1441 duration. Valid round trip; invalid field paths fail atomically | FR-005-007; SC-001 |
| Day ordering | Supply repeated valid day numbers and reordered object keys. Arrays retain order, UI keys stay stable, semantic object-key reordering alone does not regenerate | FR-001/005/010; SC-001/004 |
| Draft versus publish | Save/restore owned incomplete and legacy snapshots; try submit/approve without trimmed EN title/description, then with complete EN plus existing cover/pricing guards and unavailable derivatives. Draft retained, incomplete publish refused; derivatives do not block eligible publication | US1/US3; FR-004; SC-003 |
| All locale states | Read EN/ES/IT with current/missing/pending/stale/failed derivatives, independently missing itinerary, and empty EN itinerary. Actual language/notice matches; no empty section or misleading notice | US2; FR-014-016; SC-002 |
| Ready reload | Complete current fake translation then reload SSR and read backend detail. Current derivative replaces EN; stale-source/late completion retains current English, URLs and guide codes stable | US2/US3; SC-002/004 |
| Real races | Use barriers and independent PostgreSQL connections/processes for source save, completion, duplicate, terminal failure and adoption. No stale overwrite/ready demotion, common lock order, network work outside locks | FR-010-013; SC-004 |
| Queue/projection recovery | Force dispatch failure after source/ready commit; reconcile current unfinished generation and ready projection intent. Source/derivative remain saved; index refresh recovers without regenerating a matching ready derivative | FR-010/012/013/016 |
| Legacy adoption | Seed valid, invalid, empty, partially invalid, populated EN and missing-EN fixtures. Dry-run has no writes/jobs; bounded repeats preserve legacy/populated arrays, report counts/cursor and never fabricate EN | FR-008/009; SC-005 |
| Supported facts / media | Round-trip difficulty, guide codes, duration, cover/gallery and optional alt. Preserve legacy null difficulty on restore/unrelated save. Omitted media retains rows; duplicate authored URLs fail; legacy duplicate URLs deduplicate on read; alt text uses actual language | US4; FR-017-021; SC-006 |
| Operator / alternatives | Seed inactive/unapproved profiles, zero/hidden/flagged reviews, expired published tours and >32 invalid related candidates before valid ones. Only exact eligible aggregates/cards; correct ranking/limits; no private fields | US5; FR-022-026; SC-007 |
| Search facts / compatibility | Inspect real selected day/stop/meeting Place/gallery facts, actual languages, safe script escaping and genuine/no-review ratings. Existing fields, 404/410/unavailable/booking consumers remain compatible | US6; FR-027-029; SC-008 |
| Interface matrix | Save/errors/draft/status/fallback/empty/denial in EN/ES/IT at 390/768/1024/1440. Keyboard/focus/labels/live errors pass with Axe and reviewed screenshots; English specific alt on ES/IT pages has correct lang | FR-030; Constitution VI/VII |

## Planned Adoption and Recovery Commands

Run these only after their implementation/review in controlled non-production rehearsal. The adoption command is new and unavailable in the baseline. The translation command exists; orphan-pending recovery and --refresh-ready are planned extensions. Dry-run precedes adoption.

```powershell
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
docker compose exec -T laravel php artisan tours:adopt-itineraries --dry-run --after-id=0 --limit=100
docker compose exec -T laravel php artisan tours:adopt-itineraries --after-id=0 --limit=100
docker compose exec -T laravel php artisan tours:queue-translations --after-id=0 --limit=100 --refresh-ready
```

Continue from each reported cursor; limits remain 1-500. Preserve populated English and raw legacy data. Missing EN is reported for governed repair. Fake-based automated scenarios require no real Gemini key.

## Live Staging Smoke and Rollback

Before release, provision backend/worker-only credentials in the secret store and a model available for the project. Start asynchronous Redis workers with verified job/worker/retry timeouts. Save a dedicated staging English tour and verify source HTTP success, derivative progress and ready next-read replacement. Record sanitized status/category and release IDs; never keys/prompts/provider bodies. Provider entitlement and runtime behavior remain unverified until this smoke runs.

Apply additive schema before dependent code and compatible image/cover/gallery readers before new typed writers. Confirm EN-only consumers and post-commit projection/cache refresh. Rehearse frontend and compatible backend/worker image rollback with backup; retain columns, legacy content, media and derivatives. Ordinary rollback does not run migrate:rollback. An old image-only reader needs the compatibility patch for a complete new gallery; a retained legacy cover alone is degraded display.

## Full Gates and Evidence

After implementation, run both stacks:

```powershell
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
docker compose exec -T laravel php -d memory_limit=512M vendor/bin/pest --configuration=phpunit.pgsql.xml
docker compose exec -T laravel php vendor/bin/pint --test
docker compose exec -T laravel php -d memory_limit=512M vendor/bin/phpstan analyse
Set-Location -LiteralPath 'F:/Travel Website/bookly travel/frontend'
npm run lint
npm run typecheck
npm test -- --runInBand
npm run build
npm run test:e2e
npm run test:a11y
```

Run documented Lighthouse production-build audits on reachable affected primary pages, requiring Performance>=90 under stated conditions. Record machine/browser/build/fixture/network conditions and report paths; unreachable/misconfigured runs do not verify compliance.

Each result records commit, date, command, fixture/environment, locale/viewport, exit/result and retained evidence paths. Keep implementation, runtime verification, CI, merge, staging rollback and release acceptance separate. Spec 018 historical totals do not become Spec 019 evidence, and this guide does not mark Phase 2 complete.
