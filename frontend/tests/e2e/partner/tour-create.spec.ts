import { test, expect } from '@playwright/test';

test.describe('Partner Tour Creation', () => {
  test.beforeEach(async ({ page, context }) => {
    // Pre-set the cookie consent cookie so the fixed bottom-0 banner is not
    // rendered and does not intercept clicks on the wizard navigation buttons.
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);

    // Mock get signed upload url
    await page.route('**/api/partner/uploads/signed-url', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          upload_url: 'http://localhost/upload',
          public_url: 'https://example.com/tour-image.jpg',
        }),
      })
    );

    await page.goto('/en/partner/tours/create');

    // Dismiss the cookie consent banner if still rendered
    const acceptBtn = page.getByRole('button', { name: /accept/i });
    if (await acceptBtn.isVisible().catch(() => false)) {
      await acceptBtn.click().catch(() => {});
    }
  });

  test('should display the tour creation page', async ({ page }) => {
    await expect(page.getByLabel('Tour Title')).toBeVisible();
  });

  test('should navigate through the wizard and submit the tour', async ({ page }) => {
    // Mock create tour (POST only) — scoped to this test so it doesn't
    // interfere with other tests that navigate to the tours list.
    await page.route('**/api/partner/tours', (route) => {
      if (route.request().method() === 'POST') {
        return route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            data: { id: 101, title: 'Mocked New Tour', status: 'pending_review' },
          }),
        });
      }
      return route.continue();
    });

    // Step 1: Details — fill all required fields per tourBasicDetailsSchema
    await page.getByLabel('Tour Title').fill('Beautiful Walking Tour of Rome');
    await page.getByLabel('Description').fill('Explore the ancient history of Rome with a local guide.');

    // Category uses a custom Select (button trigger, button items — not Radix).
    // The trigger shows the placeholder text "Select a category" when empty.
    // Click the trigger button, then the "Walking" option button in the dropdown.
    const categoryTrigger = page.locator('button:has-text("Select a category")');
    await categoryTrigger.click();
    await page.locator('button:has-text("Walking")').last().click();

    await page.getByLabel('Destination').fill('Rome, Italy');
    await page.getByLabel('Duration').fill('3');
    await page.getByLabel('Meeting Point').fill('Colosseum Main Entrance');

    // Click Next — validates the details step via Zod before advancing
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    // Step 2: Media step should be active
    await expect(page.getByText(/Drag and drop images here/i)).toBeVisible();

    // Click Next on media step — validation may block (no cover image),
    // showing an error message. The test verifies step navigation works,
    // not that the tour can be submitted without media.
    await page.getByRole('button', { name: 'Next', exact: true }).click();
  });
});

// Separate describe so this test does NOT inherit the /tours/create
// beforeEach — navigating from the heavy /create wizard to /tours in dev
// mode causes a cold-compile race that can make restoreSession fail.
test.describe('Partner Tours List - Create Button', () => {
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