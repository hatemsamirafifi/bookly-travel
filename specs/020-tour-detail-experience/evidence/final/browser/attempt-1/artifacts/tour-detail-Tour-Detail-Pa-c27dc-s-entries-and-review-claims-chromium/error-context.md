# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US1 SSR sparse tour at 1440px omits absent sections, entries and review claims
- Location: tests/e2e/tour-detail.spec.ts:907:9

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('#overview')
Expected: visible
Error: strict mode violation: locator('#overview') resolved to 2 elements:
    1) <section id="overview" class="scroll-mt-20">…</section> aka locator('#main-content').getByText('About This TourDiscover the')
    2) <section id="overview" class="scroll-mt-20">…</section> aka getByText('About This TourDiscover the').nth(1)

Call log:
  - Expect "toBeVisible" with timeout 20000ms
  - waiting for locator('#overview')

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
        - generic [ref=e35]:
          - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e36]
          - button "Save to wishlist" [ref=e37]:
            - generic [ref=e38]: ♡
        - generic [ref=e40]:
          - img [ref=e41]
          - text: Rome, Italy
      - button "View image 1" [ref=e46]:
        - img "Hidden Gems of Rome Walking Tour" [ref=e47]
      - navigation "Tour sections" [ref=e48]:
        - generic [ref=e49]:
          - link "About This Tour" [ref=e50] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e51] [cursor=pointer]:
            - /url: "#facts"
      - generic [ref=e52]:
        - article [ref=e53]:
          - generic [ref=e54]:
            - heading "About This Tour" [level=2] [ref=e55]
            - generic [ref=e56]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e57]:
            - heading "Tour facts" [level=2] [ref=e58]
            - generic [ref=e59]:
              - generic [ref=e60]:
                - term [ref=e61]: Duration
                - definition [ref=e62]: 3 hours
              - generic [ref=e63]:
                - term [ref=e64]: Group size
                - definition [ref=e65]: 1–20 travelers
        - complementary [ref=e66]:
          - generic [ref=e68]:
            - generic [ref=e70]:
              - text: €45.00
              - generic [ref=e71]: / person
            - generic [ref=e72]:
              - generic [ref=e73]: Select a Date
              - paragraph [ref=e75]: Next available
              - combobox "Select a Date" [ref=e76]:
                - option "Select a Date" [selected]
                - option "10/17/2026"
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
            - generic [ref=e77]:
              - generic [ref=e78]: Participants
              - group "Participants" [ref=e79]:
                - button "Decrease participants" [disabled] [ref=e80]: "-"
                - status [ref=e81]: "1"
                - button "Increase participants" [ref=e82]: +
                - generic [ref=e83]: 1–20 allowed
            - paragraph [ref=e84]: "Estimated total: €45.00"
            - list [ref=e86]:
              - listitem [ref=e87]: Select an offered date to continue.
            - button "Book Now" [disabled] [ref=e88]
  - contentinfo [ref=e89]:
    - generic [ref=e90]:
      - generic [ref=e91]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e94]
        - generic [ref=e95]:
          - paragraph [ref=e96]: Explore
          - list [ref=e97]:
            - listitem [ref=e98]:
              - link "Search tours" [ref=e99] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e100]:
              - link "Categories" [ref=e101] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e102]:
              - link "Destinations" [ref=e103] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e104]:
          - paragraph [ref=e105]: Company
          - list [ref=e106]:
            - listitem [ref=e107]:
              - link "Privacy policy" [ref=e108] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e109]:
              - link "Terms of service" [ref=e110] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e111]:
        - generic [ref=e112]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e114]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e115]
