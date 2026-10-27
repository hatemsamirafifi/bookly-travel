# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail gallery (Spec 020 US3) >> US3 SSR gallery covers 0, 1 and 4 photos with full count, cover-first order and sparse span
- Location: tests\e2e\tour-detail.spec.ts:1394:7

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  getByRole('button', { name: /See all/ })
Expected: 0
Received: 1
Timeout:  5000ms

Call log:
  - Expect "toHaveCount" with timeout 5000ms
  - waiting for getByRole('button', { name: /See all/ })
    13 × locator resolved to 1 element
       - unexpected value "1"

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
            - img "Cover" [ref=e51]
          - button "View image 2" [ref=e52]:
            - img "Second" [ref=e53]
          - button "View image 3" [ref=e54]:
            - img "Third" [ref=e55]
          - button "View image 4" [ref=e56]:
            - img "Fourth" [ref=e57]
        - button "See all 4 photos" [ref=e58]
      - navigation "Tour sections" [ref=e59]:
        - generic [ref=e60]:
          - link "About This Tour" [ref=e61] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e62] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e63] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerary" [ref=e64] [cursor=pointer]:
            - /url: "#itinerary"
          - link "What's Included" [ref=e65] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e66] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e67] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e68] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Important information" [ref=e69] [cursor=pointer]:
            - /url: "#important"
          - link "Tour operator" [ref=e70] [cursor=pointer]:
            - /url: "#operator"
          - link "Reviews" [ref=e71] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e72] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e73]:
        - article [ref=e74]:
          - generic [ref=e75]:
            - heading "About This Tour" [level=2] [ref=e76]
            - generic [ref=e77]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e78]:
            - heading "Tour facts" [level=2] [ref=e79]
            - generic [ref=e80]:
              - generic [ref=e81]:
                - term [ref=e82]: Duration
                - definition [ref=e83]: 3 hours
              - generic [ref=e84]:
                - term [ref=e85]: Group size
                - definition [ref=e86]: 1–20 travelers
              - generic [ref=e87]:
                - term [ref=e88]: Difficulty
                - definition [ref=e89]: Moderate
            - paragraph [ref=e90]:
              - generic [ref=e91]: "Live guide:"
              - text: German, English, Spanish
          - generic [ref=e92]:
            - heading "Highlights" [level=2] [ref=e93]
            - list [ref=e94]:
              - listitem [ref=e95]:
                - img [ref=e96]
                - text: Hidden courtyards
              - listitem [ref=e98]:
                - img [ref=e99]
                - text: Local artisan shops
              - listitem [ref=e101]:
                - img [ref=e102]
                - text: Ancient Roman stories
              - listitem [ref=e104]:
                - img [ref=e105]
                - text: Off the beaten path
          - generic [ref=e107]:
            - heading "Itinerary" [level=2] [ref=e108]
            - list [ref=e109]:
              - listitem [ref=e110]:
                - 'heading "Day 1: Hidden courtyards walk" [level=3] [ref=e111]'
                - paragraph [ref=e112]: Walk the secret corners of Rome.
              - listitem [ref=e113]:
                - 'heading "Day 2: Artisan quarter" [level=3] [ref=e114]'
                - paragraph [ref=e115]: Meet local artisans.
          - generic [ref=e116]:
            - heading "What's Included" [level=3] [ref=e120]:
              - button "What's Included" [ref=e121]:
                - text: What's Included
                - img [ref=e122]
            - heading "What's Excluded" [level=3] [ref=e127]:
              - button "What's Excluded" [ref=e128]:
                - text: What's Excluded
                - img [ref=e129]
          - generic [ref=e131]:
            - heading "Meeting Point" [level=2] [ref=e132]
            - paragraph [ref=e133]:
              - img [ref=e134]
              - text: Colosseum main entrance
          - generic [ref=e137]:
            - heading "Cancellation Policy" [level=2] [ref=e138]
            - paragraph [ref=e139]: Free cancellation up to 24 hours before the tour.
          - generic [ref=e140]:
            - heading "Important information" [level=2] [ref=e141]
            - list [ref=e142]:
              - listitem [ref=e143]: Bring your passport
          - region "Tour operator" [ref=e144]:
            - heading "Tour operator" [level=2] [ref=e145]
            - paragraph [ref=e146]: Rome Walks
            - paragraph [ref=e147]:
              - generic [ref=e148]: Verified operator
            - paragraph [ref=e149]: Local guides.
            - generic [ref=e150]:
              - generic [ref=e151]: 3 tours
              - generic [ref=e152]: 8 reviews
          - generic [ref=e154]:
            - heading "Reviews" [level=3] [ref=e155]
            - generic [ref=e157]:
              - generic "Average rating 4.5 out of 5" [ref=e158]: "4.5"
              - generic [ref=e159]:
                - radiogroup "Star rating" [ref=e160]:
                  - radio "1 star" [disabled] [ref=e161]:
                    - img [ref=e162]
                  - radio "2 stars" [disabled] [ref=e164]:
                    - img [ref=e165]
                  - radio "3 stars" [disabled] [ref=e167]:
                    - img [ref=e168]
                  - radio "4 stars" [disabled] [ref=e170]:
                    - img [ref=e171]
                  - radio "5 stars" [checked] [disabled] [ref=e173]:
                    - img [ref=e174]
                - paragraph [ref=e176]: 2 reviews
            - generic [ref=e177]:
              - generic [ref=e178]:
                - generic [ref=e179]:
                  - generic [ref=e180]: Test
                  - generic [ref=e181]: Edited
                - generic [ref=e182]:
                  - radiogroup "Star rating" [ref=e183]:
                    - radio "1 star" [disabled] [ref=e184]:
                      - img [ref=e185]
                    - radio "2 stars" [disabled] [ref=e187]:
                      - img [ref=e188]
                    - radio "3 stars" [disabled] [ref=e190]:
                      - img [ref=e191]
                    - radio "4 stars" [checked] [disabled] [ref=e193]:
                      - img [ref=e194]
                    - radio "5 stars" [disabled] [ref=e196]:
                      - img [ref=e197]
                  - generic [ref=e199]: September 28, 2026
                - paragraph [ref=e200]: Updated comment value here.
              - generic [ref=e201]:
                - generic [ref=e203]: Test
                - generic [ref=e204]:
                  - radiogroup "Star rating" [ref=e205]:
                    - radio "1 star" [disabled] [ref=e206]:
                      - img [ref=e207]
                    - radio "2 stars" [disabled] [ref=e209]:
                      - img [ref=e210]
                    - radio "3 stars" [disabled] [ref=e212]:
                      - img [ref=e213]
                    - radio "4 stars" [disabled] [ref=e215]:
                      - img [ref=e216]
                    - radio "5 stars" [checked] [disabled] [ref=e218]:
                      - img [ref=e219]
                  - generic [ref=e221]: September 12, 2026
                - paragraph [ref=e222]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e223]:
          - generic [ref=e225]:
            - generic [ref=e226]:
              - generic [ref=e227]:
                - text: €45.00
                - generic [ref=e228]: / person
              - button "Save to wishlist" [ref=e229]:
                - generic [ref=e230]: ♡
            - generic [ref=e231]:
              - generic [ref=e232]: Select a Date
              - generic [ref=e233]:
                - paragraph [ref=e234]: Next available
                - paragraph [ref=e235]: 10/17/2026
              - combobox "Select a Date" [ref=e236]:
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
            - generic [ref=e237]:
              - generic [ref=e238]: Participants
              - group "Participants" [ref=e239]:
                - button "Decrease participants" [disabled] [ref=e240]: "-"
                - status [ref=e241]: "1"
                - button "Increase participants" [ref=e242]: +
                - generic [ref=e243]: 1–20 allowed
            - paragraph [ref=e244]: "Estimated total: €45.00"
            - paragraph [ref=e245]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e246]:
              - paragraph [ref=e247]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e248] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e249] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
      - region "More experiences to explore" [ref=e250]:
        - heading "More experiences to explore" [level=2] [ref=e251]
        - generic [ref=e253]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e254] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e256]
            - generic [ref=e257]:
              - generic [ref=e258]:
                - generic [ref=e259]: Rome, Italy
                - generic [ref=e260]: ·
                - generic [ref=e261]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e262]
              - generic [ref=e263]:
                - generic [ref=e264]:
                  - 'img "Rating: 4.8 out of 5" [ref=e265]':
                    - img [ref=e266]
                    - img [ref=e268]
                    - img [ref=e270]
                    - img [ref=e272]
                    - img [ref=e274]
                  - generic [ref=e276]: (4.8)
                - generic [ref=e277]: (11 reviews)
              - generic [ref=e278]:
                - generic [ref=e279]: Walking Tours
                - generic [ref=e280]: €39.00
          - button "Save to wishlist" [ref=e282]:
            - generic [ref=e283]: ♡
  - contentinfo [ref=e284]:
    - generic [ref=e285]:
      - generic [ref=e286]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e289]
        - generic [ref=e290]:
          - paragraph [ref=e291]: Explore
          - list [ref=e292]:
            - listitem [ref=e293]:
              - link "Search tours" [ref=e294] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e295]:
              - link "Categories" [ref=e296] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e297]:
              - link "Destinations" [ref=e298] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e299]:
          - paragraph [ref=e300]: Company
          - list [ref=e301]:
            - listitem [ref=e302]:
              - link "Privacy policy" [ref=e303] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e304]:
              - link "Terms of service" [ref=e305] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e306]:
        - generic [ref=e307]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e309]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e310]:
      - generic [ref=e311]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e312]:
        - button "Reject" [ref=e313]
        - button "Accept" [ref=e314]
  - alert [ref=e315]
```

# Test source

```ts
  1373 | });
  1374 | 
  1375 | // Spec 020 US3: live canonical gallery through the real built SSR server.
  1376 | // Controlled fixture readings (no shared-data writes) prove count/order,
  1377 | // cover-first, sparse span, SeeAll reachability, viewer viewport/focus/inert
  1378 | // behavior, real touch drag, honest broken slots and alt language. Next16.2
  1379 | // also renders hidden streamed nodes, so visible-main scoping
  1380 | // (main#main-content) is used wherever a testid/text locator could match a
  1381 | // hidden copy; role queries stay default visible-only and the portaled dialog
  1382 | // stays role-scoped. On native hosts the owned media adapter (flag-gated in
  1383 | // the SSR helper) forwards only cdn.bookly.test optimizer sources to the
  1384 | // owned localhost:8080 media server with real statuses/bytes; Docker runs
  1385 | // leave the flag unset against the real CDN. Adapter runs prove gallery
  1386 | // behavior, never the production optimizer.
  1387 | test.describe('Tour Detail gallery (Spec 020 US3)', () => {
  1388 |   const slug = 'hidden-gems-rome-walking-tour';
  1389 | 
  1390 |   test.beforeEach(async ({ page }) => {
  1391 |     await enableNativeMediaProxy(page.context());
  1392 |   });
  1393 | 
  1394 |   test('US3 SSR gallery covers 0, 1 and 4 photos with full count, cover-first order and sparse span', async ({ page }) => {
  1395 |     test.setTimeout(180000);
  1396 |     await page.setViewportSize({ width: 1440, height: 900 });
  1397 |     const empty = createFullTourDetail(new Date(), { images: [] });
  1398 |     let current: TourDetail = empty;
  1399 |     const harness = await startSsrHarness(slug, () => current);
  1400 |     try {
  1401 |       const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
  1402 | 
  1403 |       // Zero photos: honest empty state, no triggers, no viewer.
  1404 |       await page.goto(url);
  1405 |       const main = page.locator('main#main-content');
  1406 |       await expect(main.getByTestId('tour-gallery-empty')).toBeVisible();
  1407 |       await expect(main.getByText('No photos are available for this tour.')).toBeVisible();
  1408 |       await expect(page.getByRole('button', { name: /View image|See all/ })).toHaveCount(0);
  1409 |       await expect(page.getByRole('dialog')).toHaveCount(0);
  1410 | 
  1411 |       // One photo: a single tile, no rail arrows, no See all, viewer 1 of 1.
  1412 |       current = createFullTourDetail(new Date(), {
  1413 |         images: [{ url: 'http://cdn.bookly.test/tours/rome-cover.jpg', is_cover: true, alt: 'Only photo', alt_locale: 'en' }],
  1414 |       });
  1415 |       await page.reload();
  1416 |       const single = page.locator('main#main-content').getByTestId('tour-gallery');
  1417 |       await expect(single).toBeVisible();
  1418 |       await expect(single.getByRole('button', { name: 'View image 1' })).toBeVisible();
  1419 |       await expect(single.getByRole('button', { name: /Previous image|Next image/ })).toHaveCount(0);
  1420 |       await expect(single.getByRole('button', { name: /See all/ })).toHaveCount(0);
  1421 |       await single.getByRole('button', { name: 'View image 1' }).click();
  1422 |       const singleDialog = page.getByRole('dialog', { name: 'Image lightbox' });
  1423 |       await expect(singleDialog).toBeVisible();
  1424 |       await expect(singleDialog.getByText('1 of 1')).toBeVisible();
  1425 |       await expect(singleDialog.getByRole('button', { name: /Previous image|Next image/ })).toHaveCount(0);
  1426 |       await page.keyboard.press('Escape');
  1427 |       await expect(singleDialog).toHaveCount(0);
  1428 | 
  1429 |       // Four photos with the cover supplied last: cover first, caller order
  1430 |       // kept, full real count, last tile spans the remaining row, no padding.
  1431 |       const coverUrl = 'http://cdn.bookly.test/tours/rome-cover.jpg';
  1432 |       const secondUrl = 'http://cdn.bookly.test/tours/rome-gallery-1.jpg';
  1433 |       const thirdUrl = 'http://cdn.bookly.test/tours/rome-gallery-2.jpg';
  1434 |       const fourthUrl = 'http://cdn.bookly.test/tours/rome-gallery-3.jpg';
  1435 |       current = createFullTourDetail(new Date(), {
  1436 |         images: [
  1437 |           { url: secondUrl, is_cover: false, alt: 'Second', alt_locale: 'en' },
  1438 |           { url: thirdUrl, is_cover: false, alt: 'Third', alt_locale: 'en' },
  1439 |           { url: fourthUrl, is_cover: false, alt: 'Fourth', alt_locale: 'en' },
  1440 |           { url: coverUrl, is_cover: true, alt: 'Cover', alt_locale: 'en' },
  1441 |         ],
  1442 |       });
  1443 |       await page.reload();
  1444 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1445 |       expect(served.images.map((image) => image.url)).toEqual([secondUrl, thirdUrl, fourthUrl, coverUrl]);
  1446 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1447 |       const tiles = gallery.getByRole('button', { name: /View image \d+/ });
  1448 |       await expect(tiles).toHaveCount(4);
  1449 |       const srcs = await tiles.getByRole('img').evaluateAll((imgs) =>
  1450 |         imgs.map((img) => img.getAttribute('src') ?? ''),
  1451 |       );
  1452 |       for (const [index, expectedUrl] of [coverUrl, secondUrl, thirdUrl, fourthUrl].entries()) {
  1453 |         expect(srcs[index]).toContain(encodeURIComponent(expectedUrl));
  1454 |       }
  1455 |       // Sparse contract, genuine dimensions with ceil-pixel tolerance (not
  1456 |       // classname-only): the last tile spans the remaining right row, so it
  1457 |       // matches the cover width and its right edge meets the track edge with
  1458 |       // no unused area and no overflow.
  1459 |       await expect(tiles.nth(3)).toHaveClass(/lg:col-span-2/);
  1460 |       const track = gallery.getByTestId('tour-gallery-track');
  1461 |       const sparseBoxes = await tiles.evaluateAll((els) =>
  1462 |         els.map((el) => {
  1463 |           const rect = el.getBoundingClientRect();
  1464 |           return { left: rect.left, right: rect.right, width: rect.width };
  1465 |         }),
  1466 |       );
  1467 |       const trackRect = (await track.boundingBox())!;
  1468 |       expect(Math.abs(sparseBoxes[3].width - sparseBoxes[0].width)).toBeLessThanOrEqual(2);
  1469 |       expect(Math.abs(sparseBoxes[3].right - trackRect.x - trackRect.width)).toBeLessThanOrEqual(2);
  1470 |       expect(await track.evaluate((el) => el.scrollWidth)).toBeLessThanOrEqual(
  1471 |         (await track.boundingBox())!.width + 1,
  1472 |       );
> 1473 |       await expect(page.getByRole('button', { name: /See all/ })).toHaveCount(0);
       |                                                                   ^ Error: expect(locator).toHaveCount(expected) failed
  1474 |     } finally {
  1475 |       await harness.stop();
  1476 |     }
  1477 |   });
  1478 | 
  1479 |   test('US3 SSR seven photos expose See all beyond five with full viewer reachability', async ({ page }) => {
  1480 |     test.setTimeout(180000);
  1481 |     await page.setViewportSize({ width: 1440, height: 900 });
  1482 |     // Slots 6-7 reuse existing owned assets with unique queries (distinct
  1483 |     // identities, authentic bytes) since the fixture CDN holds cover + 1..4.
  1484 |     const images = [
  1485 |       { url: 'http://cdn.bookly.test/tours/rome-cover.jpg', is_cover: true, alt: 'US3 photo 1', alt_locale: 'en' as const },
  1486 |       ...[1, 2, 3, 4].map((n) => ({
  1487 |         url: `http://cdn.bookly.test/tours/rome-gallery-${n}.jpg`,
  1488 |         is_cover: false,
  1489 |         alt: `US3 photo ${n + 1}`,
  1490 |         alt_locale: 'en' as const,
  1491 |       })),
  1492 |       { url: 'http://cdn.bookly.test/tours/rome-gallery-1.jpg?us3-slot6', is_cover: false, alt: 'US3 photo 6', alt_locale: 'en' as const },
  1493 |       { url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg?us3-slot7', is_cover: false, alt: 'US3 photo 7', alt_locale: 'en' as const },
  1494 |     ];
  1495 |     const full = createFullTourDetail(new Date(), { images });
  1496 |     const harness = await startSsrHarness(slug, () => full);
  1497 |     try {
  1498 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1499 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1500 |       await expect(gallery).toBeVisible();
  1501 |       // getByRole is visible-only: 5 on the desktop mosaic. The full DOM
  1502 |       // count (including lg:hidden rail slots) proves all 7 mounted photos,
  1503 |       // and the viewer below proves every one reachable.
  1504 |       await expect(gallery.getByRole('button', { name: /View image \d+/ })).toHaveCount(5);
  1505 |       await expect(gallery.getByTestId('tour-gallery-track').locator('button')).toHaveCount(7);
  1506 | 
  1507 |       const seeAll = page.getByRole('button', { name: 'See all 7 photos' });
  1508 |       await expect(seeAll).toBeVisible();
  1509 |       await seeAll.click();
  1510 |       const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
  1511 |       await expect(dialog).toBeVisible();
  1512 |       await expect(dialog.getByText('1 of 7')).toBeVisible();
  1513 |       for (let step = 2; step <= 7; step += 1) {
  1514 |         await dialog.getByRole('button', { name: 'Next image' }).click();
  1515 |         await expect(dialog.getByText(`${step} of 7`)).toBeVisible();
  1516 |       }
  1517 |       await expect(dialog.locator('img')).toHaveAttribute('alt', 'US3 photo 7');
  1518 |       // Wraparound returns to the first photo.
  1519 |       await dialog.getByRole('button', { name: 'Next image' }).click();
  1520 |       await expect(dialog.getByText('1 of 7')).toBeVisible();
  1521 |       await page.keyboard.press('Escape');
  1522 |       await expect(dialog).toHaveCount(0);
  1523 |       await expect(seeAll).toBeFocused();
  1524 |     } finally {
  1525 |       await harness.stop();
  1526 |     }
  1527 |   });
  1528 | 
  1529 |   test('US3 SSR viewer fills the viewport, navigates from close without moving focus, and restores the tile', async ({ page }) => {
  1530 |     test.setTimeout(180000);
  1531 |     await page.setViewportSize({ width: 1440, height: 900 });
  1532 |     const images = [1, 2, 3, 4, 5].map((n) => ({
  1533 |       url: n === 1 ? 'http://cdn.bookly.test/tours/rome-cover.jpg' : `http://cdn.bookly.test/tours/rome-gallery-${n - 1}.jpg`,
  1534 |       is_cover: n === 1,
  1535 |       alt: `US3 photo ${n}`,
  1536 |       alt_locale: 'en' as const,
  1537 |     }));
  1538 |     const full = createFullTourDetail(new Date(), { images });
  1539 |     const harness = await startSsrHarness(slug, () => full);
  1540 |     try {
  1541 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1542 |       const tile = page.getByRole('button', { name: 'View image 3' });
  1543 |       await expect(tile).toBeVisible();
  1544 |       await tile.focus();
  1545 |       await page.keyboard.press('Enter');
  1546 | 
  1547 |       // Full viewport panel with the tile's own original index.
  1548 |       const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
  1549 |       await expect(dialog).toBeVisible();
  1550 |       const box = await dialog.boundingBox();
  1551 |       expect(box).not.toBeNull();
  1552 |       expect(box!.x).toBe(0);
  1553 |       expect(box!.y).toBe(0);
  1554 |       expect(box!.width).toBe(1440);
  1555 |       expect(box!.height).toBe(900);
  1556 |       await expect(dialog.locator('img')).toHaveAttribute('alt', 'US3 photo 3');
  1557 |       await expect(dialog.getByText('3 of 5')).toBeVisible();
  1558 | 
  1559 |       // One dialog only: no nested locks. Background is locked and inert.
  1560 |       await expect(page.getByRole('dialog')).toHaveCount(1);
  1561 |       expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');
  1562 |       expect(await page.evaluate(() => (document.querySelector('main#main-content') as HTMLElement | null)?.inert === true)).toBe(true);
  1563 | 
  1564 |       // Arrow navigation from the close button keeps focus on close.
  1565 |       const close = page.getByRole('button', { name: 'Close lightbox' });
  1566 |       await expect(close).toBeFocused();
  1567 |       await page.keyboard.press('ArrowRight');
  1568 |       await expect(dialog.getByText('4 of 5')).toBeVisible();
  1569 |       await expect(close).toBeFocused();
  1570 |       await page.keyboard.press('ArrowLeft');
  1571 |       await expect(dialog.getByText('3 of 5')).toBeVisible();
  1572 |       await expect(close).toBeFocused();
  1573 | 
```