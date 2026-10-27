const fs = require('fs');
const path = require('path');
const EV = 'F:/Travel Website/bookly travel/specs/020-tour-detail-experience/evidence/final/runtime/opencode-engine-resume-20261017';
const f = path.join(EV, 'opencode-runtime-ready.json');
const j = JSON.parse(fs.readFileSync(f, 'utf8'));
j.purpose = 'Restored EXISTING local Docker/Bookly test-env provenance after Docker Desktop engine resume. Readiness-only boolean; NOT CI/release acceptance. Root-reviewed accepted files (root-runtime-accepted.json, root-reviewed-container-source.json, root-gates-after.json) preserved untouched.';
j.engineRestore = {
  previousState: 'Engine stopped: npipe dockerDesktopLinuxEngine absent, no Docker processes, no :8080 listener (see opencode-native-resumed-20261017/native-runtime-identity.txt)',
  authorization: 'Root order 2026-10-17: starting installed Docker Desktop and existing stopped Bookly services authorized as reversible local QA setup; no rebuild/recreate/migrate/seed/settings changes',
  action: 'Start-Process -FilePath C:/Program Files/Docker/Docker/Docker Desktop.exe -WindowStyle Hidden',
  desktopPid: 20996,
  desktopStartLocal: '2026-10-17T23:00:22.0542718+03:00',
  dockerServerVersion: '29.8.2',
  readinessProbes: ['exit=1 x4 (engine warming)', 'exit=0 ok']
};
j.composeRestoration = {
  method: 'No compose start/up/build/pull run by executor; all existing bookly-* services auto-started with engine via restart: unless-stopped policies',
  servicesUp: ['postgres', 'redis', 'meilisearch', 'laravel', 'scheduler', 'queue', 'nextjs', 'nginx'],
  oneShot: { 'php-dependencies': 'Exited(0) — completed init container, not restarted' },
  untouched: ['open-seo-preview (separate compose project)', 'bookly-test-postgres + bookly-test-meilisearch (test containers, left as found: Exited)'],
  frontendEntrypoint: 'docker-entrypoint.sh; sh -c "rm -rf /app/.next/* && npm run build && npm start"',
  generatedOutputRebuilt: 'Restart legitimately regenerated Next production output (new BUILD_ID) inside the SAME baked image, from the SAME accepted source/dependencies; host inputs verified 419/419, container inputs 420/420',
  frontendImage: 'booklytravel-nextjs:latest sha256:a254386f360c143a6174b651763a97b4f3b49dfd450ff2e7fa41b4149752573d (created 5 hours ago — accepted build)',
  frontendContainerStartedAt: '2026-10-17T20:02:45.3412223Z',
  ports: '0.0.0.0:3001->3000/tcp (direct), 0.0.0.0:8080->80/tcp (nginx proxy)'
};
j.readiness = {
  direct3001: '3 probes GET http://localhost:3001/en -> HTTP 200 (lengths 169003/168960/168960)',
  proxy8080: '3 probes GET http://localhost:8080/en -> HTTP 200 (lengths 168960 x3)',
  artifact: 'readiness-probes.txt'
};
j.buildIds = { accepted: 'PWRC5Senrh_71s0M1xvjU', rebuilt: 'sAEfqK7AIAvMIn5nyjbTK' };
j.artifacts = ['compose-services.txt', 'compose-ps-a.txt', 'frontend-startup-log.txt', 'container-inspect-frontend.json', 'container-frontend-identity.txt', 'frontend-image-identity.txt', 'readiness-probes.txt', 'container-runtime-identity-new.json', 'source-parity-420-manifest.json', 'source-parity-419-manifest.json'];
fs.writeFileSync(f, JSON.stringify(j, null, 2));
console.log('merged; keys=' + Object.keys(j).join(','));
