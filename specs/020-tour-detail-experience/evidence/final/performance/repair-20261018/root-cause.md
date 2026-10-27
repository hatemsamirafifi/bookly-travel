# Spec020 Lighthouse readiness — corrected root cause + bounded-wait repair

Model: opencode/glm-5.3-flash-free --variant max. Branch codex/020-tour-detail-experience, HEAD b0465035. Ledger unchanged at 71/74.

## Part 1 — Corrections to the 2026-10-18 stage-A artifacts (root-directed)

The earlier stage-A description overstated several causal claims. Restated narrowly:

1. **A/B scope.** The controlled experiment (3 owned busy node children) proves a **load-sensitivity mechanism**: idle-phase values [1582.5, 1719, 1609.5] for the benchmark returned [1053.5, 1337.5, 1362.5, 1278.5, 1059] under owned added CPU load and [1611.5, 1793] at recovery. It is **not** a uniquely proven cause of any past failure, **not** a proven calibration/hardware mismatch, and **absence of all tooling bugs is not established** — only that no invocation divergence was found while reading current source (helper calls the exact official `pageFunctions.computeBenchmarkIndex`, same as Lighthouse's own `core/gather/driver/environment.js`).
2. **Counter readings were instantaneous.** `% Processor Performance` 150-170 at the sampled times (boosting at those moments). The historical slow-window frequency was never measured. No categorical frequency/governor/Docker/GC/memory rule-out is claimed. The container inspection covered cgroup quota absence (NanoCpus=0, CpuQuota=0) for container cc21b897f1f4 only.
3. **Memory column INVALID/UNKNOWN.** The stage-A counter CSV's available-MB column is invalid: the sampler read `CounterSamples[3]` while only 3 counters were requested (positional index bug), writing 0 for every sample. It must not be read as available memory, and no memory-pressure causal inference is drawn. Lesson applied going forward: counter lookup must use path/name with validity checks, never positional guesses. Raw file retained unmodified.
4. **Timestamps corrected.** The prior native failing samples (959, 1160.5, 1263, 1493, 1105.5) occurred **2026-10-17T20:12Z (23:12 Cairo, Oct 17)**; the Docker windows (readiness pass 1623/1533, collect-hook fail 688..1349) are also Oct 17. Historical 688 is below every stage-A measurement, so no "all values fit one band" claim is made, and no symmetric ±% interval is asserted. The diag-ab split fields (gc, noGC, total) are **three independent consecutive evaluations**, not one gc+noGC pair.
5. **Source baseline.** The 419-hash baseline covers frontend paths only; spec evidence files sit outside it. This dispatch contains exactly one intentional frontend-path edit pair: `lighthouse-runtime-preflight.mjs` + its test (Part 3). No other source exceptions.

## Part 2 — Stage-A2 extended settling diagnostic (root-approved, one run)

Command: `node …/lighthouse-repair-20261018/diag-extended.mjs` — about:blank only, official Lighthouse 12.8.2 benchmark, exact production-helper launch settings, unchanged `cpuReady` imported from the real module, no warmup, no artificial load, max 30 samples / 30s.

**Outcome: exit 1 — NO qualifying pair in 30 samples/30s.** First-5 verdict: false ([928, 926, 923.5, 921.5, 929.5]). `earliestPassAttempt: null`. Near-misses: [1485, 1570] and [1561, 1496] — both blocked by a single sub-1500 sample. Repeated initial variability is documented (923.5 → 1570 by attempt 11, back to 1039-1496 after), consistent with prior windows, but **later acceptance was NOT demonstrated**, so the tiny-window hypothesis is not directly confirmed under these conditions. Honest recorded note: the 30th sample (1064.5) completed past the budget boundary and is recorded via the budget-stop branch (not line-logged); it does not qualify.

Raw transcript: `diag-extended.jsonl`.

## Part 3 — Applied repair (bar-preserving bounded wait)

`frontend/scripts/lighthouse-runtime-preflight.mjs` (sha256 `502963ed96d1669d8b…eedf2`):

- `MAX_CPU_ATTEMPTS = 30`, `CPU_BUDGET_MS = 30000`; monotonic `Date.now` deadline enforced **before each new attempt and after each completed attempt**; deadline always fails closed (throw, page closed in `finally`).
- `cpuReady` untouched: same invalid handling, last-2 ≥ 1500, ratio ≤ 1.2. 1500/1.2 NOT lowered; 4x mobile slowdown, 6 URLs, 3 runs, all category assertions untouched.
- Same official benchmark, launch settings, about:blank-only, app-cold, cleanup. No sleeps/warmup/best-pair substitution/filtering/fallback-to-success; no env bypass or configurable threshold. Hook `lighthouse-browser-ready.cjs` untouched (sha256 `a6a51071…25339`). `lighthouserc.js` content untouched by this dispatch (its working-tree diff — the ES/IT tour URL additions — is other agents' pre-existing uncommitted work, preserved as-is).

## Part 4 — Genuine red-before / green-after tests

`frontend/scripts/lighthouse-runtime-preflight.test.mjs` (sha256 `ef032224…64aafb`), deterministic fake browser + fake elapsing clock (`Date.now` stubbed, restored in `finally`, no real waits):

- Red-before (final test file, before source edit): 9 tests → 6 pass / **3 fail exactly as designed**: settles-late browser rejected after 5 attempts; only 5 evaluates instead of 30; no deadline concept. Existing 4 `cpuReady` assertions pass unchanged in both runs.
- Green-after: 9/9 pass (settling-late accepted at unchanged criteria; low/unstable/invalid exhaust the budget at exactly 30 evaluates and reject with cleanup; deadline stops at exactly 12 evaluates with attempt 13 never taken; fast pass stays at 2; app URL can never be visited; page always closed). Transcripts: `tests-red-before.txt` / `tests-green-after.txt`.
- `npm run test:performance-tools`: **17/17**, exit 0. ESLint (eslint 9.39.4, project config) on both files: exit 0.

## Part 4b — Stage-A3 monotonic-clock correction (root-directed)

A2 timed its 30s budget with `Date.now()`, which is non-monotonic wall-clock time: NTP/skew adjustments
can move it mid-loop, so the baseline `startedAt` and per-attempt elapsed checks are not monotonicity-safe.

Fix (source scope unchanged: helper + its test only):
- `scripts/lighthouse-runtime-preflight.mjs` now imports `{performance}` from `node:perf_hooks` (same
  singleton as `globalThis.performance`) and checks the budget with `performance.now()` at exactly the
  three A2 sites (start baseline, pre-attempt `>= 30000` throw, post-attempt `> 30000` throw).
- Everything else is byte-identical to the A2-approved version: `MAX_CPU_ATTEMPTS=30`, `CPU_BUDGET_MS=30000`,
  both pre+post checks, same fail-closed messages, `cpuReady` untouched (last-2 ≥ 1500, ratio ≤ 1.2),
  same benchmark/launch settings, about:blank-only, `finally` page-close cleanup.

Tests (final fixture: helper `5df28a46ae55…36cf`, test `7d9a07cadf73…3ec1`):
- The harness mocks ONLY the monotonic clock (`mock.method(performance, 'now', …)` restored in `finally`;
  `Date.now` untouched) and adds a NEW regression test: both samples qualify — (1700, 1720) — but the 2nd
  evaluate pushes monotonic elapsed 31000ms over the 30000ms budget, so the helper must reject `/exhausted/i`
  with exactly 2 evaluates and 1 page close. This pins the post-evaluation deadline check.
- Red-first against the `Date.now`-based source with the monotonic-mocked harness:
  `tests-clock-red-before.txt` — 10 tests, 8 pass / **2 fail exactly as designed** ("Missing expected
  rejection /exhausted/i" ×2): the wall-clock source ignores the mocked monotonic deadline and even ACCEPTS
  the over-budget qualifying pair.
- Green: `tests-clock-green-after.txt` — **10/10 pass**; `npm run test:performance-tools` — **18/18 pass**
  (10 preflight + 8 audit, `tests-performance-tools.txt`); eslint on both files exit 0. Source under test
  went `Date.now` → `performance.now` between red and green and has not changed since.

## Part 4c — Prepared (not executed) Stage B audit runner + launcher

- Prepared runner: `C:/Users/HaTeM/.codex/tmp/bookly-spec020/lighthouse-repair-20261018/audit-runner.cjs`
  (sha256 `0ba555e30dd8b53797e772780168a3146fcecae481e72b0c6b1dd5a925313def`), from parent
  `specs/020-tour-detail-experience/evidence/final/tooling/opencode-native-engine-resumed-audit.cjs`
  with EXACTLY the three approved mutations: output dir
  `…/final/performance/repair-20261018/audit/`, source-parity baseline input
  `…/repair-20261018/prep/candidate-manifest.json` (original runtime-evidence path and
  `passedReadinessOnly` guard verbatim), and a strict fresh-report validator folded into the existing
  `passed` predicate. Full diff: `prep/temp-runner.diff`. `node --check` only (the runner executes on
  import; it was never required/executed).
- Fresh validation: exact URL set `http://localhost:8080` + the 6 configured paths (config default 3000
  never consulted; `LIGHTHOUSE_BASE_URL` is set by the runner before any config use), 18 unique LHRs,
  6 URLs × 3, fetchTime finite within runner startedAt..validation time, no `runtimeError`,
  `httpStatusAudit === 1` (LH 12.8.2 audit id `http-status-code`), min performance ≥ 0.9 per URL,
  non-empty `assertionResults` (assert.js saves `.lighthouseci/assertion-results.json`), all prior
  conditions retained (collect+assert exit 0, ≥18 unique NEW traces + ≥18 unique NEW devtoolslogs via the
  existing `listAssets`/`ASSET_RE` flow — LHCI's node-runner forwards `settings` (incl. `saveAssets`) via
  `--cli-flags-path`, LH's run.js writes `prefix-0.trace.json`/`prefix-0.devtoolslog.json` relative to the
  frontend cwd that `listAssets` already scans), source/config/identity before=after stable, prior 12
  reports preserved.
- Hidden bounded launcher: `C:/Users/HaTeM/.codex/tmp/bookly-spec020/lighthouse-repair-20261018/audit-launch.ps1`
  (sha256 `18952e571cb654077a80f38fc9983ab94d64452e87290a2384b0bf1bc76b0e93`), NOT started during prep:
  starts the runner hidden with cwd frontend, records PID/start/finish/exit, outer 40-min watchdog that
  tree-kills ONLY its own child.
- Read-only runtime identity re-verified today (container cc21b897f1f4 running, RestartCount 0, same
  image `sha256:a254386f…2573d`, BUILD_ID `sAEfqK7AIAvMIn5nyjbTK`, Node v20.20.2, Next 16.2.3, no CPU
  limits; no app route visits): `prep/runtime-check-20261018.json` (its parity counters are labeled
  HISTORICAL from the Oct-17 gate; the Stage B executor rehashes the 420-container manifest before launch).
- Candidate proof: `prep/candidate-manifest.json` + `prep/candidate-vs-baseline.json` — 419 original keys,
  417 identical, 2 allowed reviewed changes (helper + test), pinned `lighthouserc.js`
  (`2a0bc3e1…d7672`) verified. Summary in `prep/ready-for-review.json`.



## Part 5 — Real production helper, once (truthful)

`node scripts/lighthouse-runtime-preflight.mjs` (single run, no repeats, no warmup, no cache warming):

samples `[1161.5, 1247, 970, 1041.5, 964, 858.5, 989, 1126.5, 1459.5, 1630.5, 1498.5, 1579, 1727]` → **exit 0**, qualified at attempt 13 with pair (1579, 1727), both ≥1500, ratio 1.094, elapsed ~13s. The identical run under the old policy would have been rejected (attempts 3-7 all <1500). Positive live demonstration of the repair benefit; not claimed as proof about the 2026-10-17 failures.

## Explicit remaining limitations

- At some times (e.g., the 30/30s extended diagnostic) the host did not meet the unchanged bar at all; the repair does not remove that reality and the gate must keep failing closed there.
- No claim that settling time caused the Oct-17 Docker collect failure.
- No 18-page audits run yet in this dispatch; current readiness claim is conditional on quiet-window discipline.

## Cleanup / restoration

All owned Chrome instances closed in `finally` for every diagnostic and the helper (truthful exits recorded). No OS/power/global/Docker settings changed; container cc21b897f1f4 untouched and running; no app cache warming; no `.todo` edits; no commits/staging/hooks/provider changes.

## Next single step

Root reviews `preflight.diff` + `preflight-tests.diff` + transcripts. On approval: dispatch the bounded 6-URL × 3-run LHCI collect natively (per plan: HOST native tooling against the verified Docker runtime, `LIGHTHOUSE_BASE_URL` at the app proxy) in a quiet window; the extended gate may still legitimately fail closed under load and must never be bypassed.
