# root-reviewed-findings â€” factual record (no plans)

Post-fix doc â€” all declarations traceable to artifacts. Drafting rejected by root review; quarantined originals: [rejected-draft/index.json](rejected-draft/index.json).

## Clean-gate failure record
- 12 clean Lighthouse 12.8.2 LHRs: 12 JSON + 12 HTML under [reports/](../hero-preload-runtime/performance/reports/), 12 trace.json + 12 devtoolslog.json under [traces/](../hero-preload-runtime/performance/traces/).
- Gate config: [frontend/lighthouserc.js](frontend/lighthouserc.js) â€” SHA-256 `2a0bc3e1bbf27ab2f8b418a2cee9347b6c8e5ec5db31142a570183ee755d7672`; 4 total categories (performance, accessibility, best-practices, seo), minScore 0.9; no `.cjs` config in use.
- Gate outcome: assertion exit 1 on 4 `categories.performance` minScore rows. Collect exit 1. No ES/IT runs (missing from this cycle).
- Scores per URL (category:performance): /en 0.89/0.87/0.77; /en/search?q=rome 0.89/0.90/0.96; /en/tours/hidden-gems-rome-walking-tour 0.80/0.84/0.86; /en/blog/hidden-gems-florence 0.89/0.93/0.81. Minima via `Math.min`: home 0.77; search 0.89; EN tour 0.80; blog 0.81. **No pass claim.**
- Machine datasets: per-URL environment.benchmarkIndex â€” 1811.5/1515.5/1925.5 (home), 1499/1465/1832.5 (search), 1713.5/1779.5/1877.5 (tour; third-run value 1877.5), 1823/1657.5/1356.5 (blog). Zero audit-error messages in all 12; runtimeError absent in all 12; http-status-code audit score 1 in all 12; every category score numeric finite in [0,1].

## LCP picture (tour)
- Tour LCP node `div.relative > div.flex > button.relative > img.object-cover`; network record `isLinkPreload=true`, priority **Low**; observed request window 413â€“602 ms with per-run exacts in [lhr-summary-all12.json](lhr-summary-all12.json).
- The script initiator is NOT proof of a missing SSR image/link: the restored SSR record (see [restored-runtime-accepted.json](../../../runtime/docker-desktop-restore-20261019/restored-runtime-accepted.json)) confirms one SSR cover `<img>` and one SSR preload `<link>` in the raw HTML, no bailout.

## Correction record
- "High â‡’ 428â€“1044ms" load-delay band is withdrawn: search run1 (High priority) has sim LoadDelay 2424.25 ms â€” priority class does not uniquely determine simulated Load Delay.
- The modern engine run eliminated internal audit errors (0 error messages) but did NOT produce a performance pass.
- Prefetch-wave/CPU correlation does NOT prove attributable CPU savings; no attribution claim is made.
- No fabricated terms retained. My earlier "visit $HOME` paths stale" references are replaced with valid artifacts only.
- `deprecatedConstants`/old-range values (428â€“1044ms band / 0-samples ES block) recorded from an obsolete snapshot have been discarded; current numbers come exclusively from plan files above.

## Mechanistic findings (root-endorsed)
- Tour perf failure is reproducibly tied to the LCP request class: query priority & request order (evidence: [lhr-summary-all12.json](lhr-summary-all12.json) + network requests inside raw LHRs). Blog LCP lazy-card: failed `lcp-lazy-loaded=0` only where target render used 'use client' internal state arrays (RelatedPosts render still counts as package state).
- Name-given phrase from root, recorded verbatim: "perf running randomized inserts on omissions" â€” no further unpacking attempted.
- TourContainer: static; tour page's own payload is ~512.15kB; shared shell JS ~514kB. These are load-shape facts, not perf-pass evidence.

## Boundary
- T072â€“T074 remain unchecked (71/74). No commits. No pass claim. No drifted narrative from previous realization is reused.
