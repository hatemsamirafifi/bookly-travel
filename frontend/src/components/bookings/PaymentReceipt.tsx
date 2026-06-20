import { useTranslations } from 'next-intl';

interface PaymentReceiptProps {
  payment?: {
    status: string;
    amount?: number | { amount?: number; formatted?: string };
    method?: {
      brand?: string;
      type?: string;
      last4?: string;
    };
    transaction_date?: string;
  } | null;
}

function formatMoneyValue(value?: number | { amount?: number; formatted?: string }) {
  if (typeof value === 'object' && value?.formatted) return value.formatted;
  const amount = typeof value === 'object' ? value.amount : value;
  if (typeof amount !== 'number') return '';
  return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(amount / 100);
}

export default function PaymentReceipt({ payment }: PaymentReceiptProps) {
  const detailT = useTranslations('traveler.bookingDetail');

  if (!payment) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-5">
        <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('paymentReceipt')}</h2>
        <p className="text-sm text-gray-600">{detailT('receiptUnavailable')}</p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('paymentReceipt')}</h2>
      <div className="space-y-3 text-sm">
        <div>
          <p className="text-sm text-gray-500">{detailT('status')}</p>
          <p>{payment.status}</p>
        </div>
        <div>
          <p className="text-sm text-gray-500">{detailT('amount')}</p>
          <p>{formatMoneyValue(payment.amount)}</p>
        </div>
        <div>
          <p className="text-sm text-gray-500">{detailT('method')}</p>
          <p>
            {payment.method?.brand || payment.method?.type || detailT('card')}
            {payment.method?.last4 ? ` ${detailT('ending')} ${payment.method.last4}` : ''}
          </p>
        </div>
        {payment.transaction_date && (
          <div>
            <p className="text-sm text-gray-500">{detailT('transactionDate')}</p>
            <p>{new Date(payment.transaction_date).toLocaleString()}</p>
          </div>
        )}
      </div>
    </div>
  );
}
