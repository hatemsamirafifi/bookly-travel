import { act, render, screen } from '@testing-library/react';
import TourReviews from '../TourReviews';
import { fetchTourReviews } from '@/lib/reviews/review-api';

jest.mock('@/lib/reviews/review-api', () => ({ fetchTourReviews: jest.fn() }));
jest.mock('next-intl', () => {
  const catalog = jest.requireActual('../../../../messages/en.json');
  const translators = new Map<string, (key: string, values?: Record<string, unknown>) => string>();
  return {
    useTranslations: (namespace: string) => {
      if (!translators.has(namespace)) {
        translators.set(namespace, (key, values) => {
          if (key === 'review_count') return `${values?.count} reviews`;
          const template = catalog[namespace][key] as string;
          return Object.entries(values ?? {}).reduce(
            (text, [name, value]) => text.replace(`{${name}}`, String(value)), template,
          );
        });
      }
      return translators.get(namespace);
    },
  };
});

it('preserves the focused section heading while the real review list finishes loading', async () => {
  type Response = Awaited<ReturnType<typeof fetchTourReviews>>;
  let complete!: (response: Response) => void;
  jest.mocked(fetchTourReviews).mockReturnValue(new Promise((resolve) => { complete = resolve; }));
  render(<TourReviews tourSlug="rome-walk" locale="en" title="Guest reviews" />);
  const heading = screen.getByRole('heading', { level: 2, name: 'Guest reviews' });
  heading.tabIndex = -1;
  heading.focus();
  expect(heading).toHaveFocus();
  expect(screen.queryByText('Reader One')).not.toBeInTheDocument();
  await act(async () => complete({
    data: [{ id: 1, reviewer_name: 'Reader One', rating: 4, comment: 'A thoughtful walking tour.',
      status: 'published', edited: false, created_at: '2026-05-12T14:30:00Z' }],
    meta: { average_rating: 4, review_count: 1, current_page: 1, per_page: 5, total: 1 },
  }));
  expect(await screen.findByText('Reader One')).toBeVisible();
  expect(screen.getByRole('heading', { level: 2, name: 'Guest reviews' })).toBe(heading);
  expect(heading).toHaveFocus();
});
