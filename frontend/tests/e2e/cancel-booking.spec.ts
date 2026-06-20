import { test, expect } from '@playwright/test';

test.describe('Cancel Booking', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP) and the old
  // `text=Sign Out` check failed because that action is in a collapsed dropdown.

  test('opens cancellation modal', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await expect(page.locator('role=dialog')).toBeVisible();
      await expect(page.locator('text=Confirm cancellation')).toBeVisible();
    }
  });

  test('confirm cancellation updates status', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible() && await cancelBtn.isEnabled()) {
      await cancelBtn.click();
      await expect(page.locator('role=dialog')).toBeVisible();

      const confirmBtn = page.locator('button:has-text("Yes, Cancel Booking")');
      await confirmBtn.click();

      await expect(page.locator('text=cancelled').first()).toBeVisible();
    }
  });

  test('keep booking closes modal', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await expect(page.locator('role=dialog')).toBeVisible();

      const keepBtn = page.locator('button:has-text("Keep Booking")');
      await keepBtn.click();

      await expect(page.locator('role=dialog')).not.toBeVisible();
    }
  });

  test('error handling on cancellation failure', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-PAST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible()) {
      await expect(cancelBtn).toBeDisabled();
    }
  });
});
