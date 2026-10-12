# Independent review evidence, 2026-10-10/11

Final acceptance updated 2026-10-12: all 78 Spec019 tasks pass. Complete Tests
CI38263823843 and Lighthouse CI38263823800 pass on source ee24b45. The accepted
twelve raw Lighthouse reports (91-99 Performance, all other categories100)
are retained in lighthouse-reports/ciee-lighthouse-artifacts as a hash-verified
ZIP plus readable summary; all failed groups remain retained too. Local source
Jest414, productionbuild and affected browser/Axe148 pass. Final source CI
metadata is in accepted-final-source-ci-ee-20261012.json; Lighthouse metadata
is in accepted-final-lighthouse-ci-ee-20261012.json. Verification section32
owns final FR/SC/Constitution acceptance and documented CI/local differences.
The earlier pending/failure descriptions below retain historical gate results.
Final documentation-head CI/readiness are tracked separately; merge/release
are pending. Spec018 is preserved. No provisional off-screen CSS is included.

These logs record root reruns of the actual working tree after OpenCode authored
the bounded US4, US5 and US6 changes. OpenCode used only
`opencode/muse-spark-1.3-contributor-free`. Author reports are not acceptance.
Numeric exit codes and timestamped argv are retained in each manifest.

## Confirmed gates

- US4/US5 backend: 120 passes, 1718 assertions; Pint and PHPStan pass.
- US4/US5 production browser scenarios: 86 passes. The ensuing accessibility
  gate failed four cases; its failure is retained, rather than reported passed.
- Legacy adoption: 23 passes, 163 assertions, including actual PostgreSQL source
  races. JSONB object keys are canonicalized only for associative-object equality;
  list order, native values and complete contents remain strictly asserted.
- Search/detail/index: 51 passes, 1153 assertions, including delayed stale
  derivative projection, genuine primary ratings and old contract consumers.
- SEO components: eight passes. Public metadata/page: five passes.
- Full backend: 1023 passes, 5287 assertions; Pint 593 files, PHPStan zero errors.
- Full frontend before the final wizard H1 correction: lint/typecheck/build pass;
  55 Jest suites, 414 tests pass. A fresh final build follows the H1 correction.
- The first fresh US6 accessibility rerun passed 27/30; all three failures were
  `page-has-heading-one` on the create wizard. Root added the localized H1.
  Do not treat this failed log as final accessibility acceptance.

Full browser, final accessibility, manual states, Lighthouse and frontend rollback
are accepted only when their separate actual results are added to verification.md.
No final Phase 2 acceptance, new-head CI, merge or release follows from these
intermediate logs.

## Configured-model local rehearsal

The user explicitly authorized isolated local testing with the existing configured
model. `live-local/` uses synthetic tours 48 and 49 in the exclusively owned
`bookly-spec019-browser` PostgreSQL/Redis project. No production deployment or
production database is involved. The public fixture's publication is a deliberate
test database operation; it is not proof of an admin publishing UI interaction.

The actual authenticated HTTP source write returned 201 in approximately 3.409s.
ES/IT were pending before a real daemon worker used `gemini-3.5-flash-lite`.
Both became ready with the exact English hash, nine source fields, native numeric
stop durations, nulls and repeated day order retained. Two transient connection
errors recovered through ordinary retries. The first worker ran approximately
26.599s; an additional 2.115s drain consumed an idempotent ready duplicate. Queue
depth reached zero. This is actual configured-model evidence, separately from
the fake-provider automated gates.

Recovery bounded at `--after-id=47 --limit=1` inspected tour 48 and queued two
locale jobs. Adoption bounded at `--after-id=48 --limit=1` inspected tour 49:
dry-run left EN, states and queue unchanged; apply adopted two string days in
order, preserved raw legacy values, and scheduled two translation jobs plus an
index job; repeat adopted zero and skipped one. Actual ES public reading returned
English fallback, two real days, no photo/review and unchanged EUR 7500 minor-unit
price. The private queue gained three jobs after adoption; no additional real
provider worker is run over those or the automated browser fixtures.

