'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  getTravelerWishlist,
  addTravelerWishlistItem,
  removeTravelerWishlistItem,
} from '@/lib/api/traveler';

export function useWishlist() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['wishlist'],
    queryFn: async () => {
      const res = await getTravelerWishlist();
      return res.data;
    },
    staleTime: 30_000,
    retry: 1,
  });

  const addMutation = useMutation({
    mutationFn: async (tourId: string | number) => {
      await addTravelerWishlistItem(tourId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] });
    },
  });

  const removeMutation = useMutation({
    mutationFn: async (tourId: string | number) => {
      await removeTravelerWishlistItem(tourId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] });
    },
  });

  return {
    ...query,
    addItem: addMutation.mutateAsync,
    removeItem: removeMutation.mutateAsync,
    isAdding: addMutation.isPending,
    isRemoving: removeMutation.isPending,
  };
}
