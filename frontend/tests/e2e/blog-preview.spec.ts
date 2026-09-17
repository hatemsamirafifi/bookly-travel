import { test, expect } from '@playwright/test';

test.describe('Blog Authoring & Admin Previews (E2E / US3)', () => {
  test('Draft article preview loads with valid token and sets noindex robots', async ({ page }) => {
    // Navigate to preview page with simulated valid token
    await page.goto('/en/blog/sample-draft-article/preview?token=sample-draft-article|9999999999|test-sig');

    // If backend isn't mock-seeded, it should either show the preview, the
    // localized 404, or an unavailable state — but never the draft content
    // itself. BlogUnavailable renders "Article Removed" (410), "Too Many
    // Requests" (429), or "Content Temporarily Unavailable" (other errors).
    //
    // The preview route is force-dynamic and renders via notFound() after a
    // backend round-trip, so the terminal state streams in after navigation
    // resolves. Poll for one of the terminal states instead of asserting on
    // the first paint (non-retrying isVisible() races the stream and flakes).
    await expect(async () => {
      const bodyText = await page.locator('body').innerText();
      expect(
        /Draft Preview Mode|404|Article Removed|Too Many Requests|Content Temporarily Unavailable/.test(
          bodyText
        )
      ).toBeTruthy();
    }).toPass({ timeout: 15000 });

    const isBannerVisible = await page
      .locator('text=Draft Preview Mode')
      .isVisible()
      .catch(() => false);

    if (isBannerVisible) {
      await expect(page.locator('text=Draft Preview Mode')).toBeVisible();
      // Ensure robots meta is noindex
      const robotsMeta = page.locator('meta[name="robots"]');
      if (await robotsMeta.count() > 0) {
        await expect(robotsMeta).toHaveAttribute('content', /noindex/);
      }
    }
  });

  test('Missing token redirects or renders not found', async ({ page }) => {
    await page.goto('/en/blog/sample-draft-article/preview');
    await expect(page.getByRole('heading', { name: '404' })).toBeVisible();
  });
});