Compatible backend HTTP readers returned fully identical EN/ES/IT DTOs, and the
compatible worker consumed two ready duplicates without a provider call. The
before/after retained-data fingerprint is
`15ab683d0950f420187c214d55c2dd6219216e9191811e1f6182714bbbde84e8`.
The compatible artifact is base commit 4613fe8 plus the nine explicitly hashed
reader/media compatibility files in its manifest. It intentionally retains the
new alt column. It is not an assertion that every application file was reverted.
The earlier frontend build `RuGVVMvrRQc-BJGPcwMvv` was actually started on the
same exclusively owned frontend server. Six real EN/ES/IT SSR pages for tours
48/49 passed visible-fact, language, canonical, hreflang, ordering and money
comparisons. The current build `1h7I7N6dpRg2PtBQVfPBL` was then restored and
the same six pages passed again. The complete retained-data fingerprint above
was unchanged after frontend restoration. See `live-local/frontend-rollback-result.json`,
`frontend-current-result.json`, and the corresponding six-page facts and native
exit logs. The initial premature server-start failure is retained separately.

After acceptance, an explicitly guarded local transaction archived only the
two exclusively owned rehearsal fixtures. Their source, derivatives, legacy
values, translation states, media, prices and availability remained unchanged;
both public routes returned the established archived-tour HTTP 410 response.
This cleanup occurred after the rollback fingerprint comparison and intentionally
changes status. The private pending adoption queue was retained without another
configured-model worker. No production data or personal containers were changed.

## Final accessibility and manual interface acceptance

The final progress-layout build passed full lint/typecheck, all 31 wizard tests
and production build. The unchanged affected Axe rules passed 30/30. A localized
H1/H2 corrected the genuine heading failures. Non-shrinking step circles and
bounded labels corrected the clipped 768px progress row found in manual review.
All five steps remain visible and accessible; truncated labels retain full titles.

`reviewer-manual-progress-final-20261011/` retains 84 actual screenshots across
EN/ES/IT and 390/768/1024/1440, with a 12-case passing manifest and reproduction
script. Runtime checks cover focus/error announcements, reduced motion, absence
of document overflow, progress bounds, loading/disabled and controlled 422/403/201
states. Those controlled POST responses are browser-only simulations, not proof
of database writes. The empty-media adopted tour is actual production SSR.
Root visually inspected one representative final screenshot in each of the 12
locale/width combinations, rather than claiming every screenshot was manually
inspected. Existing draft validation can remain after controlled draft success;
no image or review was fabricated for the adopted tour.

Actual ES JSON-LD extracted from the live fixture's HTTP 200 production SSR page
was submitted to Schema.org's official validator. Its visible result is zero
errors and zero warnings; the exact JSON, visible output and screenshot are
retained. This tests the actual emitted payload, rather than only a prototype.

## Rehearsal corrections and limits

The initial local API used a synchronous queue with a blank key. That probe did
not demonstrate generation. A local-only override moved the owned API to its
private asynchronous Redis queue; the web/frontend never received the key in
their environment. Only the short-lived backend worker used the existing private
configuration. An early Laravel console exception handler printed an exception
with exit zero; root rejected that misleading result and added explicit failure
exit/data assertions to the proof harness. Early records in the append-only
manifest must not be accepted on exit zero alone. Named output files are latest
observations; not every overwritten initial transcript is retained as a file.

Private rollback HTTP setup initially failed because of a missing mount point,
Artisan serve child environment reload, a different APP_URL and server readiness.
The final proof uses explicit isolated configuration and bounded HTTP readiness,
with the same full DTO comparison. Those were harness corrections, not product
source fixes. The old backend server was stopped after verification.

A root copy of the proof directory briefly included its private worker env file
under `/tmp` in the isolated frontend test container. Root deleted that exact
temporary copy immediately and verified it absent before starting any frontend
proof; it was never loaded, serialized or staged. Subsequent copies use explicit
file allowlists. The original backend-only private env is excluded from this
evidence, alongside storage logs, browser authentication states and raw provider
request/response bodies.

Repository text copies strip terminal escape codes and trailing whitespace.
Original logs remain outside the repository in the delegation evidence directory.
Preserved failing outputs document actual corrections; they are not silently
relabeled as passing results.

## Full-browser and performance rerun corrections

