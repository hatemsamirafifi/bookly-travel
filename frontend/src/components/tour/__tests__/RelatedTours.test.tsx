import { render, screen, within } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import RelatedTours from '../RelatedTours';
import { createFullTourDetail } from '../../../../tests/fixtures/tour-detail';
import en from '../../../../messages/en.json';
import es from '../../../../messages/es.json';
import italian from '../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));
jest.mock('@/lib/hooks/useAuth', () => ({ useAuth: () => ({ user: null }) }));
jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => {
    const { fill, blurDataURL, placeholder, ...rest } = props;
    void fill; void blurDataURL; void placeholder;
    // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
    return <img {...rest} />;
  },
}));

const catalogs = { en, es, it: italian };

beforeEach(() => localStorage.clear());

it('omits the related section when the API supplies no cards', () => {
  const { container } = render(<RelatedTours tours={[]} locale="en" title="Related tours" />);
  expect(container).toBeEmptyDOMElement();
});

it.each(['en', 'es', 'it'] as const)('renders only the supplied card and keeps its %s route', (locale) => {
  const cards = createFullTourDetail(new Date('2026-10-14T00:00:00Z')).related_tours;
  if (!cards) throw new Error('The full tour fixture must supply related cards');
  expect(cards).toHaveLength(1);
  render(
    <NextIntlClientProvider locale={locale} messages={catalogs[locale]}>
      <RelatedTours tours={cards} locale={locale} title="Related tours" />
    </NextIntlClientProvider>,
  );
  const section = screen.getByRole('region', { name: 'Related tours' });
  const links = within(section).getAllByRole('link');
  expect(links).toHaveLength(1);
  expect(links[0]).toHaveAttribute('href', `/${locale}/tours/${cards[0].slug}`);
  expect(within(links[0]).getByRole('heading', { name: cards[0].title })).toBeVisible();
  expect(within(section).getAllByRole('button')).toHaveLength(1);
  expect(within(section).getByRole('button')).toHaveAttribute('data-tour-id', String(cards[0].id));
});
