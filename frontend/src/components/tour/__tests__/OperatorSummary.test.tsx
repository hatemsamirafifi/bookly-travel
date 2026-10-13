import { render, screen } from '@testing-library/react';
import OperatorSummary from '../OperatorSummary';
import type { PublicOperatorSummary } from '@/lib/api/types';

// Spec 019 US5 (T060): the public operator summary renders allowlisted
// facts only, with an approved/active verification badge driven by the
// localized label prop. The badge contract below is the tests-first
// interface for T063: an optional `verifiedLabel` prop rendered inside
// `[data-testid="operator-verified"]` if and only if operator.verified
// is true. Typecheck stays red on the new prop until T063 adds it.

const verifiedOperator: PublicOperatorSummary = {
  name: 'Tuscan Walks',
  description: 'Local guides.',
  logo_url: null,
  verified: true,
  tour_count: 3,
  review_count: 8,
  average_rating: 4.5,
};

describe('OperatorSummary', () => {
  it('renders the allowlisted name, description and counts', () => {
    render(
      <OperatorSummary
        operator={verifiedOperator}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    expect(screen.getByRole('heading', { name: 'Tour operator' })).toBeInTheDocument();
    expect(screen.getByText('Tuscan Walks')).toBeInTheDocument();
    expect(screen.getByText('Local guides.')).toBeInTheDocument();
    expect(screen.getByText('3 tours')).toBeInTheDocument();
    expect(screen.getByText('8 reviews')).toBeInTheDocument();
  });

  it('renders the localized verification badge only when verified is true', () => {
    const { rerender } = render(
      <OperatorSummary
        operator={verifiedOperator}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    // Approved + active profiles expose the localized verified claim.
    expect(screen.getByTestId('operator-verified')).toHaveTextContent('Verified operator');

    rerender(
      <OperatorSummary
        operator={{ ...verifiedOperator, verified: false }}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );
    expect(screen.queryByTestId('operator-verified')).not.toBeInTheDocument();
  });

  it('omits the verification badge when the flag is absent', () => {
    const { name, description, logo_url, tour_count, review_count, average_rating } = verifiedOperator;
    const unflagged: PublicOperatorSummary = { name, description, logo_url, tour_count, review_count, average_rating };
    render(
      <OperatorSummary
        operator={unflagged}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    expect(screen.queryByTestId('operator-verified')).not.toBeInTheDocument();
    expect(screen.queryByText('Verified operator')).not.toBeInTheDocument();
  });

  it('omits the review label for zero reviews and invents no average', () => {
    const { container } = render(
      <OperatorSummary
        operator={{ ...verifiedOperator, review_count: 0, average_rating: null }}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="0 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    // Zero eligible reviews: no review claim and no fabricated average.
    expect(screen.getByText('3 tours')).toBeInTheDocument();
    expect(screen.queryByText('0 reviews')).not.toBeInTheDocument();
    expect(container.textContent).not.toMatch(/null|NaN|undefined/);
  });

  it('renders a null description and logo without empty placeholders', () => {
    const { container } = render(
      <OperatorSummary
        operator={{ ...verifiedOperator, description: null, logo_url: null }}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    // Nullable facts stay absent rather than rendering empty blocks.
    expect(screen.getByText('Tuscan Walks')).toBeInTheDocument();
    expect(container.querySelector('#operator p.whitespace-pre-line')).toBeNull();
  });

  it('keeps the accessible region semantics and existing design tokens', () => {
    const { container } = render(
      <OperatorSummary
        operator={verifiedOperator}
        title="Tour operator"
        tourCountLabel="3 tours"
        reviewCountLabel="8 reviews"
        verifiedLabel="Verified operator"
      />,
    );

    const section = container.querySelector('#operator');
    expect(section?.tagName).toBe('SECTION');
    expect(section).toHaveAttribute('aria-labelledby', 'operator-title');
    expect(section?.className).toMatch(/rounded-xl border border-border bg-surface/);
    expect(screen.getByRole('heading', { name: 'Tour operator' })).toHaveAttribute('id', 'operator-title');
  });
});
