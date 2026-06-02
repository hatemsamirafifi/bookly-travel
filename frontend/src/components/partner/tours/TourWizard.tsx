'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ChevronLeft, ChevronRight, Save } from 'lucide-react';

const steps = [
  { id: 'basic', label: 'Basic Info' },
  { id: 'details', label: 'Details' },
  { id: 'media', label: 'Media' },
  { id: 'pricing', label: 'Pricing' },
  { id: 'review', label: 'Review' },
];

export function TourWizard() {
  const [currentStep, setCurrentStep] = useState(0);
  const [form, setForm] = useState({
    title: '',
    description: '',
    category: '',
    destination: '',
    duration_value: '',
    duration_unit: 'hour',
    difficulty_level: 'easy',
    itinerary: '',
    inclusions: '',
    meeting_point: '',
  });

  const update = (key: string, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const stepContent = () => {
    switch (steps[currentStep].id) {
      case 'basic':
        return (
          <div className="space-y-4">
            <div className="space-y-1">
              <Label htmlFor="title">Title</Label>
              <Input
                id="title"
                value={form.title}
                onChange={(e) => update('title', e.target.value)}
                placeholder="e.g., Sunset Walking Tour of Rome"
                maxLength={120}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={form.description}
                onChange={(e) => update('description', e.target.value)}
                placeholder="Describe your tour experience..."
                rows={5}
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label htmlFor="category">Category</Label>
                <Select value={form.category} onValueChange={(v) => update('category', v)}>
                  <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="walking">Walking</SelectItem>
                    <SelectItem value="food">Food</SelectItem>
                    <SelectItem value="adventure">Adventure</SelectItem>
                    <SelectItem value="cultural">Cultural</SelectItem>
                    <SelectItem value="nature">Nature</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label htmlFor="destination">Destination</Label>
                <Input
                  id="destination"
                  value={form.destination}
                  onChange={(e) => update('destination', e.target.value)}
                  placeholder="City or region"
                />
              </div>
            </div>
          </div>
        );
      case 'details':
        return (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label htmlFor="duration_value">Duration</Label>
                <Input
                  id="duration_value"
                  type="number"
                  value={form.duration_value}
                  onChange={(e) => update('duration_value', e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="duration_unit">Unit</Label>
                <Select value={form.duration_unit} onValueChange={(v) => update('duration_unit', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hour">Hours</SelectItem>
                    <SelectItem value="day">Days</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1">
              <Label htmlFor="difficulty_level">Difficulty</Label>
              <Select value={form.difficulty_level} onValueChange={(v) => update('difficulty_level', v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="easy">Easy</SelectItem>
                  <SelectItem value="moderate">Moderate</SelectItem>
                  <SelectItem value="challenging">Challenging</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label htmlFor="meeting_point">Meeting Point</Label>
              <Input
                id="meeting_point"
                value={form.meeting_point}
                onChange={(e) => update('meeting_point', e.target.value)}
                placeholder="Address or landmark"
              />
            </div>
          </div>
        );
      case 'media':
        return (
          <div className="space-y-4">
            <div className="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#FFB800] transition-colors">
              <p className="text-sm text-gray-500 mb-2">Drag and drop images here, or click to browse</p>
              <p className="text-xs text-gray-400">JPG/PNG, max 5MB per image. Up to 10 gallery images.</p>
            </div>
          </div>
        );
      case 'pricing':
        return (
          <div className="space-y-4">
            <p className="text-sm text-gray-500">Set pricing tiers and availability in the next version.</p>
          </div>
        );
      case 'review':
        return (
          <div className="space-y-4">
            <h3 className="font-semibold text-[#0A2540]">Review & Submit</h3>
            <dl className="grid grid-cols-[120px_1fr] gap-y-2 text-sm">
              <dt className="text-gray-500">Title:</dt>
              <dd>{form.title || '—'}</dd>
              <dt className="text-gray-500">Category:</dt>
              <dd className="capitalize">{form.category || '—'}</dd>
              <dt className="text-gray-500">Destination:</dt>
              <dd>{form.destination || '—'}</dd>
              <dt className="text-gray-500">Duration:</dt>
              <dd>{form.duration_value} {form.duration_unit}</dd>
              <dt className="text-gray-500">Difficulty:</dt>
              <dd className="capitalize">{form.difficulty_level}</dd>
            </dl>
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <div className="max-w-2xl mx-auto">
      {/* Progress bar */}
      <div className="mb-8">
        <div className="flex items-center gap-2 mb-2">
          {steps.map((step, idx) => (
            <div key={step.id} className="flex items-center gap-2 flex-1">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold ${
                  idx <= currentStep
                    ? 'bg-[#FFB800] text-[#0A2540]'
                    : 'bg-gray-100 text-gray-400'
                }`}
              >
                {idx + 1}
              </div>
              <span className="hidden sm:inline text-xs font-medium text-gray-600">{step.label}</span>
              {idx < steps.length - 1 && (
                <div className="flex-1 h-px bg-gray-200 mx-2" />
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Step content */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        {stepContent()}
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between mt-6">
        <Button
          variant="outline"
          onClick={() => setCurrentStep((s) => Math.max(0, s - 1))}
          disabled={currentStep === 0}
        >
          <ChevronLeft className="w-4 h-4 mr-1" />
          Back
        </Button>

        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm">
            <Save className="w-4 h-4 mr-1" />
            Save Draft
          </Button>
          <Button
            onClick={() => setCurrentStep((s) => Math.min(steps.length - 1, s + 1))}
            disabled={currentStep === steps.length - 1}
            className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold"
          >
            {currentStep === steps.length - 1 ? 'Submit for Review' : 'Next'}
            <ChevronRight className="w-4 h-4 ml-1" />
          </Button>
        </div>
      </div>
    </div>
  );
}