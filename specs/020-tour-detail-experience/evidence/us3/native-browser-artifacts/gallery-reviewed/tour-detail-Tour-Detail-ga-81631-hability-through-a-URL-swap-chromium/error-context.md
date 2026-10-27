# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail gallery (Spec 020 US3) >> US3 SSR broken slot keeps stable count, dimensions and reachability through a URL swap
- Location: tests\e2e\tour-detail.spec.ts:1629:7

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  locator('main#main-content').getByTestId('tour-gallery').getByText('This photo is unavailable.')
Expected: 1
Received: 5
Timeout:  5000ms

Call log:
  - Expect "toHaveCount" with timeout 5000ms
  - waiting for locator('main#main-content').getByTestId('tour-gallery').getByText('This photo is unavailable.')
    14 × locator resolved to 5 elements
       - unexpected value "5"

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
            - generic [ref=e174]:
              - generic "Average rating 4.5 out of 5" [ref=e175]: "4.5"
              - generic [ref=e176]:
                - radiogroup "Star rating" [ref=e177]:
                  - radio "1 star" [disabled] [ref=e178]:
                    - img [ref=e179]
                  - radio "2 stars" [disabled] [ref=e181]:
                    - img [ref=e182]
                  - radio "3 stars" [disabled] [ref=e184]:
                    - img [ref=e185]
                  - radio "4 stars" [disabled] [ref=e187]:
                    - img [ref=e188]
                  - radio "5 stars" [checked] [disabled] [ref=e190]:
                    - img [ref=e191]
                - paragraph [ref=e193]: 2 reviews
            - generic [ref=e194]:
              - generic [ref=e195]:
                - generic [ref=e196]:
                  - generic [ref=e197]: Test
                  - generic [ref=e198]: Edited
                - generic [ref=e199]:
                  - radiogroup "Star rating" [ref=e200]:
                    - radio "1 star" [disabled] [ref=e201]:
                      - img [ref=e202]
                    - radio "2 stars" [disabled] [ref=e204]:
                      - img [ref=e205]
                    - radio "3 stars" [disabled] [ref=e207]:
                      - img [ref=e208]
                    - radio "4 stars" [checked] [disabled] [ref=e210]:
                      - img [ref=e211]
                    - radio "5 stars" [disabled] [ref=e213]:
                      - img [ref=e214]
                  - generic [ref=e216]: September 28, 2026
                - paragraph [ref=e217]: Updated comment value here.
              - generic [ref=e218]:
                - generic [ref=e220]: Test
                - generic [ref=e221]:
                  - radiogroup "Star rating" [ref=e222]:
                    - radio "1 star" [disabled] [ref=e223]:
                      - img [ref=e224]
                    - radio "2 stars" [disabled] [ref=e226]:
                      - img [ref=e227]
                    - radio "3 stars" [disabled] [ref=e229]:
                      - img [ref=e230]
                    - radio "4 stars" [disabled] [ref=e232]:
                      - img [ref=e233]
                    - radio "5 stars" [checked] [disabled] [ref=e235]:
                      - img [ref=e236]
                  - generic [ref=e238]: September 12, 2026
                - paragraph [ref=e239]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e240]:
          - generic [ref=e242]:
            - generic [ref=e243]:
              - generic [ref=e244]:
                - text: €45.00
                - generic [ref=e245]: / person
              - button "Save to wishlist" [ref=e246]:
                - generic [ref=e247]: ♡
            - generic [ref=e248]:
              - generic [ref=e249]: Select a Date
              - generic [ref=e250]:
                - paragraph [ref=e251]: Next available
                - paragraph [ref=e252]: 10/17/2026
              - combobox "Select a Date" [ref=e253]:
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
            - generic [ref=e254]:
              - generic [ref=e255]: Participants
              - group "Participants" [ref=e256]:
                - button "Decrease participants" [disabled] [ref=e257]: "-"
                - status [ref=e258]: "1"
                - button "Increase participants" [ref=e259]: +
                - generic [ref=e260]: 1–20 allowed
            - paragraph [ref=e261]: "Estimated total: €45.00"
            - paragraph [ref=e262]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e263]:
              - paragraph [ref=e264]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e265] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e266] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
      - region "More experiences to explore" [ref=e267]:
        - heading "More experiences to explore" [level=2] [ref=e268]
        - generic [ref=e270]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e271] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e273]
            - generic [ref=e274]:
              - generic [ref=e275]:
                - generic [ref=e276]: Rome, Italy
                - generic [ref=e277]: ·
                - generic [ref=e278]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e279]
              - generic [ref=e280]:
                - generic [ref=e281]:
                  - 'img "Rating: 4.8 out of 5" [ref=e282]':
                    - img [ref=e283]
                    - img [ref=e285]
                    - img [ref=e287]
                    - img [ref=e289]
                    - img [ref=e291]
                  - generic [ref=e293]: (4.8)
                - generic [ref=e294]: (11 reviews)
              - generic [ref=e295]:
                - generic [ref=e296]: Walking Tours
                - generic [ref=e297]: €39.00
          - button "Save to wishlist" [ref=e299]:
            - generic [ref=e300]: ♡
  - contentinfo [ref=e301]:
    - generic [ref=e302]:
      - generic [ref=e303]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e306]
        - generic [ref=e307]:
          - paragraph [ref=e308]: Explore
          - list [ref=e309]:
            - listitem [ref=e310]:
              - link "Search tours" [ref=e311] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e312]:
              - link "Categories" [ref=e313] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e314]:
              - link "Destinations" [ref=e315] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e316]:
          - paragraph [ref=e317]: Company
          - list [ref=e318]:
            - listitem [ref=e319]:
              - link "Privacy policy" [ref=e320] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e321]:
              - link "Terms of service" [ref=e322] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e323]:
        - generic [ref=e324]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e326]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e327]:
      - generic [ref=e328]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e329]:
        - button "Reject" [ref=e330]
        - button "Accept" [ref=e331]
  - alert [ref=e332]
