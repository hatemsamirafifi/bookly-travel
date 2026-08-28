'use client';

import { useTranslations } from 'next-intl';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import type { AnalyticsBookingsPoint } from '@/types/partner';

interface BookingsChartProps {
  data: AnalyticsBookingsPoint[];
}

export function BookingsChart({ data }: BookingsChartProps) {
  const t = useTranslations('partner.analytics');
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h2 className="text-sm font-semibold text-[#0A2540] mb-4">{t('bookingsOverTime')}</h2>
      <div className="h-72">
        {data.length === 0 ? (
          <div className="flex h-full items-center justify-center text-sm text-gray-400">
            {t('noData')}
          </div>
        ) : (
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" />
              <XAxis
                dataKey="date"
                tickFormatter={(date: string) =>
                  new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                }
                stroke="#9ca3af"
                fontSize={12}
              />
              <YAxis yAxisId={0} stroke="#9ca3af" fontSize={12} />
              <YAxis yAxisId={1} orientation="right" stroke="#9ca3af" fontSize={12} />
              <Tooltip
                contentStyle={{
                  borderRadius: '0.75rem',
                  border: '1px solid #e5e7eb',
                  fontSize: '0.875rem',
                }}
              />
              <Line
                type="monotone"
                dataKey="bookings"
                yAxisId={0}
                stroke="#0A2540"
                strokeWidth={2}
                dot={{ r: 3, fill: '#0A2540' }}
                activeDot={{ r: 5, fill: '#FFB800' }}
              />
              <Line
                type="monotone"
                dataKey="revenue"
                yAxisId={1}
                stroke="#FFB800"
                strokeWidth={2}
                dot={{ r: 3, fill: '#FFB800' }}
              />
            </LineChart>
          </ResponsiveContainer>
        )}
      </div>
    </div>
  );
}