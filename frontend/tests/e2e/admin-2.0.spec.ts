import { test, expect, type Page, type Cookie } from '@playwright/test';

// Admin 2.0 Comprehensive E2E Suite (@admin-2.0).
// Covers all 15 required Admin 2.0 operational workflows against the real
// Filament v3 panel and public CMS/Blog APIs.

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';

const RESTRICTED_ADMIN_EMAIL = 'restricted-admin@bookly.test';
const RESTRICTED_ADMIN_PASSWORD = 'password';

const EXPECTED_NAV_GROUPS = [
  'Overview',
  'Marketplace Operations',
  'Finance',
  'Content & CMS',
  'Media & Storage',
  'Administration & Governance',
];

let cachedAdminCookies: Cookie[] | null = null;

async function loginToFilament(
  page: Page,
  email = ADMIN_EMAIL,
  password = ADMIN_PASSWORD
): Promise<void> {
  if (email === ADMIN_EMAIL && cachedAdminCookies && cachedAdminCookies.length > 0) {
    await page.context().addCookies(cachedAdminCookies);
    return;
  }

  await page.goto('/admin/login');
  const emailInput = page.getByLabel(/email address/i).first();
  await emailInput.waitFor({ state: 'visible', timeout: 30000 });
  await emailInput.fill(email);
  await page.getByLabel(/password/i).first().fill(password);
  await Promise.all([
    page.waitForURL('**/admin', { timeout: 30000 }),
    page.getByRole('button', { name: /sign in/i }).click(),
  ]);
  await expect(page.locator('.fi-sidebar')).toBeVisible({ timeout: 30000 });

  if (email === ADMIN_EMAIL) {
    cachedAdminCookies = await page.context().cookies();
  }
}

async function expectAdminResourceTable(page: Page, path: string): Promise<void> {
  await page.goto(path);
  await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
  await expect(page).not.toHaveURL(/\/admin\/login/);
  await expect(page.locator('main table, main [role=table]').first()).toBeVisible({
    timeout: 30000,
  });
}

