# lighthouse-engine-selector.cjs — SPEC020 retained engine-selection tooling

## What this is

`lighthouse-engine-selector.cjs` is an engine-selection shim for LHCI's node-runner.
This shim is required because the default LHCI engine selection resolves the nested Lighthouse copy at
`frontend/node_modules/@lhci/cli/node_modules/lighthouse` (version **12.1.0**); the ALREADY-INSTALLED root engine at
`frontend/node_modules/lighthouse` is version **12.8.2** and handles this trace format.
The shim is otherwise a measurement-engine selection tool, not an audit modification.

## Provenance and scope

- Copied from the TEMP diagnostic preload
  `C:/Users/HaTeM/.codex/tmp/bookly-spec020/lighthouse-repair-20261018/diag/preload.cjs`
  (SHA-256 `3a7f2f5950206c186b935ba5dc867aca75c09525279cd7c941fa59f0eb605ed3`), which is preserved byte-for-byte
  untouched at that location.
- The retained copy differs from that original ONLY in:
  1. the two hardcoded absolute directory constants, which are now derived portably from
     `path.resolve(__dirname, '../../../../..')` (this file lives at
     `specs/020-tour-detail-experience/evidence/final/tooling/`, five levels below the repository root), and
  2. stale temporary-only explanatory comments.
- The shim requires that frontend/node_modules/lighthouse/package.json reports the pinned version `12.8.2`
  and that the package, its `core/entry`, its `cli/entry`, and the LHCI node-runner file all exist; any violated
  precondition fails closed (aborts) instead of silently misdirecting loads.

## Narrow interception semantics (unchanged from the original)

Only this exact match is intercepted: bare request `lighthouse` AND parent filename exactly
`frontend/node_modules/@lhci/cli/src/collect/node-runner.js` AND no options object. Resolution results for every
other request, caller, subpath, options call, or missing parent pass through the real installed
`Module._resolveFilename` unchanged, including errors.

## Portability verification

A focused resolution test exercises six independent cases in a native temporary test process, comparing the real
installed `Module._resolveFilename` results before and after the selector is armed:

1. exact allowed caller + bare `lighthouse` — intercepted, resolves nested 12.1.0 → root 12.8.2 `core/index.js`
2. unrelated caller + bare `lighthouse` — pass-through, unchanged (stays nested 12.1.0)
3. exact allowed caller + `lighthouse` subpath — pass-through, unchanged
4. exact allowed caller + options object — pass-through, unchanged
5. bare `lighthouse` with no parent — pass-through, unchanged (identical error)
6. unrelated builtin (`node:path`) — pass-through, unchanged

Test artifacts: `lighthouse-engine-selector.focused-tests.cjs` (driver), `lighthouse-engine-selector.result.json`
(all before/after values, hashes, statuses, selector stderr evidence), and exact stdout/stderr captures of the
help-only invocation (`lighthouse-engine-selector.help-stdout.txt`, `lighthouse-engine-selector.help-stderr.txt`).

## Help-only invocation (no collection was performed)

From `frontend/`:

```
node --require ../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs node_modules/@lhci/cli/src/cli.js collect --help
```

This ONLY prints the CLI help (exit 0). No `collect` run, no browser, no network, no audit, no trace was started.

## Explicit non-claims

- This tooling and its verification do NOT claim a CI gate success.
- This tooling and its verification do NOT claim a performance improvement or pass.
- The shim selects the measurement engine for focused reproduction-style diagnostic runs; it changes no audit
  logic, no site code, no dependency manifests, and nothing under `node_modules`.

## Usage rule

Stop on failing checks rather than changing interception semantics. If any focused-case check fails, the shim must
not be altered to make the test pass.
