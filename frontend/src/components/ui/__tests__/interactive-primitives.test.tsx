import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import Link from 'next/link';
import { renderToString } from 'react-dom/server';
import { Accordion } from '../Accordion';
import { Tabs } from '../Tabs';
import { Dialog } from '../Dialog';
import { Drawer } from '../Drawer';
import { Chip } from '../Chip';
import { IconButton } from '../IconButton';
import { CarouselRail } from '../CarouselRail';

it('announces selection on an interactive chip and names icon-only actions', () => {
  const toggle = jest.fn();
  render(<><Chip selected onClick={toggle}>Walking</Chip><IconButton label="Save tour">♥</IconButton></>);
  const chip = screen.getByRole('button', { name: 'Walking' });
  expect(chip).toHaveAttribute('aria-pressed', 'true');
  fireEvent.click(chip);
  expect(toggle).toHaveBeenCalledTimes(1);
  expect(screen.getByRole('button', { name: 'Save tour' })).toHaveClass('min-h-11', 'min-w-11');
});

it('connects accordion controls to hidden panels and supports arrow-key focus', () => {
  render(<Accordion items={[{ id: 'a', title: 'Meeting point', content: 'At the station' }, { id: 'b', title: 'Included', content: 'Guide' }]} />);
  const first = screen.getByRole('button', { name: 'Meeting point' });
  expect(first).toHaveAttribute('aria-expanded', 'false');
  fireEvent.click(first);
  expect(first).toHaveAttribute('aria-expanded', 'true');
  expect(screen.getByRole('region', { name: 'Meeting point' })).toHaveTextContent('At the station');
  first.focus();
  fireEvent.keyDown(first, { key: 'ArrowDown' });
  expect(screen.getByRole('button', { name: 'Included' })).toHaveFocus();
});

it('moves tabs with arrows, skips disabled tabs, and activates the matching panel', () => {
  render(<Tabs label="Tour information" items={[{ id: 'overview', label: 'Overview', content: 'Description' }, { id: 'reviews', label: 'Reviews', content: 'Reviews content', disabled: true }, { id: 'plan', label: 'Itinerary', content: 'Day one' }]} />);
  const overview = screen.getByRole('tab', { name: 'Overview' });
  overview.focus();
  fireEvent.keyDown(overview, { key: 'ArrowRight' });
  expect(screen.getByRole('tab', { name: 'Itinerary' })).toHaveFocus();
  expect(screen.getByRole('tab', { name: 'Itinerary' })).toHaveAttribute('aria-selected', 'true');
  expect(screen.getByRole('tabpanel', { name: 'Itinerary' })).toHaveTextContent('Day one');
  fireEvent.keyDown(screen.getByRole('tab', { name: 'Itinerary' }), { key: 'Home' });
  expect(overview).toHaveFocus();
});

for (const Component of [Dialog, Drawer]) {
  it(`${Component.name} traps focus, locks scrolling and restores focus on Escape`, () => {
    function Example() {
      const [open, setOpen] = useState(false);
      return <><button onClick={() => setOpen(true)}>Open</button><Component open={open} onClose={() => setOpen(false)} title="Navigation" closeLabel="Close"><button>Last action</button></Component></>;
    }
    render(<Example />);
    const trigger = screen.getByRole('button', { name: 'Open' });
    trigger.focus();
    fireEvent.click(trigger);
    const close = screen.getByRole('button', { name: 'Close' });
    expect(close).toHaveFocus();
    expect(document.body.style.overflow).toBe('hidden');
    fireEvent.keyDown(close, { key: 'Tab', shiftKey: true });
    expect(screen.getByRole('button', { name: 'Last action' })).toHaveFocus();
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
    expect(document.body.style.overflow).toBe('');
  });
}

it('names a carousel and keeps its scroll area keyboard reachable without forced motion', () => {
  render(<CarouselRail label="Featured tours"><Link href="/tour">Tour</Link></CarouselRail>);
  const rail = screen.getByRole('region', { name: 'Featured tours' });
  expect(rail).toHaveAttribute('tabindex', '0');
  expect(rail).toHaveClass('motion-reduce:scroll-auto');
  expect(screen.getByRole('link', { name: 'Tour' })).toBeVisible();
});

it('dismisses only the top dialog and retains the lower dialog scroll lock', () => {
  const closeFirst = jest.fn();
  function Nested() {
    const [second, setSecond] = useState(false);
    return <Dialog open onClose={closeFirst} title="First" closeLabel="Close first"><button onClick={() => setSecond(true)}>Open second</button><Dialog open={second} onClose={() => setSecond(false)} title="Second" closeLabel="Close second">Details</Dialog></Dialog>;
  }
  render(<Nested />);
  screen.getByRole('button', { name: 'Open second' }).focus();
  fireEvent.click(screen.getByRole('button', { name: 'Open second' }));
  fireEvent.keyDown(screen.getByRole('dialog', { name: 'Second' }), { key: 'Escape' });
  expect(screen.queryByRole('dialog', { name: 'Second' })).not.toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Open second' })).toHaveFocus();
  expect(document.body.style.overflow).toBe('hidden');
  expect(closeFirst).not.toHaveBeenCalled();
});

it('restores page scrolling when overlapping dialogs close out of order', () => {
  const { rerender } = render(<><Dialog open onClose={jest.fn()} title="First" closeLabel="Close first">First</Dialog><Dialog open onClose={jest.fn()} title="Second" closeLabel="Close second">Second</Dialog></>);
  rerender(<><Dialog open={false} onClose={jest.fn()} title="First" closeLabel="Close first">First</Dialog><Dialog open onClose={jest.fn()} title="Second" closeLabel="Close second">Second</Dialog></>);
  expect(document.body.style.overflow).toBe('hidden');
  expect(screen.getByRole('button', { name: 'Close second' })).toHaveFocus();
  rerender(<><Dialog open={false} onClose={jest.fn()} title="First" closeLabel="Close first">First</Dialog><Dialog open={false} onClose={jest.fn()} title="Second" closeLabel="Close second">Second</Dialog></>);
  expect(document.body.style.overflow).toBe('');
});

it('ignores hidden and disabled controls when wrapping dialog focus', () => {
  render(<Dialog open onClose={jest.fn()} title="Filters" closeLabel="Close"><button>Apply</button><button style={{ display: 'none' }}>Hidden</button><input type="hidden" tabIndex={0} /><button disabled tabIndex={0}>Disabled</button></Dialog>);
  const close = screen.getByRole('button', { name: 'Close' });
  fireEvent.keyDown(close, { key: 'Tab', shiftKey: true });
  expect(screen.getByRole('button', { name: 'Apply' })).toHaveFocus();
});

it('can be server-rendered while initially open without accessing the portal DOM', () => {
  expect(() => renderToString(<Dialog open onClose={jest.fn()} title="Filters" closeLabel="Close">Filters</Dialog>)).not.toThrow();
});
