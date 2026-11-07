# Spec 021 — Workspace Isolation Audit Report

**Task:** Homepage Discovery — Spec 021 isolation into dedicated branch/worktree
**Date:** 2026-11-04
**Primary repository:** `F:\Travel Website\bookly travel` (branch `fix/master-admin-audit-remediation`)
**Dedicated worktree:** `F:\Travel Website\bookly-homepage-discovery` (branch `feature/021-homepage-discovery`)

---

## A. Initial Git State

| Item | Value |
|---|---|
| Branch | `fix/master-admin-audit-remediation` |
| HEAD | `e517cf7820791524c52af67a07e59a1aac3b824c` (matched mission expectation) |
| Staged files | 8 (exactly the eight reconciliation files, status `M`) |
| Unstaged modified files | 44 |
| Untracked files (expanded, `-uall`) | 132 |
| Total change entries | 184 (all SHA-256 hashed, 0 missing) |
| Stashes | 4 — `stash@{0}`: `wip-admin2-merge-eight-files` (untouched) |
| Safety snapshot | `F:\Travel Website\bookly-spec021-pre-isolation-backup-20261104_014600` (verified present) |
| Admin 2.0 backup / quarantine | `bookly-admin-premerge-backup-20261103_024422`, `bookly-admin-quarantine-20261103_025028` (verified present) |
| Admin 2.0 worktree | `F:\Travel Website\bookly-admin-2.0` (unmoved) |
| Pre-existing branch note | Branch `021-homepage-discovery` (no `feature/` prefix) already exists at `6fcdbb8` and is an **ancestor** of the primary HEAD (already integrated). The new branch uses the distinct name `feature/021-homepage-discovery`. |

Complete state captured to `bookly-spec021-pre-isolation-backup-20261104_014600\spec021-isolation-artifacts\`:
`full-status-porcelain.txt`, `working-state-sha256-manifest.txt` (184 rows), `staged-eight-files.patch`, `unstaged-all.patch`, `untracked-list.txt`, `head.txt`.

**Forbidden operations used: none.** No `reset --hard`, `clean`, `checkout .`, `restore .`, `stash pop`, stash drops, backup deletions, commits in primary, pushes, or deployments.

## B. Change Ownership Manifest

Classification was made **by content inspection** (git diffs, imports, spec cross-references), not by filename.

| Category | Count | Handling |
|---|---|---|
| `SHARED_ADMIN2_AND_SPEC021` | 8 | All staged files; transferred as complete working content (Admin 2.0 baseline is committed, so the staged delta is Spec 021-only) |
| `SPEC021_ONLY` | 168 | Transferred to `feature/021-homepage-discovery` (39 unstaged-modified + 129 untracked) |
| `OTHER_EXISTING_WORK` | 5 (`TourPolicy.php`, `TourResource/Pages/ListTours.php`, `TourBookingController.test.tsx`, `caching.test.ts`, `admin-release-conditions-closure.md`) | Kept in primary — admin-audit remediation / test hygiene, not Spec 021 |
| `ADMIN2_ONLY` | 3 (`AdminRoleSeeder.php`, `AdminRbacSeeder.php`, `docs/reports/admin-2.0-main-repository-transfer-audit.md`) | Kept in primary |
| `GENERATED_ARTIFACT` | 0 | — |
| `UNKNOWN_REQUIRES_REVIEW` | 0 | — |

Key ownership evidence:
- **Wishlist ownership resolved through source comments, not guessing:** `frontend/src/lib/wishlist/guestWishlist.ts` states *"Implements the unauthenticated wishlist lifecycle specified in Spec 021 (FR-013, FR-024)"*; `specs/021-homepage-discovery/contracts/homepage-discovery-api.md:178` specifies the merge endpoint; `spec.md` FR-013/FR-024 codify it. Therefore the staged route `POST /api/public/traveler/wishlist/merge`, `WishlistController::merge`, `WishlistTest.php`, `traveler.ts`, `useAuth.tsx`, `WishlistHeartButton.tsx`, `useWishlist.ts`, `guestWishlist.ts`, `wishlist.spec.ts` and wishlist message keys are all **Spec 021**.
- `EventServiceProvider.php` changes carry a literal comment: *"Spec 021: homepage cache invalidation"*.
- `CategoryResource` (tour categories, FR-008 image upload) and `DemoContentManager`/`DemoMediaDeployer` wire into the two Filament navigation groups added by the staged `AdminPanelProvider.php` (`Content`, `Demo Content`).
- Seeders, `docker-compose.yml` (`./docker/cdn:/var/cdn`), `next.config.ts` (image hosts + `/storage` rewrite), `proxy.ts` (`/storage/` exclusion) form the Spec 021 CDN/image-data pipeline.

Full machine-readable manifest (184 rows with SHA-256):
`docs/reports/spec-021-ownership-manifest.csv`
Transfer/recovery artifacts: `transfer-list.txt`, `transfer-copy-manifest.txt` (176 rows, OK), `branch-committed-filelist.txt` (176 rows).

## C. Eight-File Reconciliation Analysis

Method: the committed HEAD state is the Admin 2.0 baseline (never modified); the staged delta of each file was diffed and attributed; the staged patch was extracted to `spec021-isolation-artifacts\staged-eight-files.patch` (and re-extracted `staged-after-verification.patch`, byte-identical).

| # | File | Committed Admin 2.0 baseline | Staged delta (ownership) |
|---|---|---|---|
| 1 | `GetBlogCategoryAction.php` | Blog category API action (integrated baseline) | `image_url` in PHPDoc + output — Spec 021 category images |
| 2 | `BlogPostDetailTransformer.php` | Blog post detail API | cover/category image normalization — Spec 021 images |
| 3 | `BlogPostTransformer.php` | Blog post card API | `formatImageUrl()` helper (shared) + category `image_url` — Spec 021 |
| 4 | `BlogCategoryResource.php` | Filament blog-category CRUD | `FileUpload`/`ImageColumn` for category images — Spec 021 (FR-008 support) |
| 5 | `BlogPostResource.php` | Filament blog-post CRUD | `->disk('public')` on cover upload/column — Spec 021 CDN storage |
| 6 | `AdminPanelProvider.php` | Admin 2.0 panel definition | adds `Content` + `Demo Content` nav groups — Spec 021 wiring |
| 7 | `backend/routes/api/public.php` | Public API routes | `POST wishlist/merge` route — Spec 021 FR-024 (guest wishlist) |
| 8 | `frontend/src/lib/api/types.ts` | Shared frontend API types | `badge` (FR-014), `CuratedRail` + `curated_rails` (FR-001/FR-007), category `image_url` — Spec 021 |

Shared-dependency note: `BlogPostTransformer::formatImageUrl` is reused by files 1–2 and by unstaged `TourCardTransformer`/`CategoryController`; Admin 2.0 behavior was preserved intact in every case (deltas are strictly additive).

## D. Dedicated Branch/Worktree Status

| Item | Value |
|---|---|
| Branch | `feature/021-homepage-discovery` |
| Base | `e517cf7820791524c52af67a07e59a1aac3b824c` (primary HEAD, verified) |
| Worktree | `F:\Travel Website\bookly-homepage-discovery` (created; path was absent; Admin 2.0 worktree untouched) |
| Worktree final state | clean — 0 uncommitted entries |
| Branch diff vs base | exactly 176 files |
| Transfer fidelity | all 176 files **byte-identical** to primary working copies (SHA-256 re-verified post-transfer, 0 mismatch) |

Commits (chronological; verified staged paths explicitly per commit, never `git add .`):
1. `e3ba0a7` feat(home): add homepage discovery API and data contracts (11 files)
2. `c94923c` feat(home): implement homepage category management (15)
3. `a03e61f` feat(home): implement homepage discovery UI (29)
4. `9ef119c` feat(search): implement homepage discovery filters (9)
5. `c26b2aa` feat(home): add demo content and fixtures (54)
6. `4d46286` chore(infra): configure homepage CDN and assets (38)
7. `a282f2d` test(home): add homepage discovery coverage (7)
8. `043e636` docs(spec-021): update implementation and verification records (12)
9. `43364f4` refactor(search): shared ImageWithFallback component for TourCard image resilience (1)

Total: 176 files; +4348 / −295 lines.

## E. Spec 021 Completeness (validated in the isolated worktree)

- **All spec-kit artifacts present** (11 files, `specs/021-homepage-discovery/**`).
- **Frontend modules:** all 11 home components, 4 search components, `WishlistHeartButton`, `ImageWithFallback`, wishlist lib, page + search route, fixtures — present.
- **Backend modules:** homepage action/controller, category controller/transformer, wishlist merge endpoint + route, category demo manager/deployer, `blog_categories.image_url` migration, 4 seeders, 3 backend `lang/{en,es,it}/homepage.php` — present.
- **Contracts ↔ types:** staged `types.ts` additions (`badge`, `CuratedRail`, `curated_rails`, category `image_url`) were written against and verified against `contracts/homepage-discovery-api.md` and `discovery-ui-contracts.md`.
- **Assets:** 47 seed fixtures + 47 Docker CDN copies present; `DemoMediaDeployer` candidate paths (`seeders/fixtures/cdn`, `docker/cdn`, `/var/cdn`) all resolve.
- **Docker/CDN:** `docker-compose.yml` mounts `/var/cdn` into nginx/php-worker/queue; `next.config.ts` serves `/storage/:path*`.
- **Translations:** `messages/{en,es,it}.json` updated; backend `homepage.php` ×3 present.
- **Import graph:** scripted resolution of every internal import (`~/@/./../`) across transferred frontend files — **85/85 resolved, 0 unresolved** inside the worktree.
- **Frontend tests (isolated run in the dedicated worktree via worktree-local node_modules junction):** 10 suites/40 tests + 6 suites/49 tests = **16 suites, 89 tests, all passed** (home, home-sections, search, filters, wishlist, page + page-resilience).
- **Backend tests:** HomepageTest (13 cases) **could not boot in either workspace** — identical failure exists in the primary workspace (Pest/Laravel test-case wiring + DB connector); this is a pre-existing local environment limitation, not introduced by the isolation. Static backend validation passed (routes/transformers/seeders wiring verified above). The running `bookly-audit-runner` Docker container is available for a later in-container run.
- **Worktree-local runtime conveniences (additive, gitignored, safe):** `frontend/node_modules` and `backend/vendor` junctions to primary; `backend/.env` copy — used only to run the isolated test suite.

## F. Original Workspace Integrity

1. HEAD unchanged: `e517cf7820791524c52af67a07e59a1aac3b824c`. 2. All eight staged files preserved — staged patch re-extracted and **byte-identical** to the Phase 0 capture. 3. Full `git status --porcelain=v1 -uall` after isolation: **184 entries, exactly equal to the pre-isolation baseline, 0 differences** (all Admin 2.0 committed files untouched; nothing lost or altered). 4. `stash@{0}` and all 4 stashes intact. 5. All backups/quarantine dirs intact. 6. The two report deliverables added afterwards (`spec-021-ownership-manifest.csv`, this file) are new untracked files in `docs/reports/` — additive only. Primary branch received **zero commits**.

## G. Remaining Risks

1. **Backend runtime verification open:** HomepageTest/WishlistTest must be run inside `bookly-audit-runner` (or with local Pest boot fixed) — pre-existing environment issue, both workspaces affected identically. The transferred backend content was validated statically only.
2. **Playwright E2E** (`homepage.spec.ts`, `search.spec.ts`, `wishlist.spec.ts`, `a11y.spec.ts`) not executed in this session (browser harness not run); fixtures exist and paths resolve.
3. **Duplicated state:** Spec 021 working copies remain in the primary workspace (by design — nothing was moved). When primary work resumes on the wishlist-merge integration or the branch is merged, the admin-audit files excluded here (`TourPolicy.php`, `ListTours.php`, two test-hygiene files, one admin report newline) are still uncommitted in primary and must be committed separately or intentionally retained.
4. **Name collision watch:** old branch `021-homepage-discovery` (integrated ancestor) coexists with `feature/021-homepage-discovery`; keep both names distinct in tooling.
5. **Worktree-local junctions**: `frontend/node_modules` and `backend/vendor` junctions point into the primary tree; remove (`fsutil reparsepoint delete` or plain `Remove-Item` of the link) if a fully dependency-free worktree is desired.
6. **CRLF:** repo-level LF→CRLF normalization warnings are pre-existing (Windows autocrlf) and identical in both workspaces; the 176-file SHA verification was done on working copies (byte-identical), and committed blobs normalize consistently with repo settings.

## H. Final Verdict

**SPEC021 SUCCESSFULLY ISOLATED — PRIMARY WORKSPACE PRESERVED**