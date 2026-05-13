'use client';

import { useTranslations } from 'next-intl';

interface PaymentStatusProps {
  status: string;
  amount: { amount: number; currency: string; formatted: string };
  cardLastFour?: string;
  cardBrand?: string;
  paidAt?: string;
}

export default function PaymentStatus({ status, amount, cardLastFour, cardBrand, paidAt }: PaymentStatusProps) {
  const t = useTranslations('payment');

  const statusLabels: Record<string, string> = {
    pending: t('statusPending'),
    succeeded: t('statusSucceeded'),
    failed: t('statusFailed'),
    refunded: t('statusRefunded'),
    disputed: t('statusDisputed'),
  };

  return (
    <div className="rounded-lg border border-gray-200 p-4 space-y-3">
      <h3 className="text-sm font-semibold text-gray-700">{t('title')}</h3>

      <div className="flex justify-between items-center">
        <span className="text-sm text-gray-500">{t('status')}</span>
        <span className={`text-sm font-medium ${
          status === 'succeeded' ? 'text-green-600' :
          status === 'failed' || status === 'disputed' ? 'text-red-600' :
          'text-gray-700'
        }`}>
          {statusLabels[status] || status}
        </span>
      </div>

      <div className="flex justify-between items-center">
        <span className="text-sm text-gray-500">{t('amount')}</span>
        <span className="text-sm font-medium text-gray-700">{amount.formatted}</span>
      </div>

      {cardLastFour && (
        <div className="flex justify-between items-center">
          <span className="text-sm text-gray-500">{t('card')}</span>
          <span className="text-sm font-medium text-gray-700">
            {cardBrand ? `${cardBrand.toUpperCase()} ` : ''}•••• {cardLastFour}
          </span>
        </div>
      )}

      {paidAt && (
        <div className="flex justify-between items-center">
          <span className="text-sm text-gray-500">{t('paidAt')}</span>
          <span className="text-sm text-gray-700">
            {new Date(paidAt).toLocaleString()}
          </span>
        </div>
      )}
    </div>
  );
}
