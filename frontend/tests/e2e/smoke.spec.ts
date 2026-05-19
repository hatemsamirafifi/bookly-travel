import { test, expect } from '@playwright/test';

test.describe('Smoke Test', () => {
  test('homepage loads with hero and categories', async ({ page }) => {
    await page.goto('/en');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText(/Categories/i)).toBeVisible();
  });

  test('search flow works end to end', async ({ page }) => {
    await page.goto('/en/search?q=rome');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('tour detail page loads', async ({ page }) => {
    await page.goto('/en/tours/test-adventure');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('category page loads', async ({ page }) => {
    await page.goto('/en/categories/adventure');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('destination page loads', async ({ page }) => {
    await page.goto('/en/destinations/rome');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('booking page loads', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('auth pages load', async ({ page }) => {
    await page.goto('/en/auth/login');
    await expect(page.getByRole('heading', { name: /Welcome back/i })).toBeVisible();
    await page.goto('/en/auth/register');
    await expect(page.getByRole('heading', { name: /Create your account/i })).toBeVisible();
  });

  test('privacy and terms pages load', async ({ page }) => {
    await page.goto('/en/privacy');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.goto('/en/terms');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('404 page loads', async ({ page }) => {
    await page.goto('/en/nonexistent-page');
    await expect(page.getByText(/404/i)).toBeVisible();
  });
});
