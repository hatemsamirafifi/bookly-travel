interface PriceBreakdownProps {
  pricePerPerson: string;
  participantCount: number;
  total: string;
}

export default function PriceBreakdown({ pricePerPerson, participantCount, total }: PriceBreakdownProps) {
  return (
    <div className="rounded-lg border border-gray-200 p-4">
      <h3 className="text-sm font-medium text-gray-700 mb-3">Price Breakdown</h3>
      <div className="space-y-2">
        <div className="flex justify-between text-sm text-gray-600">
          <span>
            {pricePerPerson} × {participantCount} {participantCount === 1 ? 'participant' : 'participants'}
          </span>
          <span>{total}</span>
        </div>
        <div className="border-t border-gray-200 pt-2 flex justify-between text-base font-semibold text-gray-900">
          <span>Total</span>
          <span>{total}</span>
        </div>
      </div>
    </div>
  );
}
