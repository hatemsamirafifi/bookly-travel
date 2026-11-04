import { test, expect, type APIRequestContext, type Page } from '@playwright/test';

test.describe('Wishlist', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP) and the old
  // `text=Sign Out` check failed because that action is in a collapsed dropdown.

  // Spec 020 US5: every mutating test below operates ONLY on this owned
  // public tour, snapshots its authenticated saved status first, exercises
  // the actual UI/API, and restores exactly that tour's original state in a
  // finally via the existing wishlist API (cleanup failure surfaces as a
  // test failure). No bulk clears, no unrelated items touched. Tokens stay
  // in memory (localStorage read); nothing is logged or written to artifacts.
  const OWNED_SLUG = 'hidden-gems-rome-walking-tour';

  async function ownedTourId(request: APIRequestContext): Promise<number> {
    const response = await request.get(`/api/public/tours/${OWNED_SLUG}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    expect(typeof data.id).toBe('number');
    return data.id as number;
  }

  async function authHeaders(page: Page): Promise<Record<string, string>> {
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));
    expect(token).toBeTruthy();
    return { Authorization: `Bearer ${token}` };
  }

  async function ownedSavedStatus(
    request: APIRequestContext,
    headers: Record<string, string>,
    tourId: number,
  ): Promise<boolean> {
    const response = await request.get(
      `/api/public/traveler/wishlist/status?tour_ids[]=${tourId}`,
      { headers },
    );
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    return Boolean(data[String(tourId)]);
  }

  async function setOwnedSaved(
    request: APIRequestContext,
    headers: Record<string, string>,
    tourId: number,
    saved: boolean,
  ): Promise<void> {
    if (saved) {
      const response = await request.post('/api/public/traveler/wishlist', {
        headers,
        data: { tour_id: tourId },
      });
      expect(response.ok()).toBeTruthy();
    } else {
      const response = await request.delete(`/api/public/traveler/wishlist/${tourId}`, { headers });
      expect([200, 204]).toContain(response.status());
    }
  }

  function ownedHeaderButton(page: Page, tourId: number) {
    return page.locator(`main [data-testid="wishlist-button"][data-tour-id="${tourId}"]`);
  }

  test('wishlist page renders', async ({ page }) => {
    await page.goto('/en/wishlist');

    await expect(page.getByRole('heading', { name: /Wishlist/i })).toBeVisible();
  });

  test('wishlist shows empty state or grid', async ({ page }) => {
    await page.goto('/en/wishlist');

    // Empty state ("Your wishlist is empty.") when the traveler has no saved
    // tours, otherwise the populated grid ([data-testid="wishlist-grid"]).
    await expect(
      page.getByText('Your wishlist is empty.').or(page.locator('[data-testid="wishlist-grid"]')).first()
    ).toBeVisible();
  });

  test('owned tour header button toggles saved state with real API and restores it', async ({
    page,
    request,
  }) => {
    await page.goto(`/en/tours/${OWNED_SLUG}`);
    const headers = await authHeaders(page);
    const tourId = await ownedTourId(request);
    const originalSaved = await ownedSavedStatus(page.request, headers, tourId);

    const button = ownedHeaderButton(page, tourId);
    // Exactly one current-tour control, settled to the snapshotted API state.
    await expect(button).toHaveCount(1);
    await expect(button).toHaveAttribute('aria-pressed', String(originalSaved));

    try {
      const flipped = String(!originalSaved);
      const mutation = page.waitForResponse(
        (response) =>
          response.url().includes('/api/public/traveler/wishlist') &&
          ['POST', 'DELETE'].includes(response.request().method()),
      );
      await button.click();
      await expect(button).toHaveAttribute('aria-pressed', flipped);
      expect((await mutation).ok()).toBe(true);
      await page.reload();
      await expect(ownedHeaderButton(page, tourId)).toHaveAttribute('aria-pressed', flipped);
    } finally {
      await setOwnedSaved(page.request, headers, tourId, originalSaved);
      await page.reload();
      await expect(ownedHeaderButton(page, tourId)).toHaveAttribute(
        'aria-pressed',
        String(originalSaved),
      );
    }
  });

  test('denied wishlist toggle rolls back with a localized announcement', async ({ page, request }) => {
    await page.goto(`/en/tours/${OWNED_SLUG}`);
    const headers = await authHeaders(page);
    const tourId = await ownedTourId(request);
    const originalSaved = await ownedSavedStatus(page.request, headers, tourId);

    try {
      if (originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, false);
      }
      // Establish the real UI status after provisioning before intercepting.
      await page.reload();
      const button = ownedHeaderButton(page, tourId);
      await expect(button).toHaveAttribute('aria-pressed', 'false');

      // Controlled failure only at the protected mutation boundary; the status
      // read stays real so the button settles to a known unsaved state.
      await page.route(
        '**/api/public/traveler/wishlist**',
        async (route) => {
          if (['POST', 'DELETE'].includes(route.request().method())) {
            await route.fulfill({ status: 403, contentType: 'application/json', body: '{}' });
          } else {
            await route.continue();
          }
        },
      );

      await button.click();
      // Optimistic flip, then real rollback with the denied announcement and
      // no false success.
      await expect(page.locator('main#main-content').getByRole('alert')).toHaveText('You cannot update this wishlist.');
      await expect(button).toHaveAttribute('aria-pressed', 'false');
      await expect(button).toBeEnabled();
      // The booking participant count is also a live status. Only wishlist
      // success announcements would falsely report this rejected mutation.
      await expect(page.getByRole('status').filter({
        hasText: /^(Saved to your wishlist\.|Removed from your wishlist\.)$/,
      })).toHaveCount(0);
    } finally {
      await page.unroute('**/api/public/traveler/wishlist**');
      const current = await ownedSavedStatus(page.request, headers, tourId);
      if (current !== originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, originalSaved);
      }
    }
  });

  test('failed wishlist toggle rolls back with a localized announcement', async ({ page, request }) => {
    await page.goto(`/en/tours/${OWNED_SLUG}`);
    const headers = await authHeaders(page);
    const tourId = await ownedTourId(request);
    const originalSaved = await ownedSavedStatus(page.request, headers, tourId);

    try {
      if (originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, false);
      }
      // Establish the real UI status after provisioning before intercepting.
      await page.reload();
      const button = ownedHeaderButton(page, tourId);
      await expect(button).toHaveAttribute('aria-pressed', 'false');

      await page.route(
        '**/api/public/traveler/wishlist**',
        async (route) => {
          if (['POST', 'DELETE'].includes(route.request().method())) {
            await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' });
          } else {
            await route.continue();
          }
        },
      );

      await button.click();
      await expect(page.locator('main#main-content').getByRole('alert')).toHaveText(
        'Could not update your wishlist. Try again.',
      );
      await expect(button).toHaveAttribute('aria-pressed', 'false');
      await expect(button).toBeEnabled();
      await expect(page.getByRole('status').filter({
        hasText: /^(Saved to your wishlist\.|Removed from your wishlist\.)$/,
      })).toHaveCount(0);
    } finally {
      await page.unroute('**/api/public/traveler/wishlist**');
      const current = await ownedSavedStatus(page.request, headers, tourId);
      if (current !== originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, originalSaved);
      }
    }
  });

  test('removing the owned tour from the wishlist grid restores its original state', async ({
    page,
    request,
  }) => {
    await page.goto(`/en/tours/${OWNED_SLUG}`);
    const headers = await authHeaders(page);
    const tourId = await ownedTourId(request);
    const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
    // Provision the owned item so the removal behavior executes unconditionally.
    try {
      if (!originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, true);
      }
      await page.goto('/en/wishlist');
      const ownedCard = page
        .locator('[data-testid="wishlist-grid"] article', {
          has: page.locator(`a[href$="/tours/${OWNED_SLUG}"]`),
        });
      await expect(ownedCard).toHaveCount(1);
      const removeButton = ownedCard.getByRole('button', { name: /remove/i });
      await expect(removeButton).toBeVisible();
      const removal = page.waitForResponse(
        (response) =>
          response.url().includes('/api/public/traveler/wishlist/') &&
          response.request().method() === 'DELETE',
      );
      await removeButton.click();
      expect((await removal).status()).toBe(204);
      await expect(ownedCard).toHaveCount(0);
    } finally {
      const current = await ownedSavedStatus(page.request, headers, tourId);
      if (current !== originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, originalSaved);
      }
    }
  });

  test('owned wishlist item links to its tour detail', async ({ page, request }) => {
    await page.goto(`/en/tours/${OWNED_SLUG}`);
    const headers = await authHeaders(page);
    const tourId = await ownedTourId(request);
    const originalSaved = await ownedSavedStatus(page.request, headers, tourId);
    // Provision the owned item so the link behavior executes unconditionally.
    try {
      if (!originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, true);
      }
      await page.goto('/en/wishlist');
      const ownedLink = page
        .locator('[data-testid="wishlist-grid"]')
        .locator(`a[href$="/tours/${OWNED_SLUG}"]`)
        .first();
      await expect(ownedLink).toBeVisible();
      await ownedLink.click();
      await page.waitForURL(new RegExp(`/en/tours/${OWNED_SLUG}`));
      await expect(page.locator('h1')).toBeVisible();
    } finally {
      const current = await ownedSavedStatus(page.request, headers, tourId);
      if (current !== originalSaved) {
        await setOwnedSaved(page.request, headers, tourId, originalSaved);
      }
    }
  });
});

test.describe('Guest Wishlist Lifecycle', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test.beforeEach(async ({ context }) => {
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);
  });

  test('guest wishlist bookmarks tour to localStorage and persists across page reload', async ({ page }) => {
    await page.goto('/en/search');
    const firstHeart = page.locator('button[data-testid="wishlist-button"]').first();
    await expect(firstHeart).toBeVisible();
    await firstHeart.click();
    await expect(firstHeart).toHaveAttribute('aria-pressed', 'true');

    // Verify stored in localStorage
    const stored = await page.evaluate(() => localStorage.getItem('bookly_guest_wishlist'));
    expect(stored).toBeTruthy();
    const parsed = JSON.parse(stored || '{}');
    expect(parsed.tour_ids.length).toBeGreaterThan(0);

    // Reload page and verify still active
    await page.reload({ waitUntil: 'networkidle' });
    const reloadedHeart = page.locator('button[data-testid="wishlist-button"]').first();
    await expect(reloadedHeart).toHaveAttribute('aria-pressed', 'true');
  });

  test('guest favorites merge into the account on login and localStorage is cleared', async ({ page }) => {
    await page.goto('/en/search');
    const firstHeart = page.locator('button[data-testid="wishlist-button"]').first();
    await expect(firstHeart).toBeVisible();
    await firstHeart.click();
    await expect(firstHeart).toHaveAttribute('aria-pressed', 'true');

    const stored = await page.evaluate(() => localStorage.getItem('bookly_guest_wishlist'));
    expect(stored).toBeTruthy();
    expect(JSON.parse(stored || '{}').tour_ids.length).toBeGreaterThan(0);

    // Exactly ONE login flow in this test: the backend `auth` rate limiter is
    // shared, so reuse this page context for the whole lifecycle. Arm the merge
    // listener BEFORE navigating so the trigger fired by LoginForm is not missed.
    const mergeResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('/api/public/traveler/wishlist/merge') &&
        resp.request().method() === 'POST',
      { timeout: 30000 },
    );
    await page.goto('/en/auth/login');
    await page.locator('input[name="email"]').waitFor({ state: 'visible', timeout: 30000 });
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');

    const mergeResponse = await mergeResponsePromise;
    expect(mergeResponse.status()).toBe(200);
    const mergePayload = await mergeResponse.json();
    expect(mergePayload.status).toBe('success');
    // merged_count can legitimately be 0 if the seeded account already holds
    // the bookmarked tour; the total always covers the guest item either way.
    expect(mergePayload.merged_count).toBeGreaterThanOrEqual(0);
    expect(mergePayload.total_wishlist_count).toBeGreaterThanOrEqual(1);

    // A successful merge purges the guest store.
    await expect.poll(async () => page.evaluate(() => localStorage.getItem('bookly_guest_wishlist'))).toBeNull();

    // The bookmarked tour is now backed by the account wishlist.
    await page.goto('/en/search');
    await expect(page.locator('button[data-testid="wishlist-button"]').first()).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });
});
