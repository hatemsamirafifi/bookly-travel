'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getTravelerProfile, updateTravelerProfile } from '@/lib/api/traveler';
import type { TravelerProfile } from '@/types/traveler';

export function useProfile() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['profile'],
    queryFn: async () => {
      const res = await getTravelerProfile();
      return res.data;
    },
    staleTime: 60_000,
    retry: 1,
  });

  const mutation = useMutation({
    mutationFn: async (data: Partial<Omit<TravelerProfile, 'id' | 'email' | 'avatar_url'>>) => {
      const res = await updateTravelerProfile(data as Omit<TravelerProfile, 'id' | 'email' | 'avatar_url'>);
      return res.data;
    },
    onSuccess: (updated) => {
      queryClient.setQueryData(['profile'], updated);
    },
  });

  return { ...query, updateProfile: mutation.mutateAsync, isUpdating: mutation.isPending };
}
