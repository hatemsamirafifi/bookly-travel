'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '@/lib/hooks/useAuth';
import {
  isGuestWishlisted,
  toggleGuestWishlist,
  GUEST_WISHLIST_EVENT,
} from './guestWishlist';
import {
  getTravelerWishlistStatus,
  addTravelerWishlistItem,
  removeTravelerWishlistItem,
} from '@/lib/api/traveler';

export function useWishlist(tourId: number | string) {
  const numericId = typeof tourId === 'number' ? tourId : parseInt(tourId, 10);
  const { user } = useAuth();
  const isAuthenticated = Boolean(user);

  const [isSaved, setIsSaved] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    if (!isAuthenticated) {
      return isGuestWishlisted(numericId);
    }
    return false;
  });

  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const userToggledRef = useRef<boolean>(false);

  useEffect(() => {
    userToggledRef.current = false;

    if (!isAuthenticated) {
      setIsSaved(isGuestWishlisted(numericId));

      const handleStorageUpdate = (e: Event) => {
        const customEvent = e as CustomEvent<{ tourIds: number[] }>;
        if (customEvent.detail && Array.isArray(customEvent.detail.tourIds)) {
          setIsSaved(customEvent.detail.tourIds.includes(numericId));
        } else {
          setIsSaved(isGuestWishlisted(numericId));
        }
      };

      window.addEventListener(GUEST_WISHLIST_EVENT, handleStorageUpdate);
      return () => window.removeEventListener(GUEST_WISHLIST_EVENT, handleStorageUpdate);
    } else {
      let isMounted = true;
      setIsLoading(true);
      getTravelerWishlistStatus(numericId)
        .then((res) => {
          if (isMounted && res && res.data && !userToggledRef.current) {
            setIsSaved(Boolean(res.data[String(numericId)]));
          }
        })
        .catch(() => {
          // ignore status fetch errors
        })
        .finally(() => {
          if (isMounted) setIsLoading(false);
        });

      return () => {
        isMounted = false;
      };
    }
  }, [numericId, isAuthenticated]);

  const toggle = useCallback(async () => {
    setError(null);
    userToggledRef.current = true;

    if (!isAuthenticated) {
      const res = toggleGuestWishlist(numericId);
      setIsSaved(res.isSaved);
      return;
    }

    const previousState = isSaved;
    const nextState = !previousState;
    // Optimistic update
    setIsSaved(nextState);
    setIsLoading(true);

    try {
      if (nextState) {
        await addTravelerWishlistItem(numericId);
      } else {
        await removeTravelerWishlistItem(numericId);
      }
    } catch {
      // Rollback on failure
      setIsSaved(previousState);
      setError('Failed to update wishlist');
    } finally {
      setIsLoading(false);
    }
  }, [numericId, isAuthenticated, isSaved]);

  return { isSaved, toggle, isLoading, error };
}