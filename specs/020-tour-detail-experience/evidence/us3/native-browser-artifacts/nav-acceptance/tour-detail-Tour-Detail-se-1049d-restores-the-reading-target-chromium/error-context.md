# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail section navigation (Spec 020 US4 T053) >> US4 initial deep link restores the reading target
- Location: tests\e2e\tour-detail.spec.ts:2193:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator:  getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' })
Expected: "location"
Received: ""
Timeout:  5000ms

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' })
    2 × locator resolved to <a href="#reviews" class="rounded-lg px-3 py-2 text-sm font-medium text-bookly-navy hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus">Reviews</a>
      - unexpected value "null"

```

```yaml
- link "Reviews":
  - /url: "#reviews"
```

# Test source

```ts
  2104 |       await expect
  2105 |         .poll(async () => {
  2106 |           const box = await lastLink.boundingBox();
  2107 |           if (!box) return 'hidden';
  2108 |           return box.x >= -1 && box.x + box.width <= 391 ? 'revealed' : 'clipped';
  2109 |         }, { timeout: 10000 })
  2110 |         .toBe('revealed');
  2111 |       const linkBox = await lastLink.boundingBox();
  2112 |       expect(linkBox).not.toBeNull();
  2113 |       expect(linkBox!.x).toBeGreaterThanOrEqual(-1);
  2114 |       expect(linkBox!.x + linkBox!.width).toBeLessThanOrEqual(391);
  2115 |       await expect(lastLink).toHaveAttribute('aria-current', 'location');
  2116 |       await noPageHorizontalOverflow(page);
  2117 | 
  2118 |       // The full fixture is bookable, so the mobile bar must be present with
  2119 |       // an actually enabled continue control — never an optional no-op — and
  2120 |       // the settled heading sits above the live bar.
  2121 |       const bar = page.locator('main#main-content').getByTestId('mobile-booking-bar');
  2122 |       await expect(bar).toBeVisible();
  2123 |       const barContinue = bar.getByRole('link', { name: 'Continue to booking' });
  2124 |       await expect(barContinue).toBeVisible();
  2125 |       await expect(barContinue).toBeEnabled();
  2126 |       const barBox = await bar.boundingBox();
  2127 |       const headBox = await headingFor(page, 'related').boundingBox();
  2128 |       expect(barBox).not.toBeNull();
  2129 |       expect(headBox).not.toBeNull();
  2130 |       expect(headBox!.y + headBox!.height).toBeLessThanOrEqual(barBox!.y + 1);
  2131 |     } finally {
  2132 |       await harness.stop();
  2133 |     }
  2134 |   });
  2135 | 
  2136 |   test('US4 long locale labels survive resize with reachable entries and no page overflow', async ({ page }) => {
  2137 |     test.setTimeout(180000);
  2138 |     const full = createFullTourDetail(new Date());
  2139 |     const harness = await startSsrHarness(slug, () => full);
  2140 |     try {
  2141 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2142 |       await page.setViewportSize({ width: 390, height: 844 });
  2143 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
  2144 |       await expect(page.locator('main#main-content')).toBeVisible();
  2145 |       await page.evaluate(() => document.fonts.ready);
  2146 | 
  2147 |       await activateEntry(page, 'Secciones del tour', 'itinerary', 'Itinerario');
  2148 |       await noPageHorizontalOverflow(page);
  2149 | 
  2150 |       // Real resize both ways: exactly one current entry, rail stays usable.
  2151 |       await page.setViewportSize({ width: 1440, height: 900 });
  2152 |       await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
  2153 |       await expect
  2154 |         .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
  2155 |         .toBe(1);
  2156 |       await noPageHorizontalOverflow(page);
  2157 | 
  2158 |       await page.setViewportSize({ width: 390, height: 844 });
  2159 |       await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
  2160 |       await expect
  2161 |         .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
  2162 |         .toBe(1);
  2163 |       await noPageHorizontalOverflow(page);
  2164 |     } finally {
  2165 |       await harness.stop();
  2166 |     }
  2167 |   });
  2168 | 
  2169 |   test('US4 reduced motion targets the reading position immediately', async ({ page }) => {
  2170 |     test.setTimeout(180000);
  2171 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  2172 |     await page.setViewportSize({ width: 1280, height: 900 });
  2173 |     const full = createFullTourDetail(new Date());
  2174 |     const harness = await startSsrHarness(slug, () => full);
  2175 |     try {
  2176 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2177 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2178 |       await expect(page.locator('main#main-content')).toBeVisible();
  2179 |       expect(
  2180 |         await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches),
  2181 |       ).toBe(true);
  2182 | 
  2183 |       await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
  2184 |       // Immediate targeting under reduced motion: re-assert the settled
  2185 |       // heading on a tight budget rather than only an eventual long poll.
  2186 |       await headingClearOfNav(page, 'Tour sections', 'facts', 3000);
  2187 |       await noPageHorizontalOverflow(page);
  2188 |     } finally {
  2189 |       await harness.stop();
  2190 |     }
  2191 |   });
  2192 | 
  2193 |   test('US4 initial deep link restores the reading target', async ({ page }) => {
  2194 |     test.setTimeout(180000);
  2195 |     await page.setViewportSize({ width: 1280, height: 900 });
  2196 |     const full = createFullTourDetail(new Date());
  2197 |     const harness = await startSsrHarness(slug, () => full);
  2198 |     try {
  2199 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2200 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}#reviews`);
  2201 |       await expect(page.locator('main#main-content')).toBeVisible();
  2202 | 
  2203 |       const nav = page.getByRole('navigation', { name: 'Tour sections' });
> 2204 |       await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
       |                                                                ^ Error: expect(locator).toHaveAttribute(expected) failed
  2205 |       await expect(headingFor(page, 'reviews')).toBeFocused();
  2206 |       expect(page.url()).toContain('#reviews');
  2207 |       await headingClearOfNav(page, 'Tour sections', 'reviews');
  2208 |       await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
  2209 |       await noPageHorizontalOverflow(page);
  2210 |     } finally {
  2211 |       await harness.stop();
  2212 |     }
  2213 |   });
  2214 | 
  2215 |   // Forthcoming-red geometry probe (T055/T056 integration target, NOT a
  2216 |   // source change): at a realistic 200% nav text size the sticky booking card
  2217 |   // must clear the measured nav obstruction. The hardcoded lg:top-20 (80px)
  2218 |   // card slot cannot follow a taller nav, so the final no-overlap assertion
  2219 |   // is expected to fail on current source; root records that red before the
  2220 |   // integration phase. The injected style is browser-only and labeled as a
  2221 |   // test-only accessibility text-resize probe.
  2222 |   test('US4 sticky booking card clears the measured nav obstruction at 200% nav text size', async ({ page }) => {
  2223 |     test.setTimeout(180000);
  2224 |     await page.setViewportSize({ width: 1280, height: 900 });
  2225 |     // Reduced motion matches root's actual reproduction and makes the scroll
  2226 |     // settle immediate and deterministic.
  2227 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  2228 |     const full = createFullTourDetail(new Date());
  2229 |     const harness = await startSsrHarness(slug, () => full);
  2230 |     try {
  2231 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2232 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2233 |       await expect(page.locator('main#main-content')).toBeVisible();
  2234 |       await page.evaluate(() => document.fonts.ready);
  2235 | 
  2236 |       const nav = await navFor(page, 'Tour sections');
  2237 |       const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
  2238 |       await expect(cardSlot).toBeVisible();
  2239 |       const aside = page.locator('main#main-content aside', { has: page.getByTestId('tour-booking-card-slot') });
  2240 |       const baselineNavHeight = (await nav.boundingBox())!.height;
  2241 |       expect(baselineNavHeight).toBeGreaterThan(0);
  2242 | 
  2243 |       // Browser-only text resize: real reflow, real ResizeObserver
  2244 |       // notification, production source untouched. The 200%/2.0 pairing is a
  2245 |       // usable text-resize premise shared with the unit slice.
  2246 |       await page.addStyleTag({
  2247 |         content:
  2248 |           'nav[aria-label="Tour sections"] a{' +
  2249 |           'font-size:200%!important;line-height:2!important}' +
  2250 |           '/* T053 accessibility text-resize probe (test-only, production unchanged) */',
  2251 |       });
  2252 |       await expect
  2253 |         .poll(async () => (await nav.boundingBox())?.height ?? 0, { timeout: 10000 })
  2254 |         .toBeGreaterThan(baselineNavHeight);
  2255 |       const grownNavHeight = (await nav.boundingBox())!.height;
  2256 |       // Premise provenance: the nav must actually exceed the hardcoded 80px
  2257 |       // card offset, or the overlap check below would prove nothing.
  2258 |       expect(grownNavHeight).toBeGreaterThan(80);
  2259 | 
  2260 |       // Middle of the article (facts, per root's actual Rome confirmation):
  2261 |       // plenty of real article remains below, so the card is top-stuck rather
  2262 |       // than legitimately pushed up by the containing-block end.
  2263 |       await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
  2264 | 
  2265 |       // Stuck premise: the nav sits at the viewport top...
  2266 |       const stuckNavBox = await nav.boundingBox();
  2267 |       expect(stuckNavBox).not.toBeNull();
  2268 |       expect(Math.abs(stuckNavBox!.y)).toBeLessThanOrEqual(1);
  2269 | 
  2270 |       // ...and the aside keeps more than a token gap of real content below
  2271 |       // the card, excluding container-end push-up by construction (push-up
  2272 |       // begins at zero remaining; root actual: 528px remaining).
  2273 |       const gapPx = await sharedTokenGapPx(page);
  2274 |       const cardBox = await cardSlot.boundingBox();
  2275 |       const asideBox = await aside.boundingBox();
  2276 |       expect(cardBox).not.toBeNull();
  2277 |       expect(asideBox).not.toBeNull();
  2278 |       expect(asideBox!.y + asideBox!.height - (cardBox!.y + cardBox!.height)).toBeGreaterThan(gapPx);
  2279 | 
  2280 |       // Single geometry provenance check: actual nav bottom vs actual card
  2281 |       // top. No classname assertions, no overbroad tolerance (±1px rounding).
  2282 |       const geometry = await page.evaluate(() => {
  2283 |         const stuckNav = document
  2284 |           .querySelector('main#main-content')
  2285 |           ?.querySelector('nav[aria-label="Tour sections"]');
  2286 |         const slot = document
  2287 |           .querySelector('main#main-content')
  2288 |           ?.querySelector('[data-testid="tour-booking-card-slot"]');
  2289 |         if (!(stuckNav instanceof HTMLElement) || !(slot instanceof HTMLElement)) return null;
  2290 |         const navBox = stuckNav.getBoundingClientRect();
  2291 |         const slotBox = slot.getBoundingClientRect();
  2292 |         return { navBottom: navBox.y + navBox.height, cardTop: slotBox.y };
  2293 |       });
  2294 |       expect(geometry).not.toBeNull();
  2295 |       expect(geometry!.cardTop).toBeGreaterThanOrEqual(geometry!.navBottom - 1);
  2296 |       await noPageHorizontalOverflow(page);
  2297 |     } finally {
  2298 |       await harness.stop();
  2299 |     }
  2300 |   });
  2301 | });
  2302 | 
```