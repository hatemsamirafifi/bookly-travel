import { expect, test } from '@playwright/test';

for (const [locale, title] of [
  ['en', 'Terms & Conditions'],
  ['es', 'Términos y Condiciones'],
  ['it', 'Termini e Condizioni'],
]) {
  test(`${locale} terms preserve localized legal content at mobile width`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}/terms`);
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await expect(page.locator('main h2')).toHaveCount(5);
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });
}

for (const [locale, privacyTitle, missingTitle] of [
  ['en', 'Privacy Policy', 'Page Not Found'],
  ['es', 'Política de Privacidad', 'Página No Encontrada'],
  ['it', 'Informativa sulla Privacy', 'Pagina Non Trovata'],
]) {
  test(`${locale} privacy and missing-page recovery remain usable at mobile width`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}/privacy`);
    await expect(page.getByRole('heading', { level: 1, name: privacyTitle })).toBeVisible();
    await expect(page.locator('main h2')).toHaveCount(5);

    await page.goto(`/${locale}/missing-support-page`);
    await expect(page.getByRole('heading', { level: 1, name: '404' })).toBeVisible();
    await expect(page.getByText(missingTitle)).toBeVisible();
    await expect(page.locator('main a[href]')).toHaveCount(2);
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });
}
