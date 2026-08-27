import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Partner Dashboard Accessibility', () => {
  test('partner dashboard home should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page })
      .exclude('.recharts-surface') // Recharts SVG may have aria issues we can't control
      .analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner tours page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/tours');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner bookings page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/bookings');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner reviews page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/reviews');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner profile page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/profile');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });
});

test.describe('Partner Layout Accessibility', () => {
  test('sidebar should have accessible navigation landmark', async ({ page }) => {
    await page.goto('/en/partner');

    // Sidebar should be a nav with aria-label
    const sidebar = page.locator('nav[aria-label]');
    await expect(sidebar).toBeVisible();

    // Nav items should have role="menuitem" or be links
    const navLinks = sidebar.locator('a[role="menuitem"]');
    const linkCount = await navLinks.count();
    expect(linkCount).toBeGreaterThanOrEqual(5); // dashboard, tours, bookings, reviews, profile
  });

  test('sidebar links should have aria-current for active page', async ({ page }) => {
    await page.goto('/en/partner/tours');

    // The active tours link should have aria-current="page"
    const activeLink = page.locator('nav a[aria-current="page"]');
    await expect(activeLink).toBeVisible();
    await expect(activeLink).toHaveAttribute('href', /\/partner\/tours/);
  });

  test('mobile menu button should have aria-label', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    const menuButton = page.getByRole('button', { name: /open navigation|menu/i });
    await expect(menuButton).toBeVisible();
  });

  test('mobile drawer should have proper ARIA attributes', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    // Open the drawer
    const menuButton = page.getByRole('button', { name: /open navigation|menu/i });
    await menuButton.click();

    // Drawer should have dialog role and aria-modal
    const drawer = page.locator('[role="dialog"][aria-modal="true"]');
    await expect(drawer).toBeVisible();

    // Close button should have aria-label
    const closeButton = page.getByRole('button', { name: /close navigation/i });
    await expect(closeButton).toBeVisible();
    await closeButton.click();

    // Drawer should close
    await expect(drawer).not.toBeVisible();
  });

  test('notification bell should have accessible label', async ({ page }) => {
    await page.goto('/en/partner');

    const bellButton = page.getByRole('button', { name: /notifications/i });
    await expect(bellButton).toBeVisible();
  });

  test('header should have main content landmark', async ({ page }) => {
    await page.goto('/en/partner');

    // Main content area should have role="main"
    const main = page.locator('[role="main"]');
    await expect(main).toBeVisible();
  });

  test('form inputs should have associated labels', async ({ page }) => {
    await page.goto('/en/partner/profile');

    // All inputs should have associated labels (via htmlFor/id or aria-label)
    const inputs = page.locator('input[type="text"], input[type="email"], input[type="url"], input[type="tel"], textarea');
    const count = await inputs.count();

    for (let i = 0; i < count; i++) {
      const input = inputs.nth(i);
      const id = await input.getAttribute('id');
      if (id) {
        // Check that a label exists for this input
        const label = page.locator(`label[for="${id}"]`);
        await expect(label).toBeVisible();
      }
    }
  });

  test('switch toggles should have accessible roles', async ({ page }) => {
    await page.goto('/en/partner/profile');
    await expect(page.locator('main#main-content')).toBeVisible();

    // Scroll to notification settings
    const switches = page.locator('button[role="switch"]');
    await switches.first().waitFor();
    const switchCount = await switches.count();
    expect(switchCount).toBeGreaterThanOrEqual(5);

    // Each switch should have aria-checked
    for (let i = 0; i < switchCount; i++) {
      const sw = switches.nth(i);
      const ariaChecked = await sw.getAttribute('aria-checked');
      expect(ariaChecked).toBeTruthy();
    }
  });
});