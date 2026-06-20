'use client';

import { useState } from 'react';
import { Bell } from 'lucide-react';
import { usePartnerRealtime } from '@/lib/hooks/usePartnerRealtime';
import { markNotificationAsRead, markAllNotificationsAsRead } from '@/lib/api/partner';

export function NotificationBell() {
  const { notifications, unreadCount, refresh } = usePartnerRealtime();
  const [dropdownOpen, setDropdownOpen] = useState(false);

  const handleMarkRead = async (id: number) => {
    await markNotificationAsRead(String(id));
    refresh();
  };

  const handleMarkAllRead = async () => {
    await markAllNotificationsAsRead();
    refresh();
  };

  return (
    <div className="relative">
      <button
        type="button"
        className="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
        onClick={() => setDropdownOpen((v) => !v)}
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
      >
        <Bell className="w-5 h-5" aria-hidden="true" />
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {dropdownOpen && (
        <>
          <div
            className="fixed inset-0 z-10"
            onClick={() => setDropdownOpen(false)}
            aria-hidden="true"
          />
          <div className="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-20 overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
              {unreadCount > 0 && (
                <button
                  type="button"
                  className="text-xs text-[#0A2540] hover:underline"
                  onClick={handleMarkAllRead}
                >
                  Mark all as read
                </button>
              )}
            </div>
            <div className="max-h-80 overflow-y-auto">
              {notifications.length === 0 ? (
                <div className="px-4 py-8 text-center text-sm text-gray-500">
                  No new notifications
                </div>
              ) : (
                notifications.map((n) => (
                  <div
                    key={n.id}
                    className="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 cursor-pointer"
                    onClick={() => handleMarkRead(n.id)}
                  >
                    <p className="text-sm font-medium text-gray-900">{n.title}</p>
                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{n.body}</p>
                  </div>
                ))
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}