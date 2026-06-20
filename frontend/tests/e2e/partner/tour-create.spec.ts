import { test, expect } from '@playwright/test';

test.describe('Partner Tour Creation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner/tours/create');
  });

  test('should display the tour creation page', async ({ page }) => {
    // The create page should render (exact heading depends on TourWizard implementation)
    await expect(page.getByRole('heading', { level: 1 }).or(page.getByRole('heading', { level: 2 }).or(page.getByText(/create|new tour/i)))).toBeVisible();
  });

  test('should display Create Tour button on tours list page', async ({ page }) => {
    await page.goto('/en/partner/tours');

    // "Create Tour" button with Plus icon should be visible
    const createLink = page.getByRole('link', { name: /create tour/i });
    await expect(createLink).toBeVisible();
  });
});

test.describe('Partner Tour Edit', () => {
  test('should navigate to edit page from tour card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Editable Tour',
              slug: 'editable-tour',
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

    // Click the edit link on the tour card
    const editLink = page.getByRole('link', { name: /edit tour/i });
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', '/partner/tours/1/edit');

    // Navigate to edit page
    await editLink.click();
    await expect(page).toHaveURL(/\/partner\/tours\/1\/edit/);
  });
});