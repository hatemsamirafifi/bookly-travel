# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US2 auth cross-links preserve the full booking query from login to register and back
- Location: tests\e2e\tour-detail.spec.ts:1088:7

# Error details

```
Error: locator.getAttribute: Error: strict mode violation: getByRole('link', { name: /sign in/i }) resolved to 2 elements:
    1) <a aria-label="Sign In" href="/en/auth/login" class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-full px-2 text-sm font-semibold text-primary hover:bg-surface-alt">…</a> aka getByRole('link', { name: 'Sign In', exact: true })
    2) <a class="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline" href="/en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-10-26">Sign in</a> aka getByRole('link', { name: 'Sign in', exact: true })

Call log:
  - waiting for getByRole('link', { name: /sign in/i })

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - banner [ref=e3]:
      - link "Skip to main content" [ref=e4] [cursor=pointer]:
        - /url: "#main-content"
      - generic [ref=e5]:
        - link "Bookly" [ref=e6] [cursor=pointer]:
          - /url: /en
        - navigation "Main navigation" [ref=e7]:
          - link "Home" [ref=e8] [cursor=pointer]:
            - /url: /en
          - link "Search tours" [ref=e9] [cursor=pointer]:
            - /url: /en/search
          - link "Categories" [ref=e10] [cursor=pointer]:
            - /url: /en/categories
          - link "Destinations" [ref=e11] [cursor=pointer]:
            - /url: /en/destinations
        - generic [ref=e12]:
          - button "Search tours" [ref=e13]:
            - img [ref=e14]
          - combobox "Switch language" [ref=e19]:
            - option "English" [selected]
            - option "Español"
            - option "Italiano"
          - link "Sign In" [ref=e20] [cursor=pointer]:
            - /url: /en/auth/login
            - generic [ref=e21]: Sign In
          - link "Sign Up" [ref=e22] [cursor=pointer]:
            - /url: /en/auth/register
    - main [ref=e24]:
      - generic [ref=e25]:
        - generic [ref=e26]:
          - paragraph [ref=e27]: Bookly
          - heading "Create your account" [level=1] [ref=e28]
          - paragraph [ref=e29]: Join thousands of travelers discovering the world
        - form "Create your account" [ref=e30]:
          - generic [ref=e31]:
            - generic [ref=e32]: Full Name
            - textbox "Full Name" [ref=e33]:
              - /placeholder: John Doe
          - generic [ref=e34]:
            - generic [ref=e35]: Email Address
            - textbox "Email Address" [ref=e36]:
              - /placeholder: you@example.com
          - generic [ref=e37]:
            - generic [ref=e38]: Password
            - generic [ref=e39]:
              - textbox "Password" [ref=e40]:
                - /placeholder: Min. 8 chars with upper, lower & number
              - button "Show password" [ref=e41] [cursor=pointer]:
                - img [ref=e42]
          - button "Create Account" [ref=e45]
          - paragraph [ref=e46]:
            - text: Already have an account?
            - link "Sign in" [ref=e47] [cursor=pointer]:
              - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-10-26
    - contentinfo [ref=e48]:
      - generic [ref=e49]:
        - generic [ref=e50]:
          - img "Bookly Travel — Book smarter. Travel better." [ref=e53]
          - generic [ref=e54]:
            - paragraph [ref=e55]: Explore
            - list [ref=e56]:
              - listitem [ref=e57]:
                - link "Search tours" [ref=e58] [cursor=pointer]:
                  - /url: /en/search
              - listitem [ref=e59]:
                - link "Categories" [ref=e60] [cursor=pointer]:
                  - /url: /en/categories
              - listitem [ref=e61]:
                - link "Destinations" [ref=e62] [cursor=pointer]:
                  - /url: /en/destinations
          - generic [ref=e63]:
            - paragraph [ref=e64]: Company
            - list [ref=e65]:
              - listitem [ref=e66]:
                - link "Privacy policy" [ref=e67] [cursor=pointer]:
                  - /url: /en/privacy
              - listitem [ref=e68]:
                - link "Terms of service" [ref=e69] [cursor=pointer]:
                  - /url: /en/terms
        - generic [ref=e70]:
          - generic [ref=e71]: © 2026 Bookly. All rights reserved.
          - combobox "Switch language" [ref=e73]:
            - option "English" [selected]
            - option "Español"
            - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e74]:
      - generic [ref=e75]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e76]:
        - button "Reject" [ref=e77]
        - button "Accept" [ref=e78]
  - alert [ref=e79]: Create Account | Bookly
```

# Test source

```ts
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
  1022 |       const dialog = page.getByRole('dialog');
  1023 |       await expect(dialog).toBeVisible();
  1024 |       await dialog.getByLabel('Select a Date').selectOption(picked);
  1025 |       await dialog.getByRole('button', { name: 'Increase participants' }).click();
  1026 |       // Dialog stepper targets meet the 44px minimum as well.
  1027 |       const dialogIncrease = dialog.getByRole('button', { name: 'Increase participants' });
  1028 |       expect((await dialogIncrease.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
  1029 |       await page.keyboard.press('Escape');
  1030 |       await expect(dialog).not.toBeVisible();
  1031 | 
  1032 |       // Same visible formatted summary, estimate and query after close.
  1033 |       const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
  1034 |       await expect(summary).toContainText('2');
  1035 |       const barLoginHref = await bar.getByRole('link', { name: 'Continue to booking' }).getAttribute('href');
  1036 |       const barReturn = new URL(barLoginHref!, 'http://bookly.test').searchParams.get('returnUrl');
  1037 |       expect(barReturn).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
  1038 | 
  1039 |       // Desktop: the card kept the same selection, estimate and query.
  1040 |       await page.setViewportSize({ width: 1280, height: 900 });
  1041 |       await expect(page.locator('main#main-content').getByLabel('Select a Date')).toHaveValue(picked);
  1042 |       await expect(page.locator('main#main-content').getByTestId('tour-booking-card-slot').getByText(formatCurrency(base * 2, 'EUR', 'en'), { exact: true })).toBeVisible();
  1043 |       const cardLoginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1044 |       expect(new URL(cardLoginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
  1045 |         `/en/booking?tour=${slug}&participants=2&date=${picked}`,
  1046 |       );
  1047 | 
  1048 |       // Sticky geometry: beside practical content and reviews the card holds
  1049 |       // the shared nav offset and stays bounded by the article column.
  1050 |       await page.locator('#reviews').scrollIntoViewIfNeeded();
  1051 |       const cardBox = await page.locator('main#main-content').getByTestId('tour-booking-card-slot').boundingBox();
  1052 |       const asideBox = await page
  1053 |         .locator('main#main-content aside', { has: page.getByTestId('tour-booking-card-slot') })
  1054 |         .boundingBox();
  1055 |       expect(cardBox).not.toBeNull();
  1056 |       expect(asideBox).not.toBeNull();
  1057 |       // Measured offset (migrated from the old hardcoded 80px): the card sits
  1058 |       // one shared token gap below the live sticky nav, read from real
  1059 |       // computed styles and card geometry. Tight bound preserved, not loosened.
  1060 |       const stuckNavBox = await page.getByRole('navigation', { name: 'Tour sections' }).boundingBox();
  1061 |       expect(stuckNavBox).not.toBeNull();
  1062 |       const tokenGapPx = await page.evaluate(() => {
  1063 |         const root = getComputedStyle(document.documentElement);
  1064 |         const raw = root.getPropertyValue('--spacing-inline').trim();
  1065 |         const rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize);
  1066 |         const value = parseFloat(raw);
  1067 |         return raw.endsWith('rem') ? value * rootPx : value;
  1068 |       });
  1069 |       expect(tokenGapPx).toBeGreaterThan(0);
  1070 |       expect(
  1071 |         Math.abs(cardBox!.y - (stuckNavBox!.y + stuckNavBox!.height + tokenGapPx)),
  1072 |       ).toBeLessThanOrEqual(2);
  1073 |       expect(cardBox!.y + cardBox!.height).toBeLessThanOrEqual(asideBox!.y + asideBox!.height + 2);
  1074 | 
  1075 |       // Back to mobile: the bar still shows the identical selection.
  1076 |       await page.setViewportSize({ width: 390, height: 844 });
  1077 |       await expect(page.locator('main#main-content').getByTestId('mobile-bar-summary')).toContainText('2');
  1078 |       const barReturnAfter = new URL(
  1079 |         (await page.locator('main#main-content').getByTestId('mobile-booking-bar').getByRole('link', { name: 'Continue to booking' }).getAttribute('href'))!,
  1080 |         'http://bookly.test',
  1081 |       ).searchParams.get('returnUrl');
  1082 |       expect(barReturnAfter).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
  1083 |     } finally {
  1084 |       await harness.stop();
  1085 |     }
  1086 |   });
  1087 | 
  1088 |   test('US2 auth cross-links preserve the full booking query from login to register and back', async ({ page, request }) => {
  1089 |     test.setTimeout(120000);
  1090 |     const slug = 'hidden-gems-rome-walking-tour';
  1091 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  1092 |     expect(response.ok()).toBeTruthy();
  1093 |     const { data } = await response.json();
  1094 |     const dates: string[] = data?.availability?.available_dates ?? [];
  1095 |     expect(dates.length).toBeGreaterThan(10);
  1096 |     const booking = `/en/booking?tour=${slug}&participants=2&date=${dates[10]}`;
  1097 | 
  1098 |     await page.goto(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
  1099 |     const registerHref = await page.getByRole('link', { name: /create account/i }).getAttribute('href');
  1100 |     expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(booking)}`);
  1101 | 
  1102 |     await page.getByRole('link', { name: /create account/i }).click();
  1103 |     await expect(page).toHaveURL(/\/en\/auth\/register\?returnUrl=/);
  1104 |     expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(booking);
  1105 | 
> 1106 |     const loginHref = await page.getByRole('link', { name: /sign in/i }).getAttribute('href');
       |                                                                          ^ Error: locator.getAttribute: Error: strict mode violation: getByRole('link', { name: /sign in/i }) resolved to 2 elements:
  1107 |     expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
  1108 | 
  1109 |     await page.getByRole('link', { name: /sign in/i }).click();
  1110 |     await page.fill('input[name="email"]', 'test@example.com');
  1111 |     await page.fill('input[name="password"]', 'Password123!');
  1112 |     await page.click('button[type="submit"]');
  1113 | 
  1114 |     // Seeded login succeeds and returns to the full booking query untouched.
  1115 |     await expect(page).toHaveURL(`/en/booking?tour=${slug}&participants=2&date=${dates[10]}`);
  1116 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  1117 |   });
  1118 | 
  1119 |   test('US2 SSR exposes all 60 offered dates with no slice', async ({ page }) => {    test.setTimeout(180000);
  1120 |     const slug = 'hidden-gems-rome-walking-tour';
  1121 |     const complete = createFullTourDetail(new Date());
  1122 |     const harness = await startSsrHarness(slug, () => complete);
  1123 |     try {
  1124 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1125 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1126 | 
  1127 |       const values = await page.locator('main#main-content').getByLabel('Select a Date').evaluateAll(
  1128 |         (selects) => selects.flatMap((select) =>
  1129 |           Array.from((select as HTMLSelectElement).options).map((option) => option.value),
  1130 |         ),
  1131 |       );
  1132 |       for (const date of served.availability.available_dates) {
  1133 |         expect(values).toContain(date);
  1134 |       }
  1135 |       expect(values).toContain(served.availability.available_dates[31]);
  1136 |     } finally {
  1137 |       await harness.stop();
  1138 |     }
  1139 |   });
  1140 | 
  1141 |   test('US2 signed-out SSR preserves the full query through login and registration links', async ({ page }) => {
  1142 |     test.setTimeout(180000);
  1143 |     const slug = 'hidden-gems-rome-walking-tour';
  1144 |     const complete = createFullTourDetail(new Date());
  1145 |     const harness = await startSsrHarness(slug, () => complete);
  1146 |     try {
  1147 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1148 |       const picked = served.availability.available_dates[4];
  1149 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  1150 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1151 | 
  1152 |       await page.locator('main#main-content').getByLabel('Select a Date').selectOption(picked);
  1153 |       const expected = `/en/booking?tour=${slug}&participants=1&date=${picked}`;
  1154 |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1155 |       expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(expected)}`);
  1156 |       const registerHref = await page.getByRole('link', { name: /crear cuenta|create account|crea account/i }).first().getAttribute('href');
  1157 |       expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(expected)}`);
  1158 | 
  1159 |       // Following the login link keeps the full returnUrl for post-login return.
  1160 |       await page.getByRole('link', { name: 'Book Now' }).first().click();
  1161 |       await expect(page).toHaveURL(/\/en\/auth\/login\?returnUrl=/);
  1162 |       expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(expected);
  1163 |     } finally {
  1164 |       await harness.stop();
  1165 |     }
  1166 |   });
  1167 | 
  1168 |   test('US2 SSR UTC boundary never books today and accepts an explicit future date', async ({ page }) => {
  1169 |     test.setTimeout(180000);
  1170 |     const slug = 'hidden-gems-rome-walking-tour';
  1171 |     const runtimeToday = new Date().toISOString().slice(0, 10);
  1172 |     const complete = createFullTourDetail(new Date());
  1173 |     complete.availability = {
  1174 |       available_dates: [runtimeToday, ...complete.availability.available_dates],
  1175 |       next_available_date: runtimeToday,
  1176 |       is_unavailable: false,
  1177 |     };
  1178 |     const harness = await startSsrHarness(slug, () => complete);
  1179 |     try {
  1180 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1181 |       const future = served.availability.available_dates.find((date) => date > runtimeToday)!;
  1182 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1183 | 
  1184 |       // Today is listed but strictly rejected: no automatic choice, no link.
  1185 |       // The desktop card slot owns the live select (a hidden streamed copy
  1186 |       // exists); wait for the enabled control before asserting.
  1187 |       const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
  1188 |       const dateSelect = cardSlot.getByLabel('Select a Date');
  1189 |       await expect(dateSelect).toBeEnabled();
  1190 |       await expect(dateSelect).toHaveValue('');
  1191 |       await expect(cardSlot.getByText('Select an offered date to continue.')).toBeVisible();
  1192 |       await expect(page.getByRole('link', { name: 'Book Now' })).toHaveCount(0);
  1193 | 
  1194 |       // An explicit offered future date recovers continuation.
  1195 |       await dateSelect.selectOption(future);
  1196 |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1197 |       expect(new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toContain(`date=${future}`);
  1198 |     } finally {
  1199 |       await harness.stop();
  1200 |     }
  1201 |   });
  1202 | 
  1203 |   test('US2 SSR unavailable tour exposes no enabled checkout on either surface', async ({ page }) => {
  1204 |     test.setTimeout(180000);
  1205 |     await page.setViewportSize({ width: 390, height: 844 });
  1206 |     const slug = 'hidden-gems-rome-walking-tour';
```