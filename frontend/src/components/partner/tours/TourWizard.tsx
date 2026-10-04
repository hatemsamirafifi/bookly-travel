'use client';

import { useEffect, useRef, useState } from 'react';
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
import { ItineraryEditor } from './ItineraryEditor';
import { TourContentPreview } from './TourContentPreview';
import { PricingTierForm } from './PricingTierForm';
import { AvailabilityCalendar } from './AvailabilityCalendar';
import { createTour, updateTour, submitTour } from '@/lib/api/partner';
import { ValidationError } from '@/lib/api/client';
import {
  tourBasicDetailsSchema,
  tourMediaStepSchema,
  tourPricingStepSchema,
  tourAvailabilityStepSchema,
  tourPublishSourceSchema,
  mapServerErrorsToFields,
  localizeServerFieldErrors,
  pickFieldErrors,
} from '@/lib/validators/partner';
import type { WizardStep } from '@/types/tour';
import type { PartnerTourWritePayload } from '@/lib/api/types';

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
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [errorMsg, setErrorMsg] = useState('');
  const [notice, setNotice] = useState('');
  // Successfully materialized draft id: retries update THIS tour instead of
  // creating duplicates after a partial success (created, submit failed).
  const [createdDraftId, setCreatedDraftId] = useState<number | null>(null);
  const errorSummaryRef = useRef<HTMLDivElement>(null);

  const currentStepIdx = steps.findIndex((s) => s.id === currentStep);

  const tKey = (key: string) => t(key.startsWith('partner.tours.') ? key.replace('partner.tours.', '') : key);

  const formatError = (key?: string) => {
    if (!key) return '';
    return tKey(key);
  };

  // Raw server messages localized for the page locale at render time.
  const localizedServerErrors = localizeServerFieldErrors(serverErrors, tKey);

  // Summary entries for the announced error box: localized client messages
  // plus localized server messages, exact paths retained for mapping.
  const summaryEntries: Array<{ path: string; message: string }> = [
    ...Object.entries(validationErrors).map(([path, key]) => ({ path, message: formatError(key) })),
    ...Object.entries(localizedServerErrors).map(([path, message]) => ({ path, message })),
  ];

  // Move keyboard focus to the announced summary only when a NEW error set
  // appears (not on every keystroke while errors stay visible).
  const errorSignature =
    errorMsg + '|' + summaryEntries.map((entry) => `${entry.path}:${entry.message}`).join(';');
  const prevErrorSignatureRef = useRef('');
  useEffect(() => {
    if (errorSignature && errorSignature !== prevErrorSignatureRef.current) {
      prevErrorSignatureRef.current = errorSignature;
      errorSummaryRef.current?.focus();
    }
    if (!errorSignature) {
      prevErrorSignatureRef.current = '';
    }
  }, [errorSignature]);

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

  // Canonical English source: nested translations.en wins per field on the
  // server; shorthand basics travel alongside for compatibility. Pricing,
  // availability, group size and media are preserved in every submission.
  const buildPayload = (): PartnerTourWritePayload => ({
    title: formData.title,
    description: formData.description,
    category: formData.category,
    destination: formData.destination,
    duration_value: Number(formData.duration_value) || 0,
    duration_unit: formData.duration_unit,
    difficulty_level: formData.difficulty_level,
    meeting_point: formData.meeting_point || null,
    guide_languages: formData.languages.filter(Boolean),
    cancellation_policy: formData.cancellation_policy || null,
    itinerary: formData.itinerary,
    translations: {
      en: {
        title: formData.title,
        description: formData.description,
        highlights: formData.highlights,
        inclusions: formData.inclusions,
        exclusions: formData.exclusions,
        meeting_point: formData.meeting_point || null,
        cancellation_policy: formData.cancellation_policy || null,
        itinerary: formData.itinerary,
        important_information: formData.important_information,
      },
    },
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
    group_size_min: formData.group_size_min,
    group_size_max: formData.group_size_max,
  });

  // Server failures stay page-localized: field errors render through the
  // catalog (retained in the focused summary), while generic save/submit
  // failures use the passed localized message. Raw backend/English text
  // never reaches the UI.
  const applyServerError = (err: unknown, fallbackMessage: string) => {
    if (err instanceof ValidationError) {
      const fields = mapServerErrorsToFields(err.errors);
      setServerErrors(fields);
      setErrorMsg(Object.keys(fields).length > 0 ? '' : fallbackMessage);
      return;
    }
    setErrorMsg(fallbackMessage);
  };

  // Materialize the draft: create once, then update the retained id so
  // retries after a partial success never duplicate the tour.
  const materializeDraft = async () => {
    const payload = buildPayload();
    if (createdDraftId !== null) {
      const updated = await updateTour(createdDraftId, payload);
      return updated.data.id;
    }
    const created = await createTour(payload);
    setCreatedDraftId(Number(created.data.id));
    return created.data.id;
  };

  const handleSaveDraft = async () => {
    setIsSubmitting(true);
    setErrorMsg('');
    setNotice('');
    setServerErrors({});
    try {
      // The server validates materialized drafts; incomplete local state
      // remains persisted when a write is refused. Retain the saved id
      // for retry-safe updates.
      await materializeDraft();
      setNotice(t('wizard.saved'));
    } catch (err: unknown) {
      applyServerError(err, t('wizard.saveFailed'));
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

    // Publication requires trimmed nonempty English source on top of the
    // step checks; incomplete drafts stay saveable via Save Draft instead.
    const publishCheck = tourPublishSourceSchema.safeParse({
      title: formData.title,
      description: formData.description,
    });
    if (!publishCheck.success) {
      const fieldErrors: Record<string, string> = {};
      publishCheck.error.issues.forEach((issue) => {
        fieldErrors[issue.path.join('.')] = issue.message;
      });
      setValidationErrors(fieldErrors);
      return;
    }

    setIsSubmitting(true);
    setErrorMsg('');
    setNotice('');
    setServerErrors({});
    try {
      // Materialize a draft first, then submit through the guarded endpoint
      // (pricing/cover/lifecycle checks stay server-side). Creation never
      // publishes: supplied status is prohibited by the API. A failed
      // submit keeps the materialized id so the next retry updates it.
      const id = await materializeDraft();
      await submitTour(id);
      setNotice(t('wizard.submitted'));
      setCreatedDraftId(null);
      reset();
      router.push(`/${locale}/partner`);
    } catch (err: unknown) {
      applyServerError(err, t('wizard.submitFailed'));
    } finally {
      setIsSubmitting(false);
    }
  };

  // Translated visible labels for the stored enum codes; stored values
  // stay codes (walking/hour/easy) and absent difficulty stays blank.
  const categoryLabels: Record<string, string> = {
    walking: t('form.walking'),
    food: t('form.food'),
    adventure: t('form.adventure'),
    cultural: t('form.cultural'),
    nature: t('form.nature'),
  };
  const durationUnitLabels: Record<string, string> = {
    hour: t('form.hours'),
    day: t('form.days'),
  };
  const difficultyLabels: Record<string, string> = {
    easy: t('form.easy'),
    moderate: t('form.moderate'),
    challenging: t('form.challenging'),
  };

  const commaListProps = (key: 'highlights' | 'inclusions' | 'exclusions' | 'important_information') => ({
    value: formData[key].join(', '),
    onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
      updateField(key, e.target.value.split(',').map((s) => s.trim()).filter(Boolean)),
  });

  // Combined field messages: localized client messages plus localized
  // server messages, including indexed descendants (e.g. `highlights.0`)
  // under their shared source control.
  const controlErrors = (base: string): string[] => [
    ...(validationErrors[base] ? [formatError(validationErrors[base])] : []),
    ...pickFieldErrors(localizedServerErrors, base),
  ];

  const renderControlErrors = (base: string) =>
    controlErrors(base).map((message, index) => (
      <p key={`${base}-${index}`} className="text-xs text-red-500 mt-1" role="alert">
        {message}
      </p>
    ));

  const clientItineraryErrors = Object.fromEntries(
    Object.entries(validationErrors)
      .filter(([path]) => path.startsWith('itinerary'))
      .map(([path, key]) => [path, formatError(key)])
  );
  const serverItineraryErrors = Object.fromEntries(
    Object.entries(localizedServerErrors).filter(([path]) => path.startsWith('itinerary'))
  );
  const itineraryErrors = { ...clientItineraryErrors, ...serverItineraryErrors };

  const stepContent = () => {
    switch (currentStep) {
      case 'details':
        return (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">{t('form.sourceLanguageNotice')}</p>
            <div className="space-y-1">
              <Label htmlFor="title">{t('form.title')}</Label>
              {/* No native maxlength: it counts UTF-16 units and would block the
                  schema/server-accepted 120 code-point boundary. The bound stays
                  enforced by validation with localized feedback. */}
              <Input
                id="title"
                value={formData.title}
                onChange={(e) => updateField('title', e.target.value)}
                placeholder={t('form.titlePlaceholder')}
                disabled={isSubmitting}
                aria-invalid={validationErrors['title'] || serverErrors['title'] ? true : undefined}
              />
              {renderControlErrors('title')}
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
                aria-invalid={validationErrors['description'] || serverErrors['description'] ? true : undefined}
              />
              {renderControlErrors('description')}
            </div>
            <div className="space-y-1">
              <Label htmlFor="highlights">{t('form.highlights')}</Label>
              <Input
                id="highlights"
                {...commaListProps('highlights')}
                placeholder={t('form.highlightsPlaceholder')}
                disabled={isSubmitting}
              />
              {renderControlErrors('highlights')}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label htmlFor="inclusions">{t('form.inclusions')}</Label>
                <Input
                  id="inclusions"
                  {...commaListProps('inclusions')}
                  placeholder={t('form.inclusionsPlaceholder')}
                  disabled={isSubmitting}
                />
                {renderControlErrors('inclusions')}
              </div>
              <div className="space-y-1">
                <Label htmlFor="exclusions">{t('form.exclusions')}</Label>
                <Input
                  id="exclusions"
                  {...commaListProps('exclusions')}
                  placeholder={t('form.exclusionsPlaceholder')}
                  disabled={isSubmitting}
                />
                {renderControlErrors('exclusions')}
              </div>
            </div>
            <div className="space-y-1">
              <Label htmlFor="important_information">{t('form.importantInformation')}</Label>
              <Input
                id="important_information"
                {...commaListProps('important_information')}
                placeholder={t('form.importantInformationPlaceholder')}
                disabled={isSubmitting}
              />
              {renderControlErrors('important_information')}
            </div>
            <ItineraryEditor value={formData.itinerary} onChange={(days) => updateField('itinerary', days)} disabled={isSubmitting} errors={itineraryErrors} />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <Label id="category-label">{t('form.category')}</Label>
                <Select
                  value={formData.category}
                  onValueChange={(v) => updateField('category', v)}
                  disabled={isSubmitting}
                >
                  <SelectTrigger aria-labelledby="category-label"><SelectValue placeholder={t('form.categoryPlaceholder')} displayValue={formData.category ? categoryLabels[formData.category] : undefined} /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="walking">{t('form.walking')}</SelectItem>
                    <SelectItem value="food">{t('form.food')}</SelectItem>
                    <SelectItem value="adventure">{t('form.adventure')}</SelectItem>
                    <SelectItem value="cultural">{t('form.cultural')}</SelectItem>
                    <SelectItem value="nature">{t('form.nature')}</SelectItem>
                  </SelectContent>
                </Select>
                {validationErrors['category'] && (
                  <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['category'])}</p>
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
                  <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['destination'])}</p>
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
                  <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['duration_value'])}</p>
                )}
              </div>
              <div className="space-y-1">
                <Label id="duration_unit-label">{t('form.durationUnit')}</Label>
                <Select
                  value={formData.duration_unit}
                  onValueChange={(v) => updateField('duration_unit', v as 'hour' | 'day')}
                  disabled={isSubmitting}
                >
                  <SelectTrigger aria-labelledby="duration_unit-label"><SelectValue displayValue={durationUnitLabels[formData.duration_unit]} /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hour">{t('form.hours')}</SelectItem>
                    <SelectItem value="day">{t('form.days')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1">
              <Label id="difficulty_level-label">{t('form.difficultyLevel')}</Label>
              <Select
                value={formData.difficulty_level ?? ''}
                onValueChange={(v) => updateField('difficulty_level', v as 'easy' | 'moderate' | 'challenging')}
                disabled={isSubmitting}
              >
                <SelectTrigger aria-labelledby="difficulty_level-label"><SelectValue displayValue={formData.difficulty_level ? difficultyLabels[formData.difficulty_level] : ''} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="easy">{t('form.easy')}</SelectItem>
                  <SelectItem value="moderate">{t('form.moderate')}</SelectItem>
                  <SelectItem value="challenging">{t('form.challenging')}</SelectItem>
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
              {renderControlErrors('meeting_point')}
            </div>
            <div className="space-y-1">
              <Label htmlFor="cancellation_policy">{t('form.cancellationPolicy')}</Label>
              <Textarea
                id="cancellation_policy"
                value={formData.cancellation_policy}
                onChange={(e) => updateField('cancellation_policy', e.target.value)}
                placeholder={t('form.cancellationPolicyPlaceholder')}
                rows={3}
                disabled={isSubmitting}
              />
              {renderControlErrors('cancellation_policy')}
            </div>
            <div className="space-y-1">
              <Label htmlFor="guide_languages">{t('form.guideLanguageCodes')}</Label>
              <Input
                id="guide_languages"
                value={formData.languages.join(', ')}
                onChange={(e) => updateField('languages', e.target.value.split(',').map((code) => code.trim().toLowerCase()))}
                onBlur={() => updateField('languages', formData.languages.filter(Boolean))}
                placeholder="de, en, es"
                aria-describedby="guide-languages-hint"
                disabled={isSubmitting}
              />
              <p id="guide-languages-hint" className="text-xs text-gray-500">{t('form.guideLanguageCodesHint')}</p>
            </div>
          </div>
        );
      case 'media':
        return (
          <div className="space-y-4">
            <ImageUploader media={formData.media} onChange={setMedia} disabled={isSubmitting} />
            {validationErrors['media'] && (
              <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['media'])}</p>
            )}
          </div>
        );
      case 'pricing':
        return (
          <div className="space-y-4">
            <PricingTierForm disabled={isSubmitting} />
            {validationErrors['pricing_tiers'] && (
              <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['pricing_tiers'])}</p>
            )}
          </div>
        );
      case 'availability':
        return (
          <div className="space-y-4">
            <AvailabilityCalendar disabled={isSubmitting} />
            {validationErrors['availability_rules'] && (
              <p className="text-xs text-red-500 mt-1" role="alert">{formatError(validationErrors['availability_rules'])}</p>
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
              <dd className="capitalize">{formData.difficulty_level ?? '—'}</dd>
              <dt className="text-gray-500">{t('form.meetingPoint')}:</dt>
              <dd>{formData.meeting_point || '—'}</dd>
              <dt className="text-gray-500">{t('form.pricingTiers')}:</dt>
              <dd>{formData.pricing_tiers.length} defined</dd>
              <dt className="text-gray-500">{t('form.recurringSchedule')}:</dt>
              <dd>{formData.availability_rules.length} defined</dd>
            </dl>
            <TourContentPreview
              title={formData.title}
              description={formData.description}
              itinerary={formData.itinerary}
              media={formData.media}
              highlights={formData.highlights}
              inclusions={formData.inclusions}
              exclusions={formData.exclusions}
              important_information={formData.important_information}
              meeting_point={formData.meeting_point || null}
              cancellation_policy={formData.cancellation_policy || null}
            />
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

      {(errorMsg || summaryEntries.length > 0) && (
        <div ref={errorSummaryRef} tabIndex={-1} className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm" role="alert" aria-live="assertive">
          <p className="font-semibold">{t('form.errorSummary')}</p>
          {errorMsg && <p>{errorMsg}</p>}
          {summaryEntries.length > 0 && (
            <ul className="mt-1 list-inside list-disc">
              {summaryEntries.map(({ path, message }) => (
                <li key={path}>{message}</li>
              ))}
            </ul>
          )}
        </div>
      )}
      {notice && (
        <div className="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm" role="status" aria-live="polite">
          {notice}
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