The first 879-case run was deliberately interrupted after 225 passes, four
failures and one interrupted test; 649 did not run (native exit 130).
The asset test hardcoded the debug-mode Livewire path although installed
Livewire registers `livewire.min.js` when debug=false. It now discovers and
strictly checks the actual rendered script before requesting it through the
same proxy. Login failures separately showed rejected credentials; the owned
database had no `admin@bookly.test` row. Root seeded that synthetic account with
the existing official `AdminUserSeeder`, as browser CI does. No production
authorization rule, password assertion or proxy rule was relaxed.

The first complete eight-report Lighthouse run is retained under
`lighthouse-reports/run-2026-10-10T22-34-46-433Z`: Performance was
57/86/71/91 then 91/91/86/91 for home/search/tour/blog. It failed acceptance.
The owned frontend container had a root-added two-CPU quota absent from browser
CI. An attempted `docker update --cpus 0` returned success but later inspection
proved NanoCpus remained 2000000000. The second complete eight-report run,
retained under `run-2026-10-10T22-38-24-213Z`, therefore still used that quota
and also failed. Root does not claim this command removed the CPU limit.

The reports identify the first search image as lazily loaded with no high
priority hint. Root uses the existing TourCard imagePriority for the first result
and adds an explicit high priority hint to the already preloaded tour hero.
The calendar now reuses two locale-specific Intl formatters instead of creating
new formatters for every date/label. New full frontend/build/browser/performance
gates must establish whether these bounded changes meet acceptance. No score
threshold, throttling setting, cookie interaction or Lighthouse assertion changed.

Full lint/typecheck, 55 Jest suites/414 tests and production build passed after
these bounded changes (`reviewer-frontend-performance-final-20261011`). The
fresh build ID is `LMXlxfuWfJMqHaFcFVRtz`. The corrected actual-script/admin
fixture browser rerun passed all seven tests in 31.0 seconds.

The subsequent eight-report run at `run-2026-10-10T22-53-10-605Z` also failed.
During that run, root discovered the unchanged quota and set the exclusively
owned container to the eight CPUs actually available to Docker. NanoCpus
8000000000 and cgroup cpu.max `800000 100000` were verified. Because this
setting changed during the recorded audit, that run has mixed conditions and
cannot establish acceptance. Its reports and explicit correction observation
are retained. A new complete round under fixed conditions is required.

Each recorded Lighthouse directory now retains its readable summary, an
eight-report `raw-reports.zip` and `raw-report-sha256.json`. All 32 archived
reports were read back and compared with the original SHA-256 before their
verbose JSON copies were removed from the review diff. Nothing was omitted
from the archives; original logs/reports also remain outside the tracked tree.

## Runtime restoration and CI integration corrections (2026-10-12)

The owned runtime exited before its corrected 879-case suite completed; the
last recorded case was 570 and no complete-suite exit exists. All six owned
container labels were checked before restarting. PostgreSQL's disposable
schema was empty on restart, so only this isolated database was migrated and
seeded; its isolated tour search index was flushed and reimported. Production
build `LMXlxfuWfJMqHaFcFVRtz`, NSS and the eight-CPU setting were retained.
Uniform HTTP readiness checks covered all four audit routes plus admin/detail.

The resulting fixed-condition local eight-report audit gave home/search/tour/blog
65/92/92/93 and 95/93/95/92. Its native exit is one: seven passing results do
not accept the whole group. No low score is discarded or selectively retried.

CI on source checkpoint 857a31b passed PHP 8.3/8.4/8.5, Pint, PHPStan, frontend
checks/tests and the existing home-only Lighthouse job, but all browser shards
failed. Root traced missing partner storage state to absent base-project setup
dependencies, native-runner image loading to the additional SSR server outside
the private CDN network, and US1 selectors to changed truthful image alternatives
and the localized location input. Base browser projects now depend on setup;
real source/media scenarios use the actual production proxy/SSR server; the
controlled-response SSR harness remains separate. Actual image loading and
full source/media/money preservation assertions remain required.

The initial focused rerun passed 70/72 and exposed two US1 selector failures.
After correcting the two-image expectation and labeled location control, both
desktop/mobile retention scenarios plus authentication setup passed 4/4 with
native exit zero. The full browser rerun still remains required.

The existing Lighthouse CI configuration now audits the same four primary routes
three times each, retaining its 90 threshold and requiring the least performance
score with `aggregationMethod: pessimistic`. This strengthens the former
home-only optimistic gate. CI acceptance requires actual successful reports;
configuration syntax checks alone are not performance evidence.
