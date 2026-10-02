import { test, expect } from '@playwright/test';

test.describe('Auth Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => url.pathname === '/en');
    await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();
  });

  test('header shows user menu after login', async ({ page }) => {
    await page.goto('/en');
    await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();
  });

  test('dropdown opens and navigates to dashboard', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      await menuBtn.click();
      await expect(page.locator('role=menuitem', { hasText: 'Dashboard' })).toBeVisible();
      await page.locator('role=menuitem', { hasText: 'Dashboard' }).click();
      await page.waitForURL('**/en/my-bookings**');
    }
  });

  test('dropdown navigates to all traveler pages', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      const links = [
        { label: 'My Bookings', path: '/en/my-bookings' },
        { label: 'Wishlist', path: '/en/wishlist' },
        { label: 'My Reviews', path: '/en/my-reviews' },
        { label: 'Profile Settings', path: '/en/profile' },
      ];

      for (const link of links) {
        await page.goto('/en');
        await page.click('button[aria-haspopup="menu"]');
        await page.locator('role=menuitem', { hasText: link.label }).click();
        await page.waitForURL(`**${link.path}**`);
      }
    }
  });

  test('logout redirects to homepage', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      await menuBtn.click();
      await page.locator('role=menuitem', { hasText: 'Sign Out' }).click();
      await page.waitForURL((url) => url.pathname === '/en');
      await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();
    }
  });

});

test('guest mobile navigation traps focus, closes with Escape, and restores the trigger', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/en');

  const trigger = page.getByRole('button', { name: 'Open menu' });
  const transitionSeconds = await trigger.evaluate((element) => {
    const duration = getComputedStyle(element).transitionDuration.split(',')[0].trim();
    return duration.endsWith('ms') ? Number.parseFloat(duration) / 1000 : Number.parseFloat(duration);
  });
  expect(transitionSeconds).toBeLessThanOrEqual(0.001);
  await trigger.focus();
  await page.keyboard.press('Enter');
  const dialog = page.getByRole('dialog', { name: 'Main navigation' });
  await expect(dialog).toBeVisible();
  await expect(page.getByRole('button', { name: 'Close menu' })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(dialog.getByRole('link', { name: 'Sign Up' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(trigger).toBeFocused();
});

for (const { locale, privacy } of [
  { locale: 'en', privacy: 'Privacy policy' },
  { locale: 'es', privacy: 'Política de privacidad' },
  { locale: 'it', privacy: 'Informativa sulla privacy' },
]) {
  test(`auth shell keeps localized navigation and footer in ${locale}`, async ({ page }) => {
    await page.goto(`/${locale}/auth/login`);
    await expect(page.getByRole('banner').getByRole('link', { name: 'Bookly' })).toBeVisible();
    await expect(page.getByRole('contentinfo').getByRole('link', { name: privacy })).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });
}

for (const { locale, invalidToken } of [
  { locale: 'en', invalidToken: 'This reset link is invalid or has expired.' },
  { locale: 'es', invalidToken: 'Este enlace de restablecimiento es inválido o ha expirado.' },
  { locale: 'it', invalidToken: 'Questo link di reimpostazione non è valido o è scaduto.' },
]) {
  test(`${locale} guest can reach recovery from login and keeps registration/recovery controls at mobile width`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}/auth/login`);
    await expect(page.locator('main h1')).toBeVisible();
    await page.locator(`main a[href="/${locale}/auth/forgot-password"]`).click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/auth/forgot-password$`));
    await expect(page.locator('main input[type="email"]')).toBeVisible();
    await expect(page.locator('main button[type="submit"]')).toBeEnabled();

    await page.goto(`/${locale}/auth/register`);
    await expect(page.locator('main input[name="name"]')).toBeVisible();
    await expect(page.locator('main input[name="email"]')).toBeVisible();
    await expect(page.locator('main button[type="submit"]')).toBeEnabled();

    await page.goto(`/${locale}/auth/reset-password`);
    await expect(page.locator('main [role="alert"]')).toHaveText(invalidToken);
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });
}
