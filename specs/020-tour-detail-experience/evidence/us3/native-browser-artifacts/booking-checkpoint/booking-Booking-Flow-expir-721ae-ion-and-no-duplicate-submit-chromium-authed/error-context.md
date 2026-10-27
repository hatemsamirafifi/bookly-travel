# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: booking.spec.ts >> Booking Flow >> expired session recovers through login with the full selection and no duplicate submit
- Location: tests\e2e\booking.spec.ts:243:7

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: page.waitForResponse: Test timeout of 30000ms exceeded.
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
          - generic [ref=e41]:
            - generic [ref=e42]: Participants
            - generic [ref=e43]:
              - button "Decrease participants" [ref=e44]:
                - img [ref=e45]
              - generic "Participants" [ref=e46]: "2"
              - button "Increase participants" [ref=e47]:
                - img [ref=e48]
              - generic [ref=e50]: 1–20 allowed
            - paragraph [ref=e51]: €45.00 / person
        - generic [ref=e52]:
          - generic [ref=e53]:
            - heading "Price Breakdown" [level=3] [ref=e54]
            - generic [ref=e55]:
              - generic [ref=e56]:
                - generic [ref=e57]: €45.00 × 2 participants
                - generic [ref=e58]: €90.00
              - generic [ref=e59]:
                - generic [ref=e60]: Total
                - generic [ref=e61]: €90.00
          - alert [ref=e62]: This tour date is sold out. Please select a different date.
          - button "Confirm & Pay" [ref=e63]
  - contentinfo [ref=e64]:
    - generic [ref=e65]:
      - generic [ref=e66]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e69]
        - generic [ref=e70]:
          - paragraph [ref=e71]: Explore
          - list [ref=e72]:
            - listitem [ref=e73]:
              - link "Search tours" [ref=e74] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e75]:
              - link "Categories" [ref=e76] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e77]:
              - link "Destinations" [ref=e78] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e79]:
          - paragraph [ref=e80]: Company
          - list [ref=e81]:
            - listitem [ref=e82]:
              - link "Privacy policy" [ref=e83] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e84]:
              - link "Terms of service" [ref=e85] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e86]:
        - generic [ref=e87]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e89]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent"
  - alert [ref=e90]: Book hidden-gems-rome-walking-tour | Bookly
```

# Test source

```ts
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
  226 |     await expect(page.getByRole('link', { name: /Change date/i })).toHaveAttribute(
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
> 284 |       page.waitForResponse(
      |            ^ Error: page.waitForResponse: Test timeout of 30000ms exceeded.
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