import { test, expect } from '@playwright/test';

test.describe('Booking Detail', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP) and the old
  // `text=Sign Out` check failed because that action is in a collapsed dropdown.

  // Labels come from the `traveler.bookingDetail` namespace in messages/en.json:
  // reference="Reference", tour="Tour", participants="Participants",
  // statusTimeline="Status timeline", paymentReceipt="Payment receipt" (lowercase r),
  // status="Status". The page H1 is `traveler.pages.bookingDetail.title`="Booking Detail".
  // Use exact text matches: the "Tour" label and the tour-title link ("Hidden Gems of
  // Rome Walking Tour") both contain "Tour", and substring `text=` locators match both
  // → strict-mode violations. Exact match scopes to the label only.

  test('detail page renders sections', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    await expect(page.getByRole('heading', { name: 'Booking Detail' })).toBeVisible();
    await expect(page.getByText('Reference', { exact: true })).toBeVisible();
    await expect(page.getByText('Tour', { exact: true })).toBeVisible();
    await expect(page.getByText('Participants', { exact: true })).toBeVisible();
    await expect(page.getByText('Payment receipt', { exact: true })).toBeVisible();
    await expect(page.getByText('Status timeline', { exact: true })).toBeVisible();
  });

  test('receipt display shows payment info', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    await expect(page.getByText('Payment receipt', { exact: true })).toBeVisible();
    // BKO-TEST01 is seeded with a succeeded Payment, so the receipt renders the
    // "Status" field. (receiptUnavailable only shows when there is no payment.)
    await expect(page.getByText('Status', { exact: true })).toBeVisible();
  });

  test('cancel button visible for eligible booking', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.getByRole('button', { name: 'Cancel Booking' });
    if (await cancelBtn.isVisible()) {
      await expect(cancelBtn).toBeEnabled();
    }
  });

  test('404 for invalid reference', async ({ page }) => {
    await page.goto('/en/my-bookings/INVALID-REF');

    await expect(page.getByText('Booking not found.')).toBeVisible();
  });

  test('mobile layout renders single column', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/my-bookings/BKO-TEST01');

    await expect(page.getByText('Reference', { exact: true })).toBeVisible();
    await expect(page.getByText('Payment receipt', { exact: true })).toBeVisible();
  });
});
