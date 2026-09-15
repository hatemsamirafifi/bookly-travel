import { test, expect } from '@playwright/test';

// Guards the backend->frontend i18n error contract: the Laravel register
// endpoint returns field errors as i18n KEYS (auth.errors.emailTaken) and the
// RegisterForm must resolve them via formatError() before rendering. If the
// rendering path ever skips the translation step, users see raw keys like
// "auth.errors.emailTaken" in the UI.

test('register with duplicate email shows translated error (no raw i18n key)', async ({ page }) => {
  await page.goto('/en/auth/register');
  await page.locator('input[name="name"]').waitFor({ state: 'visible', timeout: 30000 });
  await page.fill('input[name="name"]', 'E2E Dup User');
  await page.fill('input[name="email"]', 'test@example.com'); // already registered
  await page.fill('input[name="password"]', 'Password123!');
  if (await page.locator('input[name="password_confirmation"]').count()) {
    await page.fill('input[name="password_confirmation"]', 'Password123!');
  }
  await page.click('#register-submit');
  // Expect the translated message, not the raw key
  await expect(page.getByText(/already registered/i).first()).toBeVisible({ timeout: 20000 });
  const html = await page.content();
  expect(html).not.toContain('auth.errors.emailTaken');
  expect(html).not.toContain('auth.errors.');
});
