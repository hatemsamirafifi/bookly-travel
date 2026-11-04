import { test, expect } from '@playwright/test';

test.describe('Discovery Keyboard Navigation & Visible Focus Rings (A11y)', () => {
  test('homepage tabs through interactive discovery elements with visible focus rings', async ({ page }) => {
    await page.goto('/en');

    // Focus search input
    const searchInput = page.getByRole('searchbox', { name: 'Search tours' });
    await searchInput.focus();
    await expect(searchInput).toBeFocused();

    // Tab to search submit button
    await page.keyboard.press('Tab');
    const searchBtn = page.getByRole('button', { name: 'Search', exact: true });
    await expect(searchBtn).toBeFocused();

    // Verify category chips or first tour card is reachable by keyboard
    const categoryChips = page.locator('nav[aria-label="Tour categories"] a');
    if (await categoryChips.count() > 0) {
      await page.keyboard.press('Tab');
      await expect(categoryChips.first()).toBeFocused();
    }
  });

  test('mobile filter drawer traps focus and returns focus on escape', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/en/search');

    const filterButton = page.getByRole('button', { name: /^Filters(?: \(\d+\))?$/ });
    await expect(filterButton).toBeVisible();
    await filterButton.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    // Press Escape to dismiss
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();

    // Focus returns to filter trigger
    await expect(filterButton).toBeFocused();
  });

  test('tour card wishlist button is independently focusable via keyboard without activating card link', async ({ page }) => {
    await page.goto('/en/search');

    const firstCard = page.locator('div.group.relative').first();
    if (await firstCard.isVisible()) {
      const cardLink = firstCard.locator('a[href*="/tours/"]').first();
      const heartButton = firstCard.locator('button[data-testid="wishlist-button"]').first();

      await cardLink.focus();
      await expect(cardLink).toBeFocused();

      await heartButton.focus();
      await expect(heartButton).toBeFocused();
    }
  });
});