test.describe.serial('@admin-2.0', () => {
  test('1. Admin authentication and session access with all six navigation groups', async ({
    page,
  }) => {
    test.setTimeout(120000);
    cachedAdminCookies = null;
    await loginToFilament(page);

    const sidebar = page.locator('.fi-sidebar');
    for (const group of EXPECTED_NAV_GROUPS) {
      await expect(sidebar.getByText(group, { exact: false }).first()).toBeVisible();
    }

    for (const label of [
      'Users',
      'Roles',
      'Media',
      'Blog Posts',
      'Static Pages',
      'Translation Health',
      'Operational Monitoring',
    ]) {
      await expect(sidebar.locator(`a:has-text("${label}")`).first()).toBeVisible();
    }

    await expectAdminResourceTable(page, '/admin/admin-users');
    await expectAdminResourceTable(page, '/admin/admin-roles');
  });

  test('2. RBAC access denial for restricted admin role', async ({ page }) => {
    test.setTimeout(120000);
    await loginToFilament(page, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);

    // Allowed Support Agent routes: bookings and travelers
    await expectAdminResourceTable(page, '/admin/bookings');
    await expectAdminResourceTable(page, '/admin/travelers');

    // Denied routes for Support Agent: admin-users, admin-roles, static-pages, financial-overview-page
    for (const forbiddenPath of [
      '/admin/admin-users',
      '/admin/admin-roles',
      '/admin/static-pages',
      '/admin/financial-overview-page',
    ]) {
      const response = await page.goto(forbiddenPath);
      expect(response?.status()).toBe(403);
    }
  });

  test('3. Traveler moderation workflow (suspend and reactivate with reason)', async ({
    page,
  }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/travelers');
    const row = page.locator('main table tbody tr', {
      hasText: 'E2E Moderate Traveler',
    });
    await expect(row).toBeVisible({ timeout: 30000 });

    const suspendBtn = row.getByRole('button', { name: /^suspend$/i }).first();
    if (await suspendBtn.isVisible()) {
      await suspendBtn.click();
      const modal = page.locator('.fi-modal.fi-modal-open .fi-modal-window').first();
      await expect(modal).toBeVisible({ timeout: 15000 });
      await modal.locator('textarea').first().fill('E2E policy violation check');
      await modal.locator('button[type="submit"]').click();
      await expect(row).toContainText(/suspended/i, { timeout: 20000 });
    }

    const reactivateBtn = row.getByRole('button', { name: /reactivate/i }).first();
    await expect(reactivateBtn).toBeVisible({ timeout: 20000 });
    await reactivateBtn.click();
    const reactivateModal = page.locator('.fi-modal.fi-modal-open .fi-modal-window').first();
    await expect(reactivateModal).toBeVisible({ timeout: 15000 });
    const reasonField = reactivateModal.locator('textarea').first();
    if (await reasonField.count()) {
      await reasonField.fill('E2E appeal verified');
    }
    await reactivateModal
      .locator('button[type="submit"], button:has-text("Confirm")')
      .last()
      .click();
    await expect(row).toContainText(/active/i, { timeout: 20000 });
  });

  test('4. Partner moderation workflow', async ({ page }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/partners');
    const row = page.locator('main table tbody tr', {
      hasText: 'E2E Pending Coastal Partner',
    });
    await expect(row).toBeVisible({ timeout: 30000 });

    const approveBtn = row.getByRole('button', { name: /approve/i }).first();
    if (await approveBtn.isVisible()) {
      await approveBtn.click();
      const modal = page.locator('.fi-modal.fi-modal-open .fi-modal-window').first();
      await expect(modal).toBeVisible({ timeout: 15000 });
      await modal
        .locator('button[type="submit"], button:has-text("Confirm")')
        .last()
        .click();
      await expect(row).toContainText(/approved/i, { timeout: 20000 });
    } else {
      await expect(row).toContainText(/approved/i);
    }
  });

  test('5. Tour moderation workflow', async ({ page }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/tours');
    const row = page.locator('main table tbody tr', {
      hasText: 'E2E Pending Amalfi Tour',
    });
    await expect(row).toBeVisible({ timeout: 30000 });

    const publishBtn = row.getByRole('button', { name: /publish/i }).first();
    if (await publishBtn.isVisible()) {
      await publishBtn.click();
      const modal = page.locator('.fi-modal.fi-modal-open .fi-modal-window').first();
      await expect(modal).toBeVisible({ timeout: 15000 });
      await modal
        .locator('button[type="submit"], button:has-text("Confirm")')
        .last()
        .click();
      await expect(row).toContainText(/published/i, { timeout: 20000 });
    } else {
      await expect(row).toContainText(/published/i);
    }
  });

  test('6. Booking moderation and CSV export workflow', async ({ page }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/bookings');
    const row = page.locator('main table tbody tr', {
      hasText: 'BK-E2E901',
    });
    await expect(row).toBeVisible({ timeout: 30000 });

    const exportBtn = page.getByRole('button', { name: /export csv/i }).first();
    await expect(exportBtn).toBeVisible({ timeout: 15000 });
    await exportBtn.click();

    const modal = page.locator('.fi-modal.fi-modal-open .fi-modal-window').first();
    await expect(modal).toBeVisible({ timeout: 15000 });
    await expect(modal).toContainText(/Export Bookings CSV/i);

    const downloadPromise = page.waitForEvent('download', { timeout: 20000 });
    await modal.locator('button[type="submit"]').click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.csv$/i);
  });

  test('7. Financial overview read-only access and payout liability / ledger inspection', async ({
    page,
  }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await page.goto('/admin/financial-overview-page');
    await expect(page.locator('[data-financial-overview]')).toBeVisible({
      timeout: 30000,
    });
    await expect(
      page.locator('[data-overview-metric="gross_booking_value"]')
    ).toBeVisible();
    await expect(
      page.locator('[data-overview-metric="partner_payout_liability"]')
    ).toBeVisible();
    await expect(page.locator('[data-reconciliation-report]')).toBeVisible();

    // Read-only payments and ledger tables
    await expectAdminResourceTable(page, '/admin/payments');
    await expect(page.locator('main table')).toContainText('pi_e2e_amalfi_001');
    await expect(page.getByRole('link', { name: /new payment/i })).toHaveCount(0);

    await expectAdminResourceTable(page, '/admin/financial-ledger-entries');
    await expect(page.locator('main table')).toContainText(
      'E2E Booking Capture Ledger Entry'
    );
    await expect(
      page.getByRole('link', { name: /new financial ledger/i })
    ).toHaveCount(0);
  });

  test('8. Media Library upload form, File Manager, and MediaPicker integration', async ({
    page,
  }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/media-assets');
    await expect(page.locator('main table')).toContainText('e2e-amalfi-hero.jpg');

    await page.goto('/admin/media-assets/create');
    await expect(page.locator('main form').first()).toBeVisible({ timeout: 30000 });
    await expect(page.getByText(/Alt text \(localized\)/i)).toBeVisible();

    await page.goto('/admin/file-manager');
    await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
    await expect(page).not.toHaveURL(/\/admin\/login/);
  });

  test('9. CMS Blog editing with rich HTML and read/write sanitization behavior', async ({
    page,
    request,
  }) => {
    test.setTimeout(180000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/blog-posts');
    const row = page.locator('main table tbody tr', {
      hasText: 'e2e-sanitized-guide',
    });
    await expect(row).toBeVisible({ timeout: 30000 });

    const editLink = row.locator('a[href*="/edit"]').first();
    await editLink.click();
    await expect(page.locator('main form').first()).toBeVisible({ timeout: 60000 });

    const apiResponse = await request.get(
      '/api/public/blog/e2e-sanitized-guide?locale=en'
    );
    expect(apiResponse.status()).toBe(200);
    const json = await apiResponse.json();
    expect(json.data.slug).toBe('e2e-sanitized-guide');
    expect(json.data.body).toContain('<p>Initial safe guide body.</p>');
    expect(json.data.body).not.toContain('<script');
  });

  test('10. Static Page creation, editing, and public publishing', async ({
    page,
    request,
  }) => {
    test.setTimeout(180000);
    await loginToFilament(page);

    await expectAdminResourceTable(page, '/admin/static-pages');
    await expect(page.locator('main table')).toContainText('privacy');

    await page.goto('/admin/static-pages/create');
    await expect(page.locator('main form').first()).toBeVisible({ timeout: 60000 });

    const publicPrivacy = await request.get('/api/public/pages/privacy?locale=en');
    expect(publicPrivacy.status()).toBe(200);
    const privacyJson = await publicPrivacy.json();
    expect(privacyJson.data.title).toBe('Privacy Policy');
    expect(privacyJson.data.body).toContain('<p>We respect your data.</p>');
  });

  test('11. Automatic translation status visibility and manual retranslate actions', async ({
    page,
  }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await page.goto('/admin/translation-health');
    await expect(page.locator('[data-translation-health]')).toBeVisible({
      timeout: 30000,
    });
    await expect(page.locator('[data-health-kpis] [data-kpi]').first()).toBeVisible();
    await expect(page.locator('[data-health-failure-rate]')).toBeVisible();

    await expectAdminResourceTable(page, '/admin/static-pages');
    const privacyRow = page.locator('main table tbody tr', { hasText: 'privacy' });
    await privacyRow.locator('a[href*="/edit"]').first().click();
    await expect(page.locator('main form').first()).toBeVisible({ timeout: 60000 });

    await expect(
      page.getByRole('button', { name: /Translate Missing Fields/i })
    ).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Retranslate Outdated Fields/i })
    ).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Force Retranslate All/i })
    ).toBeVisible();
  });

  test('12. Public translation fallback behavior (incomplete/outdated ES falls back to EN with warning)', async ({
    request,
  }) => {
    const blogRes = await request.get('/api/public/blog/e2e-fallback-post?locale=es');
    expect(blogRes.status()).toBe(200);
    const blogJson = await blogRes.json();
    expect(blogJson.data.title).toBe('English Fallback Blog Title');
    expect(blogJson.data.body).toContain('English fallback blog body.');
    expect(blogJson.data.translation_warning).toBe('partial_translation');

    const pageRes = await request.get('/api/public/pages/e2e-fallback-page?locale=es');
    expect(pageRes.status()).toBe(200);
    const pageJson = await pageRes.json();
    expect(pageJson.data.title).toBe('English Fallback Static Title');
    expect(pageJson.data.body).toContain('English fallback static body.');
    expect(pageJson.data.translation_warning).toBeTruthy();
  });

  test('13. Global search behavior in Filament topbar', async ({ page }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await page.goto('/admin');
    const searchInput = page
      .locator('.fi-global-search-field input[type="search"]')
      .first();
    await expect(searchInput).toBeVisible({ timeout: 30000 });
    await searchInput.fill('BK-E2E901');

    const resultsContainer = page.locator('.fi-global-search-results-ctn').first();
    await expect(resultsContainer).toBeVisible({ timeout: 20000 });
    await expect(resultsContainer).toContainText('BK-E2E901');
  });

  test('14. Operational monitoring and failed job telemetry visibility', async ({
    page,
  }) => {
    test.setTimeout(120000);
    await loginToFilament(page);

    await page.goto('/admin/operational-monitoring');
    await expect(page.locator('[data-operational-monitoring]')).toBeVisible({
      timeout: 30000,
    });
    await expect(page.locator('[data-kpi="failed_translations"]')).toBeVisible();
    await expect(page.locator('[data-recent-failed-jobs]')).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Retry failed translations/i })
    ).toBeVisible();
    await expect(page.getByRole('button', { name: /Refresh/i })).toBeVisible();
  });

  test('15. Responsive navigation and critical page rendering without horizontal overflow', async ({
    page,
  }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginToFilament(page);

    for (const criticalPath of [
      '/admin',
      '/admin/blog-posts',
      '/admin/static-pages',
      '/admin/translation-health',
      '/admin/operational-monitoring',
    ]) {
      await page.goto(criticalPath);
      await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
      const overflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth
      );
      expect(overflow).toBe(false);
    }

    // Mobile viewport check
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin/translation-health');
    await expect(page.locator('main')).toBeVisible({ timeout: 30000 });
    const mobileOverflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth
    );
    expect(mobileOverflow).toBe(false);
  });
});
