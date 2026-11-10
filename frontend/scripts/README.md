# Scripts

<!-- EGM contract -->

## Overview

Locale catalog parity checking and Lighthouse tooling, including Windows host resource checks and an opt-in pagefile configuration helper.

## Interface

- `Invoke-PerformanceAudit.ps1` runs the acceptance audit in the `bookly-frontend` container. `lighthouse-browser-ready.cjs` is the LHCI hook for blank-page CPU readiness. [Invoke-PerformanceAudit.ps1](./Invoke-PerformanceAudit.ps1#L19) [lighthouse-browser-ready.cjs](./lighthouse-browser-ready.cjs#L3)
- `Set-BooklyPagefile.ps1 -CheckOnly` inspects the existing settings and reports the proposed target and sizes. [Set-BooklyPagefile.ps1](./Set-BooklyPagefile.ps1#L1) [Set-BooklyPagefile.ps1](./Set-BooklyPagefile.ps1#L22)
- Source file [check-i18n-completeness.ts](./check-i18n-completeness.ts).
- Source file [Invoke-PerformanceAudit.ps1](./Invoke-PerformanceAudit.ps1).
- [lighthouse-audit.mjs](./lighthouse-audit.mjs#L16) declares `assessHostResources`, `assessReport`, `busyProcessNames`, `collectAudits`.
- [lighthouse-browser-ready.cjs](./lighthouse-browser-ready.cjs#L3) declares `module.exports`.
- [lighthouse-runtime-preflight.mjs](./lighthouse-runtime-preflight.mjs#L29) declares `cpuReady`, `prepareBrowserCpu`.
- Source file [Set-BooklyPagefile.ps1](./Set-BooklyPagefile.ps1).

## Invariants

- The locale check compares nested leaf keys in `es` and `it` against `en`, treats arrays as leaves, and exits nonzero for missing files or mismatched keys. [check-i18n-completeness.ts](./check-i18n-completeness.ts#L18) [check-i18n-completeness.ts](./check-i18n-completeness.ts#L24) [check-i18n-completeness.ts](./check-i18n-completeness.ts#L48)
- The acceptance gate requires host commit below 85%, at least 4 GiB of commit headroom, and at least 2 GiB of available physical memory. It also requires a production build and stops for recognized concurrent workloads. [Invoke-PerformanceAudit.ps1](./Invoke-PerformanceAudit.ps1#L6) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L16) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L93)
- The acceptance gate runs two serial rounds across four routes. Each report must have all four scores and performance at least 0.90. Low scores are retained, and execution or runtime errors abort the remaining runs. [lighthouse-audit.mjs](./lighthouse-audit.mjs#L9) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L30) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L55)
- The LHCI CPU check visits only `about:blank` and requires the last two positive samples to be at least 1500 with a ratio no greater than 1.2, within a 30-sample and 30-second budget. [lighthouse-runtime-preflight.mjs](./lighthouse-runtime-preflight.mjs#L24)

## Side Effects

- The audit reads host and container resource evidence, invokes Lighthouse against the configured HTTP(S) origin, and retains JSON reports and a summary under `.phase0-evidence/lighthouse/`. [Invoke-PerformanceAudit.ps1](./Invoke-PerformanceAudit.ps1#L13) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L75) [lighthouse-audit.mjs](./lighthouse-audit.mjs#L100)
- On an eligible Windows host, an elevated run can create the missing `F:\pagefile.sys` configuration with an initial size of 8192 MB and maximum of 16384 MB, then verify other entries. [Set-BooklyPagefile.ps1](./Set-BooklyPagefile.ps1#L5) [Set-BooklyPagefile.ps1](./Set-BooklyPagefile.ps1#L26)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
