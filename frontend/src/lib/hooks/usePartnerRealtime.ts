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
      const res = await getNotifications(1, true);
      setNotifications(res.data);
      setUnreadCount(res.meta.total);
    } catch {
      // Silently fail on polling fallback
    }
  };

  useEffect(() => {
    // Move async work out of effect body to avoid setState-in-effect warning
    const timeoutId = setTimeout(() => {
      fetchNotifications();
    }, 0);

    // Fallback polling every 60 seconds, only when visible
    intervalRef.current = setInterval(() => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    }, 60000);

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    // TODO: Wire Laravel Echo/Reverb WebSocket for real-time events
    // when infrastructure is ready. For now, polling fallback provides
    // functional notification delivery.

    return () => {
      clearTimeout(timeoutId);
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, []);

  return { notifications, unreadCount, refresh: fetchNotifications };
}