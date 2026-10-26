import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { fetchOfferedTourFacts } from '../helpers/offered-date';

test('checkout page should not have accessibility violations', async ({ page, request }) => {
  const facts = await fetchOfferedTourFacts(request, 'hidden-gems-rome-walking-tour', 'en');
  await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=${facts.groupMin}`);
  // READY form first: correct tour identity, the selected date rendered, and
  // submission enabled — the scan must cover the valid state, never a
  // disabled invalid one.
  await expect(page.getByRole('heading', { name: facts.title })).toBeVisible();
  const selectedDate = new Date(`${facts.date}T00:00:00`).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  await expect(page.getByText(selectedDate, { exact: false })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Confirm & Pay' })).toBeEnabled();
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
