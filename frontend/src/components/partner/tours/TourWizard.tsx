'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations, useLocale } from 'next-intl';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
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
import { ImageUploader } from './ImageUploader';
import { PricingTierForm } from './PricingTierForm';
import { AvailabilityCalendar } from './AvailabilityCalendar';
import { createTour } from '@/lib/api/partner';
import {
  tourBasicDetailsSchema,
  tourMediaStepSchema,
  tourPricingStepSchema,
  tourAvailabilityStepSchema,
} from '@/lib/validators/partner';
import type { WizardStep, Tour } from '@/types/tour';

const steps: { id: WizardStep; labelKey: string }[] = [
  { id: 'details', labelKey: 'wizard.details' },
  { id: 'media', labelKey: 'wizard.media' },
  { id: 'pricing', labelKey: 'wizard.pricing' },
  { id: 'availability', labelKey: 'wizard.availability' },
  { id: 'review', labelKey: 'wizard.review' },
];

export function TourWizard() {
  const t = useTranslations('partner.tours');
  const locale = useLocale();
  const router = useRouter();

  const {
    currentStep,
    formData,
    isSubmitting,
    setStep,
    updateField,
    setMedia,
    reset,
    setIsSubmitting,
  } = useTourWizardStore();

  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [errorMsg, setErrorMsg] = useState('');

  const currentStepIdx = steps.findIndex((s) => s.id === currentStep);

  const formatError = (key?: string) => {
    if (!key) return '';
    return t(key.startsWith('partner.tours.') ? key.replace('partner.tours.', '') : key);
  };

  const validateStep = (step: WizardStep) => {
    let result;
    if (step === 'details') {
      result = tourBasicDetailsSchema.safeParse(formData);
    } else if (step === 'media') {
      result = tourMediaStepSchema.safeParse(formData);
    } else if (step === 'pricing') {
      result = tourPricingStepSchema.safeParse(formData);
    } else if (step === 'availability') {
      result = tourAvailabilityStepSchema.safeParse(formData);
    } else {
      return true;
    }

    if (!result.success) {
      const fieldErrors: Record<string, string> = {};
      result.error.issues.forEach((issue) => {
        const path = issue.path.join('.');
        fieldErrors[path] = issue.message;
      });
      setValidationErrors(fieldErrors);
      return false;
    }

    setValidationErrors({});
    return true;
  };

  const handleNext = () => {
    if (validateStep(currentStep)) {
      if (currentStepIdx < steps.length - 1) {
        setStep(steps[currentStepIdx + 1].id);
      }
    }
  };

  const handleBack = () => {
    if (currentStepIdx > 0) {
      setStep(steps[currentStepIdx - 1].id);
    }
  };

  const handleSaveDraft = async () => {
    setIsSubmitting(true);
    setErrorMsg('');
    try {
      const payload = {
        title: formData.title,
        description: formData.description,
        category: formData.category,
        destination: formData.destination,
        duration_value: parseFloat(formData.duration_value) || 0,
        duration_unit: formData.duration_unit,
        difficulty_level: formData.difficulty_level,
        meeting_point: formData.meeting_point,
        itinerary: formData.itinerary,
        inclusions: formData.inclusions,
        languages: formData.languages,
        cancellation_policy: formData.cancellation_policy,
        media: formData.media,
        pricing_tiers: formData.pricing_tiers.map((t) => ({
          name: t.name,
          price: parseFloat(t.price) || 0,
          currency: t.currency,
          min_participants: t.min_participants,
          max_participants: t.max_participants,
        })),
        availability_rules: formData.availability_rules,
        availability_exceptions: formData.availability_exceptions,
        min_participants: formData.min_participants,
        max_participants: formData.max_participants,
        status: 'draft',
      };

      await createTour(
        payload as unknown as Omit<Tour, 'id' | 'partner_id' | 'created_at' | 'updated_at' | 'published_at'>
      );
      reset();
      router.push(`/${locale}/partner`);
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Failed to save draft');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSubmitReview = async () => {
    if (
      !validateStep('details') ||
      !validateStep('media') ||
      !validateStep('pricing') ||
      !validateStep('availability')
    ) {
      return;
    }

    setIsSubmitting(true);
    setErrorMsg('');
    try {
      const payload = {
        title: formData.title,
        description: formData.description,
        category: formData.category,
        destination: formData.destination,
        duration_value: parseFloat(formData.duration_value) || 0,
        duration_unit: formData.duration_unit,
        difficulty_level: formData.difficulty_level,
        meeting_point: formData.meeting_point,
        itinerary: formData.itinerary,
        inclusions: formData.inclusions,
        languages: formData.languages,
        cancellation_policy: formData.cancellation_policy,
        media: formData.media,
        pricing_tiers: formData.pricing_tiers.map((t) => ({
          name: t.name,
          price: parseFloat(t.price) || 0,
          currency: t.currency,
          min_participants: t.min_participants,
          max_participants: t.max_participants,
        })),
        availability_rules: formData.availability_rules,
        availability_exceptions: formData.availability_exceptions,
        min_participants: formData.min_participants,
        max_participants: formData.max_participants,
        status: 'pending_review',
      };

      await createTour(
        payload as unknown as Omit<Tour, 'id' | 'partner_id' | 'created_at' | 'updated_at' | 'published_at'>
      );
      reset();
      router.push(`/${locale}/partner`);
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Failed to submit review');
    } finally {
      setIsSubmitting(false);
    }
  };

  const stepContent = () => {
    switch (currentStep) {
      case 'details':
        return (
          <div className="space-y-4">
            <div className="space-y-1">
              <Label htmlFor="title">{t('form.title')}</Label>
              <Input
                id="title"
                value={formData.title}
                onChange={(e) => updateField('title', e.target.value)}
                placeholder={t('form.titlePlaceholder')}
                maxLength={120}
                disabled={isSubmitting}
              />
              {validationErrors['title'] && (
                <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['title'])}</p>
              )}
            </div>
            <div className="space-y-1">
              <Label htmlFor="description">{t('form.description')}</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => updateField('description', e.target.value)}
                placeholder={t('form.descriptionPlaceholder')}
                rows={5}
                disabled={isSubmitting}
              />
              {validationErrors['description'] && (
                <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['description'])}</p>
              )}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label htmlFor="category">{t('form.category')}</Label>
                <Select
                  value={formData.category}
                  onValueChange={(v) => updateField('category', v)}
                  disabled={isSubmitting}
                >
                  <SelectTrigger><SelectValue placeholder={t('form.categoryPlaceholder')} /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="walking">{t('form.walking') || 'Walking'}</SelectItem>
                    <SelectItem value="food">{t('form.food') || 'Food'}</SelectItem>
                    <SelectItem value="adventure">{t('form.adventure') || 'Adventure'}</SelectItem>
                    <SelectItem value="cultural">{t('form.cultural') || 'Cultural'}</SelectItem>
                    <SelectItem value="nature">{t('form.nature') || 'Nature'}</SelectItem>
                  </SelectContent>
                </Select>
                {validationErrors['category'] && (
                  <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['category'])}</p>
                )}
              </div>
              <div className="space-y-1">
                <Label htmlFor="destination">{t('form.destination')}</Label>
                <Input
                  id="destination"
                  value={formData.destination}
                  onChange={(e) => updateField('destination', e.target.value)}
                  placeholder={t('form.destinationPlaceholder')}
                  disabled={isSubmitting}
                />
                {validationErrors['destination'] && (
                  <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['destination'])}</p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label htmlFor="duration_value">{t('form.durationValue')}</Label>
                <Input
                  id="duration_value"
                  type="number"
                  value={formData.duration_value}
                  onChange={(e) => updateField('duration_value', e.target.value)}
                  disabled={isSubmitting}
                />
                {validationErrors['duration_value'] && (
                  <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['duration_value'])}</p>
                )}
              </div>
              <div className="space-y-1">
                <Label htmlFor="duration_unit">{t('form.durationUnit')}</Label>
                <Select
                  value={formData.duration_unit}
                  onValueChange={(v) => updateField('duration_unit', v as 'hour' | 'day')}
                  disabled={isSubmitting}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hour">{t('form.daily') || 'Hours'}</SelectItem>
                    <SelectItem value="day">{t('form.weekly') || 'Days'}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1">
              <Label htmlFor="difficulty_level">{t('form.difficultyLevel')}</Label>
              <Select
                value={formData.difficulty_level}
                onValueChange={(v) => updateField('difficulty_level', v as 'easy' | 'moderate' | 'challenging')}
                disabled={isSubmitting}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="easy">{t('form.easy') || 'Easy'}</SelectItem>
                  <SelectItem value="moderate">{t('form.moderate') || 'Moderate'}</SelectItem>
                  <SelectItem value="challenging">{t('form.challenging') || 'Challenging'}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label htmlFor="meeting_point">{t('form.meetingPoint')}</Label>
              <Input
                id="meeting_point"
                value={formData.meeting_point}
                onChange={(e) => updateField('meeting_point', e.target.value)}
                placeholder={t('form.meetingPointPlaceholder')}
                disabled={isSubmitting}
              />
              {validationErrors['meeting_point'] && (
                <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['meeting_point'])}</p>
              )}
            </div>
          </div>
        );
      case 'media':
        return (
          <div className="space-y-4">
            <ImageUploader media={formData.media} onChange={setMedia} disabled={isSubmitting} />
            {validationErrors['media'] && (
              <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['media'])}</p>
            )}
          </div>
        );
      case 'pricing':
        return (
          <div className="space-y-4">
            <PricingTierForm disabled={isSubmitting} />
            {validationErrors['pricing_tiers'] && (
              <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['pricing_tiers'])}</p>
            )}
          </div>
        );
      case 'availability':
        return (
          <div className="space-y-4">
            <AvailabilityCalendar disabled={isSubmitting} />
            {validationErrors['availability_rules'] && (
              <p className="text-xs text-red-500 mt-1">{formatError(validationErrors['availability_rules'])}</p>
            )}
          </div>
        );
      case 'review':
        return (
          <div className="space-y-4">
            <h3 className="font-semibold text-[#0A2540]">{t('wizard.review')} &amp; {t('wizard.submit')}</h3>
            <dl className="grid grid-cols-[140px_1fr] gap-y-2 text-sm">
              <dt className="text-gray-500">{t('form.title')}:</dt>
              <dd>{formData.title || '—'}</dd>
              <dt className="text-gray-500">{t('form.category')}:</dt>
              <dd className="capitalize">{formData.category || '—'}</dd>
              <dt className="text-gray-500">{t('form.destination')}:</dt>
              <dd>{formData.destination || '—'}</dd>
              <dt className="text-gray-500">{t('form.durationValue')}:</dt>
              <dd>{formData.duration_value} {formData.duration_unit}</dd>
              <dt className="text-gray-500">{t('form.difficultyLevel')}:</dt>
              <dd className="capitalize">{formData.difficulty_level}</dd>
              <dt className="text-gray-500">{t('form.meetingPoint')}:</dt>
              <dd>{formData.meeting_point || '—'}</dd>
              <dt className="text-gray-500">{t('form.pricingTiers')}:</dt>
              <dd>{formData.pricing_tiers.length} defined</dd>
              <dt className="text-gray-500">{t('form.recurringSchedule')}:</dt>
              <dd>{formData.availability_rules.length} defined</dd>
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
                  idx <= currentStepIdx
                    ? 'bg-[#FFB800] text-[#0A2540]'
                    : 'bg-gray-100 text-gray-400'
                }`}
              >
                {idx + 1}
              </div>
              <span className="hidden sm:inline text-xs font-medium text-gray-600">{t(step.labelKey)}</span>
              {idx < steps.length - 1 && (
                <div className="flex-1 h-px bg-gray-200 mx-2" />
              )}
            </div>
          ))}
        </div>
      </div>

      {errorMsg && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm" role="alert">
          {errorMsg}
        </div>
      )}

      {/* Step content */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        {stepContent()}
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between mt-6">
        <Button
          variant="outline"
          onClick={handleBack}
          disabled={currentStepIdx === 0 || isSubmitting}
        >
          <ChevronLeft className="w-4 h-4 mr-1" />
          {t('wizard.prevStep')}
        </Button>

        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleSaveDraft}
            disabled={isSubmitting}
          >
            <Save className="w-4 h-4 mr-1" />
            {isSubmitting ? t('wizard.saving') : t('wizard.saveDraft')}
          </Button>
          {currentStep === 'review' ? (
            <Button
              onClick={handleSubmitReview}
              disabled={isSubmitting}
              className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold"
            >
              {isSubmitting ? t('wizard.submitting') : t('wizard.submit')}
              <ChevronRight className="w-4 h-4 ml-1" />
            </Button>
          ) : (
            <Button
              onClick={handleNext}
              disabled={isSubmitting}
              className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold"
            >
              {t('wizard.nextStep')}
              <ChevronRight className="w-4 h-4 ml-1" />
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}