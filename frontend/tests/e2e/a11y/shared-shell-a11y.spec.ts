import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import en from '../../../messages/en.json';
import es from '../../../messages/es.json';
import it from '../../../messages/it.json';

for (const [locale, catalog] of Object.entries({ en, es, it })) {
  for (const width of [390, 768, 1024, 1440]) {
    test(`${locale} shared auth shell and open search pass Axe at ${width}px`, async ({ page, context }, testInfo) => {
      await context.addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: testInfo.project.use.baseURL as string }]);
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/${locale}/auth/login`);
      await page.getByRole('banner').getByRole('button', { name: catalog.nav.searchTours }).click();
      await expect(page.getByRole('searchbox')).toBeFocused();
      expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
      if (width < 1024) {
        await page.keyboard.press('Escape');
        await page.getByRole('button', { name: catalog.nav.openMenu }).click();
        await expect(page.getByRole('dialog')).toBeVisible();
        expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
      }
    });
  }
}
