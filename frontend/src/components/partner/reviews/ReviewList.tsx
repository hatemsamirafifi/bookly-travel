'use client';

import { useState } from 'react';
import { Star } from 'lucide-react';
import type { Review } from '@/types/partner';

const mockReviews: Review[] = [
  {
    id: 1,
    booking_id: 101,
    tour_id: 1,
    traveler_id: 1,
    traveler_name: 'Alice M.',
    rating: 5,
    comment: 'Absolutely fantastic experience! Our guide was knowledgeable and friendly.',
    status: 'visible',
    created_at: '2026-05-20T10:00:00Z',
  },
  {
    id: 2,
    booking_id: 102,
    tour_id: 1,
    traveler_id: 2,
    traveler_name: 'Bob D.',
    rating: 4,
    comment: 'Great tour, but a bit rushed at the end. Would recommend anyway!',
    status: 'visible',
    created_at: '2026-05-18T14:30:00Z',
  },
];

export function ReviewList() {
  const [reviews] = useState<Review[]>(mockReviews);
  const [respondingId, setRespondingId] = useState<number | null>(null);
  const [responseText, setResponseText] = useState('');

  const handleSubmitResponse = (reviewId: number) => {
    // TODO: Wire API call
    console.log('Submit response', reviewId, responseText);
    setRespondingId(null);
    setResponseText('');
  };

  return (
    <div className="space-y-4">
      {reviews.map((review) => (
        <div key={review.id} className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-start justify-between mb-3">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <span className="font-semibold text-[#0A2540]">{review.traveler_name}</span>
                <span className="text-xs text-gray-400">• Verified Traveler</span>
              </div>
              <div className="flex items-center gap-0.5">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${
                      i < review.rating
                        ? 'text-[#FFB800] fill-[#FFB800]'
                        : 'text-gray-200'
                    }`}
                  />
                ))}
              </div>
            </div>
            <span className="text-xs text-gray-400">
              {new Date(review.created_at).toLocaleDateString()}
            </span>
          </div>

          <p className="text-sm text-gray-700 mb-4">{review.comment}</p>

          {review.response ? (
            <div className="bg-gray-50 rounded-lg p-3 border border-gray-100">
              <p className="text-xs font-semibold text-gray-500 mb-1">Your Response</p>
              <p className="text-sm text-gray-700">{review.response.response_text}</p>
            </div>
          ) : (
            <>
              {respondingId === review.id ? (
                <div className="space-y-2">
                  <textarea
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
                    rows={3}
                    placeholder="Write your public response..."
                    value={responseText}
                    onChange={(e) => setResponseText(e.target.value)}
                    maxLength={1000}
                  />
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-gray-400">{responseText.length}/1000</span>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        className="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        onClick={() => setRespondingId(null)}
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        className="px-3 py-1.5 text-xs font-medium bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] rounded-lg"
                        onClick={() => handleSubmitResponse(review.id)}
                        disabled={!responseText.trim()}
                      >
                        Submit Response
                      </button>
                    </div>
                  </div>
                </div>
              ) : (
                <button
                  type="button"
                  className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                  onClick={() => setRespondingId(review.id)}
                >
                  Respond
                </button>
              )}
            </>
          )}
        </div>
      ))}
    </div>
  );
}