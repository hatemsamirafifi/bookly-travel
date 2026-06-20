'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { respondToReview, updateReviewResponse } from '@/lib/api/partner';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { PartnerReview } from '@/types/partner';

const MAX_RESPONSE_LENGTH = 1000;

interface ReviewResponseFormProps {
  review: PartnerReview;
  /** Called after successful submit or update */
  onSuccess?: () => void;
}

export function ReviewResponseForm({ review, onSuccess }: ReviewResponseFormProps) {
  const queryClient = useQueryClient();
  const hasExistingResponse = !!review.response;

  const [isEditing, setIsEditing] = useState(false);
  const [responseText, setResponseText] = useState(review.response?.text ?? '');
  const [error, setError] = useState<string | null>(null);

  const respondMutation = useMutation({
    mutationFn: (text: string) => respondToReview(review.id, text),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-reviews'] });
      setIsEditing(false);
      setError(null);
      onSuccess?.();
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Failed to submit response');
    },
  });

  const updateMutation = useMutation({
    mutationFn: (text: string) => updateReviewResponse(review.id, text),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-reviews'] });
      setIsEditing(false);
      setError(null);
      onSuccess?.();
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Failed to update response');
    },
  });

  const isPending = respondMutation.isPending || updateMutation.isPending;

  const handleSubmit = () => {
    const trimmed = responseText.trim();
    if (!trimmed) return;

    if (hasExistingResponse) {
      updateMutation.mutate(trimmed);
    } else {
      respondMutation.mutate(trimmed);
    }
  };

  const handleCancel = () => {
    setResponseText(review.response?.text ?? '');
    setIsEditing(false);
    setError(null);
  };

  const charCount = responseText.length;
  const isOverLimit = charCount > MAX_RESPONSE_LENGTH;
  const isInvalid = !responseText.trim() || isOverLimit;

  // Show existing response (not editing)
  if (hasExistingResponse && !isEditing) {
    return (
      <div className="bg-gray-50 rounded-lg p-4 border border-gray-100">
        <div className="flex items-center justify-between mb-2">
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Your Response</p>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setIsEditing(true)}
            className="text-xs h-auto py-1 px-2 text-[#0A2540] hover:bg-gray-100"
          >
            Edit
          </Button>
        </div>
        <p className="text-sm text-gray-700">{review.response!.text}</p>
        <p className="text-xs text-gray-400 mt-2">
          Responded on {new Date(review.response!.created_at).toLocaleDateString()}
          {review.response!.updated_at && review.response!.updated_at !== review.response!.created_at && (
            <> &middot; Edited on {new Date(review.response!.updated_at).toLocaleDateString()}</>
          )}
        </p>
      </div>
    );
  }

  // Show form (new response or editing existing)
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
          {hasExistingResponse ? 'Edit Response' : 'Write a Response'}
        </p>
        {hasExistingResponse && (
          <button
            type="button"
            onClick={handleCancel}
            className="text-xs text-gray-500 hover:text-[#0A2540] transition-colors"
          >
            Cancel
          </button>
        )}
      </div>

      <Textarea
        value={responseText}
        onChange={(e) => setResponseText(e.target.value)}
        placeholder="Write your public response to this review..."
        rows={3}
        maxLength={MAX_RESPONSE_LENGTH}
        disabled={isPending}
      />

      {/* Character counter */}
      <div className="flex items-center justify-between">
        <span className={`text-xs ${
          isOverLimit
            ? 'text-red-500 font-medium'
            : charCount > MAX_RESPONSE_LENGTH * 0.9
            ? 'text-amber-600'
            : 'text-gray-400'
        }`}>
          {charCount}/{MAX_RESPONSE_LENGTH}
        </span>
        <div className="flex items-center gap-2">
          {hasExistingResponse && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleCancel}
              disabled={isPending}
              className="text-xs"
            >
              Cancel
            </Button>
          )}
          <Button
            size="sm"
            onClick={handleSubmit}
            disabled={isInvalid || isPending}
            className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold"
          >
            {isPending
              ? 'Submitting...'
              : hasExistingResponse
              ? 'Update Response'
              : 'Submit Response'}
          </Button>
        </div>
      </div>

      {/* Error message */}
      {error && (
        <p className="text-sm text-red-600">{error}</p>
      )}
    </div>
  );
}