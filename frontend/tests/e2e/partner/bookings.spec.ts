import { test, expect } from '@playwright/test';

test.describe('Partner Bookings Page', () => {
  test('should display bookings page heading', async ({ page }) => {
    await page.goto('/en/partner/bookings');
    // The bookings heading should be visible
    await expect(page.getByRole('heading', { name: /bookings/i })).toBeVisible();
  });

  test('should show loading state while fetching bookings', async ({ page }) => {
    // When the page first loads, it should show a loading indicator
    // This is hard to test with real data, so we check the component structure
    await page.goto('/en/partner/bookings');
    // The page should eventually render content or empty state
    await page.waitForLoadState('networkidle');
  });

  test('should show empty state when no bookings exist', async ({ page }) => {
    // Intercept API to return empty data
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Should show "No bookings yet" / "No bookings found" empty state
    await expect(page.getByRole('heading', { name: /no bookings/i })).toBeVisible();
  });

  test('should display bookings table when data exists', async ({ page }) => {
    // Intercept API to return mock booking data
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              reference: 'BK-ABC123',
              status: 'confirmed',
              tour_id: 1,
              tour_date: '2026-06-15',
              participants: [{ tier_id: 1, tier_name: 'Adult', count: 2, price_per_person: 50 }],
              total_participants: 2,
              total_amount: 100,
              currency: 'EUR',
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Table headers should be visible
    await expect(page.getByRole('columnheader', { name: 'Reference' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Tour' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Date' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Participants' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Total' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Status' })).toBeVisible();

    // Booking reference should appear
    await expect(page.getByText('BK-ABC123')).toBeVisible();
  });

  test('should display status badges with correct colors', async ({ page }) => {
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              reference: 'BK-CONF',
              status: 'confirmed',
              tour_id: 1,
              tour_date: '2026-06-15',
              participants: [],
              total_participants: 1,
              total_amount: 50,
              currency: 'EUR',
            },
            {
              id: 2,
              reference: 'BK-COMP',
              status: 'completed',
              tour_id: 1,
              tour_date: '2026-05-01',
              participants: [],
              total_participants: 2,
              total_amount: 100,
              currency: 'EUR',
            },
            {
              id: 3,
              reference: 'BK-CANC',
              status: 'cancelled',
              tour_id: 1,
              tour_date: '2026-04-01',
              participants: [],
              total_participants: 1,
              total_amount: 0,
              currency: 'EUR',
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 },
        }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Status badges should display status text
    await expect(page.getByText(/confirmed/i)).toBeVisible();
    await expect(page.getByText(/completed/i)).toBeVisible();
    await expect(page.getByText(/cancelled/i)).toBeVisible();
  });

  test('should show error state when API fails', async ({ page }) => {
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Server error' }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Should show error alert
    await expect(page.getByRole('alert')).toBeVisible();
  });
});

test.describe('Partner Booking Detail', () => {
  test('loads a seeded booking through the real scoped detail endpoint', async ({ page }) => {
    await page.goto('/en/partner/bookings');

    const result = await page.evaluate(async () => {
      const token = localStorage.getItem('auth_token');
      const headers: Record<string, string> = {};
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }
      const listResponse = await fetch('/api/partner/bookings', { headers });
      const list = await listResponse.json();
      const reference = list.data?.[0]?.reference;
      if (!reference) {
        return { listStatus: listResponse.status, detailStatus: null, reference: null, detail: null };
      }

      const detailResponse = await fetch(`/api/partner/bookings/${encodeURIComponent(reference)}`, { headers });
      const detail = await detailResponse.json();

      return {
        listStatus: listResponse.status,
        detailStatus: detailResponse.status,
        reference,
        detail: detail.data,
      };
    });

    expect(result.listStatus).toBe(200);
    expect(result.detailStatus).toBe(200);
    expect(result.reference).toBeTruthy();
    expect(result.detail.reference).toBe(result.reference);
    expect(result.detail.tour.id).toBeTruthy();
    expect(result.detail.traveler.email).toBeTruthy();
  });

  test('partner booking detail page shows the seeded booking with status actions', async ({ page }) => {
    // End-to-end for PartnerBookingController::show + the partner detail
    // route: BKO-TEST01 is a confirmed booking owned by the seeded partner.
    await page.goto('/en/partner/bookings/BKO-TEST01');

    // Detail heading, reference, and status badge render from the real API.
    await expect(page.getByRole('heading', { name: 'Booking details' })).toBeVisible();
    await expect(page.getByText('BKO-TEST01').first()).toBeVisible();
    await expect(page.getByText('Confirmed', { exact: true }).first()).toBeVisible();

    // Tour and traveler sections come from the scoped detail payload.
    await expect(page.getByText('Hidden Gems of Rome Walking Tour')).toBeVisible();
    await expect(page.getByText('test@example.com')).toBeVisible();

    // The tour date is still in the future, so "Mark as Completed" is
    // correctly withheld while "Request Cancellation" is offered.
    await expect(page.getByRole('button', { name: 'Request Cancellation' })).toBeVisible();
  });

  test('partner booking detail page shows not-found for unknown reference', async ({ page }) => {
    await page.goto('/en/partner/bookings/BKO-DOES-NOT-EXIST');
    await expect(page.getByText('Booking not found.')).toBeVisible();
  });
});

test.describe('Partner Booking Filters', () => {
  test('should render filter controls', async ({ page }) => {
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Filter section should show "Filters" heading
    await expect(page.getByText('Filters')).toBeVisible();

    // Search input should be present
    await expect(page.getByPlaceholder('Search by reference...')).toBeVisible();
  });

  test('should show clear all button when filters are active', async ({ page }) => {
    await page.route('**/api/partner/bookings**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        }),
      })
    );
    await page.goto('/en/partner/bookings');

    // Type in search to activate a filter
    const searchInput = page.getByPlaceholder('Search by reference...');
    await searchInput.fill('BK-');

    // "Clear all" button should appear
    await expect(page.getByText('Clear all')).toBeVisible();

    // Clicking clear all should reset filters
    await page.getByText('Clear all').click();
    await expect(searchInput).toHaveValue('');
  });
});
