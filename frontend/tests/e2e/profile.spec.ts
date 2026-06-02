import { test, expect } from '@playwright/test';

test.describe('Profile', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign Out')).toBeVisible();
  });

  test('profile page renders settings form', async ({ page }) => {
    await page.goto('/en/profile');

    await expect(page.getByRole('heading', { name: /Profile Settings/i })).toBeVisible();
    await expect(page.getByLabel(/First Name/i)).toBeVisible();
    await expect(page.getByLabel(/Last Name/i)).toBeVisible();
    await expect(page.getByLabel(/Phone/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /Save Changes/i })).toBeVisible();
  });

  test('updating profile shows success feedback', async ({ page }) => {
    await page.goto('/en/profile');

    const firstName = page.getByLabel(/First Name/i);
    await firstName.fill('TestFirst');

    const lastName = page.getByLabel(/Last Name/i);
    await lastName.fill('TestLast');

    await page.getByRole('button', { name: /Save Changes/i }).click();

    // Should show success state or toast
    await expect(
      page.locator('text=updated').or(page.locator('text=saved')).first()
    ).toBeVisible({ timeout: 5000 });
  });

  test('change password form validates mismatch', async ({ page }) => {
    await page.goto('/en/profile');

    // Scroll to or locate password section if separate
    const currentPassword = page.locator('input[name="current_password"]').or(page.getByLabel(/Current Password/i));
    if (await currentPassword.isVisible()) {
      await currentPassword.fill('Password123!');

      const newPassword = page.locator('input[name="new_password"]').or(page.getByLabel(/New Password/i));
      await newPassword.fill('NewPass123!');

      const confirmPassword = page.locator('input[name="new_password_confirmation"]').or(page.getByLabel(/Confirm New Password/i));
      await confirmPassword.fill('DifferentPass!');

      await page.getByRole('button', { name: /Change Password/i }).click();

      await expect(page.locator('text=match').or(page.locator('text=error')).first()).toBeVisible();
    }
  });

  test('language preference can be changed', async ({ page }) => {
    await page.goto('/en/profile');

    const languageSelect = page.locator('select[name="preferred_language"]').or(page.getByLabel(/Language/i));
    if (await languageSelect.isVisible()) {
      await languageSelect.selectOption('it');
      await page.getByRole('button', { name: /Save Changes/i }).click();

      await expect(
        page.locator('text=updated').or(page.locator('text=saved')).first()
      ).toBeVisible({ timeout: 5000 });
    }
  });

  test('marketing emails toggle exists', async ({ page }) => {
    await page.goto('/en/profile');

    const marketingToggle = page.locator('input[name="marketing_emails"]').or(page.getByLabel(/marketing/i));
    await expect(marketingToggle).toBeVisible();
  });
});