```

# Test source

```ts
  821  |         expect(href.pathname).toBe('/es/booking');
  822  |         expect(href.searchParams.get('tour')).toBe(slug);
  823  |         expect(href.searchParams.get('participants')).toBe(String(served.group_size.min));
  824  |         expect(href.searchParams.get('date')).toBe(served.availability.next_available_date);
  825  |       } else {
  826  |         await expect(bookingAction).toHaveCount(0);
  827  |       }
  828  |     } finally {
  829  |       if (snapshot !== null && tourId !== 0) {
  830  |         const restore = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  831  |           translations: { en: snapshot },
  832  |         });
  833  |         expect(restore.status).toBe(200);
  834  |       }
  835  |       await authedContext.close();
  836  |     }
  837  |   });
  838  | 
  839  |   // Spec 020 US1: ordered readable hierarchy through the real built SSR
  840  |   // server. Controlled complete/sparse responses prove prescribed order,
  841  |   // usable disclosure, important-information retention and no orphan nav
  842  |   // entries without touching shared tour data.
  843  |   for (const width of [390, 1440]) {
  844  |     test(`US1 SSR complete tour at ${width}px keeps prescribed order with disclosure and no orphan nav entries`, async ({ page }) => {
  845  |       test.setTimeout(180000);
  846  |       await page.setViewportSize({ width, height: 900 });
  847  |       const slug = 'hidden-gems-rome-walking-tour';
  848  |       const complete = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  849  |       complete.description = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
  850  |       const harness = await startSsrHarness(slug, () => complete);
  851  |       try {
  852  |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  853  |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  854  |         expect(served.title).toBe(complete.title);
  855  |         expect(served.reviews.count).toBeGreaterThan(0);
  856  | 
  857  |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  858  | 
  859  |         // Header: breadcrumbs, title, location and genuine rating/review link.
  860  |         await expect(page.getByRole('navigation', { name: 'Breadcrumbs' })).toBeVisible();
  861  |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
  862  |         await expect(page.getByText('Rome, Italy').first()).toBeVisible();
  863  |         const reviewLinks = page.locator('a[href="#reviews"]');
  864  |         await expect(reviewLinks).toHaveCount(2);
  865  | 
  866  |         // Prescribed section order in the served document.
  867  |         const ids = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
  868  |         const ordered = await page.evaluate((wanted: string[]) => {
  869  |           const elements = wanted.map((id) => document.getElementById(id));
  870  |           if (elements.some((element) => element === null)) return false;
  871  |           return elements.every((element, index) =>
  872  |             index === 0 || elements[index - 1]!.compareDocumentPosition(element!) === 4,
  873  |           );
  874  |         }, ids);
  875  |         expect(ordered).toBe(true);
  876  | 
  877  |         // Navigation targets exactly the rendered sections.
  878  |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  879  |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  880  |         expect(hrefs).toEqual(ids.map((id) => `#${id}`));
  881  |         for (const href of hrefs) {
  882  |           await expect(page.locator(href!)).toHaveCount(1);
  883  |         }
  884  | 
  885  |         // Long overview: preview first, full text reachable through disclosure.
  886  |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  887  |         const expand = page.getByRole('button', { name: 'Read more' });
  888  |         await expect(expand).toHaveAttribute('aria-expanded', 'false');
  889  |         expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  890  |         await expand.click();
  891  |         await expect(page.getByText('Final reachable sentence.')).toBeVisible();
  892  |         const collapse = page.getByRole('button', { name: 'Read less' });
  893  |         await expect(collapse).toHaveAttribute('aria-expanded', 'true');
  894  |         await collapse.click();
  895  |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  896  | 
  897  |         // Practical information stays fully readable; genuine facts shown.
  898  |         await expect(page.locator('#important')).toContainText('Bring your passport');
  899  |         await expect(page.locator('#facts')).toContainText('Moderate');
  900  |         await expect(page.locator('#itinerary')).toContainText('Artisan quarter');
  901  |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/complete-en-${width}.png`, fullPage: true });
  902  |       } finally {
  903  |         await harness.stop();
  904  |       }
  905  |     });
  906  | 
  907  |     test(`US1 SSR sparse tour at ${width}px omits absent sections, entries and review claims`, async ({ page }) => {
  908  |       test.setTimeout(180000);
  909  |       await page.setViewportSize({ width, height: 900 });
  910  |       const slug = 'hidden-gems-rome-walking-tour';
  911  |       const sparse = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  912  |       const harness = await startSsrHarness(slug, () => sparse);
  913  |       try {
  914  |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  915  |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  916  |         expect(served.reviews.count).toBe(0);
  917  | 
  918  |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  919  | 
  920  |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(sparse.title);
> 921  |         await expect(page.locator('#overview')).toBeVisible();
       |                                                 ^ Error: expect(locator).toBeVisible() failed
  922  |         await expect(page.locator('#facts')).toBeVisible();
  923  |         for (const id of ['highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related']) {
  924  |           await expect(page.locator(`#${id}`)).toHaveCount(0);
  925  |         }
  926  |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  927  |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  928  |         expect(hrefs).toEqual(['#overview', '#facts']);
  929  |         // Zero reviews: no scored rating and no review link anywhere.
  930  |         await expect(page.locator('a[href="#reviews"]')).toHaveCount(0);
  931  |         await expect(page.getByText(/out of 5/)).toHaveCount(0);
  932  |         // Short description stays fully readable with no disclosure.
  933  |         await expect(page.getByRole('button', { name: 'Read more' })).toHaveCount(0);
  934  |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/sparse-en-${width}.png`, fullPage: true });
  935  |       } finally {
  936  |         await harness.stop();
  937  |       }
  938  |     });
  939  |   }
  940  | 
  941  |   // Spec 020 US2: one shared booking selection with safe auth continuation.
  942  |   // Controlled SSR fixtures (60 offered dates from the real clock) prove card,
  943  |   // bar, resize, UTC boundary and login-handoff behavior without touching
  944  |   // shared tour data.
  945  |   test('US2 signed-out SSR shares a non-default date and count across card, bar and resize', async ({ page }) => {
  946  |     test.setTimeout(180000);
  947  |     const slug = 'hidden-gems-rome-walking-tour';
  948  |     const complete = createFullTourDetail(new Date());
  949  |     const harness = await startSsrHarness(slug, () => complete);
  950  |     try {
  951  |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  952  |       expect(served.availability.available_dates.length).toBe(60);
  953  |       const picked = served.availability.available_dates[31];
  954  |       const base = served.pricing.base_price.amount;
  955  | 
  956  |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  957  |       await page.setViewportSize({ width: 1280, height: 900 });
  958  |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  959  | 
  960  |       const cardSelect = page.locator('main#main-content').getByLabel('Select a Date');
  961  |       await expect(cardSelect).toBeVisible();
  962  |       await cardSelect.selectOption(picked);
  963  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  964  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  965  | 
  966  |       // One display-only estimate for both surfaces: base x 3. The visible
  967  |       // card slot owns the desktop estimate (the hidden mobile bar carries
  968  |       // the same text); the bar summary is asserted explicitly after resize.
  969  |       const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
  970  |       await expect(cardSlot.getByText(formatCurrency(base * 3, 'EUR', 'en'), { exact: false })).toBeVisible();
  971  |       // Signed out: continuation routes through safe login with the full query.
  972  |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  973  |       const returnUrl = new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  974  |       expect(returnUrl).toBe(`/en/booking?tour=${slug}&participants=3&date=${picked}`);
  975  | 
  976  |       // Narrow viewport: the mobile bar shows the same selection and estimate.
  977  |       await page.setViewportSize({ width: 390, height: 844 });
  978  |       const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
  979  |       await expect(summary).toBeVisible();
  980  |       await expect(summary).toContainText('3');
  981  | 
  982  |       // Back to desktop: the card kept the selection (no remount reset).
  983  |       await page.setViewportSize({ width: 1280, height: 900 });
  984  |       await expect(page.locator('main#main-content').getByTestId('tour-booking-card-slot').getByLabel('Select a Date')).toHaveValue(picked);
  985  |       const loginHrefAfter = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  986  |       expect(new URL(loginHrefAfter!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  987  |         `/en/booking?tour=${slug}&participants=3&date=${picked}`,
  988  |       );
  989  |     } finally {
  990  |       await harness.stop();
  991  |     }
  992  |   });
  993  | 
  994  |   // Spec 020 US2 rework: the mobile dialog edits the same shared selection
  995  |   // (default valid -> non-default index-31 date and count), closes, and the
  996  |   // summary/estimate/query survive resize in both directions with measured
  997  |   // 44px targets and a sticky card that spans the article.
  998  |   test('US2 mobile dialog edits shared selection with measured targets across resize', async ({ page }) => {
  999  |     test.setTimeout(180000);
  1000 |     const slug = 'hidden-gems-rome-walking-tour';
  1001 |     const complete = createFullTourDetail(new Date());
  1002 |     const harness = await startSsrHarness(slug, () => complete);
  1003 |     try {
  1004 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1005 |       const picked = served.availability.available_dates[31];
  1006 |       const base = served.pricing.base_price.amount;
  1007 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  1008 | 
  1009 |       await page.setViewportSize({ width: 390, height: 844 });
  1010 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1011 | 
  1012 |       // The bar is present for the initially valid default selection.
  1013 |       const bar = page.locator('main#main-content').getByTestId('mobile-booking-bar');
  1014 |       await expect(bar).toBeVisible();
  1015 | 
  1016 |       // Edit affordance meets the 44px minimum target while still valid.
  1017 |       const edit = page.getByRole('button', { name: 'Edit booking selections' });
  1018 |       await expect(edit).toBeVisible();
  1019 |       expect((await edit.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1020 | 
  1021 |       await edit.click();
```