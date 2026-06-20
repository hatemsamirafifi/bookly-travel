export type BookingStatus = 'confirmed' | 'completed' | 'cancelled' | 'pending_payment' | 'expired' | 'no_show';

export interface MoneyValue {
  amount: number;
  currency?: string;
  formatted?: string;
}

export interface TravelerTourSummary {
  id: string | number;
  name?: string;
  title?: string;
  cover_image?: string;
  cover_image_url?: string;
  slug: string;
  location: string;
  duration?: string;
  duration_label?: string;
  meeting_point?: string;
  inclusions?: string[];
}

export interface TravelerBooking {
  id: string | number;
  reference: string;
  status: BookingStatus;
  tour: TravelerTourSummary;
  booking_date?: string;
  tour_date: string;
  tour_time?: string;
  participants?: number;
  participant_count?: number;
  price_per_person?: number | MoneyValue;
  total_amount?: number | MoneyValue;
  total_price?: MoneyValue;
  special_requests?: string | null;
  cancellation_policy?: string;
  cancellation_date?: string | null;
  created_at?: string;
  can_cancel?: boolean;
  payment?: {
    status: string;
    amount: number | MoneyValue;
    transaction_date?: string;
    method?: {
      type: string;
      brand?: string;
      last4?: string;
    };
  } | null;
}

export interface PaginatedTravelerResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface TravelerProfile {
  id: string | number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  preferred_language: 'en' | 'es' | 'it';
  preferred_currency: string;
  marketing_emails: boolean;
  avatar_url?: string | null;
}

export interface WishlistItem {
  id: string | number;
  tour: {
    id: string | number;
    name: string;
    cover_image?: string;
    slug: string;
    price: number | MoneyValue;
    rating: number;
    review_count: number;
    location: string;
    duration: string;
    is_available: boolean;
  };
  added_at: string;
}

export interface TravelerReview {
  id: string | number;
  tour: {
    id: string | number;
    name: string;
    slug: string;
  };
  rating: number;
  text: string;
  submitted_at: string;
  can_edit?: boolean;
}
