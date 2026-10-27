# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: wishlist.spec.ts >> Wishlist >> failed wishlist toggle rolls back with a localized announcement
- Location: tests\e2e\wishlist.spec.ts:163:7

# Error details

```
Error: expect(locator).toHaveText(expected) failed

Locator: getByRole('alert')
Expected: "Could not update your wishlist. Try again."
Error: strict mode violation: getByRole('alert') resolved to 2 elements:
    1) <p role="alert" class="mt-2 text-sm text-error">Could not update your wishlist. Try again.</p> aka getByText('Could not update your')
    2) <div role="alert" aria-live="assertive" id="__next-route-announcer__"></div> aka locator('[id="__next-route-announcer__"]')

Call log:
  - Expect "toHaveText" with timeout 5000ms
  - waiting for getByRole('alert')
    5 × locator resolved to <div role="alert" aria-live="assertive" id="__next-route-announcer__"></div>
      - unexpected value ""

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
        - link "Wishlist" [ref=e19] [cursor=pointer]:
          - /url: /en/wishlist
          - img [ref=e20]
        - button "Account menu" [ref=e23]:
          - img [ref=e24]
          - generic [ref=e27]: Test User
  - main [ref=e28]:
    - generic [ref=e29]:
      - generic [ref=e30]:
        - navigation "Breadcrumbs" [ref=e31]:
          - list [ref=e32]:
            - listitem [ref=e33]:
              - link "Home" [ref=e34] [cursor=pointer]:
                - /url: /en
            - listitem [ref=e35]:
              - generic [ref=e36]: /
              - link "Walking Tours" [ref=e37] [cursor=pointer]:
                - /url: /en/categories/walking
            - listitem [ref=e38]:
              - generic [ref=e39]: /
              - generic [ref=e40]: Hidden Gems of Rome Walking Tour
        - generic [ref=e41]:
          - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e42]
          - button "Save to wishlist" [ref=e43]:
            - generic [ref=e44]: ♡
          - alert [ref=e45]: Could not update your wishlist. Try again.
        - generic [ref=e47]:
          - img [ref=e48]
          - text: Rome, Italy
        - generic [ref=e51]:
          - 'generic "Rating: 4.5 out of 5" [ref=e52]':
            - img [ref=e53]
            - generic [ref=e55]: "4.5"
            - generic [ref=e56]: "Rating: 4.5 out of 5"
          - link "(2 Reviews)" [ref=e57] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e58]:
        - generic [ref=e59]:
          - button "View image 1" [ref=e60]:
            - generic [ref=e61]:
              - img [ref=e62]
              - generic [ref=e64]: This photo is unavailable.
          - button "View image 2" [ref=e65]:
            - generic [ref=e66]:
              - img [ref=e67]
              - generic [ref=e69]: This photo is unavailable.
          - button "View image 3" [ref=e70]:
            - generic [ref=e71]:
              - img [ref=e72]
              - generic [ref=e74]: This photo is unavailable.
          - button "View image 4" [ref=e75]:
            - generic [ref=e76]:
              - img [ref=e77]
              - generic [ref=e79]: This photo is unavailable.
          - button "View image 5" [ref=e80]:
            - generic [ref=e81]:
              - img [ref=e82]
              - generic [ref=e84]: This photo is unavailable.
        - button "See all 5 photos" [ref=e85]
      - navigation "Tour sections" [ref=e86]:
        - generic [ref=e87]:
          - link "About This Tour" [ref=e88] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e89] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e90] [cursor=pointer]:
            - /url: "#highlights"
          - link "What's Included" [ref=e91] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e92] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e93] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e94] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reviews" [ref=e95] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e96] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e97]:
        - article [ref=e98]:
          - generic [ref=e99]:
            - heading "About This Tour" [level=2] [ref=e100]
            - generic [ref=e101]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e102]:
            - heading "Tour facts" [level=2] [ref=e103]
            - generic [ref=e104]:
              - generic [ref=e105]:
                - term [ref=e106]: Duration
                - definition [ref=e107]: 3 hours
              - generic [ref=e108]:
                - term [ref=e109]: Group size
                - definition [ref=e110]: 1–20 travelers
          - generic [ref=e111]:
            - heading "Highlights" [level=2] [ref=e112]
            - list [ref=e113]:
              - listitem [ref=e114]:
                - img [ref=e115]
                - text: Hidden courtyards
              - listitem [ref=e117]:
                - img [ref=e118]
                - text: Local artisan shops
              - listitem [ref=e120]:
                - img [ref=e121]
                - text: Ancient Roman stories
              - listitem [ref=e123]:
                - img [ref=e124]
                - text: Off the beaten path
          - generic [ref=e126]:
            - heading "What's Included" [level=3] [ref=e130]:
              - button "What's Included" [ref=e131]:
                - text: What's Included
                - img [ref=e132]
            - heading "What's Excluded" [level=3] [ref=e137]:
              - button "What's Excluded" [ref=e138]:
                - text: What's Excluded
                - img [ref=e139]
          - generic [ref=e141]:
            - heading "Meeting Point" [level=2] [ref=e142]
            - paragraph [ref=e143]:
              - img [ref=e144]
              - text: Colosseum main entrance
          - generic [ref=e147]:
            - heading "Cancellation Policy" [level=2] [ref=e148]
            - paragraph [ref=e149]: Free cancellation up to 24 hours before the tour.
          - region "Reviews" [ref=e150]:
            - heading "Reviews" [level=2] [ref=e151]
            - generic [ref=e152]:
              - heading "Reviews" [level=3] [ref=e153]
              - generic [ref=e155]:
                - generic [ref=e156]: "4.5"
                - generic [ref=e157]:
                  - 'img "Rating: 4.5 out of 5" [ref=e158]':
                    - img [ref=e159]
                    - img [ref=e161]
                    - img [ref=e163]
                    - img [ref=e165]
                    - img [ref=e167]
                  - paragraph [ref=e169]: 2 reviews
              - generic [ref=e170]:
                - generic [ref=e171]:
                  - generic [ref=e172]:
                    - generic [ref=e173]: Test
                    - generic [ref=e174]: Edited
                  - generic [ref=e175]:
                    - 'img "Rating: 4 out of 5" [ref=e176]':
                      - img [ref=e177]
                      - img [ref=e179]
                      - img [ref=e181]
                      - img [ref=e183]
                      - img [ref=e185]
                    - generic [ref=e187]: September 28, 2026
                  - paragraph [ref=e188]: Updated comment value here.
                - generic [ref=e189]:
                  - generic [ref=e191]: Test
                  - generic [ref=e192]:
                    - 'img "Rating: 5 out of 5" [ref=e193]':
                      - img [ref=e194]
                      - img [ref=e196]
                      - img [ref=e198]
                      - img [ref=e200]
                      - img [ref=e202]
                    - generic [ref=e204]: September 12, 2026
                  - paragraph [ref=e205]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e206]:
          - generic [ref=e208]:
            - generic [ref=e210]:
              - text: €45.00
              - generic [ref=e211]: / person
            - generic [ref=e212]:
              - generic [ref=e213]: Select a Date
              - generic [ref=e214]:
                - paragraph [ref=e215]: Next available
                - paragraph [ref=e216]: 10/17/2026
              - combobox "Select a Date" [ref=e217]:
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
            - generic [ref=e218]:
              - generic [ref=e219]: Participants
              - group "Participants" [ref=e220]:
                - button "Decrease participants" [disabled] [ref=e221]: "-"
                - status [ref=e222]: "1"
                - button "Increase participants" [ref=e223]: +
                - generic [ref=e224]: 1–20 allowed
            - paragraph [ref=e225]: "Estimated total: €45.00"
            - paragraph [ref=e226]: Free cancellation up to 24 hours before the tour.
            - link "Book Now" [ref=e227] [cursor=pointer]:
              - /url: /en/booking?tour=hidden-gems-rome-walking-tour&participants=1&date=2026-10-17
      - region "More experiences to explore" [ref=e228]:
        - heading "More experiences to explore" [level=2] [ref=e229]
        - generic [ref=e230]:
          - generic [ref=e231]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 17, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e232] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e233]:
                - img "Venice Hidden Canals Walking Tour" [ref=e234]
                - generic [ref=e235]: "Next: Oct 17, 2026"
              - generic [ref=e236]:
                - generic [ref=e237]:
                  - generic [ref=e238]: Venice, Italy
                  - generic [ref=e239]: ·
                  - generic [ref=e240]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e241]
                - generic [ref=e242]:
                  - generic [ref=e243]: Walking Tours
                  - generic [ref=e244]: €55.00
            - button "Save to wishlist" [ref=e246]:
              - generic [ref=e247]: ♡
          - generic [ref=e248]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 17, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e249] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e250]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e251]
                - generic [ref=e252]: "Next: Oct 17, 2026"
              - generic [ref=e253]:
                - generic [ref=e254]:
                  - generic [ref=e255]: Barcelona, Spain
                  - generic [ref=e256]: ·
                  - generic [ref=e257]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e258]
                - generic [ref=e259]:
                  - generic [ref=e260]: Walking Tours
                  - generic [ref=e261]: €65.00
            - button "Save to wishlist" [ref=e263]:
              - generic [ref=e264]: ♡
          - generic [ref=e265]:
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 17, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e266] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e267]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e268]
                - generic [ref=e269]: "Next: Oct 17, 2026"
              - generic [ref=e270]:
                - generic [ref=e271]:
                  - generic [ref=e272]: Paris, France
                  - generic [ref=e273]: ·
                  - generic [ref=e274]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e275]
                - generic [ref=e276]:
                  - generic [ref=e277]: Walking Tours
                  - generic [ref=e278]: €49.00
            - button "Save to wishlist" [ref=e280]:
              - generic [ref=e281]: ♡
  - contentinfo [ref=e282]:
    - generic [ref=e283]:
      - generic [ref=e284]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e287]
        - generic [ref=e288]:
          - paragraph [ref=e289]: Explore
          - list [ref=e290]:
            - listitem [ref=e291]:
              - link "Search tours" [ref=e292] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e293]:
              - link "Categories" [ref=e294] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e295]:
              - link "Destinations" [ref=e296] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e297]:
          - paragraph [ref=e298]: Company
          - list [ref=e299]:
            - listitem [ref=e300]:
              - link "Privacy policy" [ref=e301] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e302]:
              - link "Terms of service" [ref=e303] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e304]:
        - generic [ref=e305]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e307]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e308]
```

