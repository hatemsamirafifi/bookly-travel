import {
  tourBasicDetailsSchema,
  tourMediaStepSchema,
  tourPricingStepSchema,
  tourAvailabilityStepSchema,
  pricingTierSchema,
  availabilityRuleSchema,
} from '../partner';

const validDetails = {
  title: 'Tuscan Wine Tasting',
  description: 'A full day touring vineyards with expert guides.',
  category: 'food',
  destination: 'Tuscany',
  duration_value: '8',
  duration_unit: 'hour' as const,
  difficulty_level: 'easy' as const,
  meeting_point: 'Piazza del Campo',
};

describe('tourBasicDetailsSchema', () => {
  it('accepts a complete, valid details payload', () => {
    expect(tourBasicDetailsSchema.safeParse(validDetails).success).toBe(true);
  });

  it('rejects an empty title', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, title: '' });
    expect(r.success).toBe(false);
  });

  it('rejects a title over 120 characters', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, title: 'x'.repeat(121) });
    expect(r.success).toBe(false);
  });

  it('rejects a description shorter than 10 characters', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, description: 'short' });
    expect(r.success).toBe(false);
  });

  it('rejects an empty category and destination', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, category: '' }).success
    ).toBe(false);
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, destination: '' }).success
    ).toBe(false);
  });

  it('rejects a non-positive duration', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, duration_value: '0' }).success
    ).toBe(false);
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, duration_value: '-3' }).success
    ).toBe(false);
  });

  it('rejects an empty meeting point', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, meeting_point: '' }).success
    ).toBe(false);
  });
});

describe('pricingTierSchema', () => {
  const validTier = {
    id: '1',
    name: 'Adult',
    price: '50',
    currency: 'USD',
    min_participants: 1,
    max_participants: 10,
  };

  it('accepts a valid tier', () => {
    expect(pricingTierSchema.safeParse(validTier).success).toBe(true);
  });

  it('rejects a non-positive price', () => {
    expect(pricingTierSchema.safeParse({ ...validTier, price: '0' }).success).toBe(false);
    expect(pricingTierSchema.safeParse({ ...validTier, price: '-5' }).success).toBe(false);
  });

  it('rejects max_participants < min_participants', () => {
    const r = pricingTierSchema.safeParse({ ...validTier, min_participants: 5, max_participants: 2 });
    expect(r.success).toBe(false);
  });
});

describe('tourPricingStepSchema', () => {
  it('requires at least one pricing tier', () => {
    const r = tourPricingStepSchema.safeParse({
      pricing_tiers: [],
      min_participants: 1,
      max_participants: 10,
    });
    expect(r.success).toBe(false);
  });
});

describe('tourMediaStepSchema', () => {
  it('requires at least one cover image', () => {
    const r = tourMediaStepSchema.safeParse({
      media: [{ id: '1', url: 'https://example.com/a.png', is_cover: false }],
    });
    expect(r.success).toBe(false);
  });

  it('accepts media with a cover image', () => {
    const r = tourMediaStepSchema.safeParse({
      media: [
        { id: '1', url: 'https://example.com/a.png', is_cover: true },
        { id: '2', url: 'https://example.com/b.png', is_cover: false },
      ],
    });
    expect(r.success).toBe(true);
  });
});

describe('availabilityRuleSchema', () => {
  const validRule = {
    id: '1',
    rule_type: 'weekly' as const,
    days_of_week: [1],
    start_time: '09:00',
    start_date: '2026-01-01',
    capacity: 10,
  };

  it('accepts a valid rule', () => {
    expect(availabilityRuleSchema.safeParse(validRule).success).toBe(true);
  });

  it('rejects a malformed start time', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, start_time: '9:00' }).success
    ).toBe(false);
  });

  it('rejects a missing start date', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, start_date: '' }).success
    ).toBe(false);
  });

  it('rejects capacity below 1', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, capacity: 0 }).success
    ).toBe(false);
  });
});

describe('tourAvailabilityStepSchema', () => {
  it('requires at least one availability rule', () => {
    const r = tourAvailabilityStepSchema.safeParse({
      availability_rules: [],
      availability_exceptions: [],
    });
    expect(r.success).toBe(false);
  });
});