'use client';

import { useState } from 'react';
import { useForm, Controller, Resolver } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslations } from 'next-intl';
import StarRating from './StarRating';
import { reviewSchema, editReviewSchema } from '@/lib/validators/review';

interface ReviewFormData {
  rating: number;
  comment: string;
  booking_reference?: string;
}

interface ReviewFormProps {
  bookingReference?: string;
  locale: string;
  existingReview?: {
    id: number;
    rating: number;
    comment?: string | null;
  };
  onSubmit: (data: { rating: number; comment: string }) => Promise<void>;
  onCancel?: () => void;
}

export default function ReviewForm({
  bookingReference,
  locale,
  existingReview,
  onSubmit,
  onCancel,
}: ReviewFormProps) {
  const t = useTranslations('reviews');
  const [serverError, setServerError] = useState('');
  const [success, setSuccess] = useState(false);

  const isEdit = !!existingReview;
  const resolverSchema = isEdit ? editReviewSchema : reviewSchema;

  const {
    register,
    handleSubmit,
    control,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<ReviewFormData>({
    resolver: zodResolver(resolverSchema) as unknown as Resolver<ReviewFormData>,
    defaultValues: {
      rating: existingReview?.rating ?? 0,
      comment: existingReview?.comment ?? '',
      booking_reference: bookingReference ?? '',
    },
  });

  const ratingValue = watch('rating') || 0;
  const commentValue = watch('comment') || '';
  const charCount = commentValue.length;

  const formatError = (message?: string) => {
    if (!message) return '';
    return t(message.startsWith('reviews.') ? message.replace('reviews.', '') : message);
  };

  const handleFormSubmit = async (data: ReviewFormData) => {
    setServerError('');
    try {
      await onSubmit({ rating: data.rating, comment: data.comment || '' });
      setSuccess(true);
    } catch (err: unknown) {
      if (err instanceof Error) {
        if (err.message.includes('429')) {
          setServerError(t('rate_limit_message'));
        } else {
          setServerError(err.message || t('failed_to_load'));
        }
      } else {
        setServerError(t('failed_to_load'));
      }
    }
  };

  if (success) {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-center">
        <p className="text-sm font-medium text-green-800">
          {isEdit ? t('updated') : t('thank_you')}
        </p>
        {!isEdit && <p className="mt-1 text-xs text-green-600">It is now visible on the tour page.</p>}
      </div>
    );
  }

  return (
    <form
      onSubmit={handleSubmit(handleFormSubmit)}
      className="rounded-lg border border-gray-200 bg-white p-4"
      noValidate
    >
      <h3 className="mb-3 text-sm font-semibold text-gray-700">
        {isEdit ? t('edit_review') : t('leave_review')}
      </h3>

      {isEdit && (
        <p className="mb-2 text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded inline-block">
          {t('edited')}
        </p>
      )}

      <div className="mb-3">
        <label className="block mb-1 text-xs font-medium text-gray-600">{t('your_rating')}</label>
        <Controller
          name="rating"
          control={control}
          render={({ field: { value, onChange } }) => (
            <StarRating value={value} onChange={onChange} size="lg" />
          )}
        />
        {errors.rating && (
          <p className="mt-1 text-xs text-red-600" role="alert">
            {formatError(errors.rating.message)}
          </p>
        )}
      </div>

      <div className="mb-3">
        <label htmlFor="review-comment" className="block mb-1 text-xs font-medium text-gray-600">
          {t('your_review')}
        </label>
        <textarea
          id="review-comment"
          {...register('comment')}
          maxLength={2000}
          rows={4}
          className={`w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 ${
            errors.comment ? 'border-red-300' : 'border-gray-300'
          }`}
          placeholder={t('share_experience')}
          disabled={isSubmitting}
        />
        {errors.comment && (
          <p className="mt-1 text-xs text-red-600" role="alert">
            {formatError(errors.comment.message)}
          </p>
        )}
        <div className="mt-1 flex justify-end">
          <span
            className={`text-xs ${charCount > 2000 ? 'text-red-500 font-medium' : 'text-gray-400'}`}
          >
            {t('char_count', { count: charCount })}
          </span>
        </div>
      </div>

      {serverError && (
        <div className="mb-3 rounded-md bg-red-50 p-2 text-xs text-red-700" role="alert">
          {serverError}
        </div>
      )}

      <input type="hidden" name="locale" value={locale} />
      {!isEdit && bookingReference && (
        <input type="hidden" {...register('booking_reference')} />
      )}

      <div className="flex items-center gap-2">
        <button
          type="submit"
          disabled={isSubmitting || ratingValue === 0}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {isSubmitting
            ? t('submitting')
            : isEdit
            ? t('update_review')
            : t('submit_review')}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
          >
            {t('cancel')}
          </button>
        )}
      </div>
    </form>
  );
}

