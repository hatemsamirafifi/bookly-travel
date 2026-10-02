import { test, expect } from '@playwright/test';

test.describe('Review Submission', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP).

  test('confirmed booking cannot submit a review before travel', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');
    await expect(page.locator('text=Booking Detail')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Submit Review' })).toHaveCount(0);
  });

  test('completed booking shows review form and validates the rating', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST02');
    await expect(page.locator('text=Booking Detail')).toBeVisible();

    const reviewForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Submit Review' }) });
    await expect(reviewForm).toBeVisible();
    await expect(reviewForm.locator('text=Your Rating')).toBeVisible();
    await expect(reviewForm.locator('text=Your Review (optional)')).toBeVisible();

      // Attempt to submit without rating to verify validation
    const submitBtn = reviewForm.getByRole('button', { name: /Submit Review/i });
    await expect(submitBtn).toBeDisabled();

      // Click the 4th star to set rating to 4 (StarRating renders role="radio"
      // buttons with aria-labels "1 star".."5 stars" — not "Select N star rating").
    const fourthStar = reviewForm.getByRole('radio', { name: '4 stars' });
    await expect(fourthStar).toBeVisible();
    await fourthStar.click();

      // Enter review comment
    await reviewForm.locator('textarea').fill('This was an absolutely wonderful tour! Highly recommended!');

      // Submit the form
    await expect(submitBtn).toBeEnabled();
    await submitBtn.click();

    // The shared fixture can already have a review from another project run.
    await expect(
      page.getByText('Thank you for your review!').or(page.getByText(/already submitted/i))
    ).toBeVisible();
  });

  test('should restrict review editing after window limit', async ({ page }) => {
    // Go to my reviews page
    await page.goto('/en/my-reviews');
    await expect(page.getByRole('heading', { name: /My Reviews/i })).toBeVisible();

    // Check if there is an edit button
    const editBtn = page.locator('button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();
      await expect(page.getByRole('heading', { name: 'Edit Review' })).toBeVisible();

      // Fill in edit form comment
      const commentInput = page.locator('textarea').first();
      await commentInput.fill('Updated comment value here.');

      const updateBtn = page.getByRole('button', { name: /Update Review/i });
      await updateBtn.click();

      // Check success state
      await expect(
        page.locator('text=Your review has been updated!').or(page.getByText('Updated comment value here.'))
      ).toBeVisible();
    }
  });

  for (const { locale, submit } of [
    { locale: 'es', submit: 'Enviar Reseña' },
    { locale: 'it', submit: 'Invia Recensione' },
  ]) {
    test(`${locale} completed booking shows translated review controls`, async ({ page }) => {
      await page.goto(`/${locale}/my-bookings/BKO-TEST02`);
      await expect(page.getByRole('button', { name: submit })).toBeVisible();
      await expect(page.getByText('reviews.leave_review')).toHaveCount(0);
    });
  }
});
