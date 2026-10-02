import { render, screen } from '@testing-library/react';
import { Button } from '../button';
import { Input } from '../input';
import { designTokens } from '@/lib/design-tokens';

describe('Bookly shared design primitives', () => {
  it('uses the approved brand anchors and shared font', () => {
    expect(designTokens.colors.brand.navy).toBe('#0A2540');
    expect(designTokens.colors.brand.gold).toBe('#FFB800');
    expect(designTokens.typography.fontFamily).toContain('Plus Jakarta Sans');
  });

  it('keeps button labels and disabled state accessible', () => {
    render(<Button disabled>Continue</Button>);
    const button = screen.getByRole('button', { name: 'Continue' });
    expect(button).toBeDisabled();
    expect(button).toHaveClass('focus-visible:ring-2');
    expect(button).toHaveClass('bg-primary', 'focus-visible:ring-focus');
    expect(button.className).not.toMatch(/\[#[0-9a-f]+\]/i);
  });

  it('prevents repeated actions while loading and retains the accessible label', () => {
    render(<Button loading>Save tour</Button>);
    expect(screen.getByRole('button', { name: 'Save tour' })).toBeDisabled();
    expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'true');
  });

  it('preserves the input accessible name and invalid state', () => {
    render(<Input aria-label="Email" aria-invalid="true" />);
    const input = screen.getByRole('textbox', { name: 'Email' });
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveClass('focus-visible:ring-2');
  });
});
