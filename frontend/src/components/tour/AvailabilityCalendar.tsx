'use client';

import { useState, useMemo } from 'react';

interface AvailabilityCalendarProps {
  availableDates: string[];
  nextAvailableDate: string | null;
}

export default function AvailabilityCalendar({ availableDates, nextAvailableDate }: AvailabilityCalendarProps) {
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const today = useMemo(() => new Date().toISOString().split('T')[0], []);

  const availableSet = useMemo(() => new Set(availableDates), [availableDates]);

  const formatDisplay = (dateStr: string) => {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
  };

  if (availableDates.length === 0) {
    return (
      <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
        <p className="text-sm font-medium text-yellow-800">Currently Unavailable</p>
        <p className="mt-1 text-sm text-yellow-700">No upcoming dates are available for this tour. Check back soon.</p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">Select a Date</h3>

      <div className="mb-3">
        <p className="text-xs text-gray-500">Next available</p>
        {nextAvailableDate && (
          <p className="text-sm font-medium text-green-700">{formatDisplay(nextAvailableDate)}</p>
        )}
      </div>

      <div className="grid grid-cols-4 gap-2 max-h-48 overflow-y-auto">
        {availableDates.slice(0, 30).map((date) => {
          const isPast = date < today;
          const isSelected = date === selectedDate;

          return (
            <button
              key={date}
              disabled={isPast}
              onClick={() => setSelectedDate(date)}
              className={`rounded-md px-2 py-1.5 text-xs font-medium transition-colors ${
                isSelected
                  ? 'bg-blue-600 text-white'
                  : isPast
                    ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                    : 'bg-gray-50 text-gray-700 hover:bg-blue-50 hover:text-blue-700'
              }`}
              aria-label={formatDisplay(date)}
              aria-pressed={isSelected}
            >
              {new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
            </button>
          );
        })}
      </div>

      {selectedDate && (
        <p className="mt-3 text-sm text-blue-700">
          Selected: <span className="font-medium">{formatDisplay(selectedDate)}</span>
        </p>
      )}
    </div>
  );
}
