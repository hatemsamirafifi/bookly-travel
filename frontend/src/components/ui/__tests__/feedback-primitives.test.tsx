import { fireEvent, render, screen } from '@testing-library/react';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import LoadingSkeleton from '../LoadingSkeleton';
import { RatingBadge } from '../RatingBadge';

it('uses a localized retry label when callers do not supply one', () => {
  const retry = jest.fn();
  render(<ErrorState message="Could not load tours" onRetry={retry} />);
  const button = screen.getByRole('button', { name: 'retry' });
  expect(screen.getByRole('alert')).toHaveTextContent('Could not load tours');
  fireEvent.click(button);
  expect(retry).toHaveBeenCalledTimes(1);
});

it('provides a loading announcement while keeping placeholder shapes decorative', () => {
  render(<LoadingSkeleton label="Loading tours" count={2} />);
  expect(screen.getByRole('status')).toHaveTextContent('Loading tours');
  expect(screen.getByRole('status')).toHaveAttribute('aria-busy', 'true');
});

it('keeps empty-state recovery reachable and omits absent descriptions', () => {
  render(<EmptyState title="No saved tours" cta={{ href: '/en/search', label: 'Explore tours' }} />);
  expect(screen.getByRole('link', { name: 'Explore tours' })).toHaveAttribute('href', '/en/search');
});

it('omits rating evidence when reviews are absent or the rating is invalid', () => {
  const { rerender } = render(<RatingBadge rating={4.5} count={0} label="4.5 from 0 reviews" />);
  expect(screen.queryByText('4.5')).not.toBeInTheDocument();
  rerender(<RatingBadge rating={NaN} count={5} label="Invalid rating" />);
  expect(screen.queryByLabelText('Invalid rating')).not.toBeInTheDocument();
  rerender(<RatingBadge rating={4.5} count={5} label="4.5 out of 5 from 5 reviews" />);
  expect(screen.getByLabelText('4.5 out of 5 from 5 reviews')).toBeVisible();
});
