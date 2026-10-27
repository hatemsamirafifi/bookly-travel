# T072.1 hero-preload source edit — implementer result (2026-10-18)

Scope authority: TourGallery.tsx + TourGallery.test.tsx only (my owned files). Nothing else touched; no commits/stage/hooks/push; no build/browser/Lighthouse run.

## Change (exact delta, verified vs before copies)

Production — `frontend/src/components/tour/TourGallery.tsx` line 227 (FIRST tile branch only):
```diff
-                {...(position === 0 ? { loading: 'eager' as const, fetchPriority: 'high' as const } : { loading: 'lazy' as const })}
+                {...(position === 0 ? { preload: true } : { loading: 'lazy' as const })}
```
- Per bundled Next 16.2 docs (`node_modules/next/dist/docs/01-app/03-api-reference/02-components/image.md` L265-291): `preload=true` inserts a `<link rel="preload">` in `<head>` for LCP/hero images; must NOT be combined with `loading` or `fetchPriority`. Non-first tiles keep `loading: 'lazy'`; viewer image keeps `loading: 'lazy'`. `priority` is deprecated in Next 16 (not used).

Test — `frontend/src/components/tour/__tests__/TourGallery.test.tsx` (mock-only, bounded):
```diff
-    const { fill, placeholder, blurDataURL, ...rest } = props;
+    // Test mock consumes next Image-specific props (incl. preload) so they do
+    // not leak onto <img> as unknown DOM attributes; exposed as data-* mirrors.
+    const { fill, placeholder, blurDataURL, preload, ...rest } = props;
...
+        data-preload={preload ? 'true' : undefined}
```
Rationale: jest `next/image` stub spreads `rest` onto `<img>`; consuming `preload` (mirroring existing `fill`/`placeholder`/`blurDataURL` handling) avoids an unknown DOM attribute. No new tests added; all pre-existing behavior assertions preserved unchanged; no existing assertion pinned `loading`/`fetchpriority` on gallery tiles (verified by grep; only unrelated `search/TourCard.test.tsx` pins those for its own component).

## Hypothesis motivation (unverified, no performance claim)
Confirmed diagnosis: LCP element is the FIRST cover tile `<Image class="object-cover">` (375px view ≈ 380x238 above fold), LCP 4187ms (LoadDelay 42% + RenderDelay 45%). Current code only used `loading=eager` + `fetchPriority=high` (no head preload), so the browser discovers the LCP image late during body parsing. Hypothesis: a head `<link>` preload of the same canonical cover lowers discovery/LoadDelay. Root will measure after a fresh build — no claim of improvement here.

## Before/after SHA256
| File | Before | After |
|---|---|---|
| TourGallery.tsx | 153116c1dbb8b28e2d23fd6334892702cc59a5245b58bdaebf7abb65f781d28c | be653d721870b51bfa8c458d302ee5ccf675ae63cf48c807652e9721d7767ebb |
| TourGallery.test.tsx | 3e68199b52479862a2d8f4b68bb2530ebb0066b0dc2bc1540ef6377800c4d8ec | d9c27ccff83d2dbb448b18c12e79b9989adf630e941e17027cb8e8e43f4847ea |

## Gate commands (run in `frontend/`, PowerShell)
1. `npm test -- --runTestsByPath src/components/tour/__tests__/TourGallery.test.tsx src/components/tour/__tests__/TourDetail.test.tsx`
   → exit OK. `Test Suites: 2 passed, 2 total / Tests: 84 passed, 84 total / Time: 170.007 s`
2. `npx eslint src/components/tour/TourGallery.tsx src/components/tour/__tests__/TourGallery.test.tsx`
   → attempt 1 at 180s timeout (cold start, no output); retry with 420s timeout → exit 0, no output (clean).

Not run (out of this step's scope, root coordinates later): full 846-test suite, typecheck/build, browser, Lighthouse, benchmarks.

## Not authorized / not done
No data/commerce/UI behavior change; no docs/status/baseline/runtime updates; no T072-74 marking; no new deps; no other files.

## Artifacts (this dir)
`TourGallery.tsx.before` / `TourGallery.tsx.after` / `TourGallery.tsx.delta.diff`
`TourGallery.test.tsx.before` / `TourGallery.test.tsx.after` / `TourGallery.test.tsx.delta.diff`
`RESULT.md` (this file). Also mirrored to `%TEMP%\opencode\hero-preload\`.
