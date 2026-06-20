// Set required environment variables before any imports
process.env.NEXT_PUBLIC_API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

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
  leave_review: 'Write a Review',
  edit_review: 'Edit Your Review',
  your_rating: 'Your Rating',
  your_review: 'Your Review',
  share_experience: 'Share your experience...',
  submit_review: 'Submit Review',
  update_review: 'Update Review',
  submitting: 'Submitting...',
  cancel: 'Cancel',
  thank_you: 'Thank you for your review!',
  updated: 'Your review has been updated!',
  edited: 'Edited',
  'errors.ratingRequired': 'Please select a rating.',
  'errors.commentTooLong': 'Comment must be 2000 characters or fewer.',
  'reviews.title': 'Reviews',
  'reviews.failed_to_load': 'Failed to load reviews. Please try again later.',
  // Partner (bookly) — only the keys unit tests assert on.
  'partner.tours.noCoverImage': 'No cover image',
  'partner.tours.untitled': 'Untitled tour',
  'partner.tours.ariaEdit': 'Edit tour',
  'partner.tours.ariaArchive': 'Archive tour',
  'partner.tours.archiveConfirm': 'Archive this tour?',
  'partner.tours.archiveFailed': 'Failed to archive the tour.',
  'partner.tours.status.draft': 'Draft',
  'partner.tours.status.pending_review': 'Pending Review',
  'partner.tours.status.published': 'Published',
  'partner.tours.status.rejected': 'Rejected',
  'partner.tours.status.archived': 'Archived',
  'partner.tours.statusHint.pending_review': 'Awaiting admin review.',
  'partner.tours.statusHint.rejected': 'Needs revision before resubmitting.',
  'partner.tours.errors.unsupportedType': 'Unsupported file type.',
  'partner.tours.errors.fileTooLarge': 'File is too large.',
  'partner.tours.errors.maxImagesReached': 'Maximum number of images reached.',
  'partner.tours.form.dismissErrors': 'Dismiss',
};

jest.mock('next-intl', () => ({
  useLocale: () => 'en',
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, unknown>) => {
    if (key === 'review_count') {
      const count = values?.count ?? 0;
      return `${count} ${count === 1 ? 'review' : 'reviews'}`;
    }
    if (key === 'char_count') {
      const count = values?.count ?? 0;
      return `${count}/2000`;
    }
    const lookupKey = namespace ? `${namespace}.${key}` : key;
    return messages[lookupKey] || messages[key] || key;
  },
}));
