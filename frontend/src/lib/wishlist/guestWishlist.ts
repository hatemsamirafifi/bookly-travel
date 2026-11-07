/**
 * Guest Wishlist Manager backed by browser localStorage.
 *
 * Implements the unauthenticated wishlist lifecycle specified in Spec 021 (FR-013, FR-024).
 * Allows guests to bookmark tours friction-free without sign-in barriers,
 * with versioned persistence, safe SSR guards, and tab-level synchronization.
 */

import { mergeTravelerWishlist } from '@/lib/api/traveler';

export const GUEST_WISHLIST_STORAGE_KEY = 'bookly_guest_wishlist';
export const GUEST_WISHLIST_EVENT = 'bookly:guest-wishlist-updated';

export interface GuestWishlistStorage {
  version: 1;
  tour_ids: number[];
  updated_at: string;
}

function isBrowser(): boolean {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
}

/**
 * Retrieves all tour IDs currently saved in the guest's local storage.
 * Returns an empty array in SSR environments or if corrupted.
 */
export function getGuestWishlist(): number[] {
  if (!isBrowser()) return [];

  try {
    const raw = window.localStorage.getItem(GUEST_WISHLIST_STORAGE_KEY);
    if (!raw) return [];

    const parsed = JSON.parse(raw) as GuestWishlistStorage;
    if (parsed && parsed.version === 1 && Array.isArray(parsed.tour_ids)) {
      return parsed.tour_ids.filter((id) => typeof id === 'number' && !Number.isNaN(id));
    }
    return [];
  } catch {
    return [];
  }
}

/**
 * Checks whether a specific tour ID is in the guest wishlist.
 */
export function isGuestWishlisted(tourId: number): boolean {
  return getGuestWishlist().includes(tourId);
}

/**
 * Adds or removes a tour from the guest wishlist.
 * Dispatches an event to notify active listeners across the page.
 */
export function toggleGuestWishlist(tourId: number): { isSaved: boolean; tourIds: number[] } {
  if (!isBrowser()) return { isSaved: false, tourIds: [] };

  const current = getGuestWishlist();
  const index = current.indexOf(tourId);
  let updated: number[];
  let isSaved = false;

  if (index >= 0) {
    updated = current.filter((id) => id !== tourId);
    isSaved = false;
  } else {
    updated = [...current, tourId];
    isSaved = true;
  }

  try {
    const payload: GuestWishlistStorage = {
      version: 1,
      tour_ids: updated,
      updated_at: new Date().toISOString(),
    };
    window.localStorage.setItem(GUEST_WISHLIST_STORAGE_KEY, JSON.stringify(payload));
    window.dispatchEvent(new CustomEvent(GUEST_WISHLIST_EVENT, { detail: { tourIds: updated } }));
  } catch {
    // If quota exceeded or storage disabled, return optimistic state
  }

  return { isSaved, tourIds: updated };
}

/**
 * Clears the guest wishlist from localStorage.
 * Used upon user logout to avoid exposing prior saved tours on shared devices.
 */
export function clearGuestWishlist(): void {
  if (!isBrowser()) return;

  try {
    window.localStorage.removeItem(GUEST_WISHLIST_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(GUEST_WISHLIST_EVENT, { detail: { tourIds: [] } }));
  } catch {
    // ignore
  }
}

/**
 * Merges local guest favorites into an authenticated account wishlist list,
 * deduplicating entries while preserving order.
 */
export function mergeGuestWishlistIds(existingAccountIds: number[]): number[] {
  const guestIds = getGuestWishlist();
  const merged = new Set<number>([...existingAccountIds, ...guestIds]);
  return Array.from(merged);
}

/**
 * Synchronizes local guest favorites to the authenticated account on the backend.
 * Clears localStorage upon success, or preserves them safely on error.
 */
export async function syncGuestWishlistToAccount(): Promise<boolean> {
  const ids = getGuestWishlist();
  if (ids.length === 0) return true;

  try {
    await mergeTravelerWishlist(ids);
    clearGuestWishlist();
    return true;
  } catch {
    // Preserve locally on sync failure
    return false;
  }
}
