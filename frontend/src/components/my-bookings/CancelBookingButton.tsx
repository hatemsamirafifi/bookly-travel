'use client';

import { useState } from 'react';

interface CancelBookingButtonProps {
  canCancel: boolean;
  onCancel: () => Promise<void>;
}

export default function CancelBookingButton({ canCancel, onCancel }: CancelBookingButtonProps) {
  const [showConfirm, setShowConfirm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleCancel = async () => {
    setLoading(true);
    setError(null);
    try {
      await onCancel();
    } catch (err: any) {
      setError(err.message || 'Failed to cancel booking.');
    } finally {
      setLoading(false);
    }
  };

  if (showConfirm) {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50 p-4">
        <p className="text-sm text-red-700 mb-3">
          Are you sure? Refund will be processed within 5-10 business days.
        </p>
        {error && (
          <p className="text-sm text-red-600 mb-2" role="alert">{error}</p>
        )}
        <div className="flex gap-2">
          <button
            onClick={handleCancel}
            disabled={loading}
            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            {loading ? 'Cancelling...' : 'Yes, Cancel Booking'}
          </button>
          <button
            onClick={() => setShowConfirm(false)}
            disabled={loading}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Keep Booking
          </button>
        </div>
      </div>
    );
  }

  return (
    <button
      onClick={() => setShowConfirm(true)}
      disabled={!canCancel}
      title={!canCancel ? 'Cancellation window has passed or booking is not eligible for cancellation' : undefined}
      className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:border-gray-200 disabled:text-gray-400"
    >
      Cancel Booking
    </button>
  );
}
