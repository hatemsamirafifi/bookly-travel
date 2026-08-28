'use client';

import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

export function ProfileForm() {
  const [form, setForm] = useState({
    company_name: '',
    business_description: '',
    contact_email: '',
    contact_phone: '',
    website: '',
    tax_id: '',
  });

  const update = (key: string, value: string) => setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h2 className="text-lg font-semibold text-[#0A2540]">Business Information</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="space-y-1">
          <Label htmlFor="company_name">Company Name</Label>
          <Input id="company_name" value={form.company_name} onChange={(e) => update('company_name', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="contact_email">Contact Email</Label>
          <Input id="contact_email" type="email" value={form.contact_email} onChange={(e) => update('contact_email', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="contact_phone">Phone</Label>
          <Input id="contact_phone" value={form.contact_phone} onChange={(e) => update('contact_phone', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="website">Website</Label>
          <Input id="website" type="url" value={form.website} onChange={(e) => update('website', e.target.value)} />
        </div>
      </div>
      <div className="space-y-1">
        <Label htmlFor="business_description">Description</Label>
        <Textarea id="business_description" rows={4} value={form.business_description} onChange={(e) => update('business_description', e.target.value)} />
      </div>
      <div className="space-y-1">
        <Label htmlFor="tax_id">Tax ID</Label>
        <Input id="tax_id" value={form.tax_id} onChange={(e) => update('tax_id', e.target.value)} />
      </div>
      <div className="flex justify-end">
        <Button className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">Save Changes</Button>
      </div>
    </div>
  );
}