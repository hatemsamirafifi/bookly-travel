# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: booking.spec.ts >> Booking Flow >> completes checkout, payment, confirmation, and an idempotent replay
- Location: tests\e2e\booking.spec.ts:103:7

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
  - alert [ref=e90]
```

# Test source

```ts
  24  |         headers: token ? { Authorization: `Bearer ${token}` } : {},
  25  |       },
  26  |     );
  27  |     if (!response.ok()) {
  28  |       const description = `Could not cancel owned booking ${reference}: HTTP ${response.status()}`;
  29  |       test.info().annotations.push({
  30  |         type: 'owned-cleanup-failed',
  31  |         description,
  32  |       });
  33  |       throw new Error(description);
  34  |     }
  35  |   }
  36  | 
  37  |   for (const [status, heading] of [
  38  |     ['pending_payment', 'Payment Pending'],
  39  |     ['cancelled', 'Booking Cancelled'],
  40  |     ['expired', 'Booking Expired'],
  41  |     ['unexpected_status', 'Status'],
  42  |   ]) {
  43  |     test(`${status} confirmation never claims a completed booking`, async ({ page }) => {
  44  |       const reference = `STATE-TEST-${status}`;
  45  |       await page.route(`**/api/public/traveler/bookings/${reference}`, (route) => route.fulfill({
  46  |         status: 200,
  47  |         contentType: 'application/json',
  48  |         body: JSON.stringify({ data: {
  49  |           reference,
  50  |           status,
  51  |           tour: { name: 'Test tour', title: 'Test tour' },
  52  |           tour_date: '2026-11-15',
  53  |           participants: 2,
  54  |           pricing: { total: { amount: 9000, currency: 'EUR', formatted: '€90.00' } },
  55  |           cancellation_policy: 'Cancellation terms apply.',
  56  |         } }),
  57  |       }));
  58  |       await page.goto(`/en/booking/confirmation?ref=${reference}`);
  59  |       await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  60  |       await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toHaveCount(0);
  61  |       await expect(page).not.toHaveTitle(/Booking Confirmed/);
  62  |     });
  63  |   }
  64  | 
  65  |   test('confirmed booking alone displays the success title', async ({ page }) => {
  66  |     await page.route('**/api/public/traveler/bookings/STATE-TEST-CONFIRMED', (route) => route.fulfill({
  67  |       status: 200,
  68  |       contentType: 'application/json',
  69  |       body: JSON.stringify({ data: {
  70  |         reference: 'STATE-TEST-CONFIRMED',
  71  |         status: 'confirmed',
  72  |         tour: { name: 'Test tour' },
  73  |         tour_date: '2026-11-15',
  74  |         participants: 2,
  75  |         pricing: { total: { amount: 9000, currency: 'EUR', formatted: '€90.00' } },
  76  |       } }),
  77  |     }));
  78  |     await page.goto('/en/booking/confirmation?ref=STATE-TEST-CONFIRMED');
  79  |     await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
  80  |     await expect(page.getByText('€90.00')).toBeVisible();
  81  |   });
  82  | 
  83  |   /**
  84  |    * A bookable date far enough out that no other suite touches it. The date
  85  |    * is derived per-run from the backend's own availability window. Owned
  86  |    * bookings are cancelled in `finally`, releasing their held capacity.
  87  |    * Index 30 (~a month out) is used rather than index 0 so TestSprite TC002
  88  |    * (which books the next available date) never competes for the same spots.
  89  |    */
  90  |   async function futureBookableDate(request: APIRequestContext): Promise<string> {
  91  |     const res = await request.get(`/api/public/tours/${TOUR}?locale=en`);
  92  |     if (!res.ok()) {
  93  |       throw new Error(`tour detail API returned ${res.status()}`);
  94  |     }
  95  |     const body = await res.json();
  96  |     const dates: string[] = body?.data?.availability?.available_dates ?? [];
  97  |     if (dates.length === 0) {
  98  |       throw new Error('tour has no available dates');
  99  |     }
  100 |     return dates[Math.min(30, dates.length - 1)];
  101 |   }
  102 | 
  103 |   test('completes checkout, payment, confirmation, and an idempotent replay', async ({ page, request }) => {
  104 |     const availableDate = await futureBookableDate(request);
  105 |     let bookingRequest: { body: string; headers: Record<string, string> } | undefined;
  106 |     let ownedReference: string | null = null;
  107 | 
  108 |     try {
  109 |     page.on('request', (outgoingRequest) => {
  110 |       if (outgoingRequest.method() !== 'POST' || !outgoingRequest.url().endsWith('/api/public/bookings')) {
  111 |         return;
  112 |       }
  113 | 
  114 |       bookingRequest = {
  115 |         body: outgoingRequest.postData() ?? '{}',
  116 |         headers: outgoingRequest.headers(),
  117 |       };
  118 |     });
  119 | 
  120 |     await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
  121 |     await expect(page.getByText('2', { exact: true })).toBeVisible();
  122 | 
  123 |     const [createResponse] = await Promise.all([
> 124 |       page.waitForResponse(
      |            ^ Error: page.waitForResponse: Test timeout of 30000ms exceeded.
  125 |         (resp) =>
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
```