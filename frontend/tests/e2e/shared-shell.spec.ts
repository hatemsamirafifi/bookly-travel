import { test, expect } from '@playwright/test';
import en from '../../messages/en.json';
import es from '../../messages/es.json';
import it from '../../messages/it.json';

const catalogs = { en, es, it };
test.skip(({ isMobile }) => isMobile, 'Explicit 390px and 1440px matrix uses Chromium.');

test.beforeEach(async ({ context }, testInfo) => {
  await context.addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: testInfo.project.use.baseURL as string }]);
});

for (const locale of ['en', 'es', 'it'] as const) {
  for (const width of [390, 768, 1024, 1440]) {
    test(`${locale} guest shell supports search, focus and layout at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.goto(`/${locale}/auth/login`);
      const nav = catalogs[locale].nav;
      const banner = page.getByRole('banner');
      await expect(banner.getByRole('navigation', { name: nav.discoverTours })).toBeVisible();
      const searchTrigger = banner.getByRole('button', { name: nav.searchTours });
      await searchTrigger.focus();
      await page.keyboard.press('Enter');
      const input = page.getByRole('searchbox', { name: nav.searchTours });
      await expect(input).toBeFocused();
      const duration = await input.evaluate((element) => parseFloat(getComputedStyle(element).transitionDuration));
      expect(duration).toBeLessThanOrEqual(0.001);
      await input.fill('Rome');
      await page.keyboard.press('Escape');
      await expect(searchTrigger).toBeFocused();
      await expect(input).not.toBeVisible();
      if (width < 1024) {
        const menu = banner.getByRole('button', { name: nav.openMenu });
        await menu.focus();
        await page.keyboard.press('Enter');
        const drawer = page.getByRole('dialog', { name: nav.mainNavigation });
        await expect(drawer.getByRole('button', { name: nav.closeMenu })).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(drawer.getByRole('link', { name: catalogs[locale].traveler.nav.signUp })).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(menu).toBeFocused();
        await expect(page.getByRole('dialog')).toHaveCount(0);
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
      await page.screenshot({ path: `.phase1-evidence/screenshots/auth-${locale}-${width}.png`, fullPage: true });
      await searchTrigger.click();
      await page.getByRole('searchbox', { name: nav.searchTours }).fill('Rome');
      await page.getByRole('searchbox').press('Enter');
      await expect(page).toHaveURL(new RegExp(`/${locale}/search\\?q=Rome$`));
      await page.goto(`/${locale}`);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
      await page.screenshot({ path: `.phase1-evidence/screenshots/public-${locale}-${width}.png`, fullPage: true });
    });
  }
}

for (const role of ['traveler', 'partner'] as const) {
  test(`${role} account menu supports keyboard navigation without changing destinations`, async ({ page, request }) => {
    test.setTimeout(180000);
    const response = await request.post('/api/public/auth/login', { data: role === 'partner' ? { email: 'partner@bookly.test', password: 'password' } : { email: 'test@example.com', password: 'Password123!' } });
    expect(response.ok()).toBeTruthy();
    const payload = await response.json();
    await page.addInitScript((token: string) => localStorage.setItem('auth_token', token), payload.data.token);
    await page.goto('/en');
    const trigger = page.getByRole('button', { name: en.nav.accountMenu });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const dashboard = page.getByRole('menuitem', { name: 'Dashboard' });
    await expect(dashboard).toBeFocused();
    await expect(dashboard).toHaveAttribute('href', role === 'partner' ? '/en/partner' : '/en/my-bookings');
    await page.keyboard.press('End');
    await expect(page.getByRole('menuitem', { name: 'Sign Out' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await expect(page.getByRole('menu')).toHaveCount(0);
    for (const locale of ['en', 'es', 'it'] as const) {
    for (const width of [390, 768, 1024, 1440]) {
      const catalog = catalogs[locale];
      await page.setViewportSize({ width, height: 900 });
      await page.goto(role === 'partner' ? `/${locale}/partner` : `/${locale}/my-bookings`);
      await expect(page.locator('#main-content')).toBeVisible();
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
      await expect(page.locator('.animate-pulse')).toHaveCount(0);
      if (role === 'partner') {
        const account = page.getByRole('button', { name: catalog.nav.accountMenu });
        await account.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('menuitem', { name: catalog.partner.header.logout })).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(account).toBeFocused();
        if (width < 768) {
          const menu = page.getByRole('button', { name: catalog.partner.header.menuLabel });
          await menu.focus();
          await page.keyboard.press('Enter');
          await expect(page.getByRole('button', { name: catalog.nav.closeMenu })).toBeFocused();
          await page.keyboard.press('Escape');
          await expect(menu).toBeFocused();
        }
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
      await page.screenshot({ path: `.phase1-evidence/screenshots/${role}-${locale}-${width}.png`, fullPage: true });
    }
    }
  });
}
