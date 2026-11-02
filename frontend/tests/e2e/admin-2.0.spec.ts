import { test, expect, type Page } from '@playwright/test';

// Admin 2.0 E2E suite (@admin-2.0). Runs in the base `chromium` project only —
// it needs no frontend storageState and performs its own Filament session
// login (same seeded admin as filament-admin.spec.ts), then verifies the
// Admin 2.0 surface areas: six-group navigation, users & roles, media library
// & file manager, blog & static page CRUD screens, and the Translation Health
// dashboard.
//
// The dev server cold-compiles every Filament page on first visit, so each
// test gets a generous timeout.

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';

// The six navigation groups declared in AdminPanelProvider (Admin 2.0).
const EXPECTED_NAV_GROUPS = [
  'Overview',
  'Marketplace Operations',
  'Finance',
  'Content & CMS',
  'Media & Storage',
  'Administration & Governance',
];

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/admin/login');
  const email = page.getByLabel(/email address/i).first();
  await email.waitFor({ state: 'visible', timeout: 30000 });
  await email.fill(ADMIN_EMAIL);
  await page.getByLabel(/password/i).first().fill(ADMIN_PASSWORD);
  // Await the post-login navigation concurrently with the click: the redirect
  // can land before a subsequent waitForURL attaches.
  await Promise.all([
    page.waitForURL('**/admin', { timeout: 30000 }),
    page.getByRole('button', { name: /sign in/i }).click(),
  ]);
  await expect(page.locator('.fi-sidebar')).toBeVisible({ timeout: 30000 });
}

async function expectAdminResourcePage(page: Page, path: string): Promise<void> {
  await page.goto(path);
  // Every authenticated Filament page renders <main>; an unauthenticated URL
  // would have redirected to /admin/login and never produced one.
  await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
  await expect(page).not.toHaveURL(/\/admin\/login/);
  // Resource pages render a Filament data table inside main.
  await expect(page.locator('main table, main [role=table]').first()).toBeVisible({ timeout: 30000 });
}

test.describe('@admin-2.0', () => {
  test('admin login works and all six navigation groups are visible', async ({ page }) => {
    test.setTimeout(120000);
    await loginAsAdmin(page);

    const sidebar = page.locator('.fi-sidebar');
    for (const group of EXPECTED_NAV_GROUPS) {
      await expect(sidebar.getByText(group, { exact: false }).first()).toBeVisible();
    }

    // The six groups expose the Admin 2.0 entry points.
    for (const label of ['Users', 'Roles', 'Media', 'Blog Posts', 'Static Pages', 'Translation Health']) {
      await expect(
        sidebar.locator(`a:has-text("${label}")`).first()
      ).toBeVisible();
    }
  });

  test('Admin Users & Roles pages render', async ({ page }) => {
    test.setTimeout(120000);
    await loginAsAdmin(page);

    await expectAdminResourcePage(page, '/admin/admin-users');
    await expectAdminResourcePage(page, '/admin/admin-roles');
  });

  test('Media Library & File Manager pages render', async ({ page }) => {
    test.setTimeout(120000);
    await loginAsAdmin(page);

    // MediaAssetResource table.
    await expectAdminResourcePage(page, '/admin/media-assets');

    // FileManagerPage is a Filament custom Page (no data table).
    await page.goto('/admin/file-manager');
    await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
    await expect(page).not.toHaveURL(/\/admin\/login/);
  });

  test('Blog Posts creation & editing screens render', async ({ page }) => {
    test.setTimeout(180000);
    await loginAsAdmin(page);

    await expectAdminResourcePage(page, '/admin/blog-posts');

    // Create screen (rich-text editor form).
    await page.goto('/admin/blog-posts/create');
    await expect(page.locator('main').locator('form').first()).toBeVisible({ timeout: 60000 });
    await expect(page).not.toHaveURL(/\/admin\/login/);

    // List rows expose an edit action that resolves to a Filament edit page.
    const firstRow = page.locator('main table tbody tr').first();
    if (await firstRow.count()) {
      const editLink = firstRow.locator('a[href*="/edit"]').first();
      if (await editLink.count()) {
        await editLink.click();
        await expect(page.locator('main').locator('form').first()).toBeVisible({ timeout: 60000 });
      }
    }
  });

  test('Static Pages creation & editing screens render', async ({ page }) => {
    test.setTimeout(180000);
    await loginAsAdmin(page);

    await expectAdminResourcePage(page, '/admin/static-pages');

    await page.goto('/admin/static-pages/create');
    await expect(page.locator('main').locator('form').first()).toBeVisible({ timeout: 60000 });
    await expect(page).not.toHaveURL(/\/admin\/login/);

    // A seeded static page should be editable (privacy/terms are seeded by
    // the Admin 2.0 migrator; view/edit actions resolve from the table).
    const firstRow = page.locator('main table tbody tr').first();
    if (await firstRow.count()) {
      const editLink = firstRow.locator('a[href*="/edit"], a[href*="/view"]').first();
      if (await editLink.count()) {
        await editLink.click();
        await expect(page.locator('main')).toBeVisible({ timeout: 60000 });
        await expect(page).not.toHaveURL(/\/admin\/login/);
      }
    }
  });

  test('Translation Health dashboard renders', async ({ page }) => {
    test.setTimeout(120000);
    await loginAsAdmin(page);

    await page.goto('/admin/translation-health');
    await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
    await expect(page).not.toHaveURL(/\/admin\/login/);

    // KPI cards (one per pipeline status) and the failure-rate card.
    await expect(page.locator('[data-translation-health]')).toBeVisible({ timeout: 30000 });
    await expect(page.locator('[data-health-kpis] [data-kpi]').first()).toBeVisible();
    await expect(page.locator('[data-health-failure-rate]')).toBeVisible();
  });
});
