# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail section navigation (Spec 020 US4 T053) >> US4 passive scrolling follows reading position without moving focus
- Location: tests/e2e/tour-detail.spec.ts:2074:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "settled"
Received: "heading-obscured"

Call Log:
- Timeout 10000ms exceeded while waiting on the predicate
```

# Page snapshot

```yaml
- generic [ref=e1]:
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
        - generic [ref=e44]:
          - 'generic "Rating: 4.5 out of 5" [ref=e45]':
            - img [ref=e46]
            - generic [ref=e48]: "4.5"
            - generic [ref=e49]: "Rating: 4.5 out of 5"
          - link "(2 Reviews)" [ref=e50] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e51]:
        - generic [ref=e52]:
          - button "View image 1" [ref=e53]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e54]
          - button "View image 2" [ref=e55]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e56]
          - button "View image 3" [ref=e57]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e58]
          - button "View image 4" [ref=e59]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e60]
          - button "View image 5" [ref=e61]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e62]
        - button "See all 5 photos" [ref=e63]
      - navigation "Tour sections" [ref=e64]:
        - generic [ref=e65]:
          - link "About This Tour" [ref=e66] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e67] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e68] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerary" [ref=e69] [cursor=pointer]:
            - /url: "#itinerary"
          - link "What's Included" [ref=e70] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e71] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e72] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e73] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Important information" [ref=e74] [cursor=pointer]:
            - /url: "#important"
          - link "Tour operator" [ref=e75] [cursor=pointer]:
            - /url: "#operator"
          - link "Reviews" [ref=e76] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e77] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e78]:
        - article [ref=e79]:
          - generic [ref=e80]:
            - heading "About This Tour" [active] [level=2] [ref=e81]
            - generic [ref=e82]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e83]:
            - heading "Tour facts" [level=2] [ref=e84]
            - generic [ref=e85]:
              - generic [ref=e86]:
                - term [ref=e87]: Duration
                - definition [ref=e88]: 3 hours
              - generic [ref=e89]:
                - term [ref=e90]: Group size
                - definition [ref=e91]: 1–20 travelers
              - generic [ref=e92]:
                - term [ref=e93]: Difficulty
                - definition [ref=e94]: Moderate
            - paragraph [ref=e95]:
              - generic [ref=e96]: "Live guide:"
              - text: German, English, Spanish
          - generic [ref=e97]:
            - heading "Highlights" [level=2] [ref=e98]
            - list [ref=e99]:
              - listitem [ref=e100]:
                - img [ref=e101]
                - text: Hidden courtyards
              - listitem [ref=e103]:
                - img [ref=e104]
                - text: Local artisan shops
              - listitem [ref=e106]:
                - img [ref=e107]
                - text: Ancient Roman stories
              - listitem [ref=e109]:
                - img [ref=e110]
                - text: Off the beaten path
          - generic [ref=e112]:
            - heading "Itinerary" [level=2] [ref=e113]
            - list [ref=e114]:
              - listitem [ref=e115]:
                - 'heading "Day 1: Hidden courtyards walk" [level=3] [ref=e116]'
                - paragraph [ref=e117]: Walk the secret corners of Rome.
              - listitem [ref=e118]:
                - 'heading "Day 2: Artisan quarter" [level=3] [ref=e119]'
                - paragraph [ref=e120]: Meet local artisans.
          - generic [ref=e121]:
            - heading "What's Included" [level=3] [ref=e125]:
              - button "What's Included" [ref=e126]:
                - text: What's Included
                - img [ref=e127]
            - heading "What's Excluded" [level=3] [ref=e132]:
              - button "What's Excluded" [ref=e133]:
                - text: What's Excluded
                - img [ref=e134]
          - generic [ref=e136]:
            - heading "Meeting Point" [level=2] [ref=e137]
            - paragraph [ref=e138]:
              - img [ref=e139]
              - text: Colosseum main entrance
          - generic [ref=e142]:
            - heading "Cancellation Policy" [level=2] [ref=e143]
            - paragraph [ref=e144]: Free cancellation up to 24 hours before the tour.
          - generic [ref=e145]:
            - heading "Important information" [level=2] [ref=e146]
            - list [ref=e147]:
              - listitem [ref=e148]: Bring your passport
          - region "Tour operator" [ref=e149]:
            - heading "Tour operator" [level=2] [ref=e150]
            - paragraph [ref=e151]: Rome Walks
            - paragraph [ref=e152]:
              - generic [ref=e153]: Verified operator
            - paragraph [ref=e154]: Local guides.
            - generic [ref=e155]:
              - generic [ref=e156]: 3 tours
              - generic [ref=e157]: 8 reviews
          - region "Reviews" [ref=e158]:
            - heading "Reviews" [level=2] [ref=e159]
            - generic [ref=e160]:
              - heading "Reviews" [level=3] [ref=e161]
              - generic [ref=e163]:
                - generic [ref=e164]: "4.5"
                - generic [ref=e165]:
                  - 'img "Rating: 4.5 out of 5" [ref=e166]':
                    - img [ref=e167]
                    - img [ref=e169]
                    - img [ref=e171]
                    - img [ref=e173]
                    - img [ref=e175]
                  - paragraph [ref=e177]: 2 reviews
              - generic [ref=e178]:
                - generic [ref=e179]:
                  - generic [ref=e180]:
                    - generic [ref=e181]: Test
                    - generic [ref=e182]: Edited
                  - generic [ref=e183]:
                    - 'img "Rating: 4 out of 5" [ref=e184]':
                      - img [ref=e185]
                      - img [ref=e187]
                      - img [ref=e189]
                      - img [ref=e191]
                      - img [ref=e193]
                    - generic [ref=e195]: September 27, 2026
                  - paragraph [ref=e196]: Updated comment value here.
                - generic [ref=e197]:
                  - generic [ref=e199]: Test
                  - generic [ref=e200]:
                    - 'img "Rating: 5 out of 5" [ref=e201]':
                      - img [ref=e202]
                      - img [ref=e204]
                      - img [ref=e206]
                      - img [ref=e208]
                      - img [ref=e210]
                    - generic [ref=e212]: September 12, 2026
                  - paragraph [ref=e213]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e214]:
          - generic [ref=e216]:
            - generic [ref=e218]:
              - text: €45.00
              - generic [ref=e219]: / person
            - generic [ref=e220]:
              - generic [ref=e221]: Select a Date
              - generic [ref=e222]:
                - paragraph [ref=e223]: Next available
                - paragraph [ref=e224]: 10/18/2026
              - combobox "Select a Date" [ref=e225]:
                - option "Select a Date"
                - option "10/18/2026" [selected]
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
                - option "12/16/2026"
            - generic [ref=e226]:
              - generic [ref=e227]: Participants
              - group "Participants" [ref=e228]:
                - button "Decrease participants" [disabled] [ref=e229]: "-"
                - status [ref=e230]: "1"
                - button "Increase participants" [ref=e231]: +
                - generic [ref=e232]: 1–20 allowed
            - paragraph [ref=e233]: "Estimated total: €45.00"
            - paragraph [ref=e234]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e235]:
              - paragraph [ref=e236]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e237] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-18
              - link "Create account" [ref=e238] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-18
      - region "More experiences to explore" [ref=e239]:
        - heading "More experiences to explore" [level=2] [ref=e240]
        - generic [ref=e242]:
          - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00" [ref=e243] [cursor=pointer]':
            - /url: /en/tours/roman-forum-small-group
            - img "Roman Forum Small Group" [ref=e245]
            - generic [ref=e246]:
              - generic [ref=e247]:
                - generic [ref=e248]: Rome, Italy
                - generic [ref=e249]: ·
                - generic [ref=e250]: 2 hours
              - heading "Roman Forum Small Group" [level=2] [ref=e251]
              - generic [ref=e252]:
                - generic [ref=e253]:
                  - 'img "Rating: 4.8 out of 5" [ref=e254]':
                    - img [ref=e255]
                    - img [ref=e257]
                    - img [ref=e259]
                    - img [ref=e261]
                    - img [ref=e263]
                  - generic [ref=e265]: (4.8)
                - generic [ref=e266]: (11 reviews)
              - generic [ref=e267]:
                - generic [ref=e268]: Walking Tours
                - generic [ref=e269]: €39.00
          - button "Save to wishlist" [ref=e271]:
            - generic [ref=e272]: ♡
  - contentinfo [ref=e273]:
    - generic [ref=e274]:
      - generic [ref=e275]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e278]
        - generic [ref=e279]:
          - paragraph [ref=e280]: Explore
          - list [ref=e281]:
            - listitem [ref=e282]:
              - link "Search tours" [ref=e283] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e284]:
              - link "Categories" [ref=e285] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e286]:
              - link "Destinations" [ref=e287] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e288]:
          - paragraph [ref=e289]: Company
          - list [ref=e290]:
            - listitem [ref=e291]:
              - link "Privacy policy" [ref=e292] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e293]:
              - link "Terms of service" [ref=e294] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e295]:
        - generic [ref=e296]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e298]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e299]
```

# Test source

```ts
  1876 |       }
  1877 |     } finally {
  1878 |       await harness.stop();
  1879 |     }
  1880 |   });
  1881 | });
  1882 | 
  1883 | // Spec 020 US4 T053: reading-navigation browser acceptance through the real
  1884 | // built SSR server. Controlled complete/sparse fixtures (no shared-data
  1885 | // writes) prove native anchor intent, real heading focus clear of the
  1886 | // measured sticky nav, passive aria tracking, horizontal-only rail reveal,
  1887 | // resize, long-locale labels, reduced motion and deep-link restoration.
  1888 | // No route interception (SSR would ignore it), no mocked observers, no
  1889 | // sleeps: every wait targets real nav geometry, aria state or enabled UI
  1890 | // readiness. Touch/gallery scenarios stay with US3; booking/wishlist/catalog
  1891 | // behavior is untouched.
  1892 | test.describe('Tour Detail section navigation (Spec 020 US4 T053)', () => {
  1893 |   const slug = 'hidden-gems-rome-walking-tour';
  1894 |   const fullIds = [
  1895 |     'overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded',
  1896 |     'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related',
  1897 |   ];
  1898 | 
  1899 |   async function navFor(page: Page, name: string) {
  1900 |     const nav = page.getByRole('navigation', { name });
  1901 |     await expect(nav).toBeVisible();
  1902 |     return nav;
  1903 |   }
  1904 | 
  1905 |   function headingFor(page: Page, id: string) {
  1906 |     // Section-scoped inside the rendered main content: Next 16.2 hidden
  1907 |     // streamed trees can duplicate IDs/testids, so a bare `#id` query may
  1908 |     // resolve the wrong tree. .first() here selects within ONE section,
  1909 |     // mirroring the island's own resolveHeading rule (first h1/h2/h3 in the
  1910 |     // section) — it does not paper over ambiguous live UI. Role queries stay
  1911 |     // visible-by-default and need no change.
  1912 |     return page
  1913 |       .locator('main#main-content')
  1914 |       .locator(`#${id}`)
  1915 |       .locator(':is(h1, h2, h3)')
  1916 |       .first();
  1917 |   }
  1918 | 
  1919 |   // Explicit activation by pointer click (keyboard path covered separately
  1920 |   // below): updates the native hash, focuses the real heading, then — only
  1921 |   // once scrolling finishes — requires the observer to agree with the
  1922 |   // explicit target instead of overriding it with another visible heading.
  1923 |   // Returns the focused heading for geometry checks.
  1924 |   async function activateEntry(page: Page, navName: string, id: string, linkName: string) {
  1925 |     const nav = await navFor(page, navName);
  1926 |     const link = nav.getByRole('link', { name: linkName });
  1927 |     await expect(link).toBeVisible();
  1928 |     await link.click();
  1929 |     await expect(page).toHaveURL(new RegExp(`#${id}$`));
  1930 |     const heading = headingFor(page, id);
  1931 |     await expect(heading).toBeFocused();
  1932 |     await headingClearOfNav(page, navName, id);
  1933 |     await expect(link).toHaveAttribute('aria-current', 'location');
  1934 |     await expect(nav.locator('a[aria-current="location"]')).toHaveCount(1);
  1935 |     return heading;
  1936 |   }
  1937 | 
  1938 |   // Settle contract, not a clearance guess: the nav must actually be stuck
  1939 |   // once the page scrolled, the section top must align with the live nav
  1940 |   // bottom within real rounding — or, at the legitimate end-of-document
  1941 |   // scroll limit (computed from real max scroll), the heading must still be
  1942 |   // fully visible. Categorical result: no vacuous pass; missing boxes,
  1943 |   // an unstuck nav, a misaligned section, or an obscured heading fail.
  1944 |   async function headingClearOfNav(page: Page, navName: string, id: string, timeoutMs = 10000) {
  1945 |     await expect
  1946 |       .poll(async () => {
  1947 |         return page.evaluate(
  1948 |           ({ navLabel, sectionId }) => {
  1949 |             const scope = document.querySelector('main#main-content');
  1950 |             const nav = scope?.querySelector(`nav[aria-label="${navLabel}"]`);
  1951 |             const section = scope?.querySelector(`#${CSS.escape(sectionId)}`);
  1952 |             const heading = section?.querySelector('h1, h2, h3');
  1953 |             if (!(nav instanceof HTMLElement) || !(section instanceof HTMLElement) || !heading) {
  1954 |               return 'missing';
  1955 |             }
  1956 |             const navBox = nav.getBoundingClientRect();
  1957 |             const sectionBox = section.getBoundingClientRect();
  1958 |             const headBox = heading.getBoundingClientRect();
  1959 |             const scrolled = window.scrollY > 1;
  1960 |             const stuck = Math.abs(navBox.y) <= 1;
  1961 |             const aligned = Math.abs(sectionBox.y - (navBox.y + navBox.height)) <= 2;
  1962 |             const maxScroll =
  1963 |               document.documentElement.scrollHeight - document.documentElement.clientHeight;
  1964 |             const atScrollEnd = window.scrollY >= maxScroll - 2;
  1965 |             const headingVisible =
  1966 |               headBox.y >= navBox.y + navBox.height - 1 &&
  1967 |               headBox.y + headBox.height <= document.documentElement.clientHeight + 1;
  1968 |             if (scrolled && !stuck) return 'nav-unstuck';
  1969 |             if (!aligned && !atScrollEnd) return 'section-misaligned';
  1970 |             if (!headingVisible) return 'heading-obscured';
  1971 |             return 'settled';
  1972 |           },
  1973 |           { navLabel: navName, sectionId: id },
  1974 |         );
  1975 |       }, { timeout: timeoutMs })
> 1976 |       .toBe('settled');
       |        ^ Error: expect(received).toBe(expected) // Object.is equality
  1977 |   }
  1978 | 
  1979 |   async function noPageHorizontalOverflow(page: Page) {
  1980 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
  1981 |       await page.evaluate(() => document.documentElement.clientWidth),
  1982 |     );
  1983 |   }
  1984 | 
  1985 |   // Live shared token gap (spacing-inline): read from computed styles, never
  1986 |   // hardcoded, so geometry probes track the actual token.
  1987 |   async function sharedTokenGapPx(page: Page): Promise<number> {
  1988 |     const gap = await page.evaluate(() => {
  1989 |       const root = getComputedStyle(document.documentElement);
  1990 |       const raw = root.getPropertyValue('--spacing-inline').trim();
  1991 |       const rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize);
  1992 |       const value = parseFloat(raw);
  1993 |       return raw.endsWith('rem') ? value * rootPx : value;
  1994 |     });
  1995 |     expect(gap).toBeGreaterThan(0);
  1996 |     return gap;
  1997 |   }
  1998 | 
  1999 |   test('US4 full tour activates first, middle and last entries with focused headings clear of the sticky nav', async ({ page }) => {
  2000 |     test.setTimeout(180000);
  2001 |     await page.setViewportSize({ width: 1280, height: 900 });
  2002 |     const full = createFullTourDetail(new Date());
  2003 |     const harness = await startSsrHarness(slug, () => full);
  2004 |     try {
  2005 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2006 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2007 |       await expect(page.locator('main#main-content')).toBeVisible();
  2008 |       await page.evaluate(() => document.fonts.ready);
  2009 | 
  2010 |       const nav = await navFor(page, 'Tour sections');
  2011 |       const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  2012 |       expect(hrefs).toEqual(fullIds.map((id) => `#${id}`));
  2013 | 
  2014 |       for (const target of [
  2015 |         { id: 'overview', name: 'About This Tour' },
  2016 |         { id: 'itinerary', name: 'Itinerary' },
  2017 |         { id: 'related', name: 'More experiences to explore' },
  2018 |       ]) {
  2019 |         await test.step(`activate ${target.id}`, async () => {
  2020 |           await activateEntry(page, 'Tour sections', target.id, target.name);
  2021 |         });
  2022 |       }
  2023 | 
  2024 |       // One genuine keyboard path (Tab/Enter on a real enabled link proves
  2025 |       // operability; full keyboard coverage stays with T069/a11y and is not
  2026 |       // claimed from pointer clicks).
  2027 |       await test.step('keyboard Tab/Enter activation', async () => {
  2028 |         const navLinks = page.getByRole('navigation', { name: 'Tour sections' });
  2029 |         await navLinks.getByRole('link', { name: 'About This Tour' }).focus();
  2030 |         await page.keyboard.press('Tab');
  2031 |         await expect(navLinks.getByRole('link', { name: 'Tour facts' })).toBeFocused();
  2032 |         await page.keyboard.press('Enter');
  2033 |         await expect(page).toHaveURL(/#facts$/);
  2034 |         await expect(headingFor(page, 'facts')).toBeFocused();
  2035 |         await headingClearOfNav(page, 'Tour sections', 'facts');
  2036 |         await expect(navLinks.getByRole('link', { name: 'Tour facts' })).toHaveAttribute(
  2037 |           'aria-current',
  2038 |           'location',
  2039 |         );
  2040 |       });
  2041 | 
  2042 |       // Stuck while deep in the page: sticky geometry, not a class assertion.
  2043 |       const navBox = await nav.boundingBox();
  2044 |       expect(navBox).not.toBeNull();
  2045 |       expect(navBox!.y).toBeLessThanOrEqual(1);
  2046 |       await noPageHorizontalOverflow(page);
  2047 |     } finally {
  2048 |       await harness.stop();
  2049 |     }
  2050 |   });
  2051 | 
  2052 |   test('US4 sparse tour exposes only present entries and activates them', async ({ page }) => {
  2053 |     test.setTimeout(180000);
  2054 |     await page.setViewportSize({ width: 1280, height: 900 });
  2055 |     const sparse = createSparseTourDetail(new Date());
  2056 |     const harness = await startSsrHarness(slug, () => sparse);
  2057 |     try {
  2058 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  2059 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  2060 |       await expect(page.locator('main#main-content')).toBeVisible();
  2061 | 
  2062 |       const nav = await navFor(page, 'Tour sections');
  2063 |       const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  2064 |       expect(hrefs).toEqual(['#overview', '#facts']);
  2065 |       await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveCount(0);
  2066 | 
  2067 |       await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
  2068 |       await noPageHorizontalOverflow(page);
  2069 |     } finally {
  2070 |       await harness.stop();
  2071 |     }
  2072 |   });
  2073 | 
  2074 |   test('US4 passive scrolling follows reading position without moving focus', async ({ page }) => {
  2075 |     test.setTimeout(180000);
  2076 |     await page.setViewportSize({ width: 1280, height: 900 });
```