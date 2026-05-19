import { render, screen, fireEvent } from '@testing-library/react';
import StarRating from '../StarRating';

describe('StarRating', () => {
  it('renders 5 stars', () => {
    render(<StarRating value={0} onChange={jest.fn()} />);
    const stars = screen.getAllByRole('radio');
    expect(stars).toHaveLength(5);
  });

  it('selects correct value on click', () => {
    const onChange = jest.fn();
    render(<StarRating value={0} onChange={onChange} />);
    fireEvent.click(screen.getByLabelText('3 stars'));
    expect(onChange).toHaveBeenCalledWith(3);
  });

  it('highlights stars up to the selected value', () => {
    render(<StarRating value={4} />);
    const stars = screen.getAllByRole('radio');
    expect(stars[0]).toHaveAttribute('aria-checked', 'false');
    expect(stars[3]).toHaveAttribute('aria-checked', 'true');
  });

  it('does not respond to clicks in readOnly mode', () => {
    const onChange = jest.fn();
    render(<StarRating value={3} readOnly />);
    fireEvent.click(screen.getByLabelText('5 stars'));
    expect(onChange).not.toHaveBeenCalled();
  });

  it('has accessible radiogroup role', () => {
    render(<StarRating value={0} onChange={jest.fn()} />);
    expect(screen.getByRole('radiogroup')).toBeInTheDocument();
  });
});
