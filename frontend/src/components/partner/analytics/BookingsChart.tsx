'use client';

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

interface ChartData {
  date: string;
  bookings: number;
  revenue: number;
}

interface BookingsChartProps {
  data: ChartData[];
}

export function BookingsChart({ data }: BookingsChartProps) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-[#0A2540] mb-4">Bookings Over Time</h3>
      <div className="h-72">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" />
            <XAxis
              dataKey="date"
              tickFormatter={(date: string) => new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
              stroke="#9ca3af"
              fontSize={12}
            />
            <YAxis stroke="#9ca3af" fontSize={12} />
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
              stroke="#0A2540"
              strokeWidth={2}
              dot={{ r: 3, fill: '#0A2540' }}
              activeDot={{ r: 5, fill: '#FFB800' }}
            />
            <Line
              type="monotone"
              dataKey="revenue"
              stroke="#FFB800"
              strokeWidth={2}
              dot={{ r: 3, fill: '#FFB800' }}
              yAxisId={1}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}