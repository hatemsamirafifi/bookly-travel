# Root recoverable-state accessibility correction — 2026-10-16

Actual native production browser with controlled server503, build3izO741kD6F5DDJ3N2S0V, EN390×844: AXE returned page-has-heading-one (moderate), h1Count0, robots noindex. Exact root-error-a11y-initial.json. First diagnostic launch failed because AXE requires an explicitly owned BrowserContext; that failure is retained in root-error-a11y-launch-failure.json and is not an app failure. The subsequent explicit-context diagnostic reproduced the real missing-heading issue.

Root applied a tiny inline correction under opencode-delegate's explicit small-task exception: route error.tsx now has its localized errorTitle as h1, followed by ErrorState with only localized description and unchanged unstable_retry. No error payload reaches either string. Installed Next16.2.3 error.md was read; unstable_retry remains the actual supported callback.

Root added a data-driven browser regression for EN/ES/IT503 at390px in existing a11y/tour-detail-a11y.spec.ts: real SSR error boundary, exact localized h1, enabled retry, no private upstream text and full AXE. OpenCode US3 owns other browser file/helper; US4 owns nav/controller/composition, neither owns this error file or a11y file. Both major implementation lanes remain delegated. This small fix does not establish a new-build/browser green until root rebuilds and executes it.
