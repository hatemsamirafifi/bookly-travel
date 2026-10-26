# RESULT — mechanical docs correction (repair-20261018 portable evidence)

Executed: 2026-10-20. All values below are actually measured during verification (certutil SHA256 byte hashing, `Test-Path` existence checks, JSON parse checks). No production/source/test/config files changed; no reports, URLs, or scores were edited or invented; no tooling or gate commands were run.

## Exact changes

1. **post-preload-diagnosis/root-reviewed-findings.md — replaced.**
   - Prior rejected bytes verified byte-equal between the file and `rejected-correction/root-reviewed-findings.md` BEFORE overwrite (both SHA256 `e7ca791b01c49ca30283a96849837ef8efaa2b0a44d8b3ea10531f4e3bfb9fac`, 3673 bytes each). Prior original retained unchanged under `rejected-correction/`.
   - Replaced by exact byte copy of `C:\Users\HaTeM\.codex\tmp\bookly-spec020\lighthouse-repair-20261018\reviewed-historical-findings.PROPOSAL.md` (SHA256 `5bc36f59f9e2e0bb4664cb7965892ecc987f447a7b3079ccffda9daf9b9a508f`, 1681 bytes).
   - Post-copy SHA256 of the written file: `5bc36f59f9e2e0bb4664cb7965892ecc987f447a7b3079ccffda9daf9b9a508f` — byte-hash equal to the source; no new prose added.

2. **post-preload-diagnosis/run-bookkeeping.json — verified unchanged.**
   - SHA256 `6655c25ec1aa5970fda13d44f3365c576560a3f61ceee851852db6df24012a44` (no edit needed).
   - Path already correct: the `cpuReadinessBlockBeforeEs.source` value is `evidence/final/performance/repair-20261018/hero-preload-runtime/performance/native-collect.txt` (line 39); zero occurrences of `evidence/final/hero-preload-runtime` remain in the file.
   - Numbers preserved (re-verified by parse): `collectExit` 1, `assertExit` 1, `lhrJson` 12, `lhrHtml` 12, `traceJson` 12, `devtoolslogs` 12, `urlsRan` 4, `urlsInConfig` 6, `missingUrlRuns.count` 2 (ES/IT tour URLs), CPU samples 29 with min 835.5 / max 1347.

3. **post-preload-diagnosis/lhr-summary-all12.json — path-only correction.**
   - Exactly 1 occurrence of `evidence/final/hero-preload-runtime/performance/reports` in the `derivedFrom` note replaced by `evidence/final/performance/repair-20261018/hero-preload-runtime/performance/reports`.
   - SHA256 before: `6b6a7ee869917cc9aeede8c3d02612408b12ecf15a56b2ed08a88570d2e6b0e2`; after: `d00b54008dc2538feec739a92086c263589f73af8ed48e5a884b7a424fe073b4`.
   - Verified valid UTF-8 JSON after edit; `runs` array still 12 entries; report numbers untouched; pre-existing mojibake bytes in the `derivedFrom` prose left exactly as they were. Prior original remains under `rejected-correction/` untouched.

4. **runtime/docker-desktop-restore-20261019/restored-runtime-accepted.json — preserved then replaced.**
   - Original rejected bytes copied byte-exact into NEW `runtime/docker-desktop-restore-20261019/rejected-correction/restored-runtime-accepted.json`: SHA256 `294c9e2293be4a373c95a98b3216613dfbf7b75ff291750ca007136a3fce2c24`, identical for the original before replacement and the retained copy after.
   - Original file replaced with a summary JSON containing ONLY: `status` = `superseded-unaccepted-generated-summary`, `rawEvidencePreserved` = true, `currentSourceAcceptance` = false, `reason` (fixed text), and `rawEvidence` listing six filenames. No validated fields were inferred or added.
   - SHA256 of the replacement: `cc6aa396d6c104a5a479007ab1365cdf4d7948176a052dae88d487886587d99f`.
   - All six `rawEvidence` names verified to exist in the same directory: `03-docker-desktop-restart.md`, `05b-identity-verification.md`, `06a-host-file-hashes-419.json`, `06b-container-hashes-raw.json`, `08-ssr-probe.json`, `08-ssr-en-tour-raw.html`. Existing raw files untouched.

5. **tooling/lighthouse-engine-selector.README.md — NEW.**
   - Short usage doc (collect and assert as separate commands from `frontend/` with a relative require path; no absolute author-specific paths in the usage instructions).
   - States the already-tested selector SHA256 `b5283ec43f0ddf7aea231f3dce7697c6bde4c23e9774306f1e4632d5bf5666b4` pins INSTALLED 12.8.2 instead of NESTED 12.1.0, fail-closed with no install; links [lighthouse-engine-selector.result.json](../../../tooling/lighthouse-engine-selector.result.json) with its six actual resolution cases and the help invocation exit 0.
   - Declares tooling verification only — NOT a performance pass and NOT a CI pass. The documented usage commands were not executed while writing it.
   - Tooling files preserved unchanged (current SHA256): `lighthouse-engine-selector.cjs` `b5283ec43f0ddf7aea231f3dce7697c6bde4c23e9774306f1e4632d5bf5666b4` (matches tested SHA), `lighthouse-engine-selector.focused-tests.cjs` `e9d81ef4355b3bfc564f096fd617f84625b6e8cc1938b7f6cfe40d1db01552fc`, `lighthouse-engine-selector.result.json` `b684f74b4456a6e3b7b14c4d4f8d07f06c09d5073ed4eba963f6bb46e5892df1`, `lighthouse-engine-selector.help-stdout.txt` `134718064ac867b3f4c7fe0e019a397005121883d8f66ede3c021de56f1debf8` (equals `sha256Stdout` recorded in the result file), `lighthouse-engine-selector.help-stderr.txt` `8e5e6229a2bf5b90ddbc0c44f83578feb318ada55bb1431eb6ed4ef2523a84ac` (equals `sha256Stderr`).

6. **portable-tool-evidence/RESULT.md + portable-tool-evidence/mechanical-result.json — NEW** (this evidence pair; values mirror the measured facts above).

## Relative link targets in the replaced root-reviewed-findings.md (all verified to exist)

The file contains 5 markdown link instances / 5 unique relative targets (resolved from `post-preload-diagnosis/`) — the actual count found; all exist:

| # | Link target | Resolved path | Exists |
|---|---|---|---|
| 1 | `../hero-preload-runtime/performance/reports/` | `evidence/final/performance/repair-20261018/hero-preload-runtime/performance/reports` | yes (dir) |
| 2 | `../hero-preload-runtime/performance/native-collect.txt` | `evidence/final/performance/repair-20261018/hero-preload-runtime/performance/native-collect.txt` | yes |
| 3 | `lhr-summary-all12.json` | `evidence/final/performance/repair-20261018/post-preload-diagnosis/lhr-summary-all12.json` | yes |
| 4 | `../../../runtime/docker-desktop-restore-20261019/08-ssr-probe.json` | `evidence/final/runtime/docker-desktop-restore-20261019/08-ssr-probe.json` | yes |
| 5 | `rejected-correction/` | `evidence/final/performance/repair-20261018/post-preload-diagnosis/rejected-correction` | yes (dir) |

## Scope notes

- No source, tests, configuration, locks, or build artifacts were modified; no network, audit, install, git mutation, or commit was performed.
- No release claim, score claim, or root approval is asserted anywhere in these files.
- This documents mechanical corrections and their verification only.
