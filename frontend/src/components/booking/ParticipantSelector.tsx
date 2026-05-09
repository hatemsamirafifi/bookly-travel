'use client';

interface ParticipantSelectorProps {
  value: number;
  onChange: (value: number) => void;
  min: number;
  max: number;
  pricePerPerson?: string;
}

export default function ParticipantSelector({
  value,
  onChange,
  min,
  max,
  pricePerPerson,
}: ParticipantSelectorProps) {
  return (
    <div>
      <label id="participants-label" className="block text-sm font-medium text-gray-700 mb-2">
        Participants
      </label>
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => onChange(Math.max(min, value - 1))}
          disabled={value <= min}
          className="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-500"
          aria-label="Decrease participants"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
          </svg>
        </button>
        <span
          className="min-w-[3rem] text-center text-lg font-semibold text-gray-900"
          aria-live="polite"
          aria-labelledby="participants-label"
        >
          {value}
        </span>
        <button
          type="button"
          onClick={() => onChange(Math.min(max, value + 1))}
          disabled={value >= max}
          className="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-500"
          aria-label="Increase participants"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
        </button>
        <span className="text-sm text-gray-400">
          {min}–{max} allowed
        </span>
      </div>
      {pricePerPerson && (
        <p className="mt-1 text-sm text-gray-500">
          {pricePerPerson} per person
        </p>
      )}
    </div>
  );
}
