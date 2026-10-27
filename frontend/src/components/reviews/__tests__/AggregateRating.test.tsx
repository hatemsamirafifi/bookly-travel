import { render, screen } from '@testing-library/react';
import AggregateRating from '../AggregateRating';

jest.mock('next-intl', () => ({
  useLocale: () => 'en',
  useTranslations: (namespace?: string) => {
    const catalog: Record<string, Record<string, string>> = {
      reviews: {
        no_reviews_yet: 'No reviews yet. Be the first!',
        review_count: '{count} reviews',
      },
      tour: {
        ratingLabel: 'Rating: {rating} out of 5',
      },
    };
    return (key: string, values?: Record<string, unknown>) => {
      if (namespace === 'reviews' && key === 'review_count') {
        const count = Number(values?.count ?? 0);
        return `${count} ${count === 1 ? 'review' : 'reviews'}`;
      }
      const template = catalog[namespace ?? '']?.[key] ?? key;
      return Object.entries(values ?? {}).reduce(
        (result, [name, replacement]) => result.replace(`{${name}}`, String(replacement)),
        template,
      );
    };
  },
}));

describe('AggregateRating', () => {
  it('displays a genuine average with a localized readonly score and count', () => {
    render(<AggregateRating averageRating={4.2} reviewCount={10} />);

    expect(screen.getByText('4.2')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Rating: 4.2 out of 5' })).toBeInTheDocument();
    expect(screen.getByText('10 reviews')).toBeInTheDocument();
    expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument();
  });

  it('shows singular form for 1 review', () => {
    render(<AggregateRating averageRating={5.0} reviewCount={1} />);

    expect(screen.getByRole('img', { name: 'Rating: 5.0 out of 5' })).toBeInTheDocument();
    expect(screen.getByText('1 review')).toBeInTheDocument();
  });

  it('shows localized no-review copy for an actual zero', () => {
    render(<AggregateRating averageRating={null} reviewCount={0} />);

    expect(screen.getByText('No reviews yet. Be the first!')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('shows the genuine count without a score when the average is unavailable', () => {
    render(<AggregateRating averageRating={0} reviewCount={10} />);

    expect(screen.getByText('10 reviews')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument();
    expect(screen.queryByText('0.0')).not.toBeInTheDocument();
    expect(screen.queryByText('No reviews yet. Be the first!')).not.toBeInTheDocument();
    expect(screen.queryByText('0 reviews')).not.toBeInTheDocument();
  });

  it.each([[-1], [1.5], [Number.NaN]])(
    'renders no invented count, score or zero claim for invalid count %s',
    (count) => {
      const { container } = render(<AggregateRating averageRating={4.2} reviewCount={count} />);

      expect(container.textContent).toBe('');
      expect(screen.queryByRole('img')).not.toBeInTheDocument();
      expect(screen.queryByText('No reviews yet. Be the first!')).not.toBeInTheDocument();
    },
  );
});
