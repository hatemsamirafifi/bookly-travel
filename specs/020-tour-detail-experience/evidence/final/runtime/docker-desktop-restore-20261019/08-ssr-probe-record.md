### 06 redirect manual checks 3001/8080 + 07 ONE SSR probe (post-parity, no loops)

#### ONE SSR PROBE
url = http://127.0.0.1:8080/en/tours/hidden-gems-rome-walking-tour
status = 200
exitRecord = {"error":"","args":"-sS -D C:\\Users\\HaTeM\\AppData\\Local\\Temp\\opencode\\docker-restore-20261019\\curl-ssr.hdr.txt -w \"code=%{http_code} size=%{size_download} namelookup=%{time_namelookup} connect=%{time_connect} starttransfer=%{time_starttransfer} total=%{time_total}\" -o C:\\Users\\HaTeM\\AppData\\Local\\Temp\\opencode\\docker-restore-20261019\\curl-ssr.body.txt --max-time 20 http://127.0.0.1:8080/en/tours/hidden-gems-rome-walking-tour","elapsed_ms":554,"exe":"C:\\WINDOWS\\system32\\curl.exe","exit":0,"watchdog":"completed"}
headers >>>
HTTP/1.1 200 OK
Server: nginx/1.31.3
Date: Mon, 19 Oct 2026 20:24:57 GMT
Content-Type: text/html; charset=utf-8
Transfer-Encoding: chunked
Connection: keep-alive
link: <http://127.0.0.1/en/tours/hidden-gems-rome-walking-tour>; rel="alternate"; hreflang="en", <http://127.0.0.1/es/tours/hidden-gems-rome-walking-tour>; rel="alternate"; hreflang="es", <http://127.0.0.1/it/tours/hidden-gems-rome-walking-tour>; rel="alternate"; hreflang="it", <http://127.0.0.1/tours/hidden-gems-rome-walking-tour>; rel="alternate"; hreflang="x-default"
link: </_next/static/media/fba5a26ea33df6a3-s.p.0eehd8tgys7nv.woff2>; rel=preload; as="font"; crossorigin=""; type="font/woff2", </brand/bookly-travel-logo.webp>; rel=preload; as="image"
set-cookie: NEXT_LOCALE=en; Path=/; SameSite=lax
Vary: rsc, next-router-state-tree, next-router-prefetch, next-router-segment-prefetch, Accept-Encoding
X-Powered-By: Next.js
Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate


body-size = 159391
ts_utc=2026-10-19T20:24:57.5712089Z