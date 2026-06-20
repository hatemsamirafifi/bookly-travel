import { render, screen, fireEvent } from '@testing-library/react';
import { PricingTierForm } from '../PricingTierForm';
import { useTourWizardStore } from '@/lib/stores/tourWizard';

describe('PricingTierForm', () => {
  beforeEach(() => {
    localStorage.clear();
    useTourWizardStore.getState().reset();
  });

  it('shows the empty-state message when no tiers exist', () => {
    render(<PricingTierForm />);
    expect(screen.getByText(/At least one pricing tier is required/)).toBeInTheDocument();
  });

  it('adds a tier via "Add Tier"', () => {
    render(<PricingTierForm />);
    fireEvent.click(screen.getByRole('button', { name: 'Add Tier' }));
    expect(useTourWizardStore.getState().formData.pricing_tiers).toHaveLength(1);
    expect(screen.getByText('Tier 1')).toBeInTheDocument();
  });

  it('removes a tier (disabled until more than one exists)', () => {
    render(<PricingTierForm />);
    fireEvent.click(screen.getByRole('button', { name: 'Add Tier' }));
    // With a single tier the remove button is disabled.
    expect(screen.getByLabelText('Remove pricing tier 1')).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Add Tier' }));
    fireEvent.click(screen.getByLabelText('Remove pricing tier 1'));
    expect(useTourWizardStore.getState().formData.pricing_tiers).toHaveLength(1);
  });

  it('disables "Add Tier" once the maximum of 10 tiers is reached', () => {
    render(<PricingTierForm />);
    const addBtn = screen.getByRole('button', { name: 'Add Tier' });
    for (let i = 0; i < 10; i++) {
      fireEvent.click(addBtn);
    }
    expect(addBtn).toBeDisabled();
    expect(useTourWizardStore.getState().formData.pricing_tiers).toHaveLength(10);
  });
});