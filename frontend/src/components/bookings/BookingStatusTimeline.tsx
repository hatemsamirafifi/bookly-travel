import { useTranslations } from 'next-intl';

interface TimelineItem {
  label: string;
  timestamp?: string;
}

interface BookingStatusTimelineProps {
  items: TimelineItem[];
}

export default function BookingStatusTimeline({ items }: BookingStatusTimelineProps) {
  const detailT = useTranslations('traveler.bookingDetail');

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('statusTimeline')}</h2>
      <ol className="space-y-3">
        {items.map((item) => (
          <li key={`${item.label}-${item.timestamp}`} className="flex gap-3">
            <span className="mt-1 h-2.5 w-2.5 rounded-full bg-[#FFB800]" />
            <div>
              <p className="text-sm font-medium text-gray-900">{item.label}</p>
              {item.timestamp && <p className="text-xs text-gray-500">{new Date(item.timestamp).toLocaleString()}</p>}
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}
