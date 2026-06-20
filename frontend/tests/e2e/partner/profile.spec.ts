import { test, expect } from '@playwright/test';
import { partnerLogin } from '../helpers/auth';

test.describe('Partner Profile Page', () => {
  test.beforeEach(async ({ page }) => {
    await partnerLogin(page);
    await page.goto('/en/partner/profile');
  });

  test('should display business profile form', async ({ page }) => {
    await expect(page.getByText('Business Information')).toBeVisible();
  });

  test('should display all profile form fields', async ({ page }) => {
    // Company name
    await expect(page.getByLabel('Company Name')).toBeVisible();

    // Contact email
    await expect(page.getByLabel('Contact Email')).toBeVisible();

    // Phone
    await expect(page.getByLabel('Phone')).toBeVisible();

    // Website
    await expect(page.getByLabel('Website')).toBeVisible();

    // Description
    await expect(page.getByLabel('Description')).toBeVisible();

    // Tax ID
    await expect(page.getByLabel('Tax ID')).toBeVisible();
  });

  test('should allow typing in all profile form fields', async ({ page }) => {
    // Fill in company name
    const companyNameInput = page.getByLabel('Company Name');
    await companyNameInput.fill('Test Travel Co.');
    await expect(companyNameInput).toHaveValue('Test Travel Co.');

    // Fill in contact email
    const emailInput = page.getByLabel('Contact Email');
    await emailInput.fill('contact@testtravel.com');
    await expect(emailInput).toHaveValue('contact@testtravel.com');

    // Fill in phone
    const phoneInput = page.getByLabel('Phone');
    await phoneInput.fill('+1-555-0100');
    await expect(phoneInput).toHaveValue('+1-555-0100');

    // Fill in website
    const websiteInput = page.getByLabel('Website');
    await websiteInput.fill('https://testtravel.com');
    await expect(websiteInput).toHaveValue('https://testtravel.com');

    // Fill in description
    const descInput = page.getByLabel('Description');
    await descInput.fill('A premium travel company offering unique experiences.');
    await expect(descInput).toHaveValue('A premium travel company offering unique experiences.');

    // Fill in tax ID
    const taxInput = page.getByLabel('Tax ID');
    await taxInput.fill('VAT123456');
    await expect(taxInput).toHaveValue('VAT123456');
  });

  test('should display Save Changes button', async ({ page }) => {
    await expect(page.getByRole('button', { name: /save changes/i })).toBeVisible();
  });

  test('should display payout information section', async ({ page }) => {
    await expect(page.getByText('Payout Information')).toBeVisible();
  });

  test('should display all payout form fields', async ({ page }) => {
    // Scroll to payout section if needed
    await expect(page.getByLabel('Account Holder Name')).toBeVisible();
    await expect(page.getByLabel('Bank Name')).toBeVisible();
    await expect(page.getByLabel('IBAN')).toBeVisible();
    await expect(page.getByLabel('SWIFT/BIC')).toBeVisible();
    await expect(page.getByLabel('Country (ISO)')).toBeVisible();
  });

  test('should have IBAN placeholder hint', async ({ page }) => {
    const ibanInput = page.getByLabel('IBAN');
    await expect(ibanInput).toHaveAttribute('placeholder', /GB82 WEST/i);
  });

  test('should limit Country ISO to 2 characters', async ({ page }) => {
    const countryInput = page.getByLabel('Country (ISO)');
    await expect(countryInput).toHaveAttribute('maxlength', '2');
  });

  test('should display Save Payout Details button', async ({ page }) => {
    await expect(page.getByRole('button', { name: /save payout/i })).toBeVisible();
  });

  test('should display notification preferences section', async ({ page }) => {
    await expect(page.getByText('Notification Preferences')).toBeVisible();
  });

  test('should display all notification toggles', async ({ page }) => {
    // Each notification preference should have a switch toggle
    // The labels are generated from key names:
    // notify_new_booking → "New Booking"
    // notify_cancellation → "Cancellation"
    // notify_daily_summary → "Daily Summary"
    // notify_review_received → "Review Received"
    // notify_tour_status_change → "Tour Status Change"

    await expect(page.getByText('New booking')).toBeVisible();
    await expect(page.getByText('Cancellation')).toBeVisible();
    await expect(page.getByText('Daily summary')).toBeVisible();
    await expect(page.getByText('Review received')).toBeVisible();
    await expect(page.getByText('Tour status change')).toBeVisible();
  });

  test('should toggle notification switches', async ({ page }) => {
    // Find the first notification switch
    const firstSwitch = page.locator('button[role="switch"]').first();
    await expect(firstSwitch).toBeVisible();

    // Get initial state
    const isChecked = await firstSwitch.getAttribute('aria-checked');

    // Click to toggle
    await firstSwitch.click();

    // State should change
    const newState = await firstSwitch.getAttribute('aria-checked');
    expect(newState).not.toBe(isChecked);
  });

  test('should display Save Preferences button for notifications', async ({ page }) => {
    await expect(page.getByRole('button', { name: /save preferences/i })).toBeVisible();
  });

  test('should have proper form layout with two-column grid', async ({ page }) => {
    // The profile form uses a grid layout
    const formGrid = page.locator('.grid.grid-cols-1').first();
    await expect(formGrid).toBeVisible();
  });
});

test.describe('Partner Profile - Responsive', () => {
  test('should stack form fields on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/en/partner/profile');

    // All sections should still be visible
    await expect(page.getByText('Business Information')).toBeVisible();
    await expect(page.getByText('Payout Information')).toBeVisible();
    await expect(page.getByText('Notification Preferences')).toBeVisible();
  });
});