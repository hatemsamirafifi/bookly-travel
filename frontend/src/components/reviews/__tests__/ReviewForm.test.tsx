import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ReviewForm from '../ReviewForm';

describe('ReviewForm', () => {
  const defaultProps = {
    bookingReference: 'BK-TEST01',
    locale: 'en',
    onSubmit: jest.fn().mockResolvedValue(undefined),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders the form with star rating and textarea', () => {
    render(<ReviewForm {...defaultProps} />);
    expect(screen.getByText('Write a Review')).toBeInTheDocument();
    expect(screen.getAllByRole('radio')).toHaveLength(5);
    expect(screen.getByLabelText(/Your Review/)).toBeInTheDocument();
  });

  it('shows character count', () => {
    render(<ReviewForm {...defaultProps} />);
    expect(screen.getByText('0/2000')).toBeInTheDocument();
  });

  it('disables submit button when no rating selected', () => {
    render(<ReviewForm {...defaultProps} />);
    expect(screen.getByRole('button', { name: 'Submit Review' })).toBeDisabled();
  });

  it('enables submit when rating is selected', () => {
    render(<ReviewForm {...defaultProps} />);
    fireEvent.click(screen.getByLabelText('4 stars'));
    expect(screen.getByRole('button', { name: 'Submit Review' })).toBeEnabled();
  });

  it('shows error when submitting without rating', async () => {
    render(<ReviewForm {...defaultProps} />);
    const form = screen.getByRole('button', { name: 'Submit Review' }).closest('form')!;
    fireEvent.submit(form);
    await waitFor(() => {
      expect(screen.getByText('Please select a rating.')).toBeInTheDocument();
    });
  });

  it('calls onSubmit with correct data', async () => {
    const onSubmit = jest.fn().mockResolvedValue(undefined);
    render(<ReviewForm {...defaultProps} onSubmit={onSubmit} />);

    fireEvent.click(screen.getByLabelText('5 stars'));
    fireEvent.change(screen.getByLabelText(/Your Review/), {
      target: { value: 'Fantastic!' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({ rating: 5, comment: 'Fantastic!' });
    });
  });

  it('shows success state after submission', async () => {
    render(<ReviewForm {...defaultProps} />);
    fireEvent.click(screen.getByLabelText('4 stars'));
    fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

    await waitFor(() => {
      expect(screen.getByText('Thank you for your review!')).toBeInTheDocument();
    });
  });

  it('shows edit mode with pre-filled data', () => {
    render(
      <ReviewForm
        {...defaultProps}
        existingReview={{ id: 1, rating: 3, comment: 'Good' }}
      />,
    );
    expect(screen.getByText('Edit Your Review')).toBeInTheDocument();
    expect(screen.getByText('Edited')).toBeInTheDocument();
  });
});
