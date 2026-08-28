/* eslint-disable @typescript-eslint/no-explicit-any */
'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Plus, Trash2, Edit, Save, X } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';
import { getApiBaseUrl } from '@/lib/api/client';

interface PricingTier {
  id: number;
  name: string;
  price: string;
  min_participants: number;
  max_participants: number | null;
}

export default function PricingPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const tourId = resolvedParams.id;
  const locale = resolvedParams.locale;
  const router = useRouter();

  const [tiers, setTiers] = useState<PricingTier[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form states
  const [isEditing, setIsEditing] = useState<number | null>(null); // tier ID or -1 for new
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [minParticipants, setMinParticipants] = useState(1);
  const [maxParticipants, setMaxParticipants] = useState<number | ''>('');

  const fetchTiers = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${tourId}/pricing`, {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error('Failed to load pricing tiers');
      const json = await res.json();
      setTiers(json.data || []);
    } catch (err: any) {
      setError(err.message || 'An error occurred');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchTiers();
  }, [tourId]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      const token = getAuthToken();
      const isNew = isEditing === -1;
      const url = isNew
        ? `${getApiBaseUrl()}/api/partner/tours/${tourId}/pricing`
        : `${getApiBaseUrl()}/api/partner/tours/${tourId}/pricing/${isEditing}`;

      const res = await fetch(url, {
        method: isNew ? 'POST' : 'PUT',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          name,
          price: parseFloat(price),
          min_participants: minParticipants,
          max_participants: maxParticipants === '' ? null : maxParticipants,
        }),
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || 'Failed to save pricing tier');
      }

      setIsEditing(null);
      setName('');
      setPrice('');
      setMinParticipants(1);
      setMaxParticipants('');
      fetchTiers();
    } catch (err: any) {
      setError(err.message || 'Failed to save tier');
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this pricing tier?')) return;
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${tourId}/pricing/${id}`, {
        method: 'DELETE',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });

      if (!res.ok) throw new Error('Failed to delete pricing tier');
      fetchTiers();
    } catch (err: any) {
      setError(err.message || 'Failed to delete tier');
    }
  };

  const startEdit = (tier: PricingTier) => {
    setIsEditing(tier.id);
    setName(tier.name);
    setPrice(tier.price);
    setMinParticipants(tier.min_participants);
    setMaxParticipants(tier.max_participants ?? '');
  };

  const startCreate = () => {
    setIsEditing(-1);
    setName('');
    setPrice('');
    setMinParticipants(1);
    setMaxParticipants('');
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-gray-100 pb-4">
        <div className="flex items-center gap-3">
          <Link
            href={`/${locale}/partner`}
            className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500"
          >
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-[#0A2540]">Tour Pricing Management</h1>
            <p className="text-sm text-gray-500">Configure participant tiers, pricing levels, and participant boundaries.</p>
          </div>
        </div>
        {isEditing === null && (
          <button
            onClick={startCreate}
            className="flex items-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            Add Pricing Tier
          </button>
        )}
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {error}
        </div>
      )}

      {/* Editor Modal / Panel Inline */}
      {isEditing !== null && (
        <form onSubmit={handleSave} className="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-4">
          <h3 className="font-semibold text-lg text-[#0A2540]">
            {isEditing === -1 ? 'Create New Pricing Tier' : 'Edit Pricing Tier'}
          </h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Tier Name</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Adult, Child, Student"
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Price (EUR)</label>
              <input
                type="number"
                required
                min="0.01"
                step="0.01"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                placeholder="0.00"
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Min Participants</label>
              <input
                type="number"
                required
                min="1"
                value={minParticipants}
                onChange={(e) => setMinParticipants(parseInt(e.target.value) || 1)}
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Max Participants (Optional)</label>
              <input
                type="number"
                min="1"
                value={maxParticipants}
                onChange={(e) => setMaxParticipants(e.target.value === '' ? '' : parseInt(e.target.value) || '')}
                placeholder="Leave blank for no limit"
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <div className="flex items-center gap-3 pt-2 justify-end">
            <button
              type="button"
              onClick={() => setIsEditing(null)}
              className="flex items-center gap-1 px-4 py-2 border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg transition-colors"
            >
              <X className="w-4 h-4" />
              Cancel
            </button>
            <button
              type="submit"
              className="flex items-center gap-1 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
            >
              <Save className="w-4 h-4" />
              Save Tier
            </button>
          </div>
        </form>
      )}

      {/* List */}
      {isLoading ? (
        <div className="text-center py-12 text-gray-400">Loading pricing configuration...</div>
      ) : tiers.length === 0 ? (
        <div className="text-center py-12 border border-dashed rounded-xl text-gray-400">
          No pricing tiers defined yet. Click &ldquo;Add Pricing Tier&rdquo; to begin.
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {tiers.map((tier) => (
            <div
              key={tier.id}
              className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-[#FFB800] transition-all flex flex-col justify-between"
            >
              <div>
                <div className="flex items-start justify-between">
                  <h3 className="font-bold text-lg text-[#0A2540]">{tier.name}</h3>
                  <span className="font-mono font-bold text-[#FFB800] text-lg">€{parseFloat(tier.price).toFixed(2)}</span>
                </div>
                <div className="mt-3 text-sm text-gray-600 space-y-1">
                  <p>Min participants: <span className="font-semibold">{tier.min_participants}</span></p>
                  <p>Max participants: <span className="font-semibold">{tier.max_participants ?? 'Unlimited'}</span></p>
                </div>
              </div>

              <div className="flex items-center gap-2 mt-5 border-t border-gray-50 pt-4 justify-end">
                <button
                  onClick={() => startEdit(tier)}
                  className="flex items-center gap-1 px-3 py-1.5 border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-semibold rounded-lg transition-colors"
                >
                  <Edit className="w-3.5 h-3.5" />
                  Edit
                </button>
                <button
                  onClick={() => handleDelete(tier.id)}
                  className="flex items-center gap-1 px-3 py-1.5 border border-red-100 hover:bg-red-50 text-red-600 text-xs font-semibold rounded-lg transition-colors"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
