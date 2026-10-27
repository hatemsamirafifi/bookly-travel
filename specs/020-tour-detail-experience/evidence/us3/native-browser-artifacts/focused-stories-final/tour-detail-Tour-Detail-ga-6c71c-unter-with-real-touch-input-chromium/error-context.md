# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail gallery (Spec 020 US3) >> US3 SSR mobile rail touch drag advances the counter with real touch input
- Location: tests\e2e\tour-detail.spec.ts:1605:7

# Error details

```
Error: "apiRequestContext.get: Target page, context or browser has been closed
Call log:
  - → GET http://localhost:8080/tours/rome-gallery-4.jpg
    - user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Safari/537.36
    - accept: */*
    - accept-encoding: gzip,deflate,br
    - Host: cdn.bookly.test
" while running route callback.
Consider awaiting `await page.unrouteAll({ behavior: 'ignoreErrors' })`
before the end of the test to ignore remaining routes in flight.
```

# Test source

```ts
  125 |         await killOwned(next);
  126 |       }
  127 |     } finally {
  128 |       if (mock !== null) {
  129 |         await closeHttpServer(mock);
  130 |       }
  131 |     }
  132 |   };
  133 |   let stopped = false;
  134 |   const stop = async (): Promise<void> => {
  135 |     if (stopped) {
  136 |       return;
  137 |     }
  138 |     stopped = true;
  139 |     await cleanup();
  140 |   };
  141 |   try {
  142 |     mock = createServer((req, res) => {
  143 |       if (!req.url?.startsWith(`/api/public/tours/${slug}`)) {
  144 |         res.writeHead(404).end();
  145 |         return;
  146 |       }
  147 |       const current = readCurrent();
  148 |       if (isControlledStatus(current)) {
  149 |         res.writeHead(current.status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
  150 |         res.end(JSON.stringify(current.body ?? {}));
  151 |         return;
  152 |       }
  153 |       res.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
  154 |       res.end(JSON.stringify({ data: current }));
  155 |     });
  156 |     await listenServer(mock, 0, '127.0.0.1');
  157 |     const mockPort = (mock.address() as AddressInfo).port;
  158 |     const nextPort = await freePort();
  159 |     const child = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-p', String(nextPort), '-H', '127.0.0.1'], {
  160 |       cwd: process.cwd(),
  161 |       env: { ...process.env, API_INTERNAL_URL: `http://127.0.0.1:${mockPort}`, NEXT_TELEMETRY_DISABLED: '1' },
  162 |       stdio: ['ignore', 'pipe', 'pipe'],
  163 |     });
  164 |     next = child;
  165 |     let startupOutput = '';
  166 |     let spawnError: Error | null = null;
  167 |     child.on('error', (error: Error) => {
  168 |       spawnError = error;
  169 |       spawnFailed = true;
  170 |     });
  171 |     child.stdout.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
  172 |     child.stderr.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
  173 |     await expect.poll(async () => {
  174 |       if (spawnError !== null || childExited(child)) {
  175 |         throw new Error(`Isolated Next server exited${spawnError ? `: ${spawnError.message}` : ''}: ${startupOutput}`);
  176 |       }
  177 |       try {
  178 |         return (await fetch(`http://127.0.0.1:${nextPort}/favicon.ico`, { signal: AbortSignal.timeout(5000) })).status;
  179 |       } catch {
  180 |         return 0;
  181 |       }
  182 |     }, { timeout: 30000 }).not.toBe(0);
  183 |     return {
  184 |       mockPort,
  185 |       nextPort,
  186 |       stop,
  187 |     };
  188 |   } catch (error) {
  189 |     await cleanup();
  190 |     throw error;
  191 |   }
  192 | }
  193 | 
  194 | // The actual boundary response the isolated Next server consumes. Asserting
  195 | // the served JSON (rather than the test's own fixture object) proves what
  196 | // SSR actually rendered the fallback/ready transition from.
  197 | export async function readServedDetail(mockPort: number, slug: string, locale: string): Promise<TourDetail> {
  198 |   const response = await fetch(`http://127.0.0.1:${mockPort}/api/public/tours/${slug}?locale=${locale}`);
  199 |   expect(response.status).toBe(200);
  200 |   return ((await response.json()).data) as TourDetail;
  201 | }
  202 | 
  203 | // Focused native media transport (Spec 020 US3 review): the Docker-only
  204 | // fixture CDN (cdn.bookly.test) cannot resolve on a native host, so every
  205 | // gallery image would fail and no loaded/failed distinction could be proven.
  206 | // When SPEC020_NATIVE_MEDIA_PROXY is exactly 'http://localhost:8080', image
  207 | // requests whose encoded source host is cdn.bookly.test are forwarded to
  208 | // that owned media server with Host: cdn.bookly.test, and the ACTUAL
  209 | // upstream response (status and bytes, including real 404s for missing
  210 | // paths) is fulfilled. Everything else continues untouched: no API,
  211 | // session, or production-config interception. Root sets the flag for
  212 | // intermediate native runs only; final Docker media/optimizer acceptance
  213 | // leaves it unset (no-op) against the real CDN. Adapter runs prove gallery
  214 | // behavior, never the production optimizer.
  215 | export async function enableNativeMediaProxy(context: BrowserContext): Promise<void> {
  216 |   const proxy = process.env.SPEC020_NATIVE_MEDIA_PROXY;
  217 |   if (proxy !== 'http://localhost:8080') return;
  218 |   await context.route('**/_next/image**', async (route) => {
  219 |     const source = new URL(route.request().url()).searchParams.get('url');
  220 |     const sourceUrl = source ? new URL(source) : null;
  221 |     if (sourceUrl?.hostname !== 'cdn.bookly.test') {
  222 |       await route.continue();
  223 |       return;
  224 |     }
> 225 |     const upstream = await context.request.get(`${proxy}${sourceUrl.pathname}${sourceUrl.search}`, {
      |                                            ^ Error: "apiRequestContext.get: Target page, context or browser has been closed
  226 |       headers: { Host: 'cdn.bookly.test' },
  227 |     });
  228 |     await route.fulfill({ response: upstream });
  229 |   });
  230 | }
  231 | 
  232 | // Raw status plus parsed body of the same boundary response, for controlled
  233 | // non-200 SSR fixtures. Proves which status the Next server actually mapped
  234 | // through its real client errors (404→NotFoundError, 410→GoneError, …).
  235 | export async function fetchServedResponse(
  236 |   mockPort: number,
  237 |   slug: string,
  238 |   locale: string,
  239 | ): Promise<{ status: number; body: unknown }> {
  240 |   const response = await fetch(`http://127.0.0.1:${mockPort}/api/public/tours/${slug}?locale=${locale}`);
  241 |   const body = await response.json().catch(() => ({}));
  242 |   return { status: response.status, body };
  243 | }
  244 | 
```