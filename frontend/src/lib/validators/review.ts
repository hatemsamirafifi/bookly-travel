import { z } from 'zod';

export const reviewSchema = z.object({
  rating: z
    .number()
    .min(1, { message: 'reviews.errors.ratingRequired' })
    .max(5, { message: 'reviews.errors.ratingInvalid' }),
  comment: z
    .string()
    .max(2000, { message: 'reviews.errors.commentTooLong' })
    .optional()
    .or(z.literal('')),
  booking_reference: z.string().min(1, { message: 'reviews.errors.bookingReferenceRequired' }),
});

export const editReviewSchema = z.object({
  rating: z
    .number()
    .min(1, { message: 'reviews.errors.ratingRequired' })
    .max(5, { message: 'reviews.errors.ratingInvalid' }),
  comment: z
    .string()
    .max(2000, { message: 'reviews.errors.commentTooLong' })
    .optional()
    .or(z.literal('')),
});

export type ReviewFormData = z.infer<typeof reviewSchema>;
