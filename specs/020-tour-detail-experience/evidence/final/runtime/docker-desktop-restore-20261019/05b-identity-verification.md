### 05b corrected identity/readiness verification - ONE sweep, no loop
ts_utc=2026-10-19T20:13:34.6635977Z
context: 05 log shows container auto-start 20:02:27Z, origin 3001 HTTP 307 from iter2 (20:04:54Z). 05 readiness criterion was broken by executor probe typo (sh -c "tu -f" instead of cat). This file records the CORRECTED single-sweep evidence.

#### ps-all
ts_utc =2026-10-19T20:13:35.3048978Z
cmd    = docker.exe ps -a --format {{.Names}} | {{.Image}} | {{.Status}}
status = {"error":"","args":"ps -a --format \"{{.Names}} | {{.Image}} | {{.Status}}\"","elapsed_ms":384,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
bookly-frontend | booklytravel-nextjs:spec020-preload-be653d72 | Up 11 minutes
bookly-scheduler | booklytravel-scheduler | Up 11 minutes
bookly-queue | booklytravel-queue | Up 11 minutes
bookly-backend | booklytravel-laravel | Up 11 minutes
bookly-php-dependencies | booklytravel-php-dependencies | Exited (0) 2 days ago
open-seo-preview | open-seo-dev:local | Up 6 minutes
bookly-spec019-browser-laravel-1 | 6dbf09e2adac | Exited (137) 7 days ago
bookly-spec019-browser-nextjs-1 | node:22-bookworm-slim | Exited (137) 7 days ago
bookly-spec019-browser-nginx-1 | nginx:alpine | Exited (0) 7 days ago
bookly-spec019-browser-postgres-1 | postgres:15-alpine | Exited (137) 7 days ago
bookly-spec019-browser-redis-1 | redis:7-alpine | Exited (0) 7 days ago
bookly-spec019-browser-meilisearch-1 | getmeili/meilisearch:v1.10 | Exited (143) 7 days ago
bookly-test-meilisearch | getmeili/meilisearch:v1.10 | Exited (255) 2 days ago
bookly-test-postgres | postgres:15-alpine | Exited (255) 2 days ago
bookly-nginx | nginx:alpine | Up 11 minutes
bookly-postgres | postgres:15-alpine | Up 11 minutes (healthy)
mse-gemini-server | mse_ai_g-main-gemini-server | Exited (0) 3 weeks ago
mse_ai_api | mse_ai_api-main-mse-api | Exited (0) 3 weeks ago
bookly-redis | redis:7-alpine | Up 11 minutes (healthy)
msemax-api | msemax-msemax-api | Up 11 minutes (healthy)
gemini-proxy | gemini-proxy-gemini-proxy | Up 11 minutes (healthy)
seootter | seo_otter-seootter | Exited (0) 2 months ago
bookly-meilisearch | getmeili/meilisearch:v1.10 | Up 11 minutes
stderr >>>
<no-output-file>

#### exec-buildid
ts_utc =2026-10-19T20:13:35.6062344Z
cmd    = docker.exe exec bookly-frontend sh -c cat /app/.next/BUILD_ID
status = {"error":"","args":"exec bookly-frontend sh -c \"cat /app/.next/BUILD_ID\"","elapsed_ms":254,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
44IUYi13oU3gQJ-74yYhO
stderr >>>
<no-output-file>

#### exec-node-v
ts_utc =2026-10-19T20:13:35.8959464Z
cmd    = docker.exe exec bookly-frontend sh -c node -v
status = {"error":"","args":"exec bookly-frontend sh -c \"node -v\"","elapsed_ms":259,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
v20.20.2
stderr >>>
<no-output-file>

#### exec-next-version
ts_utc =2026-10-19T20:13:36.2052978Z
cmd    = docker.exe exec bookly-frontend sh -c cat /app/node_modules/next/package.json | grep "\\\"version\\\"" | head -1
status = {"error":"","args":"exec bookly-frontend sh -c \"cat /app/node_modules/next/package.json | grep \"\\\\\\\"version\\\\\\\"\" | head -1\"","elapsed_ms":261,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
  "version": "16.2.3",
stderr >>>
<no-output-file>

#### http-3001-root
ts_utc =2026-10-19T20:13:36.2895988Z
cmd    = curl.exe -s -o NUL -D C:\Users\HaTeM\AppData\Local\Temp\opencode\docker-restore-20261019\v-h3001.txt -w code=%{http_code} --max-time 10 http://127.0.0.1:3001/
status = {"error":"","args":"-s -o NUL -D C:\Users\HaTeM\AppData\Local\Temp\opencode\docker-restore-20261019\\v-h3001.txt -w code=%{http_code} --max-time 10 http://127.0.0.1:3001/","elapsed_ms":49,"exe":"C:\\WINDOWS\\system32\\curl.exe","exit":23,"watchdog":"completed"}
stdout >>>
code=000
stderr >>>
<no-output-file>
