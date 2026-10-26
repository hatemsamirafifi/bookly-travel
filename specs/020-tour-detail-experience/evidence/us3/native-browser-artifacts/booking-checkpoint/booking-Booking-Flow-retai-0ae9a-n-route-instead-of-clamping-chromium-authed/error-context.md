# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: booking.spec.ts >> Booking Flow >> retains a supplied invalid participant value with a reselection route instead of clamping
- Location: tests\e2e\booking.spec.ts:220:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator: getByRole('link', { name: /Change date/i })
Expected: "/en/tours/hidden-gems-rome-walking-tour"
Error: strict mode violation: getByRole('link', { name: /Change date/i }) resolved to 2 elements:
    1) <a href="/en/tours/hidden-gems-rome-walking-tour" class="text-sm font-medium text-[#0A2540] hover:text-[#071b2e] underline">Change date</a> aka getByRole('link', { name: 'Change date' }).first()
    2) <a href="/en/tours/hidden-gems-rome-walking-tour" class="font-medium underline underline-offset-2">Change date</a> aka getByRole('link', { name: 'Change date' }).nth(1)

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for getByRole('link', { name: /Change date/i })

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
      - heading "Complete Your Booking" [level=1] [ref=e30]
      - generic [ref=e31]:
        - generic [ref=e32]:
          - generic [ref=e33]:
            - heading "Hidden Gems of Rome Walking Tour" [level=2] [ref=e34]
            - paragraph [ref=e35]: Rome, Italy
          - generic [ref=e36]:
            - generic [ref=e37]:
              - paragraph [ref=e38]: Selected Date
              - paragraph [ref=e39]: Sunday, November 15, 2026
            - link "Change date" [ref=e40] [cursor=pointer]:
              - /url: /en/tours/hidden-gems-rome-walking-tour
          - alert [ref=e41]:
            - text: Invalid booking details. Please check your selection.
            - link "Change date" [ref=e42] [cursor=pointer]:
              - /url: /en/tours/hidden-gems-rome-walking-tour
        - button "Confirm & Pay" [disabled] [ref=e44]
  - contentinfo [ref=e45]:
    - generic [ref=e46]:
      - generic [ref=e47]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e50]
        - generic [ref=e51]:
          - paragraph [ref=e52]: Explore
          - list [ref=e53]:
            - listitem [ref=e54]:
              - link "Search tours" [ref=e55] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e56]:
              - link "Categories" [ref=e57] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e58]:
              - link "Destinations" [ref=e59] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e60]:
          - paragraph [ref=e61]: Company
          - list [ref=e62]:
            - listitem [ref=e63]:
              - link "Privacy policy" [ref=e64] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e65]:
              - link "Terms of service" [ref=e66] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e67]:
        - generic [ref=e68]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e70]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e71]
```

# Test source

```ts
  126 |           resp.request().method() === 'POST' &&
  127 |           resp.url().endsWith('/api/public/bookings') &&
  128 |           resp.ok(),
  129 |       ),
  130 |       page.getByRole('button', { name: /Confirm & Pay/i }).click(),
  131 |     ]);
  132 |     const createBody = await createResponse.json();
  133 |     ownedReference = createBody?.data?.reference ?? null;
  134 |     expect(ownedReference).toBeTruthy();
  135 |     await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();
  136 | 
  137 |     await page.getByRole('button', { name: 'Complete test payment' }).click();
  138 |     await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=/);
  139 |     await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
  140 |     await expect(page.getByText('confirmed', { exact: true })).toBeVisible();
  141 | 
  142 |     expect(bookingRequest).toBeDefined();
  143 |     const originalReference = new URL(page.url()).searchParams.get('ref');
  144 |     expect(originalReference).toBe(ownedReference);
  145 | 
  146 |     const replayResponse = await request.post('/api/public/bookings', {
  147 |       data: JSON.parse(bookingRequest!.body),
  148 |       headers: {
  149 |         Authorization: bookingRequest!.headers.authorization,
  150 |         'Content-Type': 'application/json',
  151 |         'Idempotency-Key': bookingRequest!.headers['idempotency-key'],
  152 |       },
  153 |     });
  154 |     expect(replayResponse.ok()).toBeTruthy();
  155 |     const replay = await replayResponse.json();
  156 |     expect(replay.data.reference).toBe(originalReference);
  157 | 
  158 |     await page.reload();
  159 |     await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
  160 |     await expect(page.getByText(originalReference!, { exact: true })).toBeVisible();
  161 |     } finally {
  162 |       await cancelOwnedBooking(page, request, ownedReference);
  163 |     }
  164 |   });
  165 | 
  166 |   test('surfaces the sold-out message when the server reports a conflict', async ({ page, request }) => {
  167 |     const availableDate = await futureBookableDate(request);
  168 |     await page.route('**/api/public/bookings', (route) =>
  169 |       route.fulfill({
  170 |         status: 409,
  171 |         contentType: 'application/json',
  172 |         body: JSON.stringify({ message: 'This tour date is sold out.' }),
  173 |       }),
  174 |     );
  175 | 
  176 |     await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
  177 |     await page.getByRole('button', { name: /Confirm & Pay/i }).click();
  178 |     await expect(page.getByText(/sold out/i)).toBeVisible();
  179 |   });
  180 | 
  181 |   test('blocks an unoffered date locally without posting', async ({ page, request }) => {
  182 |     const response = await request.get(`/api/public/tours/${TOUR}?locale=en`);
  183 |     expect(response.ok()).toBeTruthy();
  184 |     const { data } = await response.json();
  185 |     const offered: string[] = data.availability.available_dates;
  186 |     expect(offered.length).toBeGreaterThan(0);
  187 |     const unoffered = new Date(`${[...offered].sort().at(-1)}T00:00:00Z`);
  188 |     unoffered.setUTCDate(unoffered.getUTCDate() + 1);
  189 |     const unofferedDate = unoffered.toISOString().slice(0, 10);
  190 |     let postCount = 0;
  191 |     await page.route('**/api/public/bookings', async (route) => {
  192 |       postCount += 1;
  193 |       await route.continue();
  194 |     });
  195 | 
  196 |     await page.goto(`/en/booking?tour=${TOUR}&date=${unofferedDate}&participants=2`);
  197 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
  198 |     await expect(page.getByText('Select an offered date to continue.')).toBeVisible();
  199 |     expect(postCount).toBe(0);
  200 |   });
  201 | 
  202 |   test('shows the localized rate limit message', async ({ page, request }) => {
  203 |     const availableDate = await futureBookableDate(request);
  204 |     await page.route('**/api/public/bookings', (route) =>
  205 |       route.fulfill({
  206 |         status: 429,
  207 |         contentType: 'application/json',
  208 |         body: JSON.stringify({ message: 'Too Many Attempts.' }),
  209 |       }),
  210 |     );
  211 | 
  212 |     await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
  213 |     await page.getByRole('button', { name: /Confirm & Pay/i }).click();
  214 | 
  215 |     await expect(page.getByRole('alert').filter({ hasText: 'Too many booking attempts.' })).toHaveText(
  216 |       'Too many booking attempts. Please wait a moment and try again.',
  217 |     );
  218 |   });
  219 | 
  220 |   test('retains a supplied invalid participant value with a reselection route instead of clamping', async ({ page, request }) => {
  221 |     const availableDate = await futureBookableDate(request);
  222 |     await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=0`);
  223 | 
  224 |     await expect(page.getByText(/Invalid booking details/)).toBeVisible();
  225 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
> 226 |     await expect(page.getByRole('link', { name: /Change date/i })).toHaveAttribute(
      |                                                                    ^ Error: expect(locator).toHaveAttribute(expected) failed
  227 |       'href',
  228 |       `/en/tours/${TOUR}`,
  229 |     );
  230 |   });
  231 | 
  232 |   test('retains a supplied malformed date with a reselection route and blocks submit', async ({ page }) => {
  233 |     await page.goto(`/en/booking?tour=${TOUR}&date=not-a-date&participants=2`);
  234 | 
  235 |     await expect(page.getByText(/Invalid booking details/)).toBeVisible();
  236 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
  237 |     await expect(page.getByRole('link', { name: /Change date/i })).toHaveAttribute(
  238 |       'href',
  239 |       `/en/tours/${TOUR}`,
  240 |     );
  241 |   });
  242 | 
  243 |   test('expired session recovers through login with the full selection and no duplicate submit', async ({ page, request }) => {
  244 |     const availableDate = await futureBookableDate(request);
  245 |     let postCount = 0;
  246 |     let ownedReference: string | null = null;
  247 |     try {
  248 |     await page.route('**/api/public/bookings', async (route) => {
  249 |       postCount += 1;
  250 |       if (postCount === 1) {
  251 |         await route.fulfill({
  252 |           status: 401,
  253 |           contentType: 'application/json',
  254 |           body: JSON.stringify({ message: 'Unauthenticated.' }),
  255 |         });
  256 |       } else {
  257 |         await route.continue();
  258 |       }
  259 |     });
  260 |     await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
  261 |     await page.getByRole('button', { name: /Confirm & Pay/i }).click();
  262 | 
  263 |     // Bounded recovery: safe login keeps the full selection and notices expiry.
  264 |     await expect(page).toHaveURL(/\/en\/auth\/login\?sessionExpired=1&returnUrl=/);
  265 |     expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(
  266 |       `/en/booking?tour=${TOUR}&participants=2&date=${availableDate}`,
  267 |     );
  268 |     await expect(page.getByText(/session has expired/i)).toBeVisible();
  269 | 
  270 |     // Re-authenticate with the seeded traveler; the full query returns intact.
  271 |     await page.fill('input[name="email"]', 'test@example.com');
  272 |     await page.fill('input[name="password"]', 'Password123!');
  273 |     await page.click('button[type="submit"]');
  274 |     await expect(page).toHaveURL(new RegExp(`/en/booking\\?tour=${TOUR}`));
  275 | 
  276 |     // Selections intact with no automatic submission after recovery.
  277 |     await expect(page.getByText('2', { exact: true })).toBeVisible();
  278 |     expect(postCount).toBe(1);
  279 | 
  280 |     // Explicit resubmit proceeds to the deterministic payment step. The owned
  281 |     // reference is captured synchronously from the successful create response
  282 |     // so a later payment failure still leaves the exact owned reference.
  283 |     const [createResponse] = await Promise.all([
  284 |       page.waitForResponse(
  285 |         (resp) =>
  286 |           resp.request().method() === 'POST' &&
  287 |           resp.url().endsWith('/api/public/bookings') &&
  288 |           resp.ok(),
  289 |       ),
  290 |       page.getByRole('button', { name: /Confirm & Pay/i }).click(),
  291 |     ]);
  292 |     const createBody = await createResponse.json();
  293 |     ownedReference = createBody?.data?.reference ?? null;
  294 |     expect(ownedReference).toBeTruthy();
  295 |     await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();
  296 |     expect(postCount).toBe(2);
  297 | 
  298 |     // Complete the deterministic payment on the same reference before cleanup:
  299 |     // cancellation requires a confirmed booking, not pending_payment.
  300 |     await page.getByRole('button', { name: 'Complete test payment' }).click();
  301 |     await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=/);
  302 |     await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
  303 |     expect(new URL(page.url()).searchParams.get('ref')).toBe(ownedReference);
  304 |     } finally {
  305 |       await cancelOwnedBooking(page, request, ownedReference);
  306 |     }
  307 |   });
  308 | });
  309 | 
```