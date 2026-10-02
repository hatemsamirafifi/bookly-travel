import { fireEvent, render, screen } from '@testing-library/react';
import ImageGallery from '../ImageGallery';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: { number?: number }) => {
    const labels: Record<string, string> = {
      open: 'Open image gallery', dialog: 'Image lightbox', close: 'Close lightbox',
      previous: 'Previous image', next: 'Next image', thumbnails: 'Thumbnail navigation',
    };
    return key === 'view' ? `View image ${values?.number}` : labels[key];
  },
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => {
    const { fill, priority, ...rest } = props;
    // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
    return <img {...rest} data-fill={fill ? 'true' : undefined} data-priority={priority ? 'true' : undefined} />;
  },
}));
jest.mock('@/lib/images', () => ({ getImagePlaceholderProps: () => ({}) }));

const images = [
  { url: 'https://images.example.com/cover.jpg', is_cover: true, alt: 'Main square' },
  { url: 'https://images.example.com/second.jpg', is_cover: false, alt: 'Old street' },
];

describe('ImageGallery keyboard behavior', () => {
  it('opens from a focusable trigger, navigates in order, and returns focus after Escape', () => {
    render(<ImageGallery images={images} title="Rome walk" />);
    const trigger = screen.getByRole('button', { name: 'Open image gallery' });
    trigger.focus();
    fireEvent.click(trigger);

    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(dialog).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Close lightbox' })).toHaveFocus();
    fireEvent.keyDown(window, { key: 'Tab', shiftKey: true });
    expect(dialog.querySelectorAll('button')[2]).toHaveFocus();
    fireEvent.keyDown(window, { key: 'Tab' });
    expect(screen.getByRole('button', { name: 'Close lightbox' })).toHaveFocus();
    fireEvent.keyDown(window, { key: 'ArrowRight' });
    expect(dialog.querySelector('img')).toHaveAttribute('alt', 'Old street');
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('does not show enlargement controls for missing media', () => {
    render(<ImageGallery images={[]} title="Rome walk" />);
    expect(screen.queryByRole('button', { name: 'Open image gallery' })).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
