# Master-plan Phase 1: design system and shared shells

Scope: Tasks 1.1–1.3 of `docs/bookly-ui-redesign-spec-kit-master-plan.md`, mapped to Spec Kit T005–T007 and T049. This is the master plan's design-system phase, not the generated tasks file's setup phase. Verification date: 2026-10-01 (Africa/Cairo).

## Delivered

- Semantic colors, surfaces, trust/error/warning, focus, overlay, responsive gutters/content width/type scale, spacing, radii, elevation, motion and layer tokens. CSS owns values; TypeScript exports reference those values. Existing Bookly anchors and Plus Jakarta Sans through `next/font` remain intact.
- Container, Button, IconButton, Chip, RatingBadge, SectionHeading, Breadcrumbs, Accordion, Tabs, Dialog, Drawer, CarouselRail, EmptyState, ErrorState and LoadingSkeleton. Existing form primitives use semantic focus tokens; Select has listbox naming, arrow navigation, Escape and focus return.
- Public header with expandable localized GET search, primary/discovery rows, locale and named account/wishlist actions. Shared public/auth/traveler/footer containers and partner layout use the foundations. Both mobile navigation surfaces use Drawer. Partner sidebar preserves locale URLs with native navigation links; both account menus have keyboard dismissal/focus behavior.
- Dialog tests cover trapping/restoration, nested layers, out-of-order closing, hidden/disabled targets and initially open server rendering. Body inertness and scroll locking remain active until the final dialog closes.

## Verification

The requirements checklist passed 16/16; the prerequisite script resolved the existing `specs/018-bookly-ui-redesign` feature directory. Tests were first observed failing on missing token/loading behavior, missing primitives, missing header search/account behavior, untranslated retry copy, partner locale/menu behavior, nested-dialog locks/focus and server-rendered portals.

| Gate | Latest result |
|---|---|
| Full Jest (`npm test -- --runInBand`) | Passed: 48 suites, 255 tests |
| Typecheck (`npm run typecheck`) | Passed |
| ESLint (`npm run lint`) | Passed |
| Production build (`npm run build`, then `npm start`) | Passed after the final SSR guard; production server ready |
| Existing i18n completeness script | ES/IT each match all 1,042 EN leaf keys |
| Shell/auth/partner Playwright and Axe regression | Final focused run: 29 passed (3.8 min); broader run: 121 passed, 14 duplicate-matrix skips, two outdated sign-out selectors corrected and rerun successfully |
| EN/ES/IT screenshots at 390/768/1024/1440px | 48 captures: public/auth/traveler/partner; dashboard placeholders absent |

Commands run inside the existing `bookly-frontend` container. `DOCKER_ENV=true` selects the same-origin nginx URL for browser/API requests. The container's configured startup runs the production build followed by `npm start`; its restart is a local verification action.

```text
docker exec bookly-frontend npm test -- --runInBand
docker exec bookly-frontend npm run typecheck
docker exec bookly-frontend npm run lint
docker exec -e DOCKER_ENV=true bookly-frontend npx playwright test shared-shell.spec.ts a11y/shared-shell-a11y.spec.ts auth-navigation.spec.ts auth-guards.spec.ts partner/navigation.spec.ts a11y/partner-a11y.spec.ts a11y/auth-nav-a11y.spec.ts --workers=1 --reporter=line
```

The existing `scripts/check-i18n-completeness.ts` was executed via the installed TypeScript compiler's `transpileModule` with CommonJS and ES2020, preserving Set iteration; no dependency was installed. An initial default-target invocation reported zero keys and was rejected as invalid evidence; the corrected invocation verified 1,042 keys per locale.

Browser checks exercise real Enter/Tab/Shift+Tab/arrows/Escape, scroll containment, reduced motion, localized search submission, role-specific destinations and existing guards. Axe checks include the public/auth shells, account popup and open mobile partner drawer. Screenshots live under the ignored `frontend/.phase1-evidence/screenshots/`; these are fixture-driven layout evidence, not production content acceptance. Populated dashboard captures wait for loading placeholders to disappear. The accepted capture set contains 48 images named by shell, locale and width; obsolete initial loading-state captures were removed. Representative mobile traveler/partner and desktop Italian partner captures were visually inspected.

## Decisions and limits

- Continue in the existing `018-bookly-ui-redesign` checkout and preserve its extensive pre-existing work. Do not create another branch or stage/commit broad mixed directories. Cost: phase changes require review alongside an existing dirty diff; all work remains uncommitted.
- Keep the canonical `button.tsx` filename instead of adding `Button.tsx` beside it on Windows. Cost: later consumers must use the existing import casing.
- Let the public header scroll with the page so its discovery/search rows cannot cover the existing sticky tour-section navigation. Cost: global account/search controls require scrolling back to the header.
- Retain token keys as compatibility aliases while referencing CSS values. Cost: callers needing literal numeric z-index values must consume CSS variables instead; repository searches found no such production consumers.
- TODO MCP tools are unavailable; `.todo` and project task markers were not changed through filesystem tools. The foundation task entries were already checked before this work; this report supplies current implementation evidence rather than relying on those markers.
- The optional pre/post Git hooks were inspected and left uninvoked (`speckit.git.commit`, available as `$speckit-git-commit`). No mandatory implement hooks exist.
- One fresh-context reviewer completed review after the preset reviewer model failed to initialize. Its five substantive findings received one tested fix pass. No minor review findings were deferred.

This phase does not close the feature's remaining T046/T047 release-wide browser/performance/manual-acceptance gates. No new Lighthouse or backend-suite claim is made here. Existing non-failing Jest warnings from RegisterForm/partner TourCard mocks are recorded separately from test failures.

### Final browser verification

The broader run's only two failures were desktop/mobile sign-out tests that searched for the old avatar DOM structure. Failure snapshots showed the named account control was present. Both tests now locate the account button and sign-out menuitem by accessible role/name; both passed against the final production build. The final focused run also rechecks the SSR-safe Dialog, all locale/viewport shell captures, 12 guest Axe cases and the open partner drawer Axe case.

```text
docker exec -e DOCKER_ENV=true bookly-frontend npx playwright test shared-shell.spec.ts a11y/shared-shell-a11y.spec.ts partner/navigation.spec.ts a11y/partner-a11y.spec.ts --project=chromium --project=a11y --project=chromium-partner --project=mobile-partner --project=a11y-partner --no-deps --workers=1 --reporter=line --grep "guest shell|account menu supports|shared auth shell|should sign out from dropdown menu|mobile drawer should have proper ARIA"
29 passed (3.8m)
```

Local build output is retained in `frontend/.phase1-evidence/final-production-build.log`. Unit, lint, typecheck and browser results above apply to the final implementation; later documentation-only edits do not change the runtime.