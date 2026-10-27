# Environment Evidence: Spec 020 (T003 source inspection; no runtime acceptance)

**Scope**: Owned proxy/API, production SSR harness prerequisites, disposable PostgreSQL configuration, seeded auth, and deterministic payment mode — recorded from source plus bounded read-only probes. No secrets recorded. No restart/build/reseed/clear, no backend tests, no competing Jest/build runs. Unavailable or unproven runtime is NOT readiness.
**Date**: 2026-10-14 (Africa/Cairo) | **Branch HEAD**: `4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27`

## 1. Owned proxy / API boundary (source)

- `frontend/playwright.config.ts`: host-dev `baseURL` is `http://localhost:8080` (nginx reverse proxy); inside Docker (`DOCKER_ENV=true`) it is `http://nginx`; CI uses the published nginx port. Comment warns browsing the dev server directly causes CORS/HMR failures.
- Published ports observed via `docker compose ps`: nginx `0.0.0.0:8080→80`; frontend `0.0.0.0:3001→3000`.
- `docker-compose.yml` `nextjs` service: `command: sh -c "rm -rf /app/.next/* && npm run build && npm start"`; only `./frontend/.env.local` is bind-mounted (`:ro`) plus anonymous `/app/node_modules`, `/app/.next`. File comment: "Production code comes from the image… Rebuild this service after changing frontend source." A running container does NOT automatically include later host edits.
- SSR internals from source: server uses `API_INTERNAL_URL=http://nginx` in Docker (`http://127.0.0.1:8080` for native runs per quickstart); `NEXT_PUBLIC_API_URL` is intentionally empty so browser calls stay same-origin relative (`/api/...`) through nginx. No secret values recorded.

## 2. Production SSR harness prerequisites (source)

- Quickstart requires: Node 22/npm 10 environment, installed frontend dependencies, a production build, the existing proxy/API running, and owned disposable/seeded tour fixtures. Playwright does not start the application.
- Existing tour-detail SSR/API-fixture harness lives in `frontend/tests/e2e/tour-detail.spec.ts` (extraction to `tests/e2e/helpers/tour-detail-ssr.ts` is planned T008 work, not done). Harness needs a production build plus an actual base API response; browser route interception alone does not exercise SSR.
- No build, harness run, or fixture seeding performed in this job.

## 3. Disposable PostgreSQL configuration (source)

- `backend/phpunit.pgsql.xml`: `DB_CONNECTION=pgsql`, host `bookly-test-postgres:5432`, database/user `bookly_test`, empty password; `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `SCOUT_DRIVER=collection` (isolated, deterministic; no Meilisearch/Redis dependency in tests).
- `docker-compose.test.yml`: disposable `test-postgres` (`postgres:15-alpine`, no host port, tmpfs data) and `test-meilisearch` (`getmeili/meilisearch:v1.10`, tmpfs), on `bookly-network`.
- Test services were NOT started here (single database owner rule; T067 owns the run).

## 4. Seeded auth (source)

- Playwright `setup` project runs `*.setup.ts` once and saves `tests/.auth/traveler.json` and `tests/.auth/partner.json` (git-ignored via `frontend/.gitignore` `/tests/.auth`).
- Traveler-authed specs (`booking|checkout|payment|wishlist|my-bookings|…`) run only in `chromium-authed`/`mobile-authed`; base `chromium`/`mobile` projects exclude them; a11y specs run in dedicated `a11y*` projects.
- Seeded traveler (`traveler`) / partner (`partner@bookly.test`) validity NOT verified here; no login performed, no storage state inspected.

## 5. Deterministic payment mode (source)

- `docker-compose.yml` `laravel` service: `PAYMENT_GATEWAY=${PAYMENT_GATEWAY:-deterministic}`, `MAIL_MAILER=${MAIL_MAILER:-log}`. Effective runtime values depend on host env + `backend/.env`, which were not accessed (out of scope / secrets-adjacent). Deterministic gateway behavior NOT verified here.

## 6. Observed container state (read-only)

`docker compose ps` at inspection time (uptimes ~28 minutes):

| Name | Image | Status | Ports |
|---|---|---|---|
| bookly-backend | booklytravel-laravel | Up 28 minutes | 9000/tcp |
| bookly-frontend | booklytravel-nextjs | Up 28 minutes | 0.0.0.0:3001→3000 |
| bookly-meilisearch | getmeili/meilisearch:v1.10 | Up 28 minutes | 0.0.0.0:7700→7700 |
| bookly-nginx | nginx:alpine | Up 28 minutes | 0.0.0.0:8080→80 |
| bookly-postgres | postgres:15-alpine | Up 28 minutes (healthy) | 5432/tcp |
| bookly-queue | booklytravel-queue | Up 28 minutes | 9000/tcp |
| bookly-redis | redis:7-alpine | Up 28 minutes (healthy) | 6379/tcp |
| bookly-scheduler | booklytravel-scheduler | Up 28 minutes | 9000/tcp |

Running state alone does not prove proxy/API/SSR readiness for T003/T004.

## 7. Bounded read-only probes (honest results; failures NOT converted to readiness)

Each via `curl.exe --max-time 12` (single attempt, no retry storm):

| Probe | Outcome |
|---|---|
| `http://localhost:8080/` | `000`, ~12.0s timeout — unreachable within bound |
| `http://localhost:3001/` | `000`, ~12.0s timeout — unreachable within bound |
| `http://localhost:8080/en/tours/hidden-gems-rome-walking-tour` | `000`, ~12.0s timeout — unreachable within bound |
| `http://localhost:8080/api/up` | `404` after ~8.5s — a response arrived, but 404 is not a health pass |

No runtime acceptance claim is made from source inspection. Root's separate runtime check owns liveness determination; T004 baseline capture must treat an unavailable runtime as blocked, not passing.


## Root review and local readiness confirmation — 2026-10-14

The earlier bounded probe failures remain diagnostic evidence; subsequent root checks verified the local runtime. Public EN detail returned HTTP200 in0.286s, with60 offered dates, EUR pricing, EN content/itinerary and not-unavailable status (actual public response retained at evidence/baseline/public-detail-en.json). Internal nginx→Next tour-page request exited0. Both seeded test-auth fixtures returned200 and issued nested data.token; only status/boolean evidence retained, no tokens. Runtime services.payment.gateway is deterministic and its bound class is DeterministicPaymentGateway. Disposable test services started; PostgreSQL public-contract suite passed47 tests/1083 assertions in21.16s.

Native Node24.14.1/npm11.12.1 differ from the guide's Node22/npm10 recommendation. No dependencies were changed. First native focused Jest attempt stalled before any result and was stopped only after verifying its exact owned PID/command. A single-suite retry with --watchman=false passed6 tests; the complete focused baseline with that flag then passed8 suites/64 tests in14.188s. This establishes a usable runner configuration, not a proven causal diagnosis of the first stall. All baseline logs are retained.

Root reviewed the delegated evidence against actual files, bundled server/client/error/image guides, primitive interfaces, git ancestry/pointer and ignore configuration. T001-T004 are accepted for the verified local implementation baseline; external target deployment and retained-shell merge readiness remain separate future T073 gates. No source behavior has changed yet.
