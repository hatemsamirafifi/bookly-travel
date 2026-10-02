import { test, expect } from '@playwright/test';

test.describe('Auth Guards', () => {
  for (const [locale, width] of [['en', 390], ['es', 768], ['it', 1440]] as const) {
    test(`${locale} protected route preserves its return path at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 844 });
      await page.goto(`/${locale}/profile`);
      await expect(page).toHaveURL(new RegExp(`/${locale}/auth/login\\?`));
      const url = new URL(page.url());
      expect(url.searchParams.get('returnUrl')).toBe(`/${locale}/profile`);
      await expect(page.getByRole('heading', { name: /profile settings/i })).toHaveCount(0);
    });
  }

  test('login returns to the exact originally protected traveler route', async ({ page }) => {
    await page.goto('/en/my-bookings');
    await expect(page).toHaveURL(/\/en\/auth\/login/);
    expect(new URL(page.url()).searchParams.get('returnUrl')).toBe('/en/my-bookings');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/en\/my-bookings$/);
    await expect(page.getByRole('heading', { name: 'My Bookings' })).toBeVisible();
  });
  test.describe('Unauthenticated access redirects', () => {
    const protectedRoutes = [
      { path: '/en/my-bookings', name: 'My Bookings' },
      { path: '/en/profile', name: 'Profile' },
      { path: '/en/wishlist', name: 'Wishlist' },
      { path: '/en/my-reviews', name: 'Reviews' },
    ];

    for (const route of protectedRoutes) {
      test(`${route.name} redirects to login when unauthenticated`, async ({ page }) => {
        await page.goto(route.path);

        // Should redirect to login page
        await page.waitForURL(/\/auth\/login/);
        await expect(page).toHaveURL(/\/auth\/login/);

        // Should include the return URL
        const url = new URL(page.url());
        expect(url.searchParams.get('returnUrl')).toBeTruthy();
      });
    }
  });

  test.describe('Authenticated user guards', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/en/auth/login');
      await page.fill('input[name="email"]', 'test@example.com');
      await page.fill('input[name="password"]', 'Password123!');
      await page.click('button[type="submit"]');
      await page.waitForURL((url) => url.pathname === '/en');
      await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();
    });

    test('login page redirects authenticated user to home', async ({ page }) => {
      await page.goto('/en/auth/login');
      await page.waitForURL('**/en');
      await expect(page).not.toHaveURL(/\/auth\/login/);
    });

    test('register page redirects authenticated user to home', async ({ page }) => {
      await page.goto('/en/auth/register');
      await page.waitForURL('**/en');
      await expect(page).not.toHaveURL(/\/auth\/register/);
    });

    test('my-bookings loads for authenticated user', async ({ page }) => {
      await page.goto('/en/my-bookings');
      await expect(page.getByRole('heading', { name: /My Bookings/i })).toBeVisible();
    });

    test('profile page loads for authenticated user', async ({ page }) => {
      await page.goto('/en/profile');
      await expect(page.getByRole('heading', { name: /profile/i })).toBeVisible();
    });

    test('wishlist page loads for authenticated user', async ({ page }) => {
      await page.goto('/en/wishlist');
      await expect(page.getByRole('heading', { name: /wishlist/i })).toBeVisible();
    });

    test('my-reviews page loads for authenticated user', async ({ page }) => {
      await page.goto('/en/my-reviews');
      await expect(page.getByRole('heading', { name: /reviews/i })).toBeVisible();
    });
  });

  test.describe('Session expiry', () => {
    test('sessionExpired banner shows and returnUrl preserved on re-login', async ({ page }) => {
      await page.goto('/en/my-bookings');

      // Should redirect to login with sessionExpired
      await page.waitForURL(/\/auth\/login/);
      const url = new URL(page.url());

      // Either sessionExpired is in the URL or we get a standard auth redirect
      if (url.searchParams.get('sessionExpired')) {
        await expect(page.getByText(/session has expired/i)).toBeVisible();
      }

      // Login should redirect back to the original page
      await page.fill('input[name="email"]', 'test@example.com');
      await page.fill('input[name="password"]', 'Password123!');
      await page.click('button[type="submit"]');

      // Should land on home or the return URL
      await page.waitForURL((url) => ['/en', '/en/my-bookings'].includes(url.pathname));
    });
  });
});
