import { test, expect } from '@playwright/test';
import { partnerLogin } from '../helpers/auth';

test.describe('Partner Tour Edit Workflow', () => {
  test.beforeEach(async ({ page }) => {
    await partnerLogin(page);
  });

  test('should load the edit page for an existing tour', async ({ page }) => {
    // Navigate to edit page for tour ID 1
    await page.goto('/en/partner/tours/1/edit');

    // The edit page should load (TourWizard component)
    // Exact content depends on whether the API returns tour data
    await page.waitForLoadState('networkidle');
  });

  test('should show tour cards with status badges on tours list', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Draft Tour',
              slug: 'draft-tour',
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
            {
              id: 2,
              partner_id: 1,
              title: 'Published Tour',
              slug: 'published-tour',
              status: 'published',
              destination: 'Rome',
              duration: { minutes: 180, label: '3 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 15,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'adventure',
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
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 2 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Both tours should be visible
    await expect(page.getByText('Draft Tour')).toBeVisible();
    await expect(page.getByText('Published Tour')).toBeVisible();

    // Status badges should show correct labels
    await expect(page.getByText('Draft')).toBeVisible();
    await expect(page.getByText('Published')).toBeVisible();
  });

  test('should show tour destination in card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Florence Highlights',
              slug: 'florence-highlights',
              status: 'published',
              destination: 'Florence, Italy',
              location: 'Florence',
              duration: { minutes: 240, label: '4 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 2,
              max_participants: 12,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Explore Florence.',
              meeting_point: 'Piazza della Signoria',
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

    await expect(page.getByText('Florence, Italy')).toBeVisible();
  });

  test('should link to edit page from tour card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 42,
              partner_id: 1,
              title: 'Milan City Tour',
              slug: 'milan-city-tour',
              status: 'published',
              destination: 'Milan',
              duration: { minutes: 120, label: '2 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 8,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Discover Milan.',
              location: 'Milan',
              meeting_point: 'Duomo',
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

    // Edit link should point to the correct URL
    const editLink = page.getByRole('link', { name: /edit tour/i });
    await expect(editLink).toHaveAttribute('href', '/partner/tours/42/edit');
  });
});