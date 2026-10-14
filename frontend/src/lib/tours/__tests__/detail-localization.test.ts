import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

type Catalog = Record<string, unknown>;

interface NewKey {
  path: string;
  placeholders: string[];
}

const NEW_KEYS: NewKey[] = [
  { path: 'tour.ratingLabel', placeholders: ['rating'] },
  { path: 'tour.overview.readMore', placeholders: [] },
  { path: 'tour.overview.readLess', placeholders: [] },
  { path: 'tour.gallery.seeAll', placeholders: ['count'] },
  { path: 'tour.gallery.counter', placeholders: ['current', 'total'] },
  { path: 'tour.gallery.empty', placeholders: [] },
  { path: 'tour.gallery.imageFailed', placeholders: [] },
  { path: 'tour.booking.estimatedTotal', placeholders: [] },
  { path: 'tour.booking.invalidDate', placeholders: [] },
  { path: 'tour.booking.invalidCount', placeholders: ['min', 'max'] },
  { path: 'tour.booking.dateChanged', placeholders: [] },
  { path: 'tour.authRecovery.continueNotice', placeholders: [] },
  { path: 'tour.authRecovery.sessionExpired', placeholders: [] },
  { path: 'tour.states.withdrawnTitle', placeholders: [] },
  { path: 'tour.states.withdrawnDescription', placeholders: [] },
  { path: 'traveler.wishlistButton.saved', placeholders: [] },
  { path: 'traveler.wishlistButton.saveFailed', placeholders: [] },
  { path: 'tour.booking.invalidPricing', placeholders: [] },
  { path: 'tour.booking.invalidBounds', placeholders: [] },
  { path: 'tour.booking.countChanged', placeholders: [] },
  { path: 'tour.booking.editSelection', placeholders: [] },
  { path: 'traveler.wishlistButton.removed', placeholders: [] },
  { path: 'traveler.wishlistButton.statusFailed', placeholders: [] },
  { path: 'traveler.wishlistButton.denied', placeholders: [] },
];

function getByPath(catalog: Catalog, path: string): unknown {
  return path.split('.').reduce<unknown>((node, segment) => {
    if (typeof node !== 'object' || node === null) return undefined;
    return (node as Record<string, unknown>)[segment];
  }, catalog);
}

function extractPlaceholders(message: string): string[] {
  const names = new Set<string>();
  const pattern = /\{([A-Za-z0-9_]+)(?:[^A-Za-z0-9_}][^}]*)?\}/g;
  let match: RegExpExecArray | null;
  while ((match = pattern.exec(message)) !== null) {
    names.add(match[1]);
  }
  return [...names].sort();
}

describe('spec020 detail localization catalog parity', () => {
  const catalogs: Array<{ locale: string; messages: Catalog }> = [
    { locale: 'en', messages: enMessages as Catalog },
    { locale: 'es', messages: esMessages as Catalog },
    { locale: 'it', messages: itMessages as Catalog },
  ];

  it.each(NEW_KEYS.map((key) => [key.path]))(
    'provides %s as a nonempty message in en, es and it',
    (path) => {
      for (const { messages } of catalogs) {
        const value = getByPath(messages, path);
        expect(typeof value).toBe('string');
        expect((value as string).trim().length).toBeGreaterThan(0);
      }
    },
  );

  it.each(NEW_KEYS.map((key) => [key.path, key.placeholders]))(
    'keeps equal ICU placeholders for %s across all three catalogs',
    (path, expected) => {
      const expectedSorted = [...(expected as string[])].sort();
      for (const { messages } of catalogs) {
        const value = getByPath(messages, path as string) as string;
        expect(extractPlaceholders(value)).toEqual(expectedSorted);
      }
    },
  );
});
