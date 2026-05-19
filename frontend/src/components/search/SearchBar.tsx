'use client';

import { useState, useCallback } from 'react';
import { useRouter, useParams } from 'next/navigation';

interface SearchBarProps {
  initialQuery?: string;
  compact?: boolean;
}

export default function SearchBar({ initialQuery = '', compact = false }: SearchBarProps) {
  const [query, setQuery] = useState(initialQuery);
  const router = useRouter();
  const params = useParams();
  const locale = (params?.locale as string) || 'en';

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const trimmed = query.trim();
      if (trimmed) {
        router.push(`/${locale}/search?q=${encodeURIComponent(trimmed)}`);
      } else {
        router.push(`/${locale}/search`);
      }
    },
    [query, locale, router]
  );

  return (
    <form onSubmit={handleSubmit} role="search" className={compact ? 'w-full max-w-sm' : 'w-full max-w-2xl'}>
      <label htmlFor="search-input" className="sr-only">
        Search tours
      </label>
      <div className="relative">
        <input
          id="search-input"
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search tours, destinations, categories..."
          className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pl-11 text-[#0A2540] shadow-sm focus:border-[#0A2540] focus:outline-none focus:ring-2 focus:ring-[#0A2540]/20"
          autoComplete="off"
        />
        <svg
          className="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
        <button
          type="submit"
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-[#FFB800] px-4 py-1.5 text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2"
        >
          Search
        </button>
      </div>
    </form>
  );
}
