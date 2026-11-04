import { render, screen } from '@testing-library/react';
import BooklyValuePanel from '../BooklyValuePanel';

describe('BooklyValuePanel', () => {
  it('renders marketplace trust guarantees without external advertisements (FR-006)', () => {
    render(<BooklyValuePanel locale="en" />);

    expect(screen.getByRole('region', { name: /guarantee/i })).toBeInTheDocument();
    expect(screen.getByText(/Verified Local Guides/i)).toBeInTheDocument();
    expect(screen.getByText(/Secure Instant Booking/i)).toBeInTheDocument();
    expect(screen.getByText(/24\/7 Traveler Care/i)).toBeInTheDocument();
  });
});
