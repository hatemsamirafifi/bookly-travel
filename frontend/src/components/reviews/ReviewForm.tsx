'use client';

import { useState } from 'react';
import StarRating from './StarRating';

interface ReviewFormData {
  rating: number;
  comment: string;
}

interface ReviewFormProps {
  bookingReference: string;
  locale: string;
  existingReview?: {
    id: number;
    rating: number;
    comment?: string | null;
  };
  onSubmit: (data: ReviewFormData) => Promise<void>;
  onCancel?: () => void;
}

export default function ReviewForm({
  bookingReference: _bookingReference,
  locale,
  existingReview,
  onSubmit,
  onCancel,
}: ReviewFormProps) {
  const [rating, setRating] = useState(existingReview?.rating ?? 0);
  const [comment, setComment] = useState(existingReview?.comment ?? '');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  const isEdit = !!existingReview;
  const charCount = comment.length;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (rating < 1 || rating > 5) {
      setError('Please select a rating.');
      return;
    }

    if (charCount > 2000) {
      setError('Comment must be 2000 characters or fewer.');
      return;
    }

    setSubmitting(true);
    try {
      await onSubmit({ rating, comment: comment || '' });
      setSuccess(true);
    } catch (err: unknown) {
      if (err instanceof Error) {
        if (err.message.includes('429')) {
          setError('You are submitting reviews too quickly. Please wait before trying again.');
        } else {
          setError(err.message || 'Something went wrong. Please try again.');
        }
      } else {
        setError('Something went wrong. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (success) {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-center">
        <p className="text-sm font-medium text-green-800">
          {isEdit ? 'Your review has been updated!' : 'Thank you for your review!'}
        </p>
        {!isEdit && <p className="mt-1 text-xs text-green-600">It is now visible on the tour page.</p>}
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="rounded-lg border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">
        {isEdit ? 'Edit Your Review' : 'Write a Review'}
      </h3>

      {isEdit && (
        <p className="mb-2 text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded inline-block">
          Edited
        </p>
      )}

      <div className="mb-3">
        <label className="block mb-1 text-xs font-medium text-gray-600">Your Rating</label>
        <StarRating value={rating} onChange={setRating} size="lg" />
      </div>

      <div className="mb-3">
        <label htmlFor="review-comment" className="block mb-1 text-xs font-medium text-gray-600">
          Your Review (optional)
        </label>
        <textarea
          id="review-comment"
          value={comment}
          onChange={(e) => setComment(e.target.value)}
          maxLength={2000}
          rows={4}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
          placeholder="Share your experience..."
          disabled={submitting}
        />
        <div className="mt-1 flex justify-end">
          <span
            className={`text-xs ${charCount > 2000 ? 'text-red-500 font-medium' : 'text-gray-400'}`}
          >
            {charCount}/2000
          </span>
        </div>
      </div>

      {error && (
        <div className="mb-3 rounded-md bg-red-50 p-2 text-xs text-red-700">{error}</div>
      )}

      <input type="hidden" name="locale" value={locale} />

      <div className="flex items-center gap-2">
        <button
          type="submit"
          disabled={submitting || rating === 0}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {submitting ? 'Submitting...' : isEdit ? 'Update Review' : 'Submit Review'}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}
