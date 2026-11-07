import { test, expect } from '@playwright/test';

// Smoke/regression for the *official* upstream editor, not the old 3-KB
// window.HugeRte shim. Uses disposable browser-CI admin fixtures only.
test.describe('@admin-hugerte-official', () => {
  test('serves upstream editor, plugins and skin assets from Nginx', async ({ request }) => {
    const main = await request.get('/vendor/hugerte/hugerte.min.js');
    expect(main.status()).toBe(200);
    expect(main.headers()['content-type']).toMatch(/javascript/);
    expect((await main.body()).byteLength).toBeGreaterThan(100_000);

    const plugin = await request.get('/vendor/hugerte/plugins/image/plugin.min.js');
    expect(plugin.status()).toBe(200);
    const skin = await request.get('/vendor/hugerte/skins/ui/oxide/skin.min.css');
    expect(skin.status()).toBe(200);
  });

  test('creates official HugeRTE editing UI in admin Blog Posts', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel(/email address/i).first().fill('admin@bookly.test');
    await page.getByLabel(/password/i).first().fill('password');
    await Promise.all([
      page.waitForURL('**/admin', { timeout: 30000 }),
      page.getByRole('button', { name: /sign in/i }).click(),
    ]);
    await page.goto('/admin/blog-posts/create');

    await expect(page.locator('.tox-tinymce').first()).toBeVisible({ timeout: 30000 });
    await expect(page.locator('.tox-toolbar').first()).toBeVisible();
    await expect.poll(() => page.evaluate(() =>
      !!(window as typeof window & { hugerte?: { activeEditor?: unknown } }).hugerte?.activeEditor
    )).toBe(true);

    const instance = await page.evaluate(() => {
      const editor = (window as typeof window & {
        hugerte?: { activeEditor?: { setContent(html: string): void; fire(event: string): void; getContent(): string } };
      }).hugerte?.activeEditor;
      if (!editor) return null;
      editor.setContent('<h2>Test heading</h2><p><strong>Bold</strong> content</p>');
      editor.fire('change');
      return editor.getContent();
    });
    expect(instance).toContain('<h2>Test heading</h2>');

    await expect.poll(() => page.locator('textarea[data-hugerte-model]').first().inputValue())
      .toContain('<h2>Test heading</h2>');
  });
});
