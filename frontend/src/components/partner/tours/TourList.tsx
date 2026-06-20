'use client';

import { useEffect, useState } from 'react';
import { getTours } from '@/lib/api/partner';
import type { Tour } from '@/types/partner';
import { TourCard } from './TourCard';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import Link from 'next/link';

export function TourList() {
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getTours()
      .then((res) => setTours(res.data))
      .catch((err) => setError(err.message ?? 'Failed to load tours'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="text-sm text-gray-500">Loading tours...</div>;
  }

  if (error) {
    return <div className="text-sm text-red-600">{error}</div>;
  }

  if (tours.length === 0) {
    return (
      <div className="text-center py-16 bg-white rounded-xl border border-gray-200">
        <h3 className="text-lg font-semibold text-[#0A2540] mb-2">No tours yet</h3>
        <p className="text-sm text-gray-500 mb-6">Create your first tour to start earning.</p>
        <Link href="/partner/tours/create">
          <Button className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">
            <Plus className="w-4 h-4 mr-2" />
            Create Tour
          </Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-[#0A2540]">My Tours</h2>
        <Link href="/partner/tours/create">
          <Button size="sm" className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">
            <Plus className="w-4 h-4 mr-1" />
            Create Tour
          </Button>
        </Link>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {tours.map((tour) => (
          <TourCard key={tour.id} tour={tour} />
        ))}
      </div>
    </div>
  );
}