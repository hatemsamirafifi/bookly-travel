import { z } from 'zod';

export const pricingTierSchema = z.object({
  id: z.string(),
  name: z.string().min(1, { message: 'partner.tours.errors.tierNameRequired' }),
  price: z.string().refine((val) => !isNaN(Number(val)) && Number(val) > 0, {
    message: 'partner.tours.errors.tierPricePositive',
  }),
  currency: z.string().default('USD'),
  min_participants: z.number().int().min(1, { message: 'partner.tours.errors.minParticipantsPositive' }),
  max_participants: z.number().int().min(1, { message: 'partner.tours.errors.maxParticipantsPositive' }),
}).refine((data) => data.max_participants >= data.min_participants, {
  message: 'partner.tours.errors.maxParticipantsGteMin',
  path: ['max_participants'],
});

export const availabilityRuleSchema = z.object({
  id: z.string(),
  rule_type: z.enum(['daily', 'weekly', 'monthly']),
  days_of_week: z.array(z.number().min(0).max(6)),
  start_time: z.string().regex(/^\d{2}:\d{2}$/, { message: 'partner.tours.errors.invalidTime' }),
  start_date: z.string().min(1, { message: 'partner.tours.errors.startDateRequired' }),
  end_date: z.string().optional().or(z.literal('')),
  capacity: z.number().int().min(1, { message: 'partner.tours.errors.capacityMin' }),
});

export const availabilityExceptionSchema = z.object({
  id: z.string(),
  exception_type: z.enum(['blackout', 'specific']),
  date: z.string().min(1, { message: 'partner.tours.errors.dateRequired' }),
  start_time: z.string().regex(/^\d{2}:\d{2}$/, { message: 'partner.tours.errors.invalidTime' }),
  capacity: z.number().int().min(0),
  price_multiplier: z.string().refine((val) => !isNaN(Number(val)) && Number(val) >= 0, {
    message: 'partner.tours.errors.multiplierNonNegative',
  }),
  note: z.string().optional().or(z.literal('')),
});

export const tourMediaSchema = z.object({
  id: z.string().optional(),
  url: z.string().url({ message: 'partner.tours.errors.invalidUrl' }),
  is_cover: z.boolean(),
});

export const tourItinerarySchema = z.array(z.object({
  day: z.number().int().min(1).max(30),
  title: z.string().min(1).max(160),
  description: z.string().max(2000).optional().nullable(),
  stops: z.array(z.object({
    title: z.string().min(1).max(160),
    description: z.string().max(2000).optional().nullable(),
    duration_minutes: z.number().int().min(1).max(1440).optional().nullable(),
  })).max(20).optional(),
})).max(30);

// Basic details form step (combines basic and details steps)
export const tourBasicDetailsSchema = z.object({
  title: z
    .string()
    .min(1, { message: 'partner.tours.errors.titleRequired' })
    .max(120, { message: 'partner.tours.errors.titleMax' }),
  description: z.string().min(100, { message: 'partner.tours.errors.descriptionMin' }).max(5000),
  category: z.string().min(1, { message: 'partner.tours.errors.categoryRequired' }),
  destination: z.string().min(1, { message: 'partner.tours.errors.destinationRequired' }),
  duration_value: z.string().refine((val) => !isNaN(Number(val)) && Number(val) > 0, {
    message: 'partner.tours.errors.durationPositive',
  }),
  duration_unit: z.enum(['hour', 'day']),
  difficulty_level: z.enum(['easy', 'moderate', 'challenging']),
  meeting_point: z.string().min(1, { message: 'partner.tours.errors.meetingPointRequired' }),
  itinerary: tourItinerarySchema,
  inclusions: z.string().optional().or(z.literal('')),
  languages: z.array(z.string()).default([]),
  cancellation_policy: z.string().optional().or(z.literal('')),
});

export const tourMediaStepSchema = z.object({
  media: z.array(tourMediaSchema).refine((media) => media.some((m) => m.is_cover), {
    message: 'partner.tours.errors.coverImageRequired',
  }),
});

export const tourPricingStepSchema = z.object({
  pricing_tiers: z.array(pricingTierSchema).min(1, { message: 'partner.tours.errors.pricingTierMin' }),
  min_participants: z.number().int().min(1),
  max_participants: z.number().int().min(1),
}).refine((data) => data.max_participants >= data.min_participants, {
  message: 'partner.tours.errors.maxParticipantsGteMin',
  path: ['max_participants'],
});

export const tourAvailabilityStepSchema = z.object({
  availability_rules: z.array(availabilityRuleSchema).min(1, { message: 'partner.tours.errors.availabilityRuleMin' }),
  availability_exceptions: z.array(availabilityExceptionSchema).default([]),
});

// Full tour wizard schema for final submission
export const tourWizardSchema = tourBasicDetailsSchema
  .merge(tourMediaStepSchema)
  .merge(tourPricingStepSchema)
  .merge(tourAvailabilityStepSchema);
