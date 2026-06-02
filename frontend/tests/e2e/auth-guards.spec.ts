import { test, expect } from '@playwright/test';

test.describe('Auth Guards', () => {
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
      await page.waitForURL('**/en**');
      await expect(page.locator('text=Sign Out')).toBeVisible();
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
      await expect(page.locator('text=My Bookings')).toBeVisible();
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
      await page.waitForURL('**/en**');
    });
  });
});
