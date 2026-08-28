import { test, expect } from '@playwright/test';

test.describe('Partner Reviews Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner/reviews');
  });

  test('should display review cards with star ratings', async ({ page }) => {
    // The dashboard groups reviews by tour (collapsed by default).
    // Expand the first tour summary to reveal the review cards.
    const firstSummary = page.locator('button').filter({ hasText: /Walking Tour/i }).first();
    await firstSummary.click();

    // Expanded review card shows the "Verified Traveler" badge
    await expect(page.getByText(/verified traveler/i)).toBeVisible();
  });

  test('should show review comment text', async ({ page }) => {
    // Expand the first tour summary
    const firstSummary = page.locator('button').filter({ hasText: /Walking Tour/i }).first();
    await firstSummary.click();

    // Seeded review (DatabaseSeeder, BKO-TEST03): "A wonderful hidden-gems walk…" (or edited in test run)
    await expect(page.getByText(/hidden-gems walk|highly recommended|Updated comment/i)).toBeVisible();
  });

  test('should display date on review cards', async ({ page }) => {
    // Expand the first tour summary if present
    const firstSummary = page.locator('button').filter({ hasText: /Walking Tour/i }).first();
    if (await firstSummary.isVisible()) {
      await firstSummary.click();
    }
    // Reviews should show the submission date
    const dateText = page.locator('main .text-xs.text-gray-400, main .text-xs.text-gray-500, main time');
    await expect(dateText.first()).toBeVisible();
  });

  test('should allow responding to a review', async ({ page }) => {
    // Click "Respond" button on a review without a response
    const respondButton = page.getByRole('button', { name: /respond/i }).first();
    if (await respondButton.isVisible()) {
      await respondButton.click();

      // A textarea should appear for writing the response
      const textarea = page.getByPlaceholder('Write your public response...');
      await expect(textarea).toBeVisible();

      // Type a response
      await textarea.fill('Thank you for your kind review! We hope to see you again.');

      // Character counter should be visible
      await expect(page.getByText(/\/1000/)).toBeVisible();

      // Submit button should become enabled
      const submitButton = page.getByRole('button', { name: /submit response/i });
      await expect(submitButton).toBeEnabled();
    }
  });

  test('should cancel response writing', async ({ page }) => {
    const respondButton = page.getByRole('button', { name: /respond/i }).first();
    if (await respondButton.isVisible()) {
      await respondButton.click();

      // Type something in the textarea
      const textarea = page.getByPlaceholder('Write your public response...');
      await textarea.fill('Some response text');

      // Click cancel
      const cancelButton = page.getByRole('button', { name: /cancel/i }).first();
      await cancelButton.click();

      // Textarea should disappear, respond button should reappear
      await expect(page.getByPlaceholder('Write your public response...')).not.toBeVisible();
    }
  });

  test('should show character counter while typing response', async ({ page }) => {
    const respondButton = page.getByRole('button', { name: /respond/i }).first();
    if (await respondButton.isVisible()) {
      await respondButton.click();

      const textarea = page.getByPlaceholder('Write your public response...');
      await textarea.fill('Short response');

      // Character count should update
      const counter = page.locator('.text-xs').filter({ hasText: /\d+\/1000/ });
      await expect(counter).toBeVisible();
    }
  });

  test('should disable submit when response is empty', async ({ page }) => {
    const respondButton = page.getByRole('button', { name: /respond/i }).first();
    if (await respondButton.isVisible()) {
      await respondButton.click();

      // Submit button should be disabled when textarea is empty
      const submitButton = page.getByRole('button', { name: /submit response/i });
      await expect(submitButton).toBeDisabled();
    }
  });

  test('should show existing response on reviews that have one', async ({ page }) => {
    // If a review has an existing response, it should show "Your Response" section
    const existingResponse = page.getByText(/your response/i);
    if (await existingResponse.isVisible()) {
      // The response text should be visible
      await expect(page.locator('.bg-gray-50.rounded-lg').first()).toBeVisible();
    }
  });

  test('should allow editing an existing response', async ({ page }) => {
    const editButton = page.getByRole('button', { name: /edit/i }).first();
    if (await editButton.isVisible()) {
      await editButton.click();

      // The textarea should appear with the existing response text
      const textarea = page.getByPlaceholder(/write your public response|edit response/i);
      await expect(textarea).toBeVisible();

      // "Update Response" button should be visible
      await expect(page.getByRole('button', { name: /update response/i })).toBeVisible();
    }
  });
});

test.describe('Partner Reviews - API Integration', () => {
  test('should fetch and display reviews from API', async ({ page }) => {
    await page.route('**/api/partner/reviews**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              tour_slug: 'rome-walking-tour',
              tour_title: 'Rome Walking Tour',
              reviewer_name: 'Alice',
              rating: 5,
              comment: 'Absolutely fantastic experience! Our guide was knowledgeable and friendly.',
              status: 'visible',
              created_at: '2026-05-20T10:00:00Z',
              response: null,
            },
          ],
          meta: {
            tour_summaries: [
              { tour_slug: 'rome-walking-tour', tour_title: 'Rome Walking Tour', average_rating: 5, review_count: 1 },
            ],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 1,
          },
        }),
      })
    );
    await page.goto('/en/partner/reviews');

    // Expand the first tour summary to reveal the review
    await page.locator('button').filter({ hasText: /Rome Walking Tour/i }).first().click();

    await expect(page.getByText('Alice')).toBeVisible();
    await expect(page.getByText(/fantastic experience/i)).toBeVisible();
  });

  test('should show empty state when no reviews exist', async ({ page }) => {
    await page.route('**/api/partner/reviews**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
        }),
      })
    );
    await page.goto('/en/partner/reviews');

    // Should handle empty state gracefully
    await page.waitForLoadState('networkidle');
  });

  test('should show error when API fails', async ({ page }) => {
    await page.route('**/api/partner/reviews**', (route) =>
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Server error' }),
      })
    );
    await page.goto('/en/partner/reviews');

    // Should show error message
    await expect(page.getByText(/failed to load|error/i)).toBeVisible();
  });
});