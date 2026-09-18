'use client';

import { useState } from 'react';
import { confirmDeterministicPayment } from '@/lib/api/bookings';

interface DeterministicPaymentFormProps {
  bookingReference: string;
  clientSecret: string;
  onSuccess: () => void;
  onError: (message: string) => void;
}

export default function DeterministicPaymentForm({
  bookingReference,
  clientSecret,
  onSuccess,
  onError,
}: DeterministicPaymentFormProps) {
  const [processing, setProcessing] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setProcessing(true);

    try {
      await confirmDeterministicPayment(bookingReference, clientSecret);
      onSuccess();
    } catch {
      onError('The local test payment could not be completed.');
    } finally {
      setProcessing(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        Local test gateway. No card or external charge is used.
      </div>
      <button
        type="submit"
        disabled={processing}
        className="w-full rounded-xl bg-[#FFB800] py-3 text-base font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
      >
        {processing ? 'Completing test payment…' : 'Complete test payment'}
      </button>
    </form>
  );
}
