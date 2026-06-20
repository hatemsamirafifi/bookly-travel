/* eslint-disable @typescript-eslint/no-explicit-any */
'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Save, Calendar, Coins, Send, CheckCircle } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';

interface Translation {
  title: string;
  description: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  meeting_point: string;
  cancellation_policy: string;
}

interface Tour {
  id: number;
  category_id: number;
  slug: string;
  location: string;
  duration_value?: number;
  duration_unit?: string;
  duration_minutes?: number;
  group_size_min: number;
  group_size_max: number;
  status: string;
  cover_image_url: string | null;
  translations: Array<{
    locale: string;
    title: string;
    description: string;
    highlights: string[] | null;
    inclusions: string[] | null;
    exclusions: string[] | null;
    meeting_point: string | null;
    cancellation_policy: string | null;
  }>;
}

export default function PartnerTourEditPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const id = resolvedParams.id;
  const locale = resolvedParams.locale;
  const router = useRouter();

  const [tour, setTour] = useState<Tour | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // General fields
  const [categoryId, setCategoryId] = useState(1);
  const [location, setLocation] = useState('');
  const [coverImageUrl, setCoverImageUrl] = useState('');
  const [groupSizeMin, setGroupSizeMin] = useState(1);
  const [groupSizeMax, setGroupSizeMax] = useState(10);
  const [durationValue, setDurationValue] = useState(2);
  const [durationUnit, setDurationUnit] = useState('hour');

  // Translations fields grouped by language
  const [activeLangTab, setActiveLangTab] = useState<'en' | 'es' | 'it'>('en');
  const [translationData, setTranslationData] = useState<Record<'en' | 'es' | 'it', Translation>>({
    en: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
    es: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
    it: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
  });

  const fetchTour = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${id}`, {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error('Failed to load tour details');
      const json = await res.json();
      const t: Tour = json;
      setTour(t);

      // Populate states
      setCategoryId(t.category_id);
      setLocation(t.location);
      setCoverImageUrl(t.cover_image_url || '');
      setGroupSizeMin(t.group_size_min);
      setGroupSizeMax(t.group_size_max);
      
      const val = t.duration_minutes ? (t.duration_minutes >= 1440 ? t.duration_minutes / 1440 : t.duration_minutes / 60) : 2;
      const unit = t.duration_minutes && t.duration_minutes >= 1440 ? 'day' : 'hour';
      setDurationValue(val);
      setDurationUnit(unit);

      // Populate translations
      const newTrans: Record<'en' | 'es' | 'it', Translation> = {
        en: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
        es: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
        it: { title: '', description: '', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
      };

      t.translations.forEach((tr) => {
        const loc = tr.locale as 'en' | 'es' | 'it';
        if (newTrans[loc]) {
          newTrans[loc] = {
            title: tr.title || '',
            description: tr.description || '',
            highlights: tr.highlights || [],
            inclusions: tr.inclusions || [],
            exclusions: tr.exclusions || [],
            meeting_point: tr.meeting_point || '',
            cancellation_policy: tr.cancellation_policy || '',
          };
        }
      });
      setTranslationData(newTrans);
    } catch (err: any) {
      setError(err.message || 'An error occurred');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchTour();
  }, [id]);

  const updateTranslationField = (lang: 'en' | 'es' | 'it', field: keyof Translation, value: any) => {
    setTranslationData((prev) => ({
      ...prev,
      [lang]: {
        ...prev[lang],
        [field]: value,
      },
    }));
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    try {
      const token = getAuthToken();
      // Format payload translations: drop completely empty non-EN translations
      const translationsPayload: Record<string, any> = {
        en: translationData.en
      };
      if (translationData.es.title) translationsPayload.es = translationData.es;
      if (translationData.it.title) translationsPayload.it = translationData.it;

      const res = await fetch(`http://localhost:8000/api/partner/tours/${id}`, {
        method: 'PUT',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          category_id: categoryId,
          location,
          cover_image_url: coverImageUrl || null,
          group_size_min: groupSizeMin,
          group_size_max: groupSizeMax,
          duration_value: durationValue,
          duration_unit: durationUnit,
          translations: translationsPayload,
        }),
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || 'Failed to update tour details');
      }

      setSuccessMsg('Tour details saved successfully!');
      fetchTour();
    } catch (err: any) {
      setError(err.message || 'Failed to save tour');
    }
  };

  const handleSubmitReview = async () => {
    setError(null);
    setSuccessMsg(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${id}/submit`, {
        method: 'POST',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || 'Failed to submit tour for review');
      }

      setSuccessMsg('Tour submitted for admin review successfully!');
      fetchTour();
    } catch (err: any) {
      setError(err.message);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-500">Loading tour editor...</div>;
  }

  if (error && !tour) {
    return <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{error}</div>;
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between border-b border-gray-100 pb-4 gap-4">
        <div className="flex items-center gap-3">
          <Link href={`/${locale}/partner`} className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-[#0A2540]">Edit Tour</h1>
              <span className={`text-xs px-2.5 py-0.5 rounded-full font-bold uppercase ${
                tour?.status === 'published' ? 'bg-emerald-100 text-emerald-800' :
                tour?.status === 'pending_review' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'
              }`}>
                {tour?.status}
              </span>
            </div>
            <p className="text-sm text-gray-500">ID: {id} — configure multi-language content, pricing, and availability.</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Link
            href={`/${locale}/partner/tours/${id}/pricing`}
            className="flex items-center gap-1 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-[#0A2540] text-sm font-semibold rounded-lg transition-colors"
          >
            <Coins className="w-4 h-4 text-gray-500" />
            Manage Pricing
          </Link>
          <Link
            href={`/${locale}/partner/tours/${id}/availability`}
            className="flex items-center gap-1 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-[#0A2540] text-sm font-semibold rounded-lg transition-colors"
          >
            <Calendar className="w-4 h-4 text-gray-500" />
            Manage Availability
          </Link>
          {tour?.status === 'draft' && (
            <button
              onClick={handleSubmitReview}
              className="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <Send className="w-4 h-4" />
              Submit For Review
            </button>
          )}
        </div>
      </div>

      {successMsg && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center gap-2">
          <CheckCircle className="w-4 h-4" />
          {successMsg}
        </div>
      )}

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {error}
        </div>
      )}

      <form onSubmit={handleSave} className="grid gap-6 lg:grid-cols-[1fr_360px]">
        {/* Left: General Settings and Language Editor */}
        <div className="space-y-6 bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
          <h2 className="font-bold text-lg text-[#0A2540] border-b border-gray-50 pb-2 mb-4">Tour Content Editing</h2>

          {/* Languages tabs */}
          <div className="flex border-b border-gray-100 gap-1 mb-4">
            {(['en', 'es', 'it'] as const).map((lang) => {
              const hasText = translationData[lang].title.length > 0;
              return (
                <button
                  type="button"
                  key={lang}
                  onClick={() => setActiveLangTab(lang)}
                  className={`px-4 py-2 text-sm font-bold border-b-2 transition-all ${
                    activeLangTab === lang
                      ? 'border-[#0A2540] text-[#0A2540]'
                      : 'border-transparent text-gray-400 hover:text-gray-600'
                  }`}
                >
                  <span className="uppercase">{lang}</span>
                  {lang === 'en' && <span className="text-red-500 ml-0.5">*</span>}
                  {hasText && lang !== 'en' && <span className="ml-1 text-[10px] bg-emerald-100 text-emerald-800 px-1 py-0.2 rounded">Filled</span>}
                </button>
              );
            })}
          </div>

          {/* Current language tab fields */}
          <div className="space-y-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Tour Title ({activeLangTab.toUpperCase()})</label>
              <input
                type="text"
                required={activeLangTab === 'en'}
                value={translationData[activeLangTab].title}
                onChange={(e) => updateTranslationField(activeLangTab, 'title', e.target.value)}
                placeholder="e.g. Majestic Roman Colosseum Tour"
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-gray-700">Description ({activeLangTab.toUpperCase()})</label>
              <textarea
                rows={5}
                required={activeLangTab === 'en'}
                value={translationData[activeLangTab].description}
                onChange={(e) => updateTranslationField(activeLangTab, 'description', e.target.value)}
                placeholder="Write an engaging description for travelers..."
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-gray-700">Meeting Point ({activeLangTab.toUpperCase()})</label>
                <input
                  type="text"
                  value={translationData[activeLangTab].meeting_point}
                  onChange={(e) => updateTranslationField(activeLangTab, 'meeting_point', e.target.value)}
                  placeholder="e.g. In front of the metro exit"
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-gray-700">Cancellation Policy ({activeLangTab.toUpperCase()})</label>
                <input
                  type="text"
                  value={translationData[activeLangTab].cancellation_policy}
                  onChange={(e) => updateTranslationField(activeLangTab, 'cancellation_policy', e.target.value)}
                  placeholder="e.g. Cancel up to 24 hours in advance for a full refund"
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-gray-700">Highlights (Comma sep)</label>
                <input
                  type="text"
                  value={translationData[activeLangTab].highlights.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'highlights', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder="skip lines, local guide, entry tickets"
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-gray-700">Inclusions (Comma sep)</label>
                <input
                  type="text"
                  value={translationData[activeLangTab].inclusions.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'inclusions', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder="entry fees, guide, drinks"
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-gray-700">Exclusions (Comma sep)</label>
                <input
                  type="text"
                  value={translationData[activeLangTab].exclusions.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'exclusions', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder="hotel pickup, lunch"
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Right: Technical Metadata Settings */}
        <aside className="space-y-6">
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <h3 className="font-bold text-sm text-[#0A2540] border-b border-gray-100 pb-2">Technical Parameters</h3>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-gray-600">Location Name</label>
              <input
                type="text"
                required
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder="e.g. Rome, Italy"
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-gray-600">Cover Image URL</label>
              <input
                type="text"
                value={coverImageUrl}
                onChange={(e) => setCoverImageUrl(e.target.value)}
                placeholder="https://example.com/image.jpg"
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Duration</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={durationValue}
                  onChange={(e) => setDurationValue(parseInt(e.target.value) || 1)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Unit</label>
                <select
                  value={durationUnit}
                  onChange={(e) => setDurationUnit(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  <option value="hour">Hours</option>
                  <option value="day">Days</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Min Guests</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={groupSizeMin}
                  onChange={(e) => setGroupSizeMin(parseInt(e.target.value) || 1)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Max Guests</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={groupSizeMax}
                  onChange={(e) => setGroupSizeMax(parseInt(e.target.value) || 10)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <Save className="w-4 h-4" />
              Save Tour Details
            </button>
          </div>
        </aside>
      </form>
    </div>
  );
}
