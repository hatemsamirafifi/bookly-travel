import { fireEvent, render, screen } from '@testing-library/react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../select';
import { Switch } from '../switch';
import { Textarea } from '../textarea';

it('keeps textarea and switch focus indicators on semantic tokens', () => {
  render(<><Textarea aria-label="Notes" /><Switch id="alerts" /><label htmlFor="alerts">Alerts</label></>);
  expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveClass('focus-visible:ring-focus');
  expect(screen.getByRole('switch', { name: 'Alerts' })).toHaveClass('focus-visible:ring-focus');
});

it('opens a labeled listbox, navigates choices and returns focus on Escape', () => {
  render(<Select value="en" onValueChange={jest.fn()}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="en">English</SelectItem><SelectItem value="es">Spanish</SelectItem></SelectContent></Select>);
  const trigger = screen.getByRole('button', { name: 'en' });
  trigger.focus();
  fireEvent.click(trigger);
  expect(trigger).toHaveAttribute('aria-expanded', 'true');
  expect(screen.getByRole('listbox')).toBeVisible();
  expect(screen.getByRole('option', { name: 'English' })).toHaveFocus();
  fireEvent.keyDown(screen.getByRole('option', { name: 'English' }), { key: 'ArrowDown' });
  expect(screen.getByRole('option', { name: 'Spanish' })).toHaveFocus();
  fireEvent.keyDown(screen.getByRole('listbox'), { key: 'Escape' });
  expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  expect(trigger).toHaveFocus();
});
