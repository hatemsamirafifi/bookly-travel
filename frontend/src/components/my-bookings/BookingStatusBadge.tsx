interface BookingStatusBadgeProps {
  status: string;
}

const statusStyles: Record<string, string> = {
  confirmed: 'bg-green-100 text-green-800',
  completed: 'bg-blue-100 text-blue-800',
  cancelled: 'bg-red-100 text-red-800',
  no_show: 'bg-yellow-100 text-yellow-800',
  pending_payment: 'bg-amber-100 text-amber-800',
  expired: 'bg-gray-100 text-gray-800',
};

export default function BookingStatusBadge({ status }: BookingStatusBadgeProps) {
  return (
    <span
      className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ${statusStyles[status] || 'bg-gray-100 text-gray-800'}`}
    >
      {status}
    </span>
  );
}
