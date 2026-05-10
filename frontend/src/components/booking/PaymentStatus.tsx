'use client';

interface PaymentStatusProps {
  status: string;
  amount: { amount: number; currency: string; formatted: string };
  cardLastFour?: string;
  cardBrand?: string;
  paidAt?: string;
}

export default function PaymentStatus({ status, amount, cardLastFour, cardBrand, paidAt }: PaymentStatusProps) {
  const statusLabels: Record<string, string> = {
    pending: 'Payment Pending',
    succeeded: 'Payment Successful',
    failed: 'Payment Failed',
    refunded: 'Refunded',
    disputed: 'Disputed',
  };

  return (
    <div className="rounded-lg border border-gray-200 p-4 space-y-3">
      <h3 className="text-sm font-semibold text-gray-700">Payment Details</h3>

      <div className="flex justify-between items-center">
        <span className="text-sm text-gray-500">Status</span>
        <span className={`text-sm font-medium ${
          status === 'succeeded' ? 'text-green-600' :
          status === 'failed' || status === 'disputed' ? 'text-red-600' :
          'text-gray-700'
        }`}>
          {statusLabels[status] || status}
        </span>
      </div>

      <div className="flex justify-between items-center">
        <span className="text-sm text-gray-500">Amount</span>
        <span className="text-sm font-medium text-gray-700">{amount.formatted}</span>
      </div>

      {cardLastFour && (
        <div className="flex justify-between items-center">
          <span className="text-sm text-gray-500">Card</span>
          <span className="text-sm font-medium text-gray-700">
            {cardBrand ? `${cardBrand.toUpperCase()} ` : ''}•••• {cardLastFour}
          </span>
        </div>
      )}

      {paidAt && (
        <div className="flex justify-between items-center">
          <span className="text-sm text-gray-500">Paid at</span>
          <span className="text-sm text-gray-700">
            {new Date(paidAt).toLocaleString()}
          </span>
        </div>
      )}
    </div>
  );
}