```

# Test source

```ts
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
  1574 |       // Escape closes and returns focus to the actual invoking tile.
  1575 |       await page.keyboard.press('Escape');
  1576 |       await expect(dialog).toHaveCount(0);
  1577 |       await expect(tile).toBeFocused();
  1578 |       expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
  1579 |       expect(await page.evaluate(() => (document.querySelector('main#main-content') as HTMLElement | null)?.inert === true)).toBe(false);
  1580 |     } finally {
  1581 |       await harness.stop();
  1582 |     }
  1583 |   });
  1584 | 
  1585 |   test('US3 SSR mobile rail touch drag advances the counter with real touch input', async ({ browser }) => {
  1586 |     test.setTimeout(180000);
  1587 |     const full = createFullTourDetail(new Date());
  1588 |     const harness = await startSsrHarness(slug, () => full);
  1589 |     // Touch-capable owned context with an absolute SSR URL (no baseURL needed).
  1590 |     const touchContext = await browser.newContext({ hasTouch: true, viewport: { width: 390, height: 844 } });
  1591 |     await enableNativeMediaProxy(touchContext);
  1592 |     const touchPage = await touchContext.newPage();
  1593 |     try {
  1594 |       await touchPage.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1595 |       const gallery = touchPage.locator('main#main-content').getByTestId('tour-gallery');
  1596 |       await expect(gallery).toBeVisible();
  1597 |       await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
  1598 |       const counter = gallery.getByText(/^\d+ of \d+$/);
  1599 |       await expect(counter).toHaveText('1 of 5');
  1600 | 
  1601 |       // Actual browser touch input on the rail (not a scripted scrollLeft):
  1602 |       // the gesture starts inside the visible track and every move stays
  1603 |       // inside the physical viewport, advancing exactly one tile.
  1604 |       const track = gallery.getByTestId('tour-gallery-track');
  1605 |       await track.scrollIntoViewIfNeeded();
  1606 |       const box = await track.boundingBox();
  1607 |       expect(box).not.toBeNull();
  1608 |       const startX = box!.x + box!.width * 0.75;
  1609 |       const startY = box!.y + box!.height / 2;
  1610 |       const swipe = box!.width * 0.6;
  1611 |       expect(startX - swipe).toBeGreaterThan(0);
  1612 |       const cdp = await touchContext.newCDPSession(touchPage);
  1613 |       await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: startX, y: startY, id: 1 }] });
  1614 |       const steps = 12;
  1615 |       for (let step = 1; step <= steps; step += 1) {
  1616 |         await cdp.send('Input.dispatchTouchEvent', {
  1617 |           type: 'touchMove',
  1618 |           touchPoints: [{ x: startX - (swipe * step) / steps, y: startY, id: 1 }],
  1619 |         });
  1620 |       }
  1621 |       await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  1622 |       await expect(counter).toHaveText('2 of 5', { timeout: 10000 });
  1623 |     } finally {
  1624 |       await touchContext.close();
  1625 |       await harness.stop();
  1626 |     }
  1627 |   });
  1628 | 
  1629 |   test('US3 SSR broken slot keeps stable count, dimensions and reachability through a URL swap', async ({ page }) => {
  1630 |     test.setTimeout(180000);
  1631 |     await page.setViewportSize({ width: 1440, height: 900 });
  1632 |     const images = [1, 2, 3, 4, 5].map((n) => ({
  1633 |       url: n === 1 ? 'http://cdn.bookly.test/tours/rome-cover.jpg' : `http://cdn.bookly.test/tours/rome-gallery-${n - 1}.jpg`,
  1634 |       is_cover: n === 1,
  1635 |       alt: `US3 photo ${n}`,
  1636 |       alt_locale: 'en' as const,
  1637 |     }));
  1638 |     let current: TourDetail = createFullTourDetail(new Date(), { images });
  1639 |     const harness = await startSsrHarness(slug, () => current);
  1640 |     try {
  1641 |       // Allowed CDN host with a known-missing path: the optimizer fetches it
  1642 |       // through the owned media adapter and the ACTUAL owned HTTP404 is
  1643 |       // observed on the image response (a disallowed host could never prove
  1644 |       // a 404 because the optimizer rejects it before any request).
  1645 |       const missingUrl = `http://cdn.bookly.test/tours/rome-gallery-missing.jpg?us3=${Date.now().toString(36)}`;
  1646 |       current = createFullTourDetail(new Date(), {
  1647 |         images: images.map((image, index) => (index === 2 ? { ...image, url: missingUrl } : image)),
  1648 |       });
  1649 |       const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
  1650 |       const missingResponse = page.waitForResponse(
  1651 |         (response) => response.url().includes('_next/image') && response.url().includes('rome-gallery-missing'),
  1652 |       );
  1653 |       await page.goto(url);
  1654 |       expect((await missingResponse).status()).toBe(404);
  1655 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1656 |       const tiles = gallery.getByRole('button', { name: /View image \d+/ });
  1657 |       await expect(tiles).toHaveCount(5);
  1658 |       // Exactly one honest placeholder; every slot keeps a nonzero box.
  1659 |       // Mosaic contract with ceil-pixel tolerance (not exact unique counts):
  1660 |       // support quarters share dimensions within 1px, and the cover spans
  1661 |       // two quarter columns plus the 8px grid gap within 2px.
> 1662 |       await expect(gallery.getByText('This photo is unavailable.')).toHaveCount(1);
       |                                                                     ^ Error: expect(locator).toHaveCount(expected) failed
  1663 |       const boxes = await tiles.evaluateAll((els) =>
  1664 |         els.map((el) => {
  1665 |           const rect = el.getBoundingClientRect();
  1666 |           return { width: rect.width, height: rect.height };
  1667 |         }),
  1668 |       );
  1669 |       expect(boxes.every((box) => box.width > 0 && box.height > 0)).toBe(true);
  1670 |       const quarter = boxes[1];
  1671 |       for (const index of [2, 3, 4]) {
  1672 |         expect(Math.abs(boxes[index].width - quarter.width)).toBeLessThanOrEqual(1);
  1673 |         expect(Math.abs(boxes[index].height - quarter.height)).toBeLessThanOrEqual(1);
  1674 |       }
  1675 |       expect(Math.abs(boxes[0].width - (2 * quarter.width + 8))).toBeLessThanOrEqual(2);
  1676 |       expect(Math.abs(boxes[0].height - (2 * quarter.height + 8))).toBeLessThanOrEqual(2);
  1677 |       expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
  1678 |         await page.evaluate(() => document.documentElement.clientWidth),
  1679 |       );
  1680 | 
  1681 |       // Every photo stays reachable through the counter, then the broken
  1682 |       // third photo is explicitly current: caption, counter and failure all
  1683 |       // asserted together before the rest of the reachability claim.
  1684 |       await tiles.nth(0).click();
  1685 |       const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
  1686 |       await expect(dialog).toBeVisible();
  1687 |       for (let step = 2; step <= 5; step += 1) {
  1688 |         await dialog.getByRole('button', { name: 'Next image' }).click();
  1689 |         await expect(dialog.getByText(`${step} of 5`)).toBeVisible();
  1690 |       }
  1691 |       await dialog.getByRole('button', { name: 'Previous image' }).click();
  1692 |       await dialog.getByRole('button', { name: 'Previous image' }).click();
  1693 |       await expect(dialog.getByText('3 of 5')).toBeVisible();
  1694 |       await expect(dialog.locator('img')).toHaveCount(0);
  1695 |       await expect(dialog.getByText('US3 photo 3', { exact: true })).toBeVisible();
  1696 |       await expect(dialog.getByText('This photo is unavailable.')).toHaveCount(1);
  1697 |       const reopenBoxes = await tiles.evaluateAll((els) =>
  1698 |         els.map((el) => {
  1699 |           const rect = el.getBoundingClientRect();
  1700 |           return { width: rect.width, height: rect.height };
  1701 |         }),
  1702 |       );
  1703 |       expect(reopenBoxes).toEqual(boxes);
  1704 |       await page.keyboard.press('Escape');
  1705 |       await expect(dialog).toHaveCount(0);
  1706 | 
  1707 |       // New SSR identity swaps the broken URL for an EXISTING owned asset
  1708 |       // (plus query): the replacement must actually decode (naturalWidth>0)
  1709 |       // with no failure placeholder. Same-component rerender recovery is
  1710 |       // covered at unit level; the browser claims the new SSR identity only.
  1711 |       current = createFullTourDetail(new Date(), {
  1712 |         images: images.map((image, index) =>
  1713 |           index === 2 ? { ...image, url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg?us3-recovered' } : image,
  1714 |         ),
  1715 |       });
  1716 |       await page.reload();
  1717 |       const recovered = page.locator('main#main-content').getByTestId('tour-gallery');
  1718 |       await expect(recovered.getByRole('button', { name: /View image \d+/ })).toHaveCount(5);
  1719 |       await expect(recovered.getByText('This photo is unavailable.')).toHaveCount(0);
  1720 |       const recoveredImg = recovered.getByRole('button', { name: 'View image 3' }).locator('img');
  1721 |       await expect
  1722 |         .poll(() => recoveredImg.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0))
  1723 |         .toBe(true);
  1724 |       await page.getByRole('button', { name: 'View image 1' }).click();
  1725 |       const reopened = page.getByRole('dialog', { name: 'Image lightbox' });
  1726 |       await expect(reopened).toBeVisible();
  1727 |       for (let step = 2; step <= 5; step += 1) {
  1728 |         await reopened.getByRole('button', { name: 'Next image' }).click();
  1729 |         await expect(reopened.getByText(`${step} of 5`)).toBeVisible();
  1730 |       }
  1731 |       await expect(reopened.locator('img')).toHaveAttribute('alt', 'US3 photo 5');
  1732 |       await page.keyboard.press('Escape');
  1733 |       await expect(reopened).toHaveCount(0);
  1734 |     } finally {
  1735 |       await harness.stop();
  1736 |     }
  1737 |   });
  1738 | 
  1739 |   test('US3 SSR alt keeps actual language while controls follow the page locale', async ({ page }) => {
  1740 |     test.setTimeout(180000);
  1741 |     await page.setViewportSize({ width: 1440, height: 900 });
  1742 |     const full = createFullTourDetail(new Date());
  1743 |     const harness = await startSsrHarness(slug, () => full);
  1744 |     try {
  1745 |       const served = await readServedDetail(harness.mockPort, slug, 'es');
  1746 |       expect(served.content_locale).toBe('en');
  1747 | 
  1748 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
  1749 |       const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
  1750 |       await expect(gallery).toBeVisible();
  1751 |       // Page-localized controls; authored EN alt declares lang=en.
  1752 |       await expect(gallery.getByRole('button', { name: 'Ver imagen 1' })).toBeVisible();
  1753 |       await expect(page.getByRole('button', { name: 'Ver las 5 fotos' })).toBeVisible();
  1754 |       const tileImg = gallery.getByRole('button', { name: 'Ver imagen 1' }).locator('img');
  1755 |       await expect(tileImg).toHaveAttribute('lang', 'en');
  1756 | 
  1757 |       await gallery.getByRole('button', { name: 'Ver imagen 1' }).click();
  1758 |       const dialog = page.getByRole('dialog', { name: 'Galería ampliada' });
  1759 |       await expect(dialog).toBeVisible();
  1760 |       await expect(dialog.locator('img')).toHaveAttribute('lang', 'en');
  1761 |       await expect(dialog.getByText('1 de 5')).toBeVisible();
  1762 |       const closeLanguage = await page
```