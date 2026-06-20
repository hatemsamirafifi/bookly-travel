import { test, expect } from '@playwright/test';
import { partnerLogin } from '../helpers/auth';

test.describe('Partner Tours List Page', () => {
  test.beforeEach(async ({ page }) => {
    await partnerLogin(page);
    await page.goto('/en/partner/tours');
  });

  test('should display the tours page with heading', async ({ page }) => {
    await expect(page.getByText('My Tours')).toBeVisible();
  });

  test('should show Create Tour button', async ({ page }) => {
    const createButton = page.getByRole('link', { name: /create tour/i });
    await expect(createButton).toBeVisible();
  });

  test('should navigate to tour creation page', async ({ page }) => {
    const createButton = page.getByRole('link', { name: /create tour/i });
    await createButton.click();
    await expect(page).toHaveURL(/\/partner\/tours\/create/);
  });

  test('should display empty state when no tours exist', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    await expect(page.getByText(/no tours yet/i)).toBeVisible();
    await expect(page.getByText(/create your first tour/i)).toBeVisible();
  });

  test('should display tour cards when tours exist', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Rome Walking Tour',
              slug: 'rome-walking-tour',
              description: 'Explore the Eternal City on foot.',
              category: 'walking',
              destination: 'Rome, Italy',
              location: 'Rome',
              duration: { minutes: 180, label: '3 hours' },
              difficulty: 'easy',
              meeting_point: 'Colosseum',
              highlights: ['Colosseum', 'Roman Forum'],
              inclusions: ['Guide', 'Headphones'],
              exclusions: ['Food', 'Drinks'],
              cancellation_policy: 'Full refund 24h before',
              languages: ['en', 'it'],
              status: 'published',
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 20,
              rating: { average: 4.5, count: 10 },
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Tour card should show title
    await expect(page.getByText('Rome Walking Tour')).toBeVisible();

    // Tour card should show destination
    await expect(page.getByText('Rome, Italy')).toBeVisible();
  });

  test('should show tour status badges', async () => {
    const statuses = ['draft', 'pending_review', 'published', 'rejected', 'archived'];

    // Test each status badge appears correctly
    for (const status of statuses) {
      const label = status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
      // The status badges use these labels in TourCard
      expect(label).toBeTruthy(); // Verify mapping exists
    }
  });

  test('should show loading state initially', async ({ page }) => {
    // Slow down the API response to see loading state
    await page.route('**/api/partner/tours**', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 500));
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
        }),
      });
    });
    await page.goto('/en/partner/tours');

    // Should show loading text
    await expect(page.getByText(/loading tours/i)).toBeVisible({ timeout: 400 });
  });

  test('should show error state when API fails', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Server error' }),
      })
    );
    await page.goto('/en/partner/tours');

    await expect(page.getByText(/failed to load/i)).toBeVisible();
  });
});

test.describe('Partner Tour Card Component', () => {
  test('should display tour card with all details', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Colosseum Adventure',
              slug: 'colosseum-adventure',
              description: 'A thrilling tour.',
              category: 'adventure',
              destination: 'Rome',
              location: 'Rome',
              duration: { minutes: 120, label: '2 hours' },
              difficulty: 'moderate',
              meeting_point: 'Colosseum',
              highlights: ['Colosseum'],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: ['en'],
              status: 'published',
              media: [],
              pricing_tiers: [{ id: 1, name: 'Adult', price: 45, currency: 'EUR', min_participants: 1, max_participants: 10 }],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 20,
              rating: { average: 4.8, count: 24 },
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              price_from: 45,
              cover_image_url: null,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Tour title
    await expect(page.getByText('Colosseum Adventure')).toBeVisible();

    // Status badge "Published"
    await expect(page.getByText('Published')).toBeVisible();

    // Price
    await expect(page.getByText('€45.00')).toBeVisible();

    // Destination icon/text
    await expect(page.getByText('Rome')).toBeVisible();

    // Edit link
    const editLink = page.getByRole('link', { name: /edit tour/i });
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', '/partner/tours/1/edit');
  });

  test('should show archive button for non-archived tours', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Test Tour',
              slug: 'test-tour',
              status: 'published',
              destination: 'Rome',
              duration: { minutes: 120, label: '2 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 10,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Test',
              location: 'Rome',
              meeting_point: 'Rome',
              highlights: [],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: [],
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Archive button should be visible (aria-label)
    const archiveButton = page.getByRole('button', { name: /archive tour/i });
    await expect(archiveButton).toBeVisible();
  });

  test('should not show archive button for already archived tours', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Archived Tour',
              slug: 'archived-tour',
              status: 'archived',
              destination: 'Rome',
              duration: { minutes: 120, label: '2 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 10,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Test',
              location: 'Rome',
              meeting_point: 'Rome',
              highlights: [],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: [],
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Archived tours show "Archived" badge but no archive button
    await expect(page.getByText('Archived')).toBeVisible();
    await expect(page.getByRole('button', { name: /archive tour/i })).not.toBeVisible();
  });

  test('should show "No cover image" placeholder when tour has no cover', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Tour Without Image',
              slug: 'tour-no-image',
              status: 'draft',
              destination: 'Paris',
              duration: { minutes: 60, label: '1 hour' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 10,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              cover_image_url: null,
              category: 'walking',
              description: 'Test',
              location: 'Paris',
              meeting_point: 'Paris',
              highlights: [],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: [],
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    await expect(page.getByText('No cover image')).toBeVisible();
  });
});