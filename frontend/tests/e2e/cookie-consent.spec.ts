import { expect, test } from '@playwright/test';

for (const width of [390, 768, 1024, 1440]) {
  test(`cookie consent remains compact and keyboard-operable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/en');

    const banner = page.locator('aside[aria-label="Cookie consent"] > div');
    await expect(banner).toBeVisible();
    const box = await banner.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.height).toBeLessThan(width === 390 ? 150 : 100);

    const reject = banner.getByRole('button', { name: 'Reject' });
    const accept = banner.getByRole('button', { name: 'Accept' });
    await expect(reject).toBeVisible();
    await expect(accept).toBeVisible();
    await reject.focus();
    await expect(reject).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(banner).not.toBeVisible();
  });
}
