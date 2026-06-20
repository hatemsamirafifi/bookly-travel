import { test, expect } from '@playwright/test';

test.describe('Auth', () => {
  test('login page renders with form fields', async ({ page }) => {
    await page.goto('/en/auth/login');
    await expect(page.getByLabel(/Email Address/i)).toBeVisible();
    await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Sign In/i })).toBeVisible();
  });

  test('register page renders with form fields', async ({ page }) => {
    await page.goto('/en/auth/register');
    await expect(page.getByLabel(/Full Name/i)).toBeVisible();
    await expect(page.getByLabel(/Email Address/i)).toBeVisible();
    await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Create Account/i })).toBeVisible();
  });

  test('login page shows session expired banner', async ({ page }) => {
    await page.goto('/en/auth/login?sessionExpired=1');
    await expect(page.getByText(/session has expired/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/auth/login');
    await expect(page.getByRole('heading', { name: /Welcome back/i })).toBeVisible();
  });

  test('register page links to login', async ({ page }) => {
    await page.goto('/en/auth/register');
    const link = page.getByRole('link', { name: /Sign in/i });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', '/en/auth/login');
  });

  test('login page links to register', async ({ page }) => {
    await page.goto('/en/auth/login');
    const link = page.getByRole('link', { name: /Create account/i });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', '/en/auth/register');
  });
});
