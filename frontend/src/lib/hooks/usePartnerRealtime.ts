'use client';

import { useEffect, useRef, useState } from 'react';
import { getNotifications } from '@/lib/api/partner';
import type { Notification } from '@/types/partner';

export function usePartnerRealtime() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchNotifications = async () => {
    try {
      const res = await getNotifications({ unread_only: true, page: 1 });
      setNotifications(res.data);
      setUnreadCount(res.meta.unread_count);
    } catch {
      // Silently fail on polling fallback
    }
  };

  useEffect(() => {
    // Initial fetch
    fetchNotifications();

    // Fallback polling every 60 seconds
    intervalRef.current = setInterval(fetchNotifications, 60000);

    // TODO: Wire Laravel Echo/Reverb WebSocket for real-time events
    // when infrastructure is ready. For now, polling fallback provides
    // functional notification delivery.

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  return { notifications, unreadCount, refresh: fetchNotifications };
}