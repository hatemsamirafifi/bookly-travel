import { useTranslations } from 'next-intl';

interface PriceBreakdownProps {
  pricePerPerson: string;
  participantCount: number;
  total: string;
}

export default function PriceBreakdown({ pricePerPerson, participantCount, total }: PriceBreakdownProps) {
  const t = useTranslations('booking');
  return (
    <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
      <h3 className="text-sm font-semibold text-bookly-navy mb-3">{t('priceBreakdown')}</h3>
      <div className="space-y-2">
        <div className="flex justify-between text-sm text-[#5A6B7B]">
          <span>
            {pricePerPerson} × {t('participantCount', { count: participantCount })}
          </span>
          <span>{total}</span>
        </div>
        <div className="border-t border-gray-200 pt-2 flex justify-between text-base font-semibold text-[#0A2540]">
          <span>{t('total')}</span>
          <span>{total}</span>
        </div>
      </div>
    </div>
  );
}
