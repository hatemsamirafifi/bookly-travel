import { test, expect } from '@playwright/test';

test.describe('Partner Dashboard Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to partner dashboard (assumes partner is authenticated)
    // In CI, this would use a stored auth state or a login helper
    await page.goto('/en/partner');
  });

  test('should render the partner layout with sidebar and header', async ({ page }) => {
    // The sidebar should be visible on desktop
    const sidebar = page.locator('nav[aria-label]');
    await expect(sidebar).toBeVisible();

    // The header should be visible
    await expect(page.getByText('Partner Dashboard')).toBeVisible();
  });

  test('should render all sidebar navigation items', async ({ page }) => {
    const sidebar = page.locator('nav[aria-label]');

    // Check all nav items exist in sidebar
    await expect(sidebar.getByRole('link', { name: /dashboard/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /tours/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /bookings/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /reviews/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /profile/i })).toBeVisible();
  });

  test('should highlight active sidebar item based on current page', async ({ page }) => {
    // On the dashboard page, the dashboard nav item should be highlighted
    const dashboardLink = page.locator('nav[aria-label] a[href="/partner"], nav[aria-label] a[href$="/partner/"]').first();
    await expect(dashboardLink).toHaveAttribute('aria-current', 'page');

    // Navigate to tours
    await page.locator('nav[aria-label] a').filter({ hasText: /tours/i }).first().click();
    await expect(page).toHaveURL(/\/partner\/tours/);

    // Tours link should now be highlighted
    const toursLink = page.locator('nav[aria-label] a').filter({ hasText: /tours/i }).first();
    await expect(toursLink).toHaveAttribute('aria-current', 'page');
  });

  test('should navigate between all dashboard pages via sidebar', async ({ page }) => {
    const navItems = [
      { label: /dashboard/i, url: /\/partner$/ },
      { label: /tours/i, url: /\/partner\/tours/ },
      { label: /bookings/i, url: /\/partner\/bookings/ },
      { label: /reviews/i, url: /\/partner\/reviews/ },
      { label: /profile/i, url: /\/partner\/profile/ },
    ];

    for (const item of navItems) {
      await page.locator('nav[aria-label] a').filter({ hasText: item.label }).first().click();
      await expect(page).toHaveURL(item.url);
    }
  });

  test('should display app name "Bookly" in sidebar', async ({ page }) => {
    await expect(page.getByText('Bookly')).toBeVisible();
  });

  test('should show mobile hamburger menu on small screens', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    // Mobile menu button should be visible
    const menuButton = page.getByRole('button', { name: /open navigation|menu/i });
    await expect(menuButton).toBeVisible();

    // Click to open mobile drawer
    await menuButton.click();

    // Mobile drawer should appear
    await expect(page.locator('[role="dialog"][aria-modal="true"]')).toBeVisible();
  });

  test('should close mobile drawer when overlay is clicked', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    // Open drawer
    const menuButton = page.getByRole('button', { name: /open navigation|menu/i });
    await menuButton.click();
    await expect(page.locator('[role="dialog"][aria-modal="true"]')).toBeVisible();

    // Click the overlay backdrop
    await page.locator('.fixed.inset-0.bg-black\\/50').click();

    // Drawer should close
    await expect(page.locator('[role="dialog"][aria-modal="true"]')).not.toBeVisible();
  });

  test('should close mobile drawer when navigation link is clicked', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    // Open drawer
    const menuButton = page.getByRole('button', { name: /open navigation|menu/i });
    await menuButton.click();
    await expect(page.locator('[role="dialog"][aria-modal="true"]')).toBeVisible();

    // Click a nav link in the mobile drawer
    await page.locator('[role="dialog"] nav a').filter({ hasText: /tours/i }).first().click();

    // Drawer should close
    await expect(page.locator('[role="dialog"][aria-modal="true"]')).not.toBeVisible();
  });

  test('should show notification bell in header', async ({ page }) => {
    // The notification bell icon should be visible in the header
    const bellButton = page.getByRole('button', { name: /notifications/i });
    await expect(bellButton).toBeVisible();
  });

  test('should show user avatar and name in header', async ({ page }) => {
    // User initial avatar should be visible
    const avatar = page.locator('.rounded-full.bg-\\[\\#0A2540\\]');
    await expect(avatar).toBeVisible();
  });

  test('should toggle user dropdown menu', async ({ page }) => {
    // Click the user menu button
    const userButton = page.locator('header button').filter({ has: page.locator('.rounded-full') }).first();
    await userButton.click();

    // Dropdown should appear with sign out option
    await expect(page.getByText(/sign out/i)).toBeVisible();
  });

  test('should sign out from dropdown menu', async ({ page }) => {
    // Open dropdown
    const userButton = page.locator('header button').filter({ has: page.locator('.rounded-full') }).first();
    await userButton.click();

    // Click sign out
    await page.getByText(/sign out/i).click();

    // Should redirect to login or home
    await expect(page).toHaveURL(/\/(auth\/login|$)/);
  });
});

test.describe('Partner Navigation - Auth Guard', () => {
  test('should redirect unauthenticated users to login', async ({ page }) => {
    await page.goto('/en/partner');

    // Should redirect to login page
    await expect(page).toHaveURL(/\/auth\/login/);

    // URL should include returnUrl parameter
    const url = new URL(page.url());
    expect(url.searchParams.get('returnUrl')).toContain('/partner');
  });

  test('should redirect non-partner users to home', async ({ page }) => {
    // This test requires a traveler-authenticated session
    // The PartnerAuthGuard checks user.role !== 'partner' and redirects to /
    // This would need a fixture for a non-partner authenticated user
  });
});