# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail section navigation (Spec 020 US4 T053) >> US4 sticky booking card clears the measured nav obstruction at 200% nav text size
- Location: tests\e2e\tour-detail.spec.ts:2205:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByTestId('tour-booking-card-slot')
Expected: visible
Error: strict mode violation: getByTestId('tour-booking-card-slot') resolved to 2 elements:
    1) <div data-testid="tour-booking-card-slot" class="hidden lg:block lg:sticky lg:top-[calc(var(--tour-section-nav-offset,5rem)+var(--spacing-inline,0.5rem))]">…</div> aka locator('#main-content').getByTestId('tour-booking-card-slot')
    2) <div data-testid="tour-booking-card-slot" class="hidden lg:block lg:sticky lg:top-[calc(var(--tour-section-nav-offset,5rem)+var(--spacing-inline,0.5rem))]">…</div> aka getByTestId('tour-booking-card-slot').nth(1)

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByTestId('tour-booking-card-slot')

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
  - complementary "Cookie consent"
  - alert [ref=e255]
```

# Test source

```ts
  2121 |     const full = createFullTourDetail(new Date());
  2122 |     const harness = await startSsrHarness(slug, () => full);
  2123 |     try {
  2124 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2125 |       await page.setViewportSize({ width: 390, height: 844 });
  2126 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
  2127 |       await expect(page.locator('main#main-content')).toBeVisible();
  2128 |       await page.evaluate(() => document.fonts.ready);
  2129 | 
  2130 |       await activateEntry(page, 'Secciones del tour', 'itinerary', 'Itinerario');
  2131 |       await noPageHorizontalOverflow(page);
  2132 | 
  2133 |       // Real resize both ways: exactly one current entry, rail stays usable.
  2134 |       await page.setViewportSize({ width: 1440, height: 900 });
  2135 |       await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
  2136 |       await expect
  2137 |         .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
  2138 |         .toBe(1);
  2139 |       await noPageHorizontalOverflow(page);
  2140 | 
  2141 |       await page.setViewportSize({ width: 390, height: 844 });
  2142 |       await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
  2143 |       await expect
  2144 |         .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
  2145 |         .toBe(1);
  2146 |       await noPageHorizontalOverflow(page);
  2147 |     } finally {
  2148 |       await harness.stop();
  2149 |     }
  2150 |   });
  2151 | 
  2152 |   test('US4 reduced motion targets the reading position immediately', async ({ page }) => {
  2153 |     test.setTimeout(180000);
  2154 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  2155 |     await page.setViewportSize({ width: 1280, height: 900 });
  2156 |     const full = createFullTourDetail(new Date());
  2157 |     const harness = await startSsrHarness(slug, () => full);
  2158 |     try {
  2159 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2160 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2161 |       await expect(page.locator('main#main-content')).toBeVisible();
  2162 |       expect(
  2163 |         await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches),
  2164 |       ).toBe(true);
  2165 | 
  2166 |       await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
  2167 |       // Immediate targeting under reduced motion: re-assert the settled
  2168 |       // heading on a tight budget rather than only an eventual long poll.
  2169 |       await headingClearOfNav(page, 'Tour sections', 'facts', 3000);
  2170 |       await noPageHorizontalOverflow(page);
  2171 |     } finally {
  2172 |       await harness.stop();
  2173 |     }
  2174 |   });
  2175 | 
  2176 |   test('US4 initial deep link restores the reading target', async ({ page }) => {
  2177 |     test.setTimeout(180000);
  2178 |     await page.setViewportSize({ width: 1280, height: 900 });
  2179 |     const full = createFullTourDetail(new Date());
  2180 |     const harness = await startSsrHarness(slug, () => full);
  2181 |     try {
  2182 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2183 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}#reviews`);
  2184 |       await expect(page.locator('main#main-content')).toBeVisible();
  2185 | 
  2186 |       const nav = page.getByRole('navigation', { name: 'Tour sections' });
  2187 |       await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
  2188 |       await expect(headingFor(page, 'reviews')).toBeFocused();
  2189 |       expect(page.url()).toContain('#reviews');
  2190 |       await headingClearOfNav(page, 'Tour sections', 'reviews');
  2191 |       await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
  2192 |       await noPageHorizontalOverflow(page);
  2193 |     } finally {
  2194 |       await harness.stop();
  2195 |     }
  2196 |   });
  2197 | 
  2198 |   // Forthcoming-red geometry probe (T055/T056 integration target, NOT a
  2199 |   // source change): at a realistic 200% nav text size the sticky booking card
  2200 |   // must clear the measured nav obstruction. The hardcoded lg:top-20 (80px)
  2201 |   // card slot cannot follow a taller nav, so the final no-overlap assertion
  2202 |   // is expected to fail on current source; root records that red before the
  2203 |   // integration phase. The injected style is browser-only and labeled as a
  2204 |   // test-only accessibility text-resize probe.
  2205 |   test('US4 sticky booking card clears the measured nav obstruction at 200% nav text size', async ({ page }) => {
  2206 |     test.setTimeout(180000);
  2207 |     await page.setViewportSize({ width: 1280, height: 900 });
  2208 |     // Reduced motion matches root's actual reproduction and makes the scroll
  2209 |     // settle immediate and deterministic.
  2210 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  2211 |     const full = createFullTourDetail(new Date());
  2212 |     const harness = await startSsrHarness(slug, () => full);
  2213 |     try {
  2214 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2215 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2216 |       await expect(page.locator('main#main-content')).toBeVisible();
  2217 |       await page.evaluate(() => document.fonts.ready);
  2218 | 
  2219 |       const nav = await navFor(page, 'Tour sections');
  2220 |       const cardSlot = page.getByTestId('tour-booking-card-slot');
> 2221 |       await expect(cardSlot).toBeVisible();
       |                              ^ Error: expect(locator).toBeVisible() failed
  2222 |       const aside = page.locator('aside', { has: cardSlot });
  2223 |       const baselineNavHeight = (await nav.boundingBox())!.height;
  2224 |       expect(baselineNavHeight).toBeGreaterThan(0);
  2225 | 
  2226 |       // Browser-only text resize: real reflow, real ResizeObserver
  2227 |       // notification, production source untouched. The 200%/2.0 pairing is a
  2228 |       // usable text-resize premise shared with the unit slice.
  2229 |       await page.addStyleTag({
  2230 |         content:
  2231 |           'nav[aria-label="Tour sections"] a{' +
  2232 |           'font-size:200%!important;line-height:2!important}' +
  2233 |           '/* T053 accessibility text-resize probe (test-only, production unchanged) */',
  2234 |       });
  2235 |       await expect
  2236 |         .poll(async () => (await nav.boundingBox())?.height ?? 0, { timeout: 10000 })
  2237 |         .toBeGreaterThan(baselineNavHeight);
  2238 |       const grownNavHeight = (await nav.boundingBox())!.height;
  2239 |       // Premise provenance: the nav must actually exceed the hardcoded 80px
  2240 |       // card offset, or the overlap check below would prove nothing.
  2241 |       expect(grownNavHeight).toBeGreaterThan(80);
  2242 | 
  2243 |       // Middle of the article (facts, per root's actual Rome confirmation):
  2244 |       // plenty of real article remains below, so the card is top-stuck rather
  2245 |       // than legitimately pushed up by the containing-block end.
  2246 |       await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
  2247 | 
  2248 |       // Stuck premise: the nav sits at the viewport top...
  2249 |       const stuckNavBox = await nav.boundingBox();
  2250 |       expect(stuckNavBox).not.toBeNull();
  2251 |       expect(Math.abs(stuckNavBox!.y)).toBeLessThanOrEqual(1);
  2252 | 
  2253 |       // ...and the aside keeps more than a token gap of real content below
  2254 |       // the card, excluding container-end push-up by construction (push-up
  2255 |       // begins at zero remaining; root actual: 528px remaining).
  2256 |       const gapPx = await sharedTokenGapPx(page);
  2257 |       const cardBox = await cardSlot.boundingBox();
  2258 |       const asideBox = await aside.boundingBox();
  2259 |       expect(cardBox).not.toBeNull();
  2260 |       expect(asideBox).not.toBeNull();
  2261 |       expect(asideBox!.y + asideBox!.height - (cardBox!.y + cardBox!.height)).toBeGreaterThan(gapPx);
  2262 | 
  2263 |       // Single geometry provenance check: actual nav bottom vs actual card
  2264 |       // top. No classname assertions, no overbroad tolerance (±1px rounding).
  2265 |       const geometry = await page.evaluate(() => {
  2266 |         const stuckNav = document
  2267 |           .querySelector('main#main-content')
  2268 |           ?.querySelector('nav[aria-label="Tour sections"]');
  2269 |         const slot = document
  2270 |           .querySelector('main#main-content')
  2271 |           ?.querySelector('[data-testid="tour-booking-card-slot"]');
  2272 |         if (!(stuckNav instanceof HTMLElement) || !(slot instanceof HTMLElement)) return null;
  2273 |         const navBox = stuckNav.getBoundingClientRect();
  2274 |         const slotBox = slot.getBoundingClientRect();
  2275 |         return { navBottom: navBox.y + navBox.height, cardTop: slotBox.y };
  2276 |       });
  2277 |       expect(geometry).not.toBeNull();
  2278 |       expect(geometry!.cardTop).toBeGreaterThanOrEqual(geometry!.navBottom - 1);
  2279 |       await noPageHorizontalOverflow(page);
  2280 |     } finally {
  2281 |       await harness.stop();
  2282 |     }
  2283 |   });
  2284 | });
  2285 | 
```