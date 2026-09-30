# Bookly UI redesign: Phase 1

This branch contains only the shared design foundations from master-plan Tasks 1.1-1.3. It starts from `origin/main` at `f3e8e25`; the separate all-work branch contains this phase plus the other redesign and backend work. The two PRs intentionally overlap and should be coordinated before merging.

## Scope

- Semantic colors, layout/type/spacing/radius/elevation/motion/layer tokens and Plus Jakarta Sans through `next/font`.
- Container, Button, IconButton, Chip, RatingBadge, SectionHeading, Breadcrumbs, Accordion, Tabs, Dialog, Drawer, CarouselRail, EmptyState, ErrorState and LoadingSkeleton. Existing form primitives share semantic focus and keyboard behavior.
- Public discovery/search/account navigation, localized footer and public/auth/traveler/partner shells. Accessible mobile drawers, keyboard account menus and locale-preserving partner navigation.
- Focus/scroll/inert management for nested dialogs, hidden/disabled focus filtering, server-render-safe portals and reduced motion.
- Scoped EN/ES/IT retry/navigation/footer messages, unit tests and browser/Axe regressions.

Other page redesigns, backend APIs/jobs/migrations, release tooling and Spec Kit task-state changes are excluded. Shared auth route layouts are included; the separate auth page/form redesign is excluded.

## Verification

The complete all-work snapshot previously passed 255 Jest tests, lint, typecheck, production build and 29 final browser/Axe checks, with 48 locale/width shell captures. Those results apply to that snapshot and are not presented as standalone validation of this extracted branch.

Standalone checks on this branch:

| Check | Result |
|---|---|
| Shared UI and layout unit tests | 6 suites / 25 tests passed |
| Full Jest suite on the isolated branch | 38 suites / 206 tests passed |
| Full TypeScript check | Passed |
| Full ESLint check | Passed |
| Production build | Passed: Next.js 16.2.3 / Turbopack, TypeScript and 89 static pages |

## Decisions

- The public header scrolls with the page so its discovery/search rows cannot cover existing sticky tour navigation. Global controls require scrolling back to the top.
- `button.tsx` remains the canonical module because Windows cannot safely host a second `Button.tsx` differing only by case.
- CSS owns semantic values; TypeScript keeps compatibility aliases referencing those values.
- Phase 1 does not close release-wide performance, deployment/provider or manual acceptance gates.


All-work PR: https://github.com/hatemsamirafifi/bookly-travel/pull/27. LiveReview is skipped with explicit user authorization for publication; no global hook is disabled.
