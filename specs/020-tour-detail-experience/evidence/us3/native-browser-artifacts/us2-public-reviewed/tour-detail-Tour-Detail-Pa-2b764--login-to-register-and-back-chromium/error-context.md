# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US2 auth cross-links preserve the full booking query from login to register and back
- Location: tests\e2e\tour-detail.spec.ts:1088:7

# Error details

```
Error: expect(page).toHaveURL(expected) failed

Expected: "http://127.0.0.1:63831/en/booking?tour=hidden-gems-rome-walking-tour&participants=2&date=2026-10-26"
Received: "http://127.0.0.1:63831/en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-10-26"
Timeout:  5000ms

Call log:
  - Expect "toHaveURL" with timeout 5000ms
    13 × unexpected value "http://127.0.0.1:63831/en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-10-26"

```

```yaml
- banner:
  - link "Skip to main content":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /en
  - navigation "Main navigation":
    - link "Home":
      - /url: /en
    - link "Search tours":
      - /url: /en/search
    - link "Categories":
      - /url: /en/categories
    - link "Destinations":
      - /url: /en/destinations
  - button "Search tours"
  - combobox "Switch language":
    - option "English" [selected]
    - option "Español"
    - option "Italiano"
  - link "Sign In":
    - /url: /en/auth/login
  - link "Sign Up":
    - /url: /en/auth/register
- main:
  - paragraph: Bookly
  - heading "Welcome back" [level=1]
  - paragraph: Sign in to access your bookings and reviews
  - form "Welcome back":
    - text: Email Address
    - textbox "Email Address":
      - /placeholder: you@example.com
    - alert: Please enter a valid email address.
    - text: Password
    - link "Forgot password?":
      - /url: /en/auth/forgot-password
    - textbox "Password":
      - /placeholder: ""
      - text: Password123!
    - button "Show password"
    - button "Sign In"
    - paragraph:
      - text: Don't have an account?
      - link "Create account":
        - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D2%26date%3D2026-10-26
- contentinfo:
  - img "Bookly Travel — Book smarter. Travel better."
  - paragraph: Explore
  - list:
    - listitem:
      - link "Search tours":
        - /url: /en/search
    - listitem:
      - link "Categories":
        - /url: /en/categories
    - listitem:
      - link "Destinations":
        - /url: /en/destinations
  - paragraph: Company
  - list:
    - listitem:
      - link "Privacy policy":
        - /url: /en/privacy
    - listitem:
      - link "Terms of service":
        - /url: /en/terms
  - text: © 2026 Bookly. All rights reserved.
  - combobox "Switch language":
    - option "English" [selected]
    - option "Español"
    - option "Italiano"
- complementary "Cookie consent":
  - text: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
  - button "Reject"
  - button "Accept"
- alert: Sign In | Bookly
```

# Test source

```ts
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
  1106 |     const loginLink = page.locator('#main-content').getByRole('link', { name: /sign in/i });
  1107 |     const loginHref = await loginLink.getAttribute('href');
  1108 |     expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
  1109 | 
  1110 |     await loginLink.click();
  1111 |     await page.fill('input[name="email"]', 'test@example.com');
  1112 |     await page.fill('input[name="password"]', 'Password123!');
  1113 |     await page.click('button[type="submit"]');
  1114 | 
  1115 |     // Seeded login succeeds and returns to the full booking query untouched.
> 1116 |     await expect(page).toHaveURL(`/en/booking?tour=${slug}&participants=2&date=${dates[10]}`);
       |                        ^ Error: expect(page).toHaveURL(expected) failed
  1117 |     await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  1118 |   });
  1119 | 
  1120 |   test('US2 SSR exposes all 60 offered dates with no slice', async ({ page }) => {    test.setTimeout(180000);
  1121 |     const slug = 'hidden-gems-rome-walking-tour';
  1122 |     const complete = createFullTourDetail(new Date());
  1123 |     const harness = await startSsrHarness(slug, () => complete);
  1124 |     try {
  1125 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1126 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1127 | 
  1128 |       const values = await page.locator('main#main-content').getByLabel('Select a Date').evaluateAll(
  1129 |         (selects) => selects.flatMap((select) =>
  1130 |           Array.from((select as HTMLSelectElement).options).map((option) => option.value),
  1131 |         ),
  1132 |       );
  1133 |       for (const date of served.availability.available_dates) {
  1134 |         expect(values).toContain(date);
  1135 |       }
  1136 |       expect(values).toContain(served.availability.available_dates[31]);
  1137 |     } finally {
  1138 |       await harness.stop();
  1139 |     }
  1140 |   });
  1141 | 
  1142 |   test('US2 signed-out SSR preserves the full query through login and registration links', async ({ page }) => {
  1143 |     test.setTimeout(180000);
  1144 |     const slug = 'hidden-gems-rome-walking-tour';
  1145 |     const complete = createFullTourDetail(new Date());
  1146 |     const harness = await startSsrHarness(slug, () => complete);
  1147 |     try {
  1148 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1149 |       const picked = served.availability.available_dates[4];
  1150 |       await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  1151 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1152 | 
  1153 |       await page.locator('main#main-content').getByLabel('Select a Date').selectOption(picked);
  1154 |       const expected = `/en/booking?tour=${slug}&participants=1&date=${picked}`;
  1155 |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1156 |       expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(expected)}`);
  1157 |       const registerHref = await page.getByRole('link', { name: /crear cuenta|create account|crea account/i }).first().getAttribute('href');
  1158 |       expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(expected)}`);
  1159 | 
  1160 |       // Following the login link keeps the full returnUrl for post-login return.
  1161 |       await page.getByRole('link', { name: 'Book Now' }).first().click();
  1162 |       await expect(page).toHaveURL(/\/en\/auth\/login\?returnUrl=/);
  1163 |       expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(expected);
  1164 |     } finally {
  1165 |       await harness.stop();
  1166 |     }
  1167 |   });
  1168 | 
  1169 |   test('US2 SSR UTC boundary never books today and accepts an explicit future date', async ({ page }) => {
  1170 |     test.setTimeout(180000);
  1171 |     const slug = 'hidden-gems-rome-walking-tour';
  1172 |     const runtimeToday = new Date().toISOString().slice(0, 10);
  1173 |     const complete = createFullTourDetail(new Date());
  1174 |     complete.availability = {
  1175 |       available_dates: [runtimeToday, ...complete.availability.available_dates],
  1176 |       next_available_date: runtimeToday,
  1177 |       is_unavailable: false,
  1178 |     };
  1179 |     const harness = await startSsrHarness(slug, () => complete);
  1180 |     try {
  1181 |       const served = await readServedDetail(harness.mockPort, slug, 'en');
  1182 |       const future = served.availability.available_dates.find((date) => date > runtimeToday)!;
  1183 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1184 | 
  1185 |       // Today is listed but strictly rejected: no automatic choice, no link.
  1186 |       // The desktop card slot owns the live select (a hidden streamed copy
  1187 |       // exists); wait for the enabled control before asserting.
  1188 |       const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
  1189 |       const dateSelect = cardSlot.getByLabel('Select a Date');
  1190 |       await expect(dateSelect).toBeEnabled();
  1191 |       await expect(dateSelect).toHaveValue('');
  1192 |       await expect(cardSlot.getByText('Select an offered date to continue.')).toBeVisible();
  1193 |       await expect(page.getByRole('link', { name: 'Book Now' })).toHaveCount(0);
  1194 | 
  1195 |       // An explicit offered future date recovers continuation.
  1196 |       await dateSelect.selectOption(future);
  1197 |       const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
  1198 |       expect(new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toContain(`date=${future}`);
  1199 |     } finally {
  1200 |       await harness.stop();
  1201 |     }
  1202 |   });
  1203 | 
  1204 |   test('US2 SSR unavailable tour exposes no enabled checkout on either surface', async ({ page }) => {
  1205 |     test.setTimeout(180000);
  1206 |     await page.setViewportSize({ width: 390, height: 844 });
  1207 |     const slug = 'hidden-gems-rome-walking-tour';
  1208 |     const unavailable = createUnavailableTourDetail(new Date());
  1209 |     const harness = await startSsrHarness(slug, () => unavailable);
  1210 |     try {
  1211 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  1212 | 
  1213 |       await expect(page.locator('main#main-content').getByText('Currently Unavailable')).toBeVisible();
  1214 |       await expect(page.getByRole('link', { name: 'Book Now' })).toHaveCount(0);
  1215 |       await expect(page.locator('main#main-content').getByTestId('mobile-booking-bar')).toHaveCount(0);
  1216 |     } finally {
```