'use client';

import { useState } from 'react';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';

export function NotificationSettings() {
  const [settings, setSettings] = useState({
    notify_new_booking: true,
    notify_cancellation: true,
    notify_daily_summary: true,
    notify_review_received: true,
    notify_tour_status_change: true,
  });

  const toggle = (key: string) => setSettings((prev) => ({ ...prev, [key]: !prev[key as keyof typeof prev] }));

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 className="text-lg font-semibold text-[#0A2540]">Notification Preferences</h3>
      <div className="space-y-4">
        {Object.entries(settings).map(([key, value]) => (
          <div key={key} className="flex items-center justify-between">
            <Label htmlFor={key} className="text-sm text-gray-700 cursor-pointer">
              {key.replace('notify_', '').replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
            </Label>
            <Switch id={key} checked={value} onCheckedChange={() => toggle(key)} />
          </div>
        ))}
      </div>
      <div className="flex justify-end">
        <Button className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">Save Preferences</Button>
      </div>
    </div>
  );
}