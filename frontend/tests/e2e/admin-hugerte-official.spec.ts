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
    test.slow();
    await page.goto('/admin/login');
    await page.getByLabel(/email address/i).first().fill('admin@bookly.test');
    await page.getByLabel(/password/i).first().fill('password');
    await Promise.all([
      page.waitForURL('**/admin', { timeout: 30000, waitUntil: 'domcontentloaded' }),
      page.getByRole('button', { name: /sign in/i }).click(),
    ]);
    const mediaResponse = await page.request.get('/admin/hugerte/media', { headers: { Accept: 'application/json' } });
    expect(mediaResponse.status()).toBe(200);
    expect(Array.isArray((await mediaResponse.json()).assets)).toBe(true);

    await page.goto('/admin/blog-posts/create');

    await expect(page.locator('.tox-hugerte, .tox-tinymce, .tox').first()).toBeVisible({ timeout: 30000 });
    await expect(page.locator('.tox-toolbar, .tox-toolbar-overlord').first()).toBeVisible();
    await expect.poll(() => page.evaluate(() =>
      !!(window as typeof window & { hugerte?: { activeEditor?: unknown } }).hugerte?.activeEditor
    )).toBe(true);

    const instance = await page.evaluate(() => {
      interface TestEditor {
        id: string;
        setContent(html: string): void;
        fire(event: string): void;
        dispatch?(event: string): void;
        getContent(): string;
      }
      const win = (window as typeof window & {
        hugerte?: {
          get(id?: string): TestEditor[] | TestEditor | null;
          activeEditor?: TestEditor;
        };
        BooklyHugeRte?: { commit(editor: TestEditor): void };
      });
      const all = typeof win.hugerte?.get === 'function' ? win.hugerte.get() : [];
      const editor = Array.isArray(all) && all.length > 0 ? all[0] : win.hugerte?.activeEditor;
      if (!editor) return null;
      editor.setContent('<h2>Test heading</h2><p><strong>Bold</strong> content</p>');
      if (typeof editor.fire === 'function') editor.fire('change');
      if (typeof editor.dispatch === 'function') editor.dispatch('change');
      if (win.BooklyHugeRte?.commit) win.BooklyHugeRte.commit(editor);
      return editor.getContent();
    });
    expect(instance).toContain('<h2>Test heading</h2>');

    await expect.poll(() => page.locator('textarea[data-hugerte-model]').first().inputValue())
      .toContain('<h2>Test heading</h2>');
  });
});
