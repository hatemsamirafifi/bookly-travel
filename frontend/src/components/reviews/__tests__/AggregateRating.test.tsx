import { render, screen } from '@testing-library/react';
import AggregateRating from '../AggregateRating';

describe('AggregateRating', () => {
  it('displays average rating and count', () => {
    render(<AggregateRating averageRating={4.2} reviewCount={10} />);
    expect(screen.getByText('4.2')).toBeInTheDocument();
    expect(screen.getByText('10 reviews')).toBeInTheDocument();
  });

  it('shows singular form for 1 review', () => {
    render(<AggregateRating averageRating={5.0} reviewCount={1} />);
    expect(screen.getByText('1 review')).toBeInTheDocument();
  });

  it('shows empty state when no reviews', () => {
    render(<AggregateRating averageRating={null} reviewCount={0} />);
    expect(screen.getByText('No reviews yet. Be the first!')).toBeInTheDocument();
  });
});
