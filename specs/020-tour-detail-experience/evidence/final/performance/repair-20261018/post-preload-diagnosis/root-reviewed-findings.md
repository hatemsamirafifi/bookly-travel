# Historical failed Lighthouse run — reviewed facts

The retained attempt produced 12 Lighthouse 12.8.2 reports for four URLs, with zero internal audit errors. Collection and assertion both exited 1. The six-URL gate remains failed; ES and IT have no reports in this attempt. T072–T074 remain pending. These reports establish neither a performance improvement nor CI or release readiness.

Observed performance scores:

- Home: 89 / 87 / 77; minimum 77.
- Search: 89 / 90 / 96; minimum 89.
- EN tour: 80 / 84 / 86; minimum 80.
- Blog: 89 / 93 / 81; minimum 81.

Before the ES URL, the unchanged CPU readiness hook exhausted its budget: 29 recorded samples, minimum 835.5 and maximum 1347, all with `appVisited: false`. This identifies where collection stopped. It does not establish the cause of slow application performance.

The tour cover request was observed with Low priority and marked as a link preload. The third EN tour report records `benchmarkIndex: 1877.5`. Request initiator and priority alone do not establish causal performance savings. A later retained SSR GET (2026-10-19 20:24:57 UTC, HTTP 200) includes an actual cover image and a responsive preload link. That restored runtime precedes the new source experiment and does not prove performance.

Sources: [raw reports](../hero-preload-runtime/performance/reports/), [collection log](../hero-preload-runtime/performance/native-collect.txt), [derived report summary](lhr-summary-all12.json), [historical SSR probe](../../../runtime/docker-desktop-restore-20261019/08-ssr-probe.json). Earlier generated corrections are retained under [rejected-correction](rejected-correction/); they are not accepted findings.
