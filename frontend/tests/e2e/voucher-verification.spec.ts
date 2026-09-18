import { test, expect } from '@playwright/test';

// Spec 014 (FR-022..FR-028, SC-010/011) — public voucher verification page
// at the ROOT path /v/{reference} (the URL the voucher QR encodes; excluded
// from next-intl's locale prefix by src/proxy.ts).
//
// NOTE on fixtures: references must match the backend's opaque reference
// shape (BKO- + 6 chars from the unambiguous alphabet ABCDEFGHJKMNPQRSTUVWXYZ
// 23456789 — no I/L/O/0/1). The DatabaseSeeder's BKO-TESTxx references
// intentionally violate that shape (8 chars, digits 0/1), so those seeded
// bookings are NOT publicly verifiable — these tests use valid-shape
// fixtures seeded by tests/e2e/helpers (or the equivalent audit seeder).

test.describe('Voucher Verification Page (/v/{reference})', () => {
  test('renders VALID status and allowed fields for a confirmed booking', async ({ page }) => {
    // BKO-AUDVWX is a valid-shape confirmed booking created by the audit
    // fixtures. If this test runs without the fixture, it will fail with a
    // clear 404 — see README/testsprite_tests docs for seeding.
    await page.goto('/v/BKO-AUDVWX');

    await expect(page.getByText('Valid', { exact: true }).first()).toBeVisible({ timeout: 30000 });
    await expect(page.getByText('This booking is confirmed and genuine.').first()).toBeVisible();
    await expect(page.getByText('BKO-AUDVWX').first()).toBeVisible();
    // No PII: no traveler name/email anywhere on the page
    const html = await page.content();
    expect(html).not.toContain('test@example.com');
    expect(html).not.toContain('Test User');
  });

  test('renders noindex meta and no navigation to private surfaces', async ({ page }) => {
    await page.goto('/v/BKO-AUDVWX');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex,\s*nofollow/);
    const html = await page.content();
    expect(html).not.toContain('/my-bookings');
    expect(html).not.toContain('/partner');
  });

  test('renders not-found state for an unknown reference', async ({ page }) => {
    await page.goto('/v/BKO-ZZ99ZZ');
    await expect(page.getByText(/Voucher not found/i).first()).toBeVisible({ timeout: 30000 });
  });

  test('renders CANCELLED status for a cancelled booking', async ({ page }) => {
    await page.goto('/v/BKO-AUDCAN');
    await expect(page.getByText('Cancelled', { exact: true }).first()).toBeVisible({ timeout: 30000 });
  });

  test('renders PENDING status for a pending-payment booking', async ({ page }) => {
    await page.goto('/v/BKO-AUDPEN');
    await expect(page.getByText('Pending payment', { exact: true }).first()).toBeVisible({ timeout: 30000 });
  });

  test('renders EXPIRED status for an expired booking', async ({ page }) => {
    await page.goto('/v/BKO-AUDEXP');
    await expect(page.getByText('Expired', { exact: true }).first()).toBeVisible({ timeout: 30000 });
  });
});