# Test source

```ts
  90  |     const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
  91  | 
  92  |     const button = ownedHeaderButton(page, tourId);
  93  |     // Exactly one current-tour control, settled to the snapshotted API state.
  94  |     await expect(button).toHaveCount(1);
  95  |     await expect(button).toHaveAttribute('aria-pressed', String(originalSaved));
  96  | 
  97  |     try {
  98  |       const flipped = String(!originalSaved);
  99  |       const mutation = page.waitForResponse(
  100 |         (response) =>
  101 |           response.url().includes('/api/public/traveler/wishlist') &&
  102 |           ['POST', 'DELETE'].includes(response.request().method()),
  103 |       );
  104 |       await button.click();
  105 |       await expect(button).toHaveAttribute('aria-pressed', flipped);
  106 |       expect((await mutation).ok()).toBe(true);
  107 |       await page.reload();
  108 |       await expect(ownedHeaderButton(page, tourId)).toHaveAttribute('aria-pressed', flipped);
  109 |     } finally {
  110 |       await setOwnedSaved(page.request, headers, tourId, originalSaved);
  111 |       await page.reload();
  112 |       await expect(ownedHeaderButton(page, tourId)).toHaveAttribute(
  113 |         'aria-pressed',
  114 |         String(originalSaved),
  115 |       );
  116 |     }
  117 |   });
  118 | 
  119 |   test('denied wishlist toggle rolls back with a localized announcement', async ({ page, request }) => {
  120 |     await page.goto(`/en/tours/${OWNED_SLUG}`);
  121 |     const headers = await authHeaders(page);
  122 |     const tourId = await ownedTourId(request);
  123 |     const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
  124 | 
  125 |     try {
  126 |       if (originalSaved) {
  127 |         await setOwnedSaved(page.request, headers, tourId, false);
  128 |       }
  129 |       // Establish the real UI status after provisioning before intercepting.
  130 |       await page.reload();
  131 |       const button = ownedHeaderButton(page, tourId);
  132 |       await expect(button).toHaveAttribute('aria-pressed', 'false');
  133 | 
  134 |       // Controlled failure only at the protected mutation boundary; the status
  135 |       // read stays real so the button settles to a known unsaved state.
  136 |       await page.route(
  137 |         '**/api/public/traveler/wishlist**',
  138 |         async (route) => {
  139 |           if (['POST', 'DELETE'].includes(route.request().method())) {
  140 |             await route.fulfill({ status: 403, contentType: 'application/json', body: '{}' });
  141 |           } else {
  142 |             await route.continue();
  143 |           }
  144 |         },
  145 |       );
  146 | 
  147 |       await button.click();
  148 |       // Optimistic flip, then real rollback with the denied announcement and
  149 |       // no false success.
  150 |       await expect(page.getByRole('alert')).toHaveText('You cannot update this wishlist.');
  151 |       await expect(button).toHaveAttribute('aria-pressed', 'false');
  152 |       await expect(button).toBeEnabled();
  153 |       await expect(page.getByRole('status')).toHaveCount(0);
  154 |     } finally {
  155 |       await page.unroute('**/api/public/traveler/wishlist**');
  156 |       const current = await ownedSavedStatus(page.request, headers, tourId);
  157 |       if (current !== originalSaved) {
  158 |         await setOwnedSaved(page.request, headers, tourId, originalSaved);
  159 |       }
  160 |     }
  161 |   });
  162 | 
  163 |   test('failed wishlist toggle rolls back with a localized announcement', async ({ page, request }) => {
  164 |     await page.goto(`/en/tours/${OWNED_SLUG}`);
  165 |     const headers = await authHeaders(page);
  166 |     const tourId = await ownedTourId(request);
  167 |     const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
  168 | 
  169 |     try {
  170 |       if (originalSaved) {
  171 |         await setOwnedSaved(page.request, headers, tourId, false);
  172 |       }
  173 |       // Establish the real UI status after provisioning before intercepting.
  174 |       await page.reload();
  175 |       const button = ownedHeaderButton(page, tourId);
  176 |       await expect(button).toHaveAttribute('aria-pressed', 'false');
  177 | 
  178 |       await page.route(
  179 |         '**/api/public/traveler/wishlist**',
  180 |         async (route) => {
  181 |           if (['POST', 'DELETE'].includes(route.request().method())) {
  182 |             await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' });
  183 |           } else {
  184 |             await route.continue();
  185 |           }
  186 |         },
  187 |       );
  188 | 
  189 |       await button.click();
> 190 |       await expect(page.getByRole('alert')).toHaveText(
      |                                             ^ Error: expect(locator).toHaveText(expected) failed
  191 |         'Could not update your wishlist. Try again.',
  192 |       );
  193 |       await expect(button).toHaveAttribute('aria-pressed', 'false');
  194 |       await expect(button).toBeEnabled();
  195 |       await expect(page.getByRole('status')).toHaveCount(0);
  196 |     } finally {
  197 |       await page.unroute('**/api/public/traveler/wishlist**');
  198 |       const current = await ownedSavedStatus(page.request, headers, tourId);
  199 |       if (current !== originalSaved) {
  200 |         await setOwnedSaved(page.request, headers, tourId, originalSaved);
  201 |       }
  202 |     }
  203 |   });
  204 | 
  205 |   test('removing the owned tour from the wishlist grid restores its original state', async ({
  206 |     page,
  207 |     request,
  208 |   }) => {
  209 |     await page.goto(`/en/tours/${OWNED_SLUG}`);
  210 |     const headers = await authHeaders(page);
  211 |     const tourId = await ownedTourId(request);
  212 |     const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
  213 |     // Provision the owned item so the removal behavior executes unconditionally.
  214 |     try {
  215 |       if (!originalSaved) {
  216 |         await setOwnedSaved(page.request, headers, tourId, true);
  217 |       }
  218 |       await page.goto('/en/wishlist');
  219 |       const ownedCard = page
  220 |         .locator('[data-testid="wishlist-grid"] article', {
  221 |           has: page.locator(`a[href$="/tours/${OWNED_SLUG}"]`),
  222 |         });
  223 |       await expect(ownedCard).toHaveCount(1);
  224 |       const removeButton = ownedCard.getByRole('button', { name: /remove/i });
  225 |       await expect(removeButton).toBeVisible();
  226 |       const removal = page.waitForResponse(
  227 |         (response) =>
  228 |           response.url().includes('/api/public/traveler/wishlist/') &&
  229 |           response.request().method() === 'DELETE',
  230 |       );
  231 |       await removeButton.click();
  232 |       expect((await removal).status()).toBe(204);
  233 |       await expect(ownedCard).toHaveCount(0);
  234 |     } finally {
  235 |       const current = await ownedSavedStatus(page.request, headers, tourId);
  236 |       if (current !== originalSaved) {
  237 |         await setOwnedSaved(page.request, headers, tourId, originalSaved);
  238 |       }
  239 |     }
  240 |   });
  241 | 
  242 |   test('owned wishlist item links to its tour detail', async ({ page, request }) => {
  243 |     await page.goto(`/en/tours/${OWNED_SLUG}`);
  244 |     const headers = await authHeaders(page);
  245 |     const tourId = await ownedTourId(request);
  246 |     const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
  247 |     // Provision the owned item so the link behavior executes unconditionally.
  248 |     try {
  249 |       if (!originalSaved) {
  250 |         await setOwnedSaved(page.request, headers, tourId, true);
  251 |       }
  252 |       await page.goto('/en/wishlist');
  253 |       const ownedLink = page
  254 |         .locator('[data-testid="wishlist-grid"]')
  255 |         .locator(`a[href$="/tours/${OWNED_SLUG}"]`)
  256 |         .first();
  257 |       await expect(ownedLink).toBeVisible();
  258 |       await ownedLink.click();
  259 |       await page.waitForURL(new RegExp(`/en/tours/${OWNED_SLUG}`));
  260 |       await expect(page.locator('h1')).toBeVisible();
  261 |     } finally {
  262 |       const current = await ownedSavedStatus(page.request, headers, tourId);
  263 |       if (current !== originalSaved) {
  264 |         await setOwnedSaved(page.request, headers, tourId, originalSaved);
  265 |       }
  266 |     }
  267 |   });
  268 | });
  269 | 
```