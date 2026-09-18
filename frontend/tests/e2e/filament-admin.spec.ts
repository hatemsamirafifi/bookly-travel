import { test, expect } from '@playwright/test';

// Filament admin panel (served by Laravel at /admin behind the nginx reverse
// proxy). Runs in the base `chromium` project only — it needs no frontend
// storageState and performs its own Filament session login.
//
// Guards the nginx `/livewire` routing regression: without the
// `location ^~ /livewire` block in docker/nginx/nginx.conf, livewire.js 404s
// through the Next.js catch-all, Filament never hydrates, and the login form
// falls back to a native POST that hits Laravel's GET-only admin/login route
// (405 Method Not Allowed) — i.e. admin login becomes completely broken.

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';

test.describe('Filament Admin Panel', () => {
  test('livewire.js is served by Laravel through the proxy', async ({ request }) => {
    const res = await request.get('/livewire/livewire.js');
    expect(res.status()).toBe(200);
    const body = await res.text();
    expect(body.length).toBeGreaterThan(10000);
  });

  test('unauthenticated /admin redirects to the login page', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForURL('**/admin/login', { timeout: 30000 });
    await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
  });

  test('admin can sign in and see the dashboard', async ({ page }) => {
    await page.goto('/admin/login');
    const email = page.getByLabel(/email address/i).first();
    await email.waitFor({ state: 'visible', timeout: 30000 });
    await email.fill(ADMIN_EMAIL);
    await page.getByLabel(/password/i).first().fill(ADMIN_PASSWORD);
    // Await the post-login navigation concurrently with the click: the
    // redirect can land before a subsequent waitForURL attaches, which
    // would hang until timeout despite a successful sign-in.
    await Promise.all([
      page.waitForURL('**/admin', { timeout: 30000 }),
      page.getByRole('button', { name: /sign in/i }).click(),
    ]);
    await expect(page.locator('main')).toBeVisible();
  });

  test('wrong password stays on the login page', async ({ page }) => {
    await page.goto('/admin/login');
    const email = page.getByLabel(/email address/i).first();
    await email.waitFor({ state: 'visible', timeout: 30000 });
    await email.fill(ADMIN_EMAIL);
    await page.getByLabel(/password/i).first().fill('wrong-password');
    await page.getByRole('button', { name: /sign in/i }).click();
    await page.waitForURL('**/admin/login**', { timeout: 15000 });
  });

  test('admin resources load after login', async ({ page }) => {
    test.setTimeout(120000);
    await page.goto('/admin/login');
    const email = page.getByLabel(/email address/i).first();
    await email.waitFor({ state: 'visible', timeout: 30000 });
    await email.fill(ADMIN_EMAIL);
    await page.getByLabel(/password/i).first().fill(ADMIN_PASSWORD);
    // Same navigation race as above: await concurrently with the click.
    await Promise.all([
      page.waitForURL('**/admin', { timeout: 30000 }),
      page.getByRole('button', { name: /sign in/i }).click(),
    ]);

    for (const path of ['/admin/tours', '/admin/partners', '/admin/bookings', '/admin/reviews']) {
      await page.goto(path);
      await expect(page.locator('main')).toBeVisible();
      // Resource pages render a Filament table inside main
      await expect(page.locator('main table, main [role=table]').first()).toBeVisible({ timeout: 20000 });
    }
  });
});
