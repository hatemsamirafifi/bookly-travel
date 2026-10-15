import { fireEvent, render, screen } from '@testing-library/react';
import type { TourItineraryDay } from '@/lib/api/types';
import enMessages from '../../../../messages/en.json';
import TourHeader from '../TourHeader';
import TourFacts from '../TourFacts';
import TourHighlights from '../TourHighlights';
import TourItinerary from '../TourItinerary';
import TourInclusions from '../TourInclusions';
import {
  TourCancellation,
  TourImportantInformation,
  TourMeetingPoint,
} from '../TourMeetingPoint';

const tourCatalog = enMessages.tour as unknown as Record<string, string>;
const overviewCatalog = (enMessages.tour as unknown as { overview: Record<string, string> }).overview;

jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, string | number>) => {
    const catalog = namespace === 'tour.overview' ? overviewCatalog : tourCatalog;
    const template = catalog[key] ?? key;
    return Object.entries(values ?? {}).reduce(
      (result, [name, replacement]) => result.replace(`{${name}}`, String(replacement)),
      template,
    );
  },
}));

const headerProps = {
  locale: 'en',
  title: 'Hidden Gems of Rome Walking Tour',
  contentLocale: 'en',
  location: 'Rome, Italy',
  categorySlug: 'walking',
  categoryName: 'Walking Tours',
  ratingAverage: 4.7,
  ratingCount: 124,
  reviewCount: 124,
};

describe('TourHeader', () => {
  it('orders breadcrumbs, title, location and genuine rating', () => {
    const { container } = render(<TourHeader {...headerProps} />);
    const main = container;

    const nav = screen.getByRole('navigation', { name: tourCatalog.breadcrumbs });
    expect(nav.compareDocumentPosition(screen.getByRole('heading', { level: 1 }))).toBe(
      Node.DOCUMENT_POSITION_FOLLOWING,
    );
    const heading = screen.getByRole('heading', { name: headerProps.title });
    expect(heading.compareDocumentPosition(screen.getByText(headerProps.location))).toBe(
      Node.DOCUMENT_POSITION_FOLLOWING,
    );
    expect(screen.getByText(headerProps.location).compareDocumentPosition(
      screen.getByLabelText('Rating: 4.7 out of 5'),
    )).toBe(Node.DOCUMENT_POSITION_FOLLOWING);
    expect(main.textContent).toContain('Walking Tours');
    expect(heading).toHaveAttribute('lang', 'en');
  });

  it('links the genuine review count to the reviews section only when reviews exist', () => {
    const { unmount } = render(<TourHeader {...headerProps} />);
    const link = screen.getByRole('link', { name: /124/ });
    expect(link).toHaveAttribute('href', '#reviews');
    unmount();

    render(<TourHeader {...headerProps} ratingAverage={0} ratingCount={0} reviewCount={0} />);
    expect(screen.queryByLabelText(/out of 5/)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /reviews/i })).not.toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/0\.0 out of 5/);
  });
});

describe('TourFacts', () => {
  const factsProps = {
    locale: 'en',
    durationValue: '3 hours',
    groupMin: 1,
    groupMax: 20,
    difficulty: 'moderate' as const,
    guideLanguages: ['de', 'en', 'es'],
  };

  it('shows supplied duration, party bounds, difficulty and localized guide languages', () => {
    render(<TourFacts {...factsProps} />);

    expect(screen.getByText('3 hours')).toBeInTheDocument();
    expect(screen.getByText('1–20 travelers')).toBeInTheDocument();
    expect(screen.getByText('Moderate')).toBeInTheDocument();
    expect(screen.getByText('Live guide:')).toBeInTheDocument();
    expect(screen.getByText('German, English, Spanish')).toBeInTheDocument();
  });

  it('omits null difficulty and guide languages without inventing facts', () => {
    render(<TourFacts {...factsProps} difficulty={null} guideLanguages={[]} />);

    expect(screen.queryByText('Moderate')).not.toBeInTheDocument();
    expect(screen.queryByText('Live guide:')).not.toBeInTheDocument();
    expect(screen.getByText('3 hours')).toBeInTheDocument();
  });
});

describe('TourHighlights', () => {
  it('renders supplied highlights verbatim in their actual language', () => {
    const { container } = render(
      <TourHighlights items={['Hidden courtyards', 'Local artisan shops']} contentLocale="es" />,
    );

    expect(screen.getByText('Hidden courtyards')).toBeInTheDocument();
    expect(screen.getByText('Local artisan shops')).toBeInTheDocument();
    expect(container.querySelector('ul[lang="es"]')).not.toBeNull();
  });

  it('omits whitespace-only entries without an empty section', () => {
    const { container } = render(
      <TourHighlights items={['   ', '']} contentLocale="en" />,
    );

    expect(container.querySelector('#highlights')).toBeNull();
  });
});

describe('TourItinerary', () => {
  const days: TourItineraryDay[] = [
    { day: 7, title: 'First seventh day', description: 'Walk with the guide.', stops: [{ title: 'Photo stop', description: 'Ten minutes.' }] },
    { day: 2, title: 'Second day out of sequence', description: null, stops: [] },
  ];

  it('preserves authored day order, text and actual language without invention', () => {
    const { container } = render(<TourItinerary days={days} itineraryLocale="en" />);

    const headings = screen.getAllByRole('heading', { level: 3 });
    expect(headings.map((heading) => heading.textContent)).toEqual([
      'Day 7: First seventh day',
      'Day 2: Second day out of sequence',
    ]);
    expect(screen.getByText('Walk with the guide.')).toBeInTheDocument();
    expect(screen.getByText(/Photo stop/)).toBeInTheDocument();
    expect(container.querySelector('#itinerary')).not.toBeNull();
    // No synthesized transport/times/coordinates.
    expect(container.querySelector('#itinerary')?.textContent).not.toMatch(/km|minutes? by|GPS|latitude/i);
  });

  it('renders nothing for an empty translated itinerary', () => {
    const { container } = render(<TourItinerary days={[]} itineraryLocale="es" />);

    expect(container.querySelector('#itinerary')).toBeNull();
  });
});

describe('TourInclusions', () => {
  it('exposes inclusions and exclusions through accessible disclosures', () => {
    render(
      <TourInclusions
        inclusions={['Expert local guide']}
        exclusions={['Gratuities']}
        contentLocale="en"
      />,
    );

    const included = screen.getByRole('button', { name: "What's Included" });
    const excluded = screen.getByRole('button', { name: "What's Excluded" });
    expect(included).toHaveAttribute('aria-expanded', 'false');
    fireEvent.click(included);
    expect(screen.getByText('Expert local guide')).toBeVisible();
    fireEvent.click(excluded);
    expect(screen.getByText('Gratuities')).toBeVisible();
  });

  it('omits absent lists with their entries', () => {
    const { container } = render(
      <TourInclusions inclusions={[]} exclusions={['   ']} contentLocale="en" />,
    );

    expect(screen.queryByRole('button', { name: "What's Included" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "What's Excluded" })).not.toBeInTheDocument();
    expect(container.textContent).toBe('');
  });
});

describe('practical information', () => {
  it('keeps meeting point, cancellation and important information fully readable', () => {
    render(
      <>
        <TourMeetingPoint meetingPoint="Colosseum main entrance, meet by the arch." contentLocale="en" />
        <TourCancellation cancellationPolicy="Free cancellation up to 24 hours before the tour starts, no questions asked." contentLocale="en" />
        <TourImportantInformation items={['Bring your passport for the Vatican entry check.']} contentLocale="en" />
      </>,
    );

    expect(
      screen.getByText('Colosseum main entrance, meet by the arch.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Free cancellation up to 24 hours before the tour starts, no questions asked.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Bring your passport for the Vatican entry check.'),
    ).toBeInTheDocument();
    // Fully readable: no preview/clip control hides practical conditions.
    expect(screen.queryByRole('button', { name: /read more/i })).not.toBeInTheDocument();
  });

  it('renders no sections for absent practical content and invents no maps or rights', () => {
    const { container } = render(
      <>
        <TourMeetingPoint meetingPoint="   " contentLocale="en" />
        <TourCancellation cancellationPolicy="" contentLocale="en" />
        <TourImportantInformation items={[]} contentLocale="en" />
      </>,
    );

    expect(container.querySelector('#meeting')).toBeNull();
    expect(container.querySelector('#cancellation')).toBeNull();
    expect(container.querySelector('#important')).toBeNull();
    expect(container.textContent).toBe('');
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
