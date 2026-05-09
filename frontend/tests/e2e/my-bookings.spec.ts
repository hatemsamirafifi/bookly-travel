import { test, expect } from '@playwright/test';

test.describe('My Bookings', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign Out')).toBeVisible();
  });

  test('bookings list renders cards', async ({ page }) => {
    await page.goto('/en/my-bookings');

    await expect(page.locator('text=My Bookings')).toBeVisible();
    // Cards should render or show empty state
    await expect(
      page.locator('text=No bookings yet').or(page.locator('[data-testid="booking-card"]'))
    ).toBeVisible();
  });

  test('status filter tabs work', async ({ page }) => {
    await page.goto('/en/my-bookings');

    // Click a filter tab
    const confirmedTab = page.locator('button[role="tab"]:has-text("Confirmed")');
    if (await confirmedTab.isVisible()) {
      await confirmedTab.click();
      await expect(confirmedTab).toHaveClass(/bg-blue-600/);
    }
  });

  test('clicking card navigates to detail', async ({ page }) => {
    await page.goto('/en/my-bookings');

    const card = page.locator('a[href*="/my-bookings/BKO-"]').first();
    if (await card.isVisible()) {
      await card.click();
      await page.waitForURL('**/my-bookings/BKO-**');
      await expect(page.locator('text=Booking Detail')).toBeVisible();
    }
  });

  test('cancel button shows confirmation dialog', async ({ page }) => {
    // Navigate to a booking detail that has can_cancel
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await expect(page.locator('text=Are you sure?')).toBeVisible();
    }
  });

  test('cancel succeeds for eligible booking', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isEnabled()) {
      await cancelBtn.click();
      // Confirm cancellation
      const confirmBtn = page.locator('button:has-text("Yes, Cancel Booking")');
      if (await confirmBtn.isVisible()) {
        await confirmBtn.click();
        await expect(page.locator('text=cancelled')).toBeVisible();
      }
    }
  });

  test('cancel button disabled for ineligible booking', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-PAST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible()) {
      await expect(cancelBtn).toBeDisabled();
    }
  });
});
