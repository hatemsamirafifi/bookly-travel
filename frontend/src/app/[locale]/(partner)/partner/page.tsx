import { AnalyticsSummary } from '@/components/partner/analytics/AnalyticsSummary';
import { BookingsChart } from '@/components/partner/analytics/BookingsChart';

export default function PartnerDashboardPage() {
  // Placeholder data until analytics API is wired
  const chartData = Array.from({ length: 30 }, (_, i) => {
    const date = new Date();
    date.setDate(date.getDate() - (29 - i));
    return {
      date: date.toISOString().split('T')[0],
      bookings: Math.floor(Math.random() * 5),
      revenue: Math.floor(Math.random() * 500),
    };
  });

  return (
    <div className="space-y-6">
      <AnalyticsSummary
        total_bookings={124}
        total_revenue={8650}
        average_rating={4.7}
        conversion_rate={0.034}
      />
      <BookingsChart data={chartData} />
    </div>
  );
}