import { z } from 'zod';

/**
 * Validation schema for the traveler profile update payload.
 *
 * Phone is optional but, when provided, must match the E.164-ish pattern used
 * by the backend. The `refine` message is a sentinel — ProfileSettings maps the
 * failing field to a localized message via next-intl so the rendered error
 * stays locale-aware (see `traveler.profile.invalidPhone`).
 */
export const profileSchema = z.object({
  first_name: z.string().min(1, 'REQUIRED').max(255, 'TOO_LONG'),
  last_name: z.string().min(1, 'REQUIRED').max(255, 'TOO_LONG'),
  phone: z
    .string()
    .max(30, 'TOO_LONG')
    .optional()
    .nullable()
    .refine((v) => !v || /^\+?[0-9\s().-]{7,20}$/.test(v), 'INVALID_PHONE'),
  preferred_language: z.enum(['en', 'es', 'it']),
  preferred_currency: z.string().min(1, 'REQUIRED').max(10, 'TOO_LONG'),
  marketing_emails: z.boolean(),
});

export type ProfileFormData = z.infer<typeof profileSchema>;