# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US2 mobile dialog edits shared selection with measured targets across resize
- Location: tests\e2e\tour-detail.spec.ts:994:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('€90.00')
Expected: visible
Error: strict mode violation: getByText('€90.00') resolved to 2 elements:
    1) <span class="font-semibold text-primary">€90.00</span> aka getByTestId('tour-booking-card-slot').getByText('€90.00')
    2) <span class="text-text-muted">11/17/2026 · 2 · €90.00</span> aka getByText('/17/2026 · 2 · €90.00')

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByText('€90.00')

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
            - generic [ref=e159]:
              - generic "Average rating 4.5 out of 5" [ref=e160]: "4.5"
              - generic [ref=e161]:
                - radiogroup "Star rating" [ref=e162]:
                  - radio "1 star" [disabled] [ref=e163]:
                    - img [ref=e164]
                  - radio "2 stars" [disabled] [ref=e166]:
                    - img [ref=e167]
                  - radio "3 stars" [disabled] [ref=e169]:
                    - img [ref=e170]
                  - radio "4 stars" [disabled] [ref=e172]:
                    - img [ref=e173]
                  - radio "5 stars" [checked] [disabled] [ref=e175]:
                    - img [ref=e176]
                - paragraph [ref=e178]: 2 reviews
            - generic [ref=e179]:
              - generic [ref=e180]:
                - generic [ref=e181]:
                  - generic [ref=e182]: Test
                  - generic [ref=e183]: Edited
                - generic [ref=e184]:
                  - radiogroup "Star rating" [ref=e185]:
                    - radio "1 star" [disabled] [ref=e186]:
                      - img [ref=e187]
                    - radio "2 stars" [disabled] [ref=e189]:
                      - img [ref=e190]
                    - radio "3 stars" [disabled] [ref=e192]:
                      - img [ref=e193]
                    - radio "4 stars" [checked] [disabled] [ref=e195]:
                      - img [ref=e196]
                    - radio "5 stars" [disabled] [ref=e198]:
                      - img [ref=e199]
                  - generic [ref=e201]: September 28, 2026
                - paragraph [ref=e202]: Updated comment value here.
              - generic [ref=e203]:
                - generic [ref=e205]: Test
                - generic [ref=e206]:
                  - radiogroup "Star rating" [ref=e207]:
                    - radio "1 star" [disabled] [ref=e208]:
                      - img [ref=e209]
                    - radio "2 stars" [disabled] [ref=e211]:
                      - img [ref=e212]
                    - radio "3 stars" [disabled] [ref=e214]:
                      - img [ref=e215]
                    - radio "4 stars" [disabled] [ref=e217]:
                      - img [ref=e218]
                    - radio "5 stars" [checked] [disabled] [ref=e220]:
                      - img [ref=e221]
                  - generic [ref=e223]: September 12, 2026
                - paragraph [ref=e224]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e225]:
          - generic [ref=e227]:
            - generic [ref=e228]:
              - generic [ref=e229]:
                - text: €45.00
                - generic [ref=e230]: / person
              - button "Save to wishlist" [ref=e231]:
                - generic [ref=e232]: ♡
            - generic [ref=e233]:
              - generic [ref=e234]: Select a Date
              - generic [ref=e235]:
                - paragraph [ref=e236]: Next available
                - paragraph [ref=e237]: 10/17/2026
              - combobox "Select a Date" [ref=e238]:
                - option "Select a Date"
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
                - option "11/17/2026" [selected]
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
            - generic [ref=e239]:
              - generic [ref=e240]: Participants
              - group "Participants" [ref=e241]:
                - button "Decrease participants" [ref=e242]: "-"
                - status [ref=e243]: "2"
                - button "Increase participants" [ref=e244]: +
                - generic [ref=e245]: 1–20 allowed
            - paragraph [ref=e246]: "Estimated total: €90.00"
            - paragraph [ref=e247]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e248]:
              - paragraph [ref=e249]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e250] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-11-17
              - link "Create account" [ref=e251] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-11-17
      - region "More experiences to explore" [ref=e252]:
        - heading "More experiences to explore" [level=2] [ref=e253]
        - generic [ref=e255]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e256] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e258]
            - generic [ref=e259]:
              - generic [ref=e260]:
                - generic [ref=e261]: Rome, Italy
                - generic [ref=e262]: ·
                - generic [ref=e263]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e264]
              - generic [ref=e265]:
                - generic [ref=e266]:
                  - 'img "Rating: 4.8 out of 5" [ref=e267]':
                    - img [ref=e268]
                    - img [ref=e270]
                    - img [ref=e272]
                    - img [ref=e274]
                    - img [ref=e276]
                  - generic [ref=e278]: (4.8)
                - generic [ref=e279]: (11 reviews)
              - generic [ref=e280]:
                - generic [ref=e281]: Walking Tours
                - generic [ref=e282]: €39.00
          - button "Save to wishlist" [ref=e284]:
            - generic [ref=e285]: ♡
  - contentinfo [ref=e286]:
    - generic [ref=e287]:
      - generic [ref=e288]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e291]
        - generic [ref=e292]:
          - paragraph [ref=e293]: Explore
          - list [ref=e294]:
            - listitem [ref=e295]:
              - link "Search tours" [ref=e296] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e297]:
              - link "Categories" [ref=e298] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e299]:
              - link "Destinations" [ref=e300] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e301]:
          - paragraph [ref=e302]: Company
          - list [ref=e303]:
            - listitem [ref=e304]:
              - link "Privacy policy" [ref=e305] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e306]:
              - link "Terms of service" [ref=e307] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e308]:
        - generic [ref=e309]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e311]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e312]
```

# Test source

```ts
  938  |   // Controlled SSR fixtures (60 offered dates from the real clock) prove card,
  939  |   // bar, resize, UTC boundary and login-handoff behavior without touching
  940  |   // shared tour data.
  941  |   test('US2 signed-out SSR shares a non-default date and count across card, bar and resize', async ({ page }) => {
  942  |     test.setTimeout(180000);
  943  |     const slug = 'hidden-gems-rome-walking-tour';
  944  |     const complete = createFullTourDetail(new Date());
  945  |     const harness = await startSsrHarness(slug, () => complete);
  946  |     try {
  947  |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  948  |       expect(served.availability.available_dates.length).toBe(60);
  949  |       const picked = served.availability.available_dates[31];
  950  |       const base = served.pricing.base_price.amount;
  951  | 
  952  |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  953  |       await page.setViewportSize({ width: 1280, height: 900 });
  954  |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  955  | 
  956  |       const cardSelect = page.locator('main#main-content').getByLabel('Select a Date');
  957  |       await expect(cardSelect).toBeVisible();
  958  |       await cardSelect.selectOption(picked);
  959  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  960  |       await page.getByRole('button', { name: 'Increase participants' }).click();
  961  | 
  962  |       // One display-only estimate for both surfaces: base x 3. The visible
  963  |       // card slot owns the desktop estimate (the hidden mobile bar carries
  964  |       // the same text); the bar summary is asserted explicitly after resize.
  965  |       const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
  966  |       await expect(cardSlot.getByText(formatCurrency(base * 3, 'EUR', 'en'), { exact: false })).toBeVisible();
  967  |       // Signed out: continuation routes through safe login with the full query.
  968  |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  969  |       const returnUrl = new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  970  |       expect(returnUrl).toBe(`/en/booking?tour=${slug}&participants=3&date=${picked}`);
  971  | 
  972  |       // Narrow viewport: the mobile bar shows the same selection and estimate.
  973  |       await page.setViewportSize({ width: 390, height: 844 });
  974  |       const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
  975  |       await expect(summary).toBeVisible();
  976  |       await expect(summary).toContainText('3');
  977  | 
  978  |       // Back to desktop: the card kept the selection (no remount reset).
  979  |       await page.setViewportSize({ width: 1280, height: 900 });
  980  |       await expect(page.locator('main#main-content').getByTestId('tour-booking-card-slot').getByLabel('Select a Date')).toHaveValue(picked);
  981  |       const loginHrefAfter = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  982  |       expect(new URL(loginHrefAfter!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  983  |         `/en/booking?tour=${slug}&participants=3&date=${picked}`,
  984  |       );
  985  |     } finally {
  986  |       await harness.stop();
  987  |     }
  988  |   });
  989  | 
  990  |   // Spec 020 US2 rework: the mobile dialog edits the same shared selection
  991  |   // (default valid -> non-default index-31 date and count), closes, and the
  992  |   // summary/estimate/query survive resize in both directions with measured
  993  |   // 44px targets and a sticky card that spans the article.
  994  |   test('US2 mobile dialog edits shared selection with measured targets across resize', async ({ page }) => {
  995  |     test.setTimeout(180000);
  996  |     const slug = 'hidden-gems-rome-walking-tour';
  997  |     const complete = createFullTourDetail(new Date());
  998  |     const harness = await startSsrHarness(slug, () => complete);
  999  |     try {
  1000 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1001 |       const picked = served.availability.available_dates[31];
  1002 |       const base = served.pricing.base_price.amount;
  1003 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  1004 | 
  1005 |       await page.setViewportSize({ width: 390, height: 844 });
  1006 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1007 | 
  1008 |       // The bar is present for the initially valid default selection.
  1009 |       const bar = page.locator('main#main-content').getByTestId('mobile-booking-bar');
  1010 |       await expect(bar).toBeVisible();
  1011 | 
  1012 |       // Edit affordance meets the 44px minimum target while still valid.
  1013 |       const edit = page.getByRole('button', { name: 'Edit booking selections' });
  1014 |       await expect(edit).toBeVisible();
  1015 |       expect((await edit.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1016 | 
  1017 |       await edit.click();
  1018 |       const dialog = page.getByRole('dialog');
  1019 |       await expect(dialog).toBeVisible();
  1020 |       await dialog.getByLabel('Select a Date').selectOption(picked);
  1021 |       await dialog.getByRole('button', { name: 'Increase participants' }).click();
  1022 |       // Dialog stepper targets meet the 44px minimum as well.
  1023 |       const dialogIncrease = dialog.getByRole('button', { name: 'Increase participants' });
  1024 |       expect((await dialogIncrease.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1025 |       await page.keyboard.press('Escape');
  1026 |       await expect(dialog).not.toBeVisible();
  1027 | 
  1028 |       // Same visible formatted summary, estimate and query after close.
  1029 |       const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
  1030 |       await expect(summary).toContainText('2');
  1031 |       const barLoginHref = await bar.getByRole('link', { name: 'Continue to booking' }).getAttribute('href');
  1032 |       const barReturn = new URL(barLoginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  1033 |       expect(barReturn).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
  1034 | 
  1035 |       // Desktop: the card kept the same selection, estimate and query.
  1036 |       await page.setViewportSize({ width: 1280, height: 900 });
  1037 |       await expect(page.locator('main#main-content').getByLabel('Select a Date')).toHaveValue(picked);
> 1038 |       await expect(page.getByText(formatCurrency(base * 2, 'EUR', 'en'), { exact: false })).toBeVisible();
       |                                                                                             ^ Error: expect(locator).toBeVisible() failed
  1039 |       const cardLoginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1040 |       expect(new URL(cardLoginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  1041 |         `/en/booking?tour=${slug}&participants=2&date=${picked}`,
  1042 |       );
  1043 | 
  1044 |       // Sticky geometry: beside practical content and reviews the card holds
  1045 |       // the shared nav offset and stays bounded by the article column.
  1046 |       await page.locator('#reviews').scrollIntoViewIfNeeded();
  1047 |       const cardBox = await page.locator('main#main-content').getByTestId('tour-booking-card-slot').boundingBox();
  1048 |       const asideBox = await page
  1049 |         .locator('main#main-content aside', { has: page.getByTestId('tour-booking-card-slot') })
  1050 |         .boundingBox();
  1051 |       expect(cardBox).not.toBeNull();
  1052 |       expect(asideBox).not.toBeNull();
  1053 |       // Measured offset (migrated from the old hardcoded 80px): the card sits
  1054 |       // one shared token gap below the live sticky nav, read from real
  1055 |       // computed styles and card geometry. Tight bound preserved, not loosened.
  1056 |       const stuckNavBox = await page.getByRole('navigation', { name: 'Tour sections' }).boundingBox();
  1057 |       expect(stuckNavBox).not.toBeNull();
  1058 |       const tokenGapPx = await page.evaluate(() => {
  1059 |         const root = getComputedStyle(document.documentElement);
  1060 |         const raw = root.getPropertyValue('--spacing-inline').trim();
  1061 |         const rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize);
  1062 |         const value = parseFloat(raw);
  1063 |         return raw.endsWith('rem') ? value * rootPx : value;
  1064 |       });
  1065 |       expect(tokenGapPx).toBeGreaterThan(0);
  1066 |       expect(
  1067 |         Math.abs(cardBox!.y - (stuckNavBox!.y + stuckNavBox!.height + tokenGapPx)),
  1068 |       ).toBeLessThanOrEqual(2);
  1069 |       expect(cardBox!.y + cardBox!.height).toBeLessThanOrEqual(asideBox!.y + asideBox!.height + 2);
  1070 | 
  1071 |       // Back to mobile: the bar still shows the identical selection.
  1072 |       await page.setViewportSize({ width: 390, height: 844 });
  1073 |       await expect(page.locator('main#main-content').getByTestId('mobile-bar-summary')).toContainText('2');
  1074 |       const barReturnAfter = new URL(
  1075 |         (await page.locator('main#main-content').getByTestId('mobile-booking-bar').getByRole('link', { name: 'Continue to booking' }).getAttribute('href'))!,
  1076 |         'http://bookly.test',
  1077 |       ).searchParams.get('returnUrl');
  1078 |       expect(barReturnAfter).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
  1079 |     } finally {
  1080 |       await harness.stop();
  1081 |     }
  1082 |   });
  1083 | 
  1084 |   test('US2 auth cross-links preserve the full booking query from login to register and back', async ({ page, request }) => {
  1085 |     test.setTimeout(120000);
  1086 |     const slug = 'hidden-gems-rome-walking-tour';
  1087 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  1088 |     expect(response.ok()).toBeTruthy();
  1089 |     const { data } = await response.json();
  1090 |     const dates: string[] = data?.availability?.available_dates ?? [];
  1091 |     expect(dates.length).toBeGreaterThan(10);
  1092 |     const booking = `/en/booking?tour=${slug}&participants=2&date=${dates[10]}`;
  1093 | 
  1094 |     await page.goto(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
  1095 |     const registerHref = await page.getByRole('link', { name: /create account/i }).getAttribute('href');
  1096 |     expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(booking)}`);
  1097 | 
  1098 |     await page.getByRole('link', { name: /create account/i }).click();
  1099 |     await expect(page).toHaveURL(/\/en\/auth\/register\?returnUrl=/);
  1100 |     expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(booking);
  1101 | 
  1102 |     const loginHref = await page.getByRole('link', { name: /sign in/i }).getAttribute('href');
  1103 |     expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
  1104 | 
  1105 |     await page.getByRole('link', { name: /sign in/i }).click();
  1106 |     await page.fill('input[name="email"]', 'test@example.com');
  1107 |     await page.fill('input[name="password"]', 'Password123!');
  1108 |     await page.click('button[type="submit"]');
  1109 | 
  1110 |     // Seeded login succeeds and returns to the full booking query untouched.
  1111 |     await expect(page).toHaveURL(`/en/booking?tour=${slug}&participants=2&date=${dates[10]}`);
  1112 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  1113 |   });
  1114 | 
  1115 |   test('US2 SSR exposes all 60 offered dates with no slice', async ({ page }) => {    test.setTimeout(180000);
  1116 |     const slug = 'hidden-gems-rome-walking-tour';
  1117 |     const complete = createFullTourDetail(new Date());
  1118 |     const harness = await startSsrHarness(slug, () => complete);
  1119 |     try {
  1120 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1121 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1122 | 
  1123 |       const values = await page.locator('main#main-content').getByLabel('Select a Date').evaluateAll(
  1124 |         (selects) => selects.flatMap((select) =>
  1125 |           Array.from((select as HTMLSelectElement).options).map((option) => option.value),
  1126 |         ),
  1127 |       );
  1128 |       for (const date of served.availability.available_dates) {
  1129 |         expect(values).toContain(date);
  1130 |       }
  1131 |       expect(values).toContain(served.availability.available_dates[31]);
  1132 |     } finally {
  1133 |       await harness.stop();
  1134 |     }
  1135 |   });
  1136 | 
  1137 |   test('US2 signed-out SSR preserves the full query through login and registration links', async ({ page }) => {
  1138 |     test.setTimeout(180000);
```