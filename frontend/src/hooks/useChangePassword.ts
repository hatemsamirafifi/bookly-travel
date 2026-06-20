'use client';

import { useMutation } from '@tanstack/react-query';
import { changeTravelerPassword } from '@/lib/api/traveler';

interface PasswordPayload {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export function useChangePassword() {
  return useMutation({
    mutationFn: async (data: PasswordPayload) => {
      const res = await changeTravelerPassword(data);
      return res;
    },
  });
}
