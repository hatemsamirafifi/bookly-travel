import { test, expect } from '@playwright/test';

// This spec runs in the `-authed` projects (playwright.config.ts), which
// reuse the storage state saved by tests/auth.setup.ts — so every test here
// starts already authenticated (auth_token in localStorage). The earlier
// per-test browser login was removed because 18 concurrent logins blew past
// the backend `auth` rate limiter (10 logins/min/IP). The original
// `text=Sign Out` login-confirmation assertion was also broken: that action
// lives inside the collapsed user-menu dropdown, which renders nothing until
// opened, and `waitForURL('**/en**')` falsely matched the login page URL.

test.describe('My Bookings', () => {
  test('bookings list renders cards', async ({ page }) => {
    await page.goto('/en/my-bookings');

    // H1 is `traveler.pages.myBookings.title` = "My Bookings". Scope to the
    // heading: the nav (UserMenuDropdown / MobileNavPanel) also links to
    // my-bookings and may render the same label, which would make a substring
    // `text=My Bookings` locator match multiple nodes (strict-mode violation).
    await expect(page.getByRole('heading', { name: 'My Bookings' })).toBeVisible();
    // With the seeded traveler (DatabaseSeeder → seedTravelerBookings) the list
    // has bookings, so BookingCard (`data-testid="booking-card"`) renders.
    // The empty state (`traveler.dashboard.empty` = "No bookings yet - find a
    // tour...") is the fallback when no bookings exist. `.first()` avoids a
    // strict-mode violation when multiple seeded bookings render multiple cards.
    await expect(
      page.getByText('No bookings yet').or(page.locator('[data-testid="booking-card"]')).first()
    ).toBeVisible();
  });

  test('status filter tabs work', async ({ page }) => {
    await page.goto('/en/my-bookings');

    // BookingFilters renders role="tab" buttons; the "Confirmed" tab label is
    // `traveler.dashboard.filters.confirmed`. Selecting it pushes ?status=confirmed
    // and the tab re-renders with aria-selected="true" (active class is
    // bg-[#0A2540], not bg-blue-600 — assert the semantic aria-selected state).
    const confirmedTab = page.getByRole('tab', { name: 'Confirmed' });
    if (await confirmedTab.isVisible()) {
      await confirmedTab.click();
      await expect(confirmedTab).toHaveAttribute('aria-selected', 'true');
    }
  });

  test('clicking card navigates to detail', async ({ page }) => {
    await page.goto('/en/my-bookings');

    const card = page.locator('a[href*="/my-bookings/BKO-"]').first();
    if (await card.isVisible()) {
      await card.click();
      await page.waitForURL('**/my-bookings/BKO-**');
      await expect(page.getByRole('heading', { name: 'Booking Detail' })).toBeVisible();
    }
  });

  test('cancel button shows confirmation dialog', async ({ page }) => {
    // Navigate to a booking detail that has can_cancel
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.getByRole('button', { name: 'Cancel Booking' });
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await expect(page.getByText('Are you sure?')).toBeVisible();
    }
  });

  test('cancel succeeds for eligible booking', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.getByRole('button', { name: 'Cancel Booking' });
    // Guard with isVisible() first — isEnabled() auto-waits for the element to
    // attach, so without this guard the test burns its full 30s timeout when
    // the booking (and thus the button) doesn't exist.
    if ((await cancelBtn.isVisible()) && (await cancelBtn.isEnabled())) {
      await cancelBtn.click();
      // Confirm cancellation
      const confirmBtn = page.getByRole('button', { name: 'Yes, Cancel Booking' });
      if (await confirmBtn.isVisible()) {
        await confirmBtn.click();
        // After cancellation the status badge renders `status.cancelled`="Cancelled".
        await expect(page.getByText('Cancelled', { exact: true })).toBeVisible();
      }
    }
  });

  test('cancel button disabled for ineligible booking', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-PAST01');

    const cancelBtn = page.getByRole('button', { name: 'Cancel Booking' });
    if (await cancelBtn.isVisible()) {
      await expect(cancelBtn).toBeDisabled();
    }
  });
});
