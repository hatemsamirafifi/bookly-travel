# lighthouse-engine-selector — usage

This selector makes `@lhci/cli` (0.14.0) run the installed root Lighthouse 12.8.2 instead of its nested Lighthouse 12.1.0, via require interception. It is caller-gated to `node-runner.js` only and is fail-closed: if the pinned version or required entries are unavailable, it writes a FAIL-CLOSED diagnostic and exits with code 1 — it never installs anything.

## Usage (run from `frontend/`)

Collect and assert are two separate invocations. Use the relative require path as-is:

```text
node --require ../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs node_modules/@lhci/cli/src/cli.js collect --config=lighthouserc.js
```

Then, separately (after collection finishes):

```text
node --require ../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs node_modules/@lhci/cli/src/cli.js assert --config=lighthouserc.js
```

On startup the selector prints `[lh-engine-select] armed: root engine v12.8.2 ...`, and the selected caller line confirms resolution to `frontend/node_modules/lighthouse/core/index.js`.

## Already-tested evidence (do not re-test unless the selector changes)

- Tested selector SHA256: `b5283ec43f0ddf7aea231f3dce7697c6bde4c23e9774306f1e4632d5bf5666b4` — pins INSTALLED 12.8.2 instead of NESTED 12.1.0; fail-closed with no install.
- [selector result](lighthouse-engine-selector.result.json) records six actual resolution cases (the allowed `node-runner.js` bare-`lighthouse` request is intercepted to the root 12.8.2 core entry; unrelated callers, subpath, options, missing-parent, and builtin cases pass through unchanged) plus a `collect --help` invocation with exit code 0 ([stdout](lighthouse-engine-selector.help-stdout.txt), [stderr](lighthouse-engine-selector.help-stderr.txt)).
- The selector, its focused tests, the result file, and the help captures are retained unchanged here.

## Scope

Running the selector and the `--help` check above was tooling verification only (module resolution + help invocation, exit 0). It is NOT a performance pass, NOT an audit run, and NOT a CI pass. No performance/CI acceptance is claimed by this document.

The usage commands in this document were not executed as part of writing it; they document the verified-correct invocation form for future runs.
