'use client';

import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

export function PayoutForm() {
  const [form, setForm] = useState({
    payout_holder_name: '',
    payout_bank_name: '',
    payout_iban: '',
    payout_swift_bic: '',
    payout_country: '',
  });

  const update = (key: string, value: string) => setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 className="text-lg font-semibold text-[#0A2540]">Payout Information</h3>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="space-y-1">
          <Label htmlFor="payout_holder_name">Account Holder Name</Label>
          <Input id="payout_holder_name" value={form.payout_holder_name} onChange={(e) => update('payout_holder_name', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="payout_bank_name">Bank Name</Label>
          <Input id="payout_bank_name" value={form.payout_bank_name} onChange={(e) => update('payout_bank_name', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="payout_iban">IBAN</Label>
          <Input id="payout_iban" value={form.payout_iban} onChange={(e) => update('payout_iban', e.target.value)} placeholder="e.g., GB82 WEST 1234 5698 7654 32" />
        </div>
        <div className="space-y-1">
          <Label htmlFor="payout_swift_bic">SWIFT/BIC</Label>
          <Input id="payout_swift_bic" value={form.payout_swift_bic} onChange={(e) => update('payout_swift_bic', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="payout_country">Country (ISO)</Label>
          <Input id="payout_country" value={form.payout_country} onChange={(e) => update('payout_country', e.target.value)} maxLength={2} placeholder="e.g., GB" />
        </div>
      </div>
      <div className="flex justify-end">
        <Button className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">Save Payout Details</Button>
      </div>
    </div>
  );
}