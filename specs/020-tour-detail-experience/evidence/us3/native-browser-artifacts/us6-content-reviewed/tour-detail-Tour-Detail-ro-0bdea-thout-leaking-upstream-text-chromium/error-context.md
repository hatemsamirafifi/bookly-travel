# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail route states (Spec 020 US6) >> US6 SSR recoverable failure shows a safe localized retry and recovers without leaking upstream text
- Location: tests\e2e\tour-detail.spec.ts:1291:7

# Error details

```
Error: route.fulfill: Route is already handled!
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - link "Skip to main content" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /en
      - navigation "Main navigation" [ref=e6]:
        - link "Home" [ref=e7] [cursor=pointer]:
          - /url: /en
        - link "Search tours" [ref=e8] [cursor=pointer]:
          - /url: /en/search
        - link "Categories" [ref=e9] [cursor=pointer]:
          - /url: /en/categories
        - link "Destinations" [ref=e10] [cursor=pointer]:
          - /url: /en/destinations
      - generic [ref=e11]:
        - button "Search tours" [ref=e12]:
          - img [ref=e13]
        - combobox "Switch language" [ref=e18]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
        - link "Sign In" [ref=e19] [cursor=pointer]:
          - /url: /en/auth/login
          - generic [ref=e20]: Sign In
        - link "Sign Up" [ref=e21] [cursor=pointer]:
          - /url: /en/auth/register
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic [ref=e24]:
        - navigation "Breadcrumbs" [ref=e25]:
          - list [ref=e26]:
            - listitem [ref=e27]:
              - link "Home" [ref=e28] [cursor=pointer]:
                - /url: /en
            - listitem [ref=e29]:
              - generic [ref=e30]: /
              - link "Walking Tours" [ref=e31] [cursor=pointer]:
                - /url: /en/categories/walking
            - listitem [ref=e32]:
              - generic [ref=e33]: /
              - generic [ref=e34]: Hidden Gems of Rome Walking Tour
        - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e35]
        - generic [ref=e37]:
          - img [ref=e38]
          - text: Rome, Italy
        - generic [ref=e41]:
          - 'generic "Rating: 4.5 out of 5" [ref=e42]':
            - img [ref=e43]
            - generic [ref=e45]: "4.5"
            - generic [ref=e46]: "Rating: 4.5 out of 5"
          - link "(2 Reviews)" [ref=e47] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e48]:
        - generic [ref=e49]:
          - button "View image 1" [ref=e50]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e51]
          - button "View image 2" [ref=e52]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e53]
          - button "View image 3" [ref=e54]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e55]
          - button "View image 4" [ref=e56]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e57]
          - button "View image 5" [ref=e58]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e59]
        - button "See all 5 photos" [ref=e60]
      - navigation "Tour sections" [ref=e61]:
        - generic [ref=e62]:
          - link "About This Tour" [ref=e63] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e64] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e65] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerary" [ref=e66] [cursor=pointer]:
            - /url: "#itinerary"
          - link "What's Included" [ref=e67] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e68] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e69] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e70] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Important information" [ref=e71] [cursor=pointer]:
            - /url: "#important"
          - link "Tour operator" [ref=e72] [cursor=pointer]:
            - /url: "#operator"
          - link "Reviews" [ref=e73] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e74] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e75]:
        - article [ref=e76]:
          - generic [ref=e77]:
            - heading "About This Tour" [level=2] [ref=e78]
            - generic [ref=e79]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e80]:
            - heading "Tour facts" [level=2] [ref=e81]
            - generic [ref=e82]:
              - generic [ref=e83]:
                - term [ref=e84]: Duration
                - definition [ref=e85]: 3 hours
              - generic [ref=e86]:
                - term [ref=e87]: Group size
                - definition [ref=e88]: 1–20 travelers
              - generic [ref=e89]:
                - term [ref=e90]: Difficulty
                - definition [ref=e91]: Moderate
            - paragraph [ref=e92]:
              - generic [ref=e93]: "Live guide:"
              - text: German, English, Spanish
          - generic [ref=e94]:
            - heading "Highlights" [level=2] [ref=e95]
            - list [ref=e96]:
              - listitem [ref=e97]:
                - img [ref=e98]
                - text: Hidden courtyards
              - listitem [ref=e100]:
                - img [ref=e101]
                - text: Local artisan shops
              - listitem [ref=e103]:
                - img [ref=e104]
                - text: Ancient Roman stories
              - listitem [ref=e106]:
                - img [ref=e107]
                - text: Off the beaten path
          - generic [ref=e109]:
            - heading "Itinerary" [level=2] [ref=e110]
            - list [ref=e111]:
              - listitem [ref=e112]:
                - 'heading "Day 1: Hidden courtyards walk" [level=3] [ref=e113]'
                - paragraph [ref=e114]: Walk the secret corners of Rome.
              - listitem [ref=e115]:
                - 'heading "Day 2: Artisan quarter" [level=3] [ref=e116]'
                - paragraph [ref=e117]: Meet local artisans.
          - generic [ref=e118]:
            - heading "What's Included" [level=3] [ref=e122]:
              - button "What's Included" [ref=e123]:
                - text: What's Included
                - img [ref=e124]
            - heading "What's Excluded" [level=3] [ref=e129]:
              - button "What's Excluded" [ref=e130]:
                - text: What's Excluded
                - img [ref=e131]
          - generic [ref=e133]:
            - heading "Meeting Point" [level=2] [ref=e134]
            - paragraph [ref=e135]:
              - img [ref=e136]
              - text: Colosseum main entrance
          - generic [ref=e139]:
            - heading "Cancellation Policy" [level=2] [ref=e140]
            - paragraph [ref=e141]: Free cancellation up to 24 hours before the tour.
          - generic [ref=e142]:
            - heading "Important information" [level=2] [ref=e143]
            - list [ref=e144]:
              - listitem [ref=e145]: Bring your passport
          - region "Tour operator" [ref=e146]:
            - heading "Tour operator" [level=2] [ref=e147]
            - paragraph [ref=e148]: Rome Walks
            - paragraph [ref=e149]:
              - generic [ref=e150]: Verified operator
            - paragraph [ref=e151]: Local guides.
            - generic [ref=e152]:
              - generic [ref=e153]: 3 tours
              - generic [ref=e154]: 8 reviews
          - generic [ref=e156]:
            - heading "Reviews" [level=3] [ref=e157]
            - status [ref=e158]: Loading...
        - complementary [ref=e168]:
          - generic [ref=e170]:
            - generic [ref=e171]:
              - generic [ref=e172]:
                - text: €45.00
                - generic [ref=e173]: / person
              - button "Save to wishlist" [ref=e174]:
                - generic [ref=e175]: ♡
            - generic [ref=e176]:
              - generic [ref=e177]: Select a Date
              - generic [ref=e178]:
                - paragraph [ref=e179]: Next available
                - paragraph [ref=e180]: 10/17/2026
              - combobox "Select a Date" [ref=e181]:
                - option "Select a Date"
                - option "10/17/2026" [selected]
                - option "10/18/2026"
                - option "10/19/2026"
                - option "10/20/2026"
                - option "10/21/2026"
                - option "10/22/2026"
                - option "10/23/2026"
                - option "10/24/2026"
                - option "10/25/2026"
                - option "10/26/2026"
                - option "10/27/2026"
                - option "10/28/2026"
                - option "10/29/2026"
                - option "10/30/2026"
                - option "10/31/2026"
                - option "11/01/2026"
                - option "11/02/2026"
                - option "11/03/2026"
                - option "11/04/2026"
                - option "11/05/2026"
                - option "11/06/2026"
                - option "11/07/2026"
                - option "11/08/2026"
                - option "11/09/2026"
                - option "11/10/2026"
                - option "11/11/2026"
                - option "11/12/2026"
                - option "11/13/2026"
                - option "11/14/2026"
                - option "11/15/2026"
                - option "11/16/2026"
                - option "11/17/2026"
                - option "11/18/2026"
                - option "11/19/2026"
                - option "11/20/2026"
                - option "11/21/2026"
                - option "11/22/2026"
                - option "11/23/2026"
                - option "11/24/2026"
                - option "11/25/2026"
                - option "11/26/2026"
                - option "11/27/2026"
                - option "11/28/2026"
                - option "11/29/2026"
                - option "11/30/2026"
                - option "12/01/2026"
                - option "12/02/2026"
                - option "12/03/2026"
                - option "12/04/2026"
                - option "12/05/2026"
                - option "12/06/2026"
                - option "12/07/2026"
                - option "12/08/2026"
                - option "12/09/2026"
                - option "12/10/2026"
                - option "12/11/2026"
                - option "12/12/2026"
                - option "12/13/2026"
                - option "12/14/2026"
                - option "12/15/2026"
            - generic [ref=e182]:
              - generic [ref=e183]: Participants
              - group "Participants" [ref=e184]:
                - button "Decrease participants" [disabled] [ref=e185]: "-"
                - status [ref=e186]: "1"
                - button "Increase participants" [ref=e187]: +
                - generic [ref=e188]: 1–20 allowed
            - paragraph [ref=e189]: "Estimated total: €45.00"
            - paragraph [ref=e190]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e191]:
              - paragraph [ref=e192]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e193] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e194] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
      - region "More experiences to explore" [ref=e195]:
        - heading "More experiences to explore" [level=2] [ref=e196]
        - generic [ref=e198]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e199] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e201]
            - generic [ref=e202]:
              - generic [ref=e203]:
                - generic [ref=e204]: Rome, Italy
                - generic [ref=e205]: ·
                - generic [ref=e206]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e207]
              - generic [ref=e208]:
                - generic [ref=e209]:
                  - 'img "Rating: 4.8 out of 5" [ref=e210]':
                    - img [ref=e211]
                    - img [ref=e213]
                    - img [ref=e215]
                    - img [ref=e217]
                    - img [ref=e219]
                  - generic [ref=e221]: (4.8)
                - generic [ref=e222]: (11 reviews)
              - generic [ref=e223]:
                - generic [ref=e224]: Walking Tours
                - generic [ref=e225]: €39.00
          - button "Save to wishlist" [ref=e227]:
            - generic [ref=e228]: ♡
  - contentinfo [ref=e229]:
    - generic [ref=e230]:
      - generic [ref=e231]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e234]
        - generic [ref=e235]:
          - paragraph [ref=e236]: Explore
          - list [ref=e237]:
            - listitem [ref=e238]:
              - link "Search tours" [ref=e239] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e240]:
              - link "Categories" [ref=e241] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e242]:
              - link "Destinations" [ref=e243] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e244]:
          - paragraph [ref=e245]: Company
          - list [ref=e246]:
            - listitem [ref=e247]:
              - link "Privacy policy" [ref=e248] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e249]:
              - link "Terms of service" [ref=e250] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e251]:
        - generic [ref=e252]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e254]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e255]:
      - generic [ref=e256]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e257]:
        - button "Reject" [ref=e258]
        - button "Accept" [ref=e259]
  - alert [ref=e260]: Hidden Gems of Rome Walking Tour | Bookly
```

# Test source

```ts
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
  225 |     const upstream = await context.request.get(`${proxy}${sourceUrl.pathname}${sourceUrl.search}`, {
  226 |       headers: { Host: 'cdn.bookly.test' },
  227 |     });
> 228 |     await route.fulfill({ response: upstream });
      |                 ^ Error: route.fulfill: Route is already handled!
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