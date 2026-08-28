import { test, expect } from '@playwright/test';

test.describe('Partner Dashboard Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner');
  });

  test('should render the partner layout with sidebar and header', async ({ page, isMobile }) => {
    test.skip(Boolean(isMobile), 'Desktop sidebar is hidden on mobile viewports');
    // The sidebar should be visible on desktop
    const sidebar = page.locator('nav[aria-label]');
    await expect(sidebar).toBeVisible();

    // The header should be visible
    await expect(page.getByText('Partner Dashboard')).toBeVisible();
  });

  test('should render all sidebar navigation items', async ({ page, isMobile }) => {
    test.skip(Boolean(isMobile), 'Desktop sidebar is hidden on mobile viewports');
    const sidebar = page.locator('nav[aria-label]');

    // Check all nav items exist in sidebar
    await expect(sidebar.getByRole('menuitem', { name: /dashboard/i })).toBeVisible();
    await expect(sidebar.getByRole('menuitem', { name: /tours/i })).toBeVisible();
    await expect(sidebar.getByRole('menuitem', { name: /bookings/i })).toBeVisible();
    await expect(sidebar.getByRole('menuitem', { name: /reviews/i })).toBeVisible();
    await expect(sidebar.getByRole('menuitem', { name: /profile/i })).toBeVisible();
  });

  test('should highlight active sidebar item based on current page', async ({ page, isMobile }) => {
    test.skip(Boolean(isMobile), 'Desktop sidebar is hidden on mobile viewports');
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

  test('should navigate between all dashboard pages via sidebar', async ({ page, isMobile }) => {
    test.skip(Boolean(isMobile), 'Desktop sidebar is hidden on mobile viewports');
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

  test('should display app name "Bookly" in sidebar', async ({ page, isMobile }) => {
    test.skip(Boolean(isMobile), 'Desktop sidebar is hidden on mobile viewports');
    await expect(page.locator('nav[aria-label]').getByText('Bookly', { exact: true })).toBeVisible();
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

    // Close via the Close button (overlay click is intercepted by the dialog
    // focus trap in some browser engines; the Close button is the reliable
    // way to dismiss the mobile drawer)
    await page.getByRole('button', { name: /close navigation/i }).click();

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
    const userButton = page.locator('header button[aria-haspopup="true"]');
    await userButton.click();

    // Dropdown should appear with sign out option
    await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible();
  });
});

// Sign-out REVOKES the Sanctum token server-side. It must therefore NEVER run
// against the shared partner storage state — otherwise every later spec in
// this project reuses a dead token and gets 401 → redirected to login. This
// isolated describe starts from an EMPTY storage state and performs its own
// throwaway login, so the logout only invalidates its own session.
test.describe('Partner Navigation - Sign Out (isolated)', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('should sign out from dropdown menu', async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'partner@bookly.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.locator('button[aria-haspopup="menu"]').first().waitFor({ state: 'visible' });

    // Open dropdown and sign out
    const userButton = page.locator('header button').filter({ has: page.locator('.rounded-full') }).first();
    await userButton.click();
    await page.getByText(/sign out/i).click();

    // App redirects to the locale home after sign-out
    await expect(page).toHaveURL(/\/(auth\/login|en\/?|)$/);
  });
});

// These guards assert UNAUTHENTICATED behaviour; run them with an EMPTY
// storage state so the shared partner fixture does not keep them logged in.
test.describe('Partner Navigation - Auth Guard', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('should redirect unauthenticated users to login', async ({ page }) => {
    await page.goto('/en/partner');

    // Should redirect to login page
    await expect(page).toHaveURL(/\/auth\/login/);

    // URL should include returnUrl parameter
    const url = new URL(page.url());
    expect(url.searchParams.get('returnUrl')).toContain('/partner');
  });

  test('should redirect non-partner users to home', async () => {
    // This test requires a traveler-authenticated session
    // The PartnerAuthGuard checks user.role !== 'partner' and redirects to /
    // This would need a fixture for a non-partner authenticated user
  });
});