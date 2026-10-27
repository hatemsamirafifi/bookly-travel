import { render, screen } from '@testing-library/react';
import { Breadcrumbs } from '../Breadcrumbs';

describe('Breadcrumbs', () => {
  it('keeps default behavior unchanged without per-item language', () => {
    render(
      <Breadcrumbs
        label="Breadcrumbs"
        items={[
          { label: 'Home', href: '/en' },
          { label: 'Walking Tours', href: '/en/categories/walking' },
          { label: 'Hidden Gems' },
        ]}
      />,
    );

    const nav = screen.getByRole('navigation', { name: 'Breadcrumbs' });
    expect(nav.querySelectorAll('li')).toHaveLength(3);
    expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/en');
    const current = screen.getByText('Hidden Gems');
    expect(current).toHaveAttribute('aria-current', 'page');
    expect(current).not.toHaveAttribute('lang');
    expect(nav.querySelector('[lang]')).toBeNull();
  });

  it('declares the tour title content locale independently of the page locale', () => {
    render(
      <Breadcrumbs
        label="Breadcrumbs"
        items={[
          { label: 'Home', href: '/es' },
          { label: 'Tours a pie', href: '/es/categories/walking' },
          { label: 'Hidden Gems of Rome Walking Tour', lang: 'en' },
        ]}
      />,
    );

    const title = screen.getByText('Hidden Gems of Rome Walking Tour');
    expect(title.tagName).toBe('SPAN');
    expect(title).toHaveAttribute('lang', 'en');
    expect(title).toHaveAttribute('aria-current', 'page');
    // Page-locale crumbs carry no content-language override.
    expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute('lang');
  });
});
