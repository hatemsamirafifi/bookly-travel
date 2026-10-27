import { fireEvent, render, screen } from '@testing-library/react';
import TourOverview, { DESCRIPTION_PREVIEW_CODE_POINTS } from '../TourOverview';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) =>
    key === 'readMore' ? 'Read more' : key === 'readLess' ? 'Read less' : key,
}));

const SHORT_TEXT = 'A compact description of the Eternal City walk.';
const EXACT_TEXT = 'x'.repeat(600);
const LONG_TEXT = `${'y'.repeat(650)} Full trailing sentence that must stay reachable.`;

describe('TourOverview', () => {
  it('exposes the 600-code-point preview threshold as a named export', () => {
    expect(DESCRIPTION_PREVIEW_CODE_POINTS).toBe(600);
  });

  it('renders short descriptions fully readable with no disclosure control', () => {
    render(<TourOverview text={SHORT_TEXT} contentLocale="en" />);

    expect(screen.getByText(SHORT_TEXT)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /read (more|less)/i })).not.toBeInTheDocument();
  });

  it('renders a 600-code-point description fully with no disclosure control', () => {
    render(<TourOverview text={EXACT_TEXT} contentLocale="en" />);

    expect(screen.getByText(EXACT_TEXT)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /read (more|less)/i })).not.toBeInTheDocument();
  });

  it('collapses long descriptions to a preview with an accessible expand control', () => {
    render(<TourOverview text={LONG_TEXT} contentLocale="en" />);

    // The complete text must not be accessibly duplicated alongside the preview.
    expect(screen.queryByText(LONG_TEXT)).not.toBeInTheDocument();
    const expand = screen.getByRole('button', { name: 'Read more' });
    expect(expand).toHaveAttribute('aria-expanded', 'false');
    const panelId = expand.getAttribute('aria-controls');
    expect(panelId).toBeTruthy();
  });

  it('makes the full text reachable on expand and collapsible again', () => {
    render(<TourOverview text={LONG_TEXT} contentLocale="en" />);

    const expand = screen.getByRole('button', { name: 'Read more' });
    fireEvent.click(expand);

    expect(screen.getByText(LONG_TEXT)).toBeInTheDocument();
    const collapse = screen.getByRole('button', { name: 'Read less' });
    expect(collapse).toHaveAttribute('aria-expanded', 'true');

    fireEvent.click(collapse);
    expect(screen.queryByText(LONG_TEXT)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Read more' })).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });

  it('restores focus to the toggle before hiding a focused panel on collapse', () => {
    render(<TourOverview text={LONG_TEXT} contentLocale="en" />);

    fireEvent.click(screen.getByRole('button', { name: 'Read more' }));
    const panel = screen.getByText(LONG_TEXT).closest('div');
    expect(panel).not.toBeNull();
    // Simulate keyboard focus inside the expanded panel (e.g. a focused
    // descendant before collapse hides it).
    (panel as HTMLElement).tabIndex = -1;
    (panel as HTMLElement).focus();
    expect((panel as HTMLElement).contains(document.activeElement)).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Read less' }));
    expect(screen.getByRole('button', { name: 'Read more' })).toHaveFocus();
    expect(screen.queryByText(LONG_TEXT)).not.toBeInTheDocument();
  });

  it('slices the preview by Unicode code points, never splitting surrogate pairs', () => {
    const text = `${'a'.repeat(599)}😀${'b'.repeat(20)}`;
    expect(Array.from(text).length).toBeGreaterThan(600);
    const expectedPreview = Array.from(text).slice(0, 600).join('');
    expect(expectedPreview).toContain('😀');
    render(<TourOverview text={text} contentLocale="en" />);

    const expand = screen.getByRole('button', { name: 'Read more' });
    const panelId = expand.getAttribute('aria-controls');
    const panel = document.getElementById(panelId as string);
    // The controlled region shows exactly the first 600 code points, whole.
    expect(panel?.textContent).toBe(expectedPreview);
    expect(panel?.textContent).not.toMatch(/�/);
    fireEvent.click(expand);
    expect(screen.getByText(text)).toBeInTheDocument();
  });

  it('declares the actual content language on the rendered text', () => {
    const { container } = render(<TourOverview text={SHORT_TEXT} contentLocale="es" />);

    expect(container.querySelector('[lang="es"]')).toHaveTextContent(SHORT_TEXT);
  });
});
