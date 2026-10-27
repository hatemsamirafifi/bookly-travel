# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US2 signed-out SSR shares a non-default date and count across card, bar and resize
- Location: tests\e2e\tour-detail.spec.ts:933:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByLabel('Select a Date')
Expected: visible
Error: strict mode violation: getByLabel('Select a Date') resolved to 2 elements:
    1) <select id="_r_4_" class="w-full rounded-md border border-border bg-surface px-2 py-2 text-sm text-primary">…</select> aka locator('[id="_r_4_"]')
    2) <select disabled id="_R_2mav5ubt5tivb_" class="w-full rounded-md border border-border bg-surface px-2 py-2 text-sm text-primary">…</select> aka getByLabel('Select a Date').nth(1)

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByLabel('Select a Date')
    - locator resolved to <select disabled id="_R_2mav5ubt5tivb_" class="w-full rounded-md border border-border bg-surface px-2 py-2 text-sm text-primary">…</select>
    - unexpected value "hidden"

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
            - generic [ref=e51]:
              - img [ref=e52]
              - generic [ref=e54]: This photo is unavailable.
          - button "View image 2" [ref=e55]:
            - generic [ref=e56]:
              - img [ref=e57]
              - generic [ref=e59]: This photo is unavailable.
          - button "View image 3" [ref=e60]:
            - generic [ref=e61]:
              - img [ref=e62]
              - generic [ref=e64]: This photo is unavailable.
          - button "View image 4" [ref=e65]:
            - generic [ref=e66]:
              - img [ref=e67]
              - generic [ref=e69]: This photo is unavailable.
          - button "View image 5" [ref=e70]:
            - generic [ref=e71]:
              - img [ref=e72]
              - generic [ref=e74]: This photo is unavailable.
        - button "See all 5 photos" [ref=e75]
      - navigation "Tour sections" [ref=e76]:
        - generic [ref=e77]:
          - link "About This Tour" [ref=e78] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e79] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e80] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerary" [ref=e81] [cursor=pointer]:
            - /url: "#itinerary"
          - link "What's Included" [ref=e82] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e83] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e84] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e85] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Important information" [ref=e86] [cursor=pointer]:
            - /url: "#important"
          - link "Tour operator" [ref=e87] [cursor=pointer]:
            - /url: "#operator"
          - link "Reviews" [ref=e88] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e89] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e90]:
        - article [ref=e91]:
          - generic [ref=e92]:
            - heading "About This Tour" [level=2] [ref=e93]
            - generic [ref=e94]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e95]:
            - heading "Tour facts" [level=2] [ref=e96]
            - generic [ref=e97]:
              - generic [ref=e98]:
                - term [ref=e99]: Duration
                - definition [ref=e100]: 3 hours
              - generic [ref=e101]:
                - term [ref=e102]: Group size
                - definition [ref=e103]: 1–20 travelers
              - generic [ref=e104]:
                - term [ref=e105]: Difficulty
                - definition [ref=e106]: Moderate
            - paragraph [ref=e107]:
              - generic [ref=e108]: "Live guide:"
              - text: German, English, Spanish
          - generic [ref=e109]:
            - heading "Highlights" [level=2] [ref=e110]
            - list [ref=e111]:
              - listitem [ref=e112]:
                - img [ref=e113]
                - text: Hidden courtyards
              - listitem [ref=e115]:
                - img [ref=e116]
                - text: Local artisan shops
              - listitem [ref=e118]:
                - img [ref=e119]
                - text: Ancient Roman stories
              - listitem [ref=e121]:
                - img [ref=e122]
                - text: Off the beaten path
          - generic [ref=e124]:
            - heading "Itinerary" [level=2] [ref=e125]
            - list [ref=e126]:
              - listitem [ref=e127]:
                - 'heading "Day 1: Hidden courtyards walk" [level=3] [ref=e128]'
                - paragraph [ref=e129]: Walk the secret corners of Rome.
              - listitem [ref=e130]:
                - 'heading "Day 2: Artisan quarter" [level=3] [ref=e131]'
                - paragraph [ref=e132]: Meet local artisans.
          - generic [ref=e133]:
            - heading "What's Included" [level=3] [ref=e137]:
              - button "What's Included" [ref=e138]:
                - text: What's Included
                - img [ref=e139]
            - heading "What's Excluded" [level=3] [ref=e144]:
              - button "What's Excluded" [ref=e145]:
                - text: What's Excluded
                - img [ref=e146]
          - generic [ref=e148]:
            - heading "Meeting Point" [level=2] [ref=e149]
            - paragraph [ref=e150]:
              - img [ref=e151]
              - text: Colosseum main entrance
          - generic [ref=e154]:
            - heading "Cancellation Policy" [level=2] [ref=e155]
            - paragraph [ref=e156]: Free cancellation up to 24 hours before the tour.
          - generic [ref=e157]:
            - heading "Important information" [level=2] [ref=e158]
            - list [ref=e159]:
              - listitem [ref=e160]: Bring your passport
          - region "Tour operator" [ref=e161]:
            - heading "Tour operator" [level=2] [ref=e162]
            - paragraph [ref=e163]: Rome Walks
            - paragraph [ref=e164]:
              - generic [ref=e165]: Verified operator
            - paragraph [ref=e166]: Local guides.
            - generic [ref=e167]:
              - generic [ref=e168]: 3 tours
              - generic [ref=e169]: 8 reviews
          - generic [ref=e171]:
            - heading "Reviews" [level=3] [ref=e172]
            - status [ref=e173]: Loading...
        - complementary [ref=e183]:
          - generic [ref=e185]:
            - generic [ref=e186]:
              - generic [ref=e187]:
                - text: €45.00
                - generic [ref=e188]: / person
              - button "Save to wishlist" [ref=e189]:
                - generic [ref=e190]: ♡
            - generic [ref=e191]:
              - generic [ref=e192]: Select a Date
              - generic [ref=e193]:
                - paragraph [ref=e194]: Next available
                - paragraph [ref=e195]: 10/17/2026
              - combobox "Select a Date" [ref=e196]:
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
            - generic [ref=e197]:
              - generic [ref=e198]: Participants
              - group "Participants" [ref=e199]:
                - button "Decrease participants" [disabled] [ref=e200]: "-"
                - status [ref=e201]: "1"
                - button "Increase participants" [ref=e202]: +
                - generic [ref=e203]: 1–20 allowed
            - paragraph [ref=e204]: "Estimated total: €45.00"
            - paragraph [ref=e205]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e206]:
              - paragraph [ref=e207]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e208] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e209] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
      - region "More experiences to explore" [ref=e210]:
        - heading "More experiences to explore" [level=2] [ref=e211]
        - generic [ref=e213]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e214] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e216]
            - generic [ref=e217]:
              - generic [ref=e218]:
                - generic [ref=e219]: Rome, Italy
                - generic [ref=e220]: ·
                - generic [ref=e221]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e222]
              - generic [ref=e223]:
                - generic [ref=e224]:
                  - 'img "Rating: 4.8 out of 5" [ref=e225]':
                    - img [ref=e226]
                    - img [ref=e228]
                    - img [ref=e230]
                    - img [ref=e232]
                    - img [ref=e234]
                  - generic [ref=e236]: (4.8)
                - generic [ref=e237]: (11 reviews)
              - generic [ref=e238]:
                - generic [ref=e239]: Walking Tours
                - generic [ref=e240]: €39.00
          - button "Save to wishlist" [ref=e242]:
            - generic [ref=e243]: ♡
  - contentinfo [ref=e244]:
    - generic [ref=e245]:
      - generic [ref=e246]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e249]
        - generic [ref=e250]:
          - paragraph [ref=e251]: Explore
          - list [ref=e252]:
            - listitem [ref=e253]:
              - link "Search tours" [ref=e254] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e255]:
              - link "Categories" [ref=e256] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e257]:
              - link "Destinations" [ref=e258] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e259]:
          - paragraph [ref=e260]: Company
          - list [ref=e261]:
            - listitem [ref=e262]:
              - link "Privacy policy" [ref=e263] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e264]:
              - link "Terms of service" [ref=e265] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e266]:
        - generic [ref=e267]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e269]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e270]
```

# Test source

```ts
  849  |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
  850  |         await expect(page.getByText('Rome, Italy').first()).toBeVisible();
  851  |         const reviewLinks = page.locator('a[href="#reviews"]');
  852  |         await expect(reviewLinks).toHaveCount(2);
  853  | 
  854  |         // Prescribed section order in the served document.
  855  |         const ids = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
  856  |         const ordered = await page.evaluate((wanted: string[]) => {
  857  |           const elements = wanted.map((id) => document.getElementById(id));
  858  |           if (elements.some((element) => element === null)) return false;
  859  |           return elements.every((element, index) =>
  860  |             index === 0 || elements[index - 1]!.compareDocumentPosition(element!) === 4,
  861  |           );
  862  |         }, ids);
  863  |         expect(ordered).toBe(true);
  864  | 
  865  |         // Navigation targets exactly the rendered sections.
  866  |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  867  |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  868  |         expect(hrefs).toEqual(ids.map((id) => `#${id}`));
  869  |         for (const href of hrefs) {
  870  |           await expect(page.locator(href!)).toHaveCount(1);
  871  |         }
  872  | 
  873  |         // Long overview: preview first, full text reachable through disclosure.
  874  |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  875  |         const expand = page.getByRole('button', { name: 'Read more' });
  876  |         await expect(expand).toHaveAttribute('aria-expanded', 'false');
  877  |         expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  878  |         await expand.click();
  879  |         await expect(page.getByText('Final reachable sentence.')).toBeVisible();
  880  |         const collapse = page.getByRole('button', { name: 'Read less' });
  881  |         await expect(collapse).toHaveAttribute('aria-expanded', 'true');
  882  |         await collapse.click();
  883  |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  884  | 
  885  |         // Practical information stays fully readable; genuine facts shown.
  886  |         await expect(page.locator('#important')).toContainText('Bring your passport');
  887  |         await expect(page.locator('#facts')).toContainText('Moderate');
  888  |         await expect(page.locator('#itinerary')).toContainText('Artisan quarter');
  889  |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/complete-en-${width}.png`, fullPage: true });
  890  |       } finally {
  891  |         await harness.stop();
  892  |       }
  893  |     });
  894  | 
  895  |     test(`US1 SSR sparse tour at ${width}px omits absent sections, entries and review claims`, async ({ page }) => {
  896  |       test.setTimeout(180000);
  897  |       await page.setViewportSize({ width, height: 900 });
  898  |       const slug = 'hidden-gems-rome-walking-tour';
  899  |       const sparse = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  900  |       const harness = await startSsrHarness(slug, () => sparse);
  901  |       try {
  902  |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  903  |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  904  |         expect(served.reviews.count).toBe(0);
  905  | 
  906  |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  907  | 
  908  |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(sparse.title);
  909  |         await expect(page.locator('#overview')).toBeVisible();
  910  |         await expect(page.locator('#facts')).toBeVisible();
  911  |         for (const id of ['highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related']) {
  912  |           await expect(page.locator(`#${id}`)).toHaveCount(0);
  913  |         }
  914  |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  915  |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  916  |         expect(hrefs).toEqual(['#overview', '#facts']);
  917  |         // Zero reviews: no scored rating and no review link anywhere.
  918  |         await expect(page.locator('a[href="#reviews"]')).toHaveCount(0);
  919  |         await expect(page.getByText(/out of 5/)).toHaveCount(0);
  920  |         // Short description stays fully readable with no disclosure.
  921  |         await expect(page.getByRole('button', { name: 'Read more' })).toHaveCount(0);
  922  |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/sparse-en-${width}.png`, fullPage: true });
  923  |       } finally {
  924  |         await harness.stop();
  925  |       }
  926  |     });
  927  |   }
  928  | 
  929  |   // Spec 020 US2: one shared booking selection with safe auth continuation.
  930  |   // Controlled SSR fixtures (60 offered dates from the real clock) prove card,
  931  |   // bar, resize, UTC boundary and login-handoff behavior without touching
  932  |   // shared tour data.
  933  |   test('US2 signed-out SSR shares a non-default date and count across card, bar and resize', async ({ page }) => {
  934  |     test.setTimeout(180000);
  935  |     const slug = 'hidden-gems-rome-walking-tour';
  936  |     const complete = createFullTourDetail(new Date());
  937  |     const harness = await startSsrHarness(slug, () => complete);
  938  |     try {
  939  |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  940  |       expect(served.availability.available_dates.length).toBe(60);
  941  |       const picked = served.availability.available_dates[31];
  942  |       const base = served.pricing.base_price.amount;
  943  | 
  944  |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  945  |       await page.setViewportSize({ width: 1280, height: 900 });
  946  |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  947  | 
  948  |       const cardSelect = page.getByLabel('Select a Date');
> 949  |       await expect(cardSelect).toBeVisible();
       |                                ^ Error: expect(locator).toBeVisible() failed
  950  |       await cardSelect.selectOption(picked);
  951  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  952  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  953  | 
  954  |       // One display-only estimate for both surfaces: base x 3. The visible
  955  |       // card slot owns the desktop estimate (the hidden mobile bar carries
  956  |       // the same text); the bar summary is asserted explicitly after resize.
  957  |       const cardSlot = page.getByTestId('tour-booking-card-slot');
  958  |       await expect(cardSlot.getByText(formatCurrency(base * 3, 'EUR', 'en'), { exact: false })).toBeVisible();
  959  |       // Signed out: continuation routes through safe login with the full query.
  960  |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  961  |       const returnUrl = new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  962  |       expect(returnUrl).toBe(`/en/booking?tour=${slug}&participants=3&date=${picked}`);
  963  | 
  964  |       // Narrow viewport: the mobile bar shows the same selection and estimate.
  965  |       await page.setViewportSize({ width: 390, height: 844 });
  966  |       const summary = page.getByTestId('mobile-bar-summary');
  967  |       await expect(summary).toBeVisible();
  968  |       await expect(summary).toContainText('3');
  969  | 
  970  |       // Back to desktop: the card kept the selection (no remount reset).
  971  |       await page.setViewportSize({ width: 1280, height: 900 });
  972  |       await expect(page.getByTestId('tour-booking-card-slot').getByLabel('Select a Date')).toHaveValue(picked);
  973  |       const loginHrefAfter = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  974  |       expect(new URL(loginHrefAfter!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  975  |         `/en/booking?tour=${slug}&participants=3&date=${picked}`,
  976  |       );
  977  |     } finally {
  978  |       await harness.stop();
  979  |     }
  980  |   });
  981  | 
  982  |   // Spec 020 US2 rework: the mobile dialog edits the same shared selection
  983  |   // (default valid -> non-default index-31 date and count), closes, and the
  984  |   // summary/estimate/query survive resize in both directions with measured
  985  |   // 44px targets and a sticky card that spans the article.
  986  |   test('US2 mobile dialog edits shared selection with measured targets across resize', async ({ page }) => {
  987  |     test.setTimeout(180000);
  988  |     const slug = 'hidden-gems-rome-walking-tour';
  989  |     const complete = createFullTourDetail(new Date());
  990  |     const harness = await startSsrHarness(slug, () => complete);
  991  |     try {
  992  |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  993  |       const picked = served.availability.available_dates[31];
  994  |       const base = served.pricing.base_price.amount;
  995  |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  996  | 
  997  |       await page.setViewportSize({ width: 390, height: 844 });
  998  |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  999  | 
  1000 |       // The bar is present for the initially valid default selection.
  1001 |       const bar = page.getByTestId('mobile-booking-bar');
  1002 |       await expect(bar).toBeVisible();
  1003 | 
  1004 |       // Edit affordance meets the 44px minimum target while still valid.
  1005 |       const edit = page.getByRole('button', { name: 'Edit booking selections' });
  1006 |       await expect(edit).toBeVisible();
  1007 |       expect((await edit.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1008 | 
  1009 |       await edit.click();
  1010 |       const dialog = page.getByRole('dialog');
  1011 |       await expect(dialog).toBeVisible();
  1012 |       await dialog.getByLabel('Select a Date').selectOption(picked);
  1013 |       await dialog.getByRole('button', { name: 'Increase participants' }).click();
  1014 |       // Dialog stepper targets meet the 44px minimum as well.
  1015 |       const dialogIncrease = dialog.getByRole('button', { name: 'Increase participants' });
  1016 |       expect((await dialogIncrease.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1017 |       await page.keyboard.press('Escape');
  1018 |       await expect(dialog).not.toBeVisible();
  1019 | 
  1020 |       // Same visible formatted summary, estimate and query after close.
  1021 |       const summary = page.getByTestId('mobile-bar-summary');
  1022 |       await expect(summary).toContainText('2');
  1023 |       const barLoginHref = await bar.getByRole('link', { name: 'Continue to booking' }).getAttribute('href');
  1024 |       const barReturn = new URL(barLoginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  1025 |       expect(barReturn).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
  1026 | 
  1027 |       // Desktop: the card kept the same selection, estimate and query.
  1028 |       await page.setViewportSize({ width: 1280, height: 900 });
  1029 |       await expect(page.getByLabel('Select a Date')).toHaveValue(picked);
  1030 |       await expect(page.getByText(formatCurrency(base * 2, 'EUR', 'en'), { exact: false })).toBeVisible();
  1031 |       const cardLoginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1032 |       expect(new URL(cardLoginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  1033 |         `/en/booking?tour=${slug}&participants=2&date=${picked}`,
  1034 |       );
  1035 | 
  1036 |       // Sticky geometry: beside practical content and reviews the card holds
  1037 |       // the shared nav offset and stays bounded by the article column.
  1038 |       await page.locator('#reviews').scrollIntoViewIfNeeded();
  1039 |       const cardBox = await page.getByTestId('tour-booking-card-slot').boundingBox();
  1040 |       const asideBox = await page
  1041 |         .locator('aside', { has: page.getByTestId('tour-booking-card-slot') })
  1042 |         .boundingBox();
  1043 |       expect(cardBox).not.toBeNull();
  1044 |       expect(asideBox).not.toBeNull();
  1045 |       // Measured offset (migrated from the old hardcoded 80px): the card sits
  1046 |       // one shared token gap below the live sticky nav, read from real
  1047 |       // computed styles and card geometry. Tight bound preserved, not loosened.
  1048 |       const stuckNavBox = await page.getByRole('navigation', { name: 'Tour sections' }).boundingBox();
  1049 |       expect(stuckNavBox).not.toBeNull();
```