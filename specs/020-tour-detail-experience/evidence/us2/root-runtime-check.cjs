// Root verification against a fresh native production build and the owned API.
// Tokens and gateway secrets stay in memory; reports contain selected fields only.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const { createRequire } = require('node:module');
const frontend = path.resolve(__dirname, '../../../../frontend');
const req = createRequire(path.join(frontend, 'package.json'));
const { chromium, expect: baseExpect } = req('@playwright/test');
const expect = baseExpect.configure({ timeout: 20000 });
const sameOrigin = process.argv.includes('--same-origin');
const report = { scope: sameOrigin
  ? 'Fresh native built Next on its own origin; same-origin API rewrite reaches the actual owned backend. No frontend-resource or API response interception. Media-only CDN adapter. This is not Docker deployment/optimizer/performance acceptance.'
  : 'Fresh native built Next served through a browser frontend-resource transport adapter on the owned proxy origin. Public/auth/booking/payment/cancel API requests reach the actual owned API without interception. Media-only CDN adapter. This is not Docker deployment/optimizer/performance acceptance.', checks: [], createRequests: [], paymentStatuses: [] };
const output = path.join(__dirname, `${process.argv[2] || 'root-runtime-20261016'}.json`);
const slug = 'hidden-gems-rome-walking-tour';
let app, browser, context, page, ownedReference, ownedPaymentSecret;

function safeError(error) {
  // Playwright network call logs can contain request headers or payment data.
  return String(error.message || error).split(/\r?\n/)[0];
}

function recordCheck(message) {
  report.checks.push(message);
  fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
  console.log(message);
}

async function availablePort() {
  const server = http.createServer();
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return port;
}

async function login(target) {
  await expect(target).toHaveURL(/\/en\/auth\/login(?:\?|$)/);
  await expect(target.getByRole('form', { name: 'Welcome back', exact: true })).toBeVisible();
  await target.waitForLoadState('networkidle');
  await target.locator('input[name="email"]').fill('test@example.com');
  await target.locator('input[name="password"]').fill('Password123!');
  const response = target.waitForResponse(r => r.url().includes('/api/public/auth/login') && r.request().method() === 'POST');
  await target.locator('button[type="submit"]').click();
  expect((await response).status()).toBe(200);
}

async function revokeCurrentToken() {
  const token = await page.evaluate(() => localStorage.getItem('auth_token'));
  expect(token).toBeTruthy();
  const response = await context.request.post(`${report.origin}/api/public/auth/logout`, { headers: { Authorization: `Bearer ${token}` } });
  expect(response.status()).toBe(204);
}

async function main() {
  report.startedAtUtc = new Date().toISOString();
  const port = await availablePort();
  const nativeOrigin = `http://127.0.0.1:${port}`;
  const origin = sameOrigin ? nativeOrigin : 'http://localhost:8080';
  report.origin = origin;
  report.nativeOrigin = nativeOrigin;
  report.sourceHead = require('node:child_process').execFileSync('git', ['rev-parse', 'HEAD'], { cwd: frontend, encoding: 'utf8' }).trim();
  report.buildId = fs.readFileSync(path.join(frontend, '.next/BUILD_ID'), 'utf8').trim();
  report.sourceFiles = Object.fromEntries([
    'src/components/tour/TourBookingController.tsx',
    'src/components/tour/BookingCard.tsx',
    'src/components/tour/MobileBookingBar.tsx',
    'src/components/booking/BookingForm.tsx',
    'src/components/auth/LoginForm.tsx',
    'src/components/auth/RegisterForm.tsx',
    'src/lib/tours/booking-selection.ts',
  ].map(file => [file, require('node:crypto').createHash('sha256').update(fs.readFileSync(path.join(frontend, file))).digest('hex')]));
  app = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-H', '127.0.0.1', '-p', String(port)], {
    cwd: frontend,
    env: { ...process.env, API_INTERNAL_URL: 'http://127.0.0.1:8080', NEXT_TELEMETRY_DISABLED: '1' },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  app.stdout.resume();
  app.stderr.resume();
  await expect.poll(async () => {
    if (app.exitCode !== null) throw new Error(`Owned Next exited ${app.exitCode}`);
    try { return (await fetch(`${nativeOrigin}/favicon.ico`)).status; } catch { return 0; }
  }, { timeout: 45000 }).not.toBe(0);
  browser = await chromium.launch({ headless: true, args: ['--disable-gpu'] });
  context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  await context.addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: origin }]);
  if (!sameOrigin) await context.route(`${origin}/**`, async route => {
    const url = new URL(route.request().url());
    if (url.pathname.startsWith('/api/')) return route.continue();
    const response = await route.fetch({ url: nativeOrigin + url.pathname + url.search });
    await route.fulfill({ response });
  });
  // Native Windows cannot resolve the Docker-only fixture CDN. This forwards
  // media only to its actual owned JPEG; no public/auth/commerce response mock.
  await context.route('**/_next/image?**', async route => {
    const source = new URL(new URL(route.request().url()).searchParams.get('url'));
    if (source.hostname !== 'cdn.bookly.test') return route.continue();
    const response = await context.request.get(`http://localhost:8080${source.pathname}`, { headers: { Host: 'cdn.bookly.test' } });
    await route.fulfill({ response });
  });
  page = await context.newPage();
  page.on('request', request => {
    if (request.method() !== 'POST' || !request.url().endsWith('/api/public/bookings')) return;
    const body = request.postDataJSON();
    report.createRequests.push({ tour_slug: body.tour_slug, tour_date: body.tour_date, participant_count: body.participant_count, locale: body.locale, page_load_price: body.page_load_price });
  });
  page.on('response', response => {
    if (response.request().method() === 'POST' && response.url().includes('/deterministic-payment/confirm')) report.paymentStatuses.push(response.status());
  });
  const response = await context.request.get(`${origin}/api/public/tours/${slug}?locale=en`);
  expect(response.status()).toBe(200);
  const { data: tour } = await response.json();
  const picked = tour.availability.available_dates[31];
  const mobilePicked = tour.availability.available_dates[32];
  expect(picked).toBeTruthy();
  report.publicFacts = { slug, dateCount: tour.availability.available_dates.length, baseCents: tour.pricing.base_price.amount, currency: tour.pricing.base_price.currency, group: tour.group_size, picked, mobilePicked };
  await page.goto(`${origin}/en/tours/${slug}`);
  // The signed-out link appears after auth restoration; SSR controls can be
  // present before React attaches handlers on a cold native start.
  await expect(page.getByRole('link', { name: 'Book Now', exact: true })).toBeVisible();
  await page.getByLabel('Select a Date').selectOption(picked);
  await expect(page.getByLabel('Select a Date')).toHaveValue(picked);
  await page.getByRole('button', { name: 'Increase participants', exact: true }).click();
  await page.getByRole('button', { name: 'Increase participants', exact: true }).click();
  const bookingTarget = (date, count) => `/en/booking?tour=${slug}&participants=${count}&date=${date}`;
  let href = await page.getByRole('link', { name: 'Book Now', exact: true }).getAttribute('href');
  expect(new URL(href, origin).searchParams.get('returnUrl')).toBe(bookingTarget(picked, 3));
  await page.setViewportSize({ width: 390, height: 844 });
  const edit = page.getByRole('button', { name: 'Edit booking selections', exact: true });
  expect((await edit.boundingBox()).height).toBeGreaterThanOrEqual(44);
  await edit.click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Select a Date').selectOption(mobilePicked);
  await dialog.getByRole('button', { name: 'Increase participants', exact: true }).click();
  await page.keyboard.press('Escape');
  await expect(dialog).toHaveCount(0);
  await expect(edit).toBeFocused();
  const bar = page.getByTestId('mobile-booking-bar');
  href = await bar.getByRole('link', { name: 'Continue to booking', exact: true }).getAttribute('href');
  expect(new URL(href, origin).searchParams.get('returnUrl')).toBe(bookingTarget(mobilePicked, 4));
  await page.screenshot({ path: path.join(__dirname, 'root-runtime-selection-mobile-390.png'), fullPage: true });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await expect(page.getByLabel('Select a Date')).toHaveValue(mobilePicked);
  recordCheck('All offered dates, non-default selection, shared mobile dialog, 44px target, Escape focus return and resize preserved query.');
  await page.getByRole('link', { name: 'Book Now', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/en/auth/login\\?returnUrl='));
  expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(bookingTarget(mobilePicked, 4));
  await page.locator('form').getByRole('link', { name: 'Create account', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/en/auth/register\\?returnUrl='));
  expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(bookingTarget(mobilePicked, 4));
  await page.locator('form').getByRole('link', { name: 'Sign in', exact: true }).click();
  await login(page);
  await expect(page).toHaveURL(origin + bookingTarget(mobilePicked, 4), { timeout: 30000 });
  await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  expect(report.createRequests).toHaveLength(0);
  recordCheck('Real login through register/login cross-links restored the full selection with no create POST.');
  await revokeCurrentToken();
  await page.getByRole('button', { name: /Confirm & Pay/i }).click();
  await expect(page).toHaveURL(new RegExp('/en/auth/login\\?sessionExpired=1&returnUrl='), { timeout: 30000 });
  expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(bookingTarget(mobilePicked, 4));
  await expect(page.getByText(/Your session has expired/i)).toBeVisible();
  expect(report.createRequests).toHaveLength(1);
  await login(page);
  await expect(page).toHaveURL(origin + bookingTarget(mobilePicked, 4), { timeout: 30000 });
  await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  expect(report.createRequests).toHaveLength(1);
  const createResponse = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/api/public/bookings'));
  await page.getByRole('button', { name: /Confirm & Pay/i }).click();
  const created = await createResponse;
  expect(created.ok()).toBe(true);
  const booking = await created.json();
  ownedReference = booking.data.reference;
  report.ownedReference = ownedReference;
  expect(booking.payment.gateway).toBe('deterministic');
  ownedPaymentSecret = booking.payment.client_secret;
  const total = booking.data.pricing?.total ?? booking.data.total_price;
  expect(total.amount).toBe(tour.pricing.base_price.amount * 4);
  expect(total.currency).toBe('EUR');
  report.serverTotal = { amount: total.amount, currency: total.currency };
  recordCheck('Actual revoked-token create401 recovered full query; explicit retry alone created one deterministic booking with authoritative integer total.');
  await expect(page.getByRole('button', { name: 'Complete test payment', exact: true })).toBeVisible();
  await revokeCurrentToken();
  await page.getByRole('button', { name: 'Complete test payment', exact: true }).click();
  await expect(page.getByRole('alert').filter({ hasText: 'The local test payment could not be completed.' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Complete test payment', exact: true })).toBeEnabled();
  expect(report.createRequests).toHaveLength(2);
  expect(report.paymentStatuses).toEqual([401]);
  const loginTab = await context.newPage();
  await loginTab.goto(`${origin}/en/auth/login`);
  const restored = page.waitForResponse(r => r.url().includes('/api/public/auth/me') && r.status() === 200);
  await login(loginTab);
  await expect(loginTab).not.toHaveURL(/\/auth\/login/);
  await (await restored).finished();
  await page.waitForLoadState('networkidle');
  await loginTab.close();
  const paymentResponse = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/deterministic-payment/confirm'));
  await page.getByRole('button', { name: 'Complete test payment', exact: true }).click();
  expect((await paymentResponse).ok()).toBe(true);
  await expect(page.getByRole('heading', { name: 'Booking Confirmed!', exact: true })).toBeVisible();
  expect(new URL(page.url()).searchParams.get('ref')).toBe(ownedReference);
  expect(report.createRequests).toHaveLength(2);
  expect(report.paymentStatuses).toEqual([401, 200]);
  recordCheck('Actual revoked-token payment401 retained the same pending reference; cross-tab login restored auth and explicit retry confirmed it without another create POST.');
  await page.screenshot({ path: path.join(__dirname, 'root-runtime-confirmation.png'), fullPage: true });
  report.passed = true;
}

main().catch(async error => {
  report.passed = false;
  report.error = safeError(error);
  process.exitCode = 1;
  if (page && !page.isClosed()) {
    report.failedUrl = page.url();
    await page.screenshot({ path: path.join(__dirname, `${process.argv[2] || 'root-runtime-20261016'}-failure.png`) }).catch(() => undefined);
  }
}).finally(async () => {
  if (ownedReference && context && page) {
    let cleanupToken;
    try {
      let token = await page.evaluate(() => localStorage.getItem('auth_token'));
      const session = await context.request.get(`${report.origin}/api/public/auth/me`, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
      if (session.status() === 401) {
        const restored = await context.request.post(`${report.origin}/api/public/auth/login`, { data: { email: 'test@example.com', password: 'Password123!' } });
        expect(restored.ok()).toBe(true);
        cleanupToken = (await restored.json()).data.token;
        token = cleanupToken;
      }
      const headers = token ? { Authorization: `Bearer ${token}` } : {};
      const detail = await context.request.get(`${report.origin}/api/public/traveler/bookings/${encodeURIComponent(ownedReference)}`, { headers });
      expect(detail.ok()).toBe(true);
      const status = (await detail.json()).data.status;
      let confirmedForCleanup = false;
      if (status === 'pending_payment' && ownedPaymentSecret) {
        const confirm = await context.request.post(`${report.origin}/api/public/bookings/${encodeURIComponent(ownedReference)}/deterministic-payment/confirm`, { headers, data: { client_secret: ownedPaymentSecret } });
        expect(confirm.ok()).toBe(true);
        confirmedForCleanup = true;
      }
      if (status === 'confirmed' || confirmedForCleanup) {
        const cleanup = await context.request.post(`${report.origin}/api/public/traveler/bookings/${encodeURIComponent(ownedReference)}/cancel`, { headers, data: { reason: 'Spec020 exact owned runtime fixture cleanup' } });
        report.cleanup = { reference: ownedReference, status: cleanup.status(), passed: cleanup.ok(), confirmedForCleanup };
        if (!cleanup.ok()) { report.passed = false; process.exitCode = 1; }
      } else {
        report.cleanup = { reference: ownedReference, finalState: status, passed: ['cancelled', 'expired'].includes(status) };
        if (!report.cleanup.passed) { report.passed = false; process.exitCode = 1; }
      }
    } catch (error) { report.cleanup = { reference: ownedReference, passed: false, error: safeError(error) }; report.passed = false; process.exitCode = 1; }
    finally {
      if (cleanupToken) await context.request.post(`${report.origin}/api/public/auth/logout`, { headers: { Authorization: `Bearer ${cleanupToken}` } }).catch(() => undefined);
    }
  }
  if (page && context) {
    try {
      const token = await page.evaluate(() => localStorage.getItem('auth_token'));
      if (token) await context.request.post(`${report.origin}/api/public/auth/logout`, { headers: { Authorization: `Bearer ${token}` } });
    } catch { /* Owned context disposal follows; no token is persisted. */ }
  }
  fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
  await context?.unrouteAll({ behavior: 'ignoreErrors' });
  await context?.close();
  await browser?.close();
  if (app && app.exitCode === null) {
    app.kill();
    await new Promise(resolve => { const timer = setTimeout(resolve, 5000); app.once('exit', () => { clearTimeout(timer); resolve(); }); });
    if (app.exitCode === null) app.kill('SIGKILL');
  }
  fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({ passed: report.passed, checks: report.checks.length, error: report.error, cleanup: report.cleanup }));
});
