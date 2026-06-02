'use client';

import { TrendingUp, Users, Star, DollarSign } from 'lucide-react';

interface SummaryProps {
  total_bookings: number;
  total_revenue: number;
  average_rating: number;
  conversion_rate: number;
}

export function AnalyticsSummary({ total_bookings, total_revenue, average_rating, conversion_rate }: SummaryProps) {
  const cards = [
    { label: 'Total Bookings', value: total_bookings, icon: Users, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: 'Total Revenue', value: `€${total_revenue.toFixed(2)}`, icon: DollarSign, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { label: 'Avg. Rating', value: average_rating.toFixed(1), icon: Star, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: 'Conversion', value: `${(conversion_rate * 100).toFixed(1)}%`, icon: TrendingUp, color: 'text-violet-600', bg: 'bg-violet-50' },
  ];

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {cards.map((card) => (
        <div key={card.label} className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-gray-500">{card.label}</span>
            <div className={`p-2 rounded-lg ${card.bg}`}>
              <card.icon className={`w-4 h-4 ${card.color}`} />
            </div>
          </div>
          <span className="text-2xl font-bold text-[#0A2540]">{card.value}</span>
        </div>
      ))}
    </div>
  );
}