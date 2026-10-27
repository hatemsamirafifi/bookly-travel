# Blank-CPU readiness series — 2026-10-21 (byte-exact diagnostic copy)

Byte-exact copy of the root-reviewed blank-CPU readiness diagnostic run on
2026-10-21 16:22:16.572Z–16:24:47.894Z (151322 ms per `summary.json`). The raw
originals in the root temporary diagnostic directory remain retained and
unaltered. Executor: OpenCode `opencode/glm-5.3-flash-free` under root review.
SHA-256 provenance for every copied file: `blank-cpu-series-manifest.json`.

## Files

- `blank-cpu-series.mjs` — diagnostic runner source.
- `run.jsonl` — 96 verbatim helper sample lines (one JSON line per sample:
  `{attempt, benchmarkIndex, url: "about:blank", appVisited: false}`).
- `summary.json` — the diagnostic's own raw summary output.
- `run-err.txt` — empty (no runner stderr).
- `root-reviewed-interpretation.json` — post-hoc root review record derived
  only from the raw outputs; every raw value and every byte of the four raw
  artifacts is preserved unchanged.

## What the diagnostic shows

The same Chrome browser ran six `prepareBrowserCpu` readiness rounds with NO
application navigation — every sampled page URL stayed `about:blank`. Five
rounds finished (26, 21, 3, 3 and 14 samples); the sixth (LAST) round
exhausted the unchanged 30-sample / 30000 ms readiness budget at 29 samples
with the exact recorded error "CPU readiness budget (30 samples / 30000ms)
exhausted; performance audit must not start". Completion of all six rounds is
diagnostic completion only and is NOT a performance pass
(`performancePass: false`); no page score exists from this series.

Demonstrated conclusion (deliberately narrow): current host/browser execution
CAN fail the unchanged CPU readiness hook without any application navigation.
This does NOT prove a unique hardware, thermal, background-load or other
cause, does not establish monotonic decay, and no browser page-load leak was
observed within this blank-only test. Comparison to the exact two-argument
LHCI browser is limited: both named LHCI args (`--no-sandbox`,
`--disable-dev-shm-usage`) were present and the diagnostic added a third,
`--disable-background-networking`, identical to the production helper's
`main()` launch in `frontend/scripts/lighthouse-runtime-preflight.mjs` — a
recorded superset, not the exact two-arg set.

## appVisited inversion — corrected interpretation, raw values preserved

The diagnostic's own summary-level and round-level `appVisited` fields were
inverted (`appVisited: !anyUnexpectedUrl` etc.), so `summary.json` records
`appVisited: true` while every helper-emitted sample line correctly says
`appVisited: false` with url `about:blank`. The helper sample semantics were
correct as emitted; only the diagnostic-internal summary/round re-derivation
was inverted. `root-reviewed-interpretation.json` records the corrected
interpretation (`appVisited must be false`) while keeping `summary.json`,
`run.jsonl` and `blank-cpu-series.mjs` byte-untouched; all six sample lists
and the raw SHA-256 hashes are preserved.

## Provenance and limits

The bounded limits and point-in-time engine conditions around this series are
recorded in the spec verification record:
[verification.md](../../../../../verification.md). This series itself proves
no app-page readiness, no build/runtime acceptance and no performance
requirement.
