import '@testing-library/jest-dom';

const messages: Record<string, string> = {
  no_reviews_yet: 'No reviews yet. Be the first!',
  title: 'Payment Details',
  status: 'Status',
  statusPending: 'Payment Pending',
  statusSucceeded: 'Payment Successful',
  statusFailed: 'Payment Failed',
  statusRefunded: 'Refunded',
  statusDisputed: 'Disputed',
  amount: 'Amount',
  card: 'Card',
  paidAt: 'Paid at',
  summaryTotal: 'Total bookings',
  summaryUpcoming: 'Upcoming',
  summaryCompleted: 'Completed',
  summaryCancelled: 'Cancelled',
  recentActivity: 'Recent activity',
  quickActions: 'Quick actions',
  browseTours: 'Browse Tours',
  viewWishlist: 'View Wishlist',
  editProfile: 'Edit Profile',
  empty: 'No bookings yet - find a tour to start your adventure',
};

jest.mock('next-intl', () => ({
  useLocale: () => 'en',
  useTranslations: () => (key: string, values?: Record<string, number>) => {
    if (key === 'review_count') {
      const count = values?.count ?? 0;
      return `${count} ${count === 1 ? 'review' : 'reviews'}`;
    }
    return messages[key] || key;
  },
}));
