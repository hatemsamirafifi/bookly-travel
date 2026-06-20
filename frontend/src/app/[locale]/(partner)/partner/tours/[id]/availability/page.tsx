/* eslint-disable @typescript-eslint/no-explicit-any */
'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Plus, Trash2, Calendar, Clock, Users, ShieldAlert } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';

interface AvailabilityRule {
  id: number;
  rule_type: string;
  days_of_week: number[] | null;
  start_time: string;
  start_date: string;
  end_date: string | null;
  capacity: number;
}

interface AvailabilityException {
  id: number;
  exception_type: string;
  date: string;
  start_time: string | null;
  capacity: number | null;
  price_multiplier: string;
  note: string | null;
}

export default function AvailabilityPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const tourId = resolvedParams.id;
  const locale = resolvedParams.locale;

  const [rules, setRules] = useState<AvailabilityRule[]>([]);
  const [exceptions, setExceptions] = useState<AvailabilityException[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Creation panel states
  const [activeTab, setActiveTab] = useState<'rules' | 'exceptions'>('rules');
  
  // New Rule form
  const [ruleType, setRuleType] = useState('weekly');
  const [daysOfWeek, setDaysOfWeek] = useState<number[]>([]);
  const [startTime, setStartTime] = useState('09:00');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [capacity, setCapacity] = useState(15);

  // New Exception form
  const [exceptionType, setExceptionType] = useState('block');
  const [excDate, setExcDate] = useState('');
  const [excStartTime, setExcStartTime] = useState('');
  const [excCapacity, setExcCapacity] = useState('');
  const [priceMultiplier, setPriceMultiplier] = useState('1.00');
  const [note, setNote] = useState('');

  const fetchAvailability = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${tourId}/availability`, {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error('Failed to load availability');
      const json = await res.json();
      setRules(json.data.rules || []);
      setExceptions(json.data.exceptions || []);
    } catch (err: any) {
      setError(err.message || 'An error occurred loading slots');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAvailability();
  }, [tourId]);

  const handleCreateRule = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${tourId}/availability/rules`, {
        method: 'POST',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          rule_type: ruleType,
          days_of_week: ruleType === 'weekly' ? daysOfWeek : null,
          start_time: startTime,
          start_date: startDate,
          end_date: endDate || null,
          capacity,
        }),
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || 'Failed to create availability rule');
      }

      // reset form
      setDaysOfWeek([]);
      setStartDate('');
      setEndDate('');
      fetchAvailability();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleCreateException = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${tourId}/availability/exceptions`, {
        method: 'POST',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          exception_type: exceptionType,
          date: excDate,
          start_time: excStartTime || null,
          capacity: exceptionType === 'capacity_override' ? parseInt(excCapacity) || 0 : null,
          price_multiplier: exceptionType === 'price_multiplier' ? parseFloat(priceMultiplier) : 1.00,
          note: note || null,
        }),
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || 'Failed to create override exception');
      }

      setExcDate('');
      setExcStartTime('');
      setExcCapacity('');
      setPriceMultiplier('1.00');
      setNote('');
      fetchAvailability();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleDeleteRule = async (id: number) => {
    if (!confirm('Delete this recurring rule?')) return;
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${tourId}/availability/rules/${id}`, {
        method: 'DELETE',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error('Failed to delete rule');
      fetchAvailability();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleDeleteException = async (id: number) => {
    if (!confirm('Delete this override exception?')) return;
    try {
      const token = getAuthToken();
      const res = await fetch(`http://localhost:8000/api/partner/tours/${tourId}/availability/exceptions/${id}`, {
        method: 'DELETE',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error('Failed to delete exception');
      fetchAvailability();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const toggleDayOfWeek = (day: number) => {
    setDaysOfWeek((prev) =>
      prev.includes(day) ? prev.filter((d) => d !== day) : [...prev, day].sort()
    );
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex items-center gap-3 border-b border-gray-100 pb-4">
        <Link
          href={`/${locale}/partner`}
          className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500"
        >
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-[#0A2540]">Tour Availability & Schedule</h1>
          <p className="text-sm text-gray-500">Manage recurring slots, blackout dates, and capacity limits.</p>
        </div>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {error}
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
        {/* Left Column: Rules & Exceptions Lists */}
        <div className="space-y-6">
          {/* Rules */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h2 className="font-bold text-lg text-[#0A2540] flex items-center gap-2">
                <Clock className="w-5 h-5 text-gray-400" />
                Recurring Schedule Rules
              </h2>
              <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-semibold">
                {rules.length} Active
              </span>
            </div>

            {isLoading ? (
              <div className="text-center py-6 text-gray-400">Loading schedules...</div>
            ) : rules.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                No recurring rules. Add one on the right to open booking times.
              </div>
            ) : (
              <div className="divide-y divide-gray-100">
                {rules.map((rule) => (
                  <div key={rule.id} className="py-4 first:pt-0 last:pb-0 flex items-start justify-between gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold capitalize text-[#0A2540]">
                          {rule.rule_type} Tour Slot
                        </span>
                        <span className="text-xs font-mono bg-[#FFB800]/10 text-[#0A2540] px-2 py-0.5 rounded font-bold">
                          {rule.start_time.substring(0, 5)}
                        </span>
                      </div>
                      <p className="text-xs text-gray-500">
                        Runs from {rule.start_date} {rule.end_date ? `to ${rule.end_date}` : 'indefinitely'}
                      </p>
                      {rule.days_of_week && (
                        <div className="flex gap-1 pt-1">
                          {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((day, i) => {
                            const active = rule.days_of_week?.includes(i);
                            return (
                              <span
                                key={i}
                                className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold ${
                                  active ? 'bg-[#FFB800] text-[#0A2540]' : 'bg-gray-100 text-gray-400'
                                }`}
                              >
                                {day}
                              </span>
                            );
                          })}
                        </div>
                      )}
                      <p className="text-xs text-gray-600 font-medium">Capacity: {rule.capacity} travelers</p>
                    </div>
                    <button
                      onClick={() => handleDeleteRule(rule.id)}
                      className="p-1.5 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Exceptions */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h2 className="font-bold text-lg text-[#0A2540] flex items-center gap-2">
                <Calendar className="w-5 h-5 text-gray-400" />
                Date Overrides & Blackouts
              </h2>
              <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-semibold">
                {exceptions.length} Configured
              </span>
            </div>

            {isLoading ? (
              <div className="text-center py-6 text-gray-400">Loading overrides...</div>
            ) : exceptions.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                No active date exceptions.
              </div>
            ) : (
              <div className="divide-y divide-gray-100">
                {exceptions.map((exc) => {
                  const isBlock = exc.exception_type === 'block';
                  return (
                    <div key={exc.id} className="py-4 first:pt-0 last:pb-0 flex items-start justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-semibold text-gray-900">
                            {new Date(exc.date + 'T00:00:00').toLocaleDateString()}
                          </span>
                          <span className={`text-[10px] px-2 py-0.5 rounded font-semibold uppercase ${
                            isBlock ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-blue-50 text-blue-700 border border-blue-100'
                          }`}>
                            {exc.exception_type.replace('_', ' ')}
                          </span>
                        </div>
                        {exc.note && <p className="text-xs text-gray-500 italic">&ldquo;{exc.note}&rdquo;</p>}
                        <div className="text-xs text-gray-600 space-y-0.5">
                          {exc.start_time && <p>Time slot: {exc.start_time.substring(0, 5)}</p>}
                          {exc.capacity !== null && <p>Override Capacity: {exc.capacity} slots</p>}
                          {parseFloat(exc.price_multiplier) !== 1 && (
                            <p>Price Multiplier: <span className="font-semibold text-blue-600">{exc.price_multiplier}x</span></p>
                          )}
                        </div>
                      </div>
                      <button
                        onClick={() => handleDeleteException(exc.id)}
                        className="p-1.5 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Right Column: Creation Panel */}
        <aside className="space-y-6">
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            {/* Tabs */}
            <div className="flex bg-gray-200/60 p-1 rounded-lg">
              <button
                onClick={() => setActiveTab('rules')}
                className={`flex-1 text-center py-1.5 text-xs font-semibold rounded-md transition-colors ${
                  activeTab === 'rules' ? 'bg-white text-[#0A2540] shadow-sm' : 'text-gray-500 hover:text-[#0A2540]'
                }`}
              >
                Add Slot
              </button>
              <button
                onClick={() => setActiveTab('exceptions')}
                className={`flex-1 text-center py-1.5 text-xs font-semibold rounded-md transition-colors ${
                  activeTab === 'exceptions' ? 'bg-white text-[#0A2540] shadow-sm' : 'text-gray-500 hover:text-[#0A2540]'
                }`}
              >
                Add Exception
              </button>
            </div>

            {activeTab === 'rules' ? (
              // Rules Form
              <form onSubmit={handleCreateRule} className="space-y-3">
                <h3 className="font-bold text-sm text-[#0A2540] pb-1 border-b border-gray-100">Add Recurring Rule</h3>
                
                <div className="flex flex-col gap-1">
                  <label className="text-xs font-semibold text-gray-600">Rule Type</label>
                  <select
                    value={ruleType}
                    onChange={(e) => setRuleType(e.target.value)}
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                  </select>
                </div>

                {ruleType === 'weekly' && (
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Days of Week</label>
                    <div className="flex justify-between gap-1 pt-1">
                      {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((day, idx) => {
                        const active = daysOfWeek.includes(idx);
                        return (
                          <button
                            type="button"
                            key={idx}
                            onClick={() => toggleDayOfWeek(idx)}
                            className={`w-8 h-8 rounded-full text-xs font-bold transition-all ${
                              active ? 'bg-[#FFB800] text-[#0A2540]' : 'bg-white border border-gray-200 text-gray-500 hover:bg-gray-100'
                            }`}
                          >
                            {day}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Start Time</label>
                    <input
                      type="time"
                      required
                      value={startTime}
                      onChange={(e) => setStartTime(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Max Capacity</label>
                    <input
                      type="number"
                      required
                      min={1}
                      value={capacity}
                      onChange={(e) => setCapacity(parseInt(e.target.value) || 1)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Start Date</label>
                    <input
                      type="date"
                      required
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">End Date (Optional)</label>
                    <input
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  Save Schedule Rule
                </button>
              </form>
            ) : (
              // Exceptions Form
              <form onSubmit={handleCreateException} className="space-y-3">
                <h3 className="font-bold text-sm text-[#0A2540] pb-1 border-b border-gray-100">Add Exception</h3>

                <div className="flex flex-col gap-1">
                  <label className="text-xs font-semibold text-gray-600">Exception Type</label>
                  <select
                    value={exceptionType}
                    onChange={(e) => setExceptionType(e.target.value)}
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  >
                    <option value="block">Blackout (Block bookings)</option>
                    <option value="capacity_override">Capacity Override</option>
                    <option value="price_multiplier">Price Multiplier</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Target Date</label>
                    <input
                      type="date"
                      required
                      value={excDate}
                      onChange={(e) => setExcDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Start Time (Opt)</label>
                    <input
                      type="time"
                      value={excStartTime}
                      onChange={(e) => setExcStartTime(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                {exceptionType === 'capacity_override' && (
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Override Capacity</label>
                    <input
                      type="number"
                      required
                      min={0}
                      value={excCapacity}
                      onChange={(e) => setExcCapacity(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                )}

                {exceptionType === 'price_multiplier' && (
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-semibold text-gray-600">Price Multiplier</label>
                    <input
                      type="number"
                      required
                      min={0.5}
                      max={5}
                      step={0.05}
                      value={priceMultiplier}
                      onChange={(e) => setPriceMultiplier(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                )}

                <div className="flex flex-col gap-1">
                  <label className="text-xs font-semibold text-gray-600">Note / Reason</label>
                  <input
                    type="text"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="e.g. Christmas Day, Holiday Surcharge"
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  Save Exception
                </button>
              </form>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
