import { z } from 'zod';

/**
 * Validation schema for the public booking creation payload.
 *
 * Mirrors `CreateBookingRequest` (src/lib/api/bookings.ts). Date and
 * participant count originate from selectors rather than free text, so this
 * schema primarily guards against submitting with a missing date/participants
 * and enforces basic shape before the network call. The backend remains the
 * source of truth for per-tour group-size limits and availability (422/409).
 */
export const createBookingSchema = z.object({
  tour_slug: z.string().min(1, 'REQUIRED'),
  tour_date: z.string().min(1, 'REQUIRED'),
  participant_count: z.number().int('REQUIRED').min(1, 'REQUIRED'),
  locale: z.enum(['en', 'es', 'it']).optional(),
  page_load_price: z.number().int().nonnegative().optional(),
});

export type CreateBookingFormData = z.infer<typeof createBookingSchema>;