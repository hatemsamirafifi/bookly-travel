import { test, expect, type Page, type APIRequestContext } from '@playwright/test';
import en from '../../messages/en.json';
import es from '../../messages/es.json';
import it from '../../messages/it.json';

const messages = { en, es, it };
const widths = [390, 768, 1024, 1440] as const;
const locales = ['en', 'es', 'it'] as const;
const tour = 'hidden-gems-rome-walking-tour';
const evidence = `.phase0-evidence/visual-matrix/${new Date().toISOString().replace(/[:.]/g, '-')}`;

// One Chromium run covers every prescribed width; don't duplicate this matrix
// in the device project, which has separate touch/browser journey coverage.
test.skip(({ isMobile }) => isMobile, 'All four widths are exercised explicitly.');

test.beforeEach(async ({ context }, testInfo) => {
  await context.addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: testInfo.project.use.baseURL as string }]);
});

async function authenticate(page: Page, request: APIRequestContext, partner = false) {
  const response = await request.post('/api/public/auth/login', {
    data: partner
      ? { email: 'partner@bookly.test', password: 'password' }
      : { email: 'test@example.com', password: 'Password123!' },
  });
  expect(response.ok()).toBeTruthy();
  const { data } = await response.json();
  expect(typeof data.token).toBe('string');
  await page.addInitScript((token: string) => localStorage.setItem('auth_token', token), data.token);
}

async function inspectPage(page: Page, name: string) {
  // An upstream 504 also has an h1: don't mistake a proxy error for a page.
  await expect(page.locator('main').first()).toBeVisible();
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
  await expect(page.locator('.animate-pulse')).toHaveCount(0);
  await expect.poll(() => page.evaluate(() =>
    document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )).toBeLessThanOrEqual(1);
  // Capture the reading layout before keyboard focus can open Filament's
  // off-canvas navigation on narrow screens.
  await page.screenshot({ path: `${evidence}/${name}.png`, fullPage: true });
  const firstLink = page.locator('a:visible').first();
  await firstLink.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(firstLink).toBeFocused();
  await expect(firstLink).toBeVisible();
}

for (const locale of locales) {
  for (const width of widths) {
    test(`${locale} public keyboard/layout matrix at ${width}px`, async ({ page }) => {
      test.setTimeout(120000);
      await page.setViewportSize({ width, height: 900 });
      for (const [name, path] of [
        ['home', `/${locale}`],
        ['search', `/${locale}/search?q=rome`],
        ['tour', `/${locale}/tours/${tour}`],
        ['blog', `/${locale}/blog/hidden-gems-florence`],
      ]) {
        await page.goto(path);
        await inspectPage(page, `${name}-${locale}-${width}`);
        if (name === 'tour') {
          const open = page.getByRole('button', { name: messages[locale].tour.gallery.open, exact: true });
          await open.focus();
          await page.keyboard.press('Enter');
          const dialog = page.getByRole('dialog', { name: messages[locale].tour.gallery.dialog });
          await expect(dialog).toBeVisible();
          await expect(dialog.getByRole('button', { name: messages[locale].tour.gallery.close })).toBeFocused();
          await page.keyboard.press('Escape');
          await expect(dialog).toHaveCount(0);
          await expect(open).toBeFocused();
        }
      }
      if (width === 390) {
        const menu = page.getByRole('button', { name: messages[locale].nav.openMenu });
        await menu.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(menu).toBeFocused();
      }
    });

    test(`${locale} traveler/checkout keyboard/layout matrix at ${width}px`, async ({ page, request }) => {
      test.setTimeout(120000);
      await page.setViewportSize({ width, height: 900 });
      await authenticate(page, request);
      const bookingsResponsePromise = page.waitForResponse((response) =>
        new URL(response.url()).pathname === '/api/public/traveler/bookings'
        && response.request().method() === 'GET',
      );
      await page.goto(`/${locale}/my-bookings`);
      const bookingsResponse = await bookingsResponsePromise;
      expect(bookingsResponse.ok()).toBeTruthy();
      const bookings = await bookingsResponse.json();
      const title = bookings.data[0].tour.title as string;
      expect(title.trim().length).toBeGreaterThan(0);
      const card = page.getByTestId('booking-card').first();
      await expect(card.getByRole('heading', { level: 3 })).toHaveText(title);
      const status = bookings.data[0].status as keyof typeof messages.en.traveler.dashboard.status;
      await expect(card.getByText(messages[locale].traveler.dashboard.status[status], { exact: true })).toBeVisible();
      const date = new Date(`${bookings.data[0].tour_date}T00:00:00`).toLocaleDateString(locale);
      await expect(card.getByText(date, { exact: true })).toBeVisible();
      const participants = bookings.data[0].participant_count as number;
      const participantWord = { en: 'participants?', es: 'participantes?', it: 'partecipant[ei]' }[locale];
      await expect(card.getByText(new RegExp(`^${participants} ${participantWord}$`))).toBeVisible();
      await inspectPage(page, `traveler-${locale}-${width}`);
      const response = await request.get(`/api/public/tours/${tour}?locale=${locale}`);
      expect(response.ok()).toBeTruthy();
      const { data } = await response.json();
      expect(data.availability.next_available_date).toBeTruthy();
      await page.goto(`/${locale}/booking?tour=${tour}&date=${data.availability.next_available_date}&participants=2`);
      await expect(page.locator('button[type="submit"]').first()).toBeVisible();
      await inspectPage(page, `checkout-${locale}-${width}`);
      // Inspect without submitting or creating a charge/booking.
      await expect(page.locator('button[type="submit"]').first()).toBeVisible();
    });

    test(`${locale} partner keyboard/layout matrix at ${width}px`, async ({ page, request }) => {
      test.setTimeout(120000);
      await page.setViewportSize({ width, height: 900 });
      await authenticate(page, request, true);
      await page.goto(`/${locale}/partner`);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(messages[locale].partner.dashboard.title);
      await expect(page.getByText(messages[locale].partner.dashboard.totalBookings, { exact: true })).toBeVisible();
      await inspectPage(page, `partner-${locale}-${width}`);
      if (width === 390) {
        const menu = page.locator('header button').first();
        await menu.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(menu).toBeFocused();
      }
    });
  }
}

for (const width of widths) {
  test(`Filament keyboard/layout matrix at ${width}px`, async ({ page }) => {
    test.setTimeout(120000);
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/admin/login');
    await page.getByLabel(/email address/i).first().fill('admin@bookly.test');
    await page.getByLabel(/password/i).first().fill('password');
    await Promise.all([
      page.waitForURL('**/admin'),
      page.getByRole('button', { name: /sign in/i }).click(),
    ]);
    await page.goto('/admin/tours');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    if (width < 1024) {
      const overlay = page.locator('.fi-sidebar-close-overlay');
      if (await overlay.isVisible()) {
        // Close the real drawer through its exposed backdrop, not by hiding
        // DOM/CSS, so the reading-layout screenshot shows the moderation table.
        await overlay.click({ position: { x: width - 10, y: 80 } });
      }
      await expect(overlay).toBeHidden();
    }
    await inspectPage(page, `admin-en-${width}`);
  });
}
