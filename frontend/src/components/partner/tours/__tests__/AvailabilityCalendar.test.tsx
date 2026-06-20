import { render, screen, fireEvent } from '@testing-library/react';
import { AvailabilityCalendar } from '../AvailabilityCalendar';
import { useTourWizardStore } from '@/lib/stores/tourWizard';

describe('AvailabilityCalendar', () => {
  beforeEach(() => {
    localStorage.clear();
    useTourWizardStore.getState().reset();
  });

  it('shows empty states when no rules or exceptions exist', () => {
    render(<AvailabilityCalendar />);
    expect(screen.getByText(/No recurring rules yet/)).toBeInTheDocument();
    expect(screen.getByText(/No exceptions yet/)).toBeInTheDocument();
  });

  it('adds a recurring rule via "Add Rule"', () => {
    render(<AvailabilityCalendar />);
    expect(useTourWizardStore.getState().formData.availability_rules).toHaveLength(0);
    fireEvent.click(screen.getByRole('button', { name: 'Add Rule' }));
    expect(useTourWizardStore.getState().formData.availability_rules).toHaveLength(1);
    // Default rule type is weekly, so a "Weekly Rule" label renders.
    expect(screen.getByText('Weekly Rule')).toBeInTheDocument();
  });

  it('adds an exception via "Add Exception"', () => {
    render(<AvailabilityCalendar />);
    fireEvent.click(screen.getByRole('button', { name: 'Add Exception' }));
    expect(useTourWizardStore.getState().formData.availability_exceptions).toHaveLength(1);
  });

  it('toggles days of the week on a weekly rule', () => {
    render(<AvailabilityCalendar />);
    fireEvent.click(screen.getByRole('button', { name: 'Add Rule' }));
    const monButton = screen.getByText('Mon');
    fireEvent.click(monButton);
    expect(useTourWizardStore.getState().formData.availability_rules[0].days_of_week).toContain(1);
    fireEvent.click(monButton);
    expect(useTourWizardStore.getState().formData.availability_rules[0].days_of_week).not.toContain(1);
  });
});