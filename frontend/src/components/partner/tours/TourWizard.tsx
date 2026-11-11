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
import { BookingOptionsForm } from './BookingOptionsForm';
import { AvailabilityCalendar } from './AvailabilityCalendar';
import { PartnerRichTextEditor } from './PartnerRichTextEditor';
import { createTour, updateTour, submitTour } from '@/lib/api/partner';
import { ValidationError } from '@/lib/api/client';
import { toMinorUnits } from '@/lib/utils';
import {
  tourSourceStepSchema,
  tourBasicDetailsSchema,
  tourMediaStepSchema,
  tourPricingStepSchema,
  tourAvailabilityStepSchema,
  tourPublishSourceSchema,
  mapServerErrorsToFields,
  localizeServerFieldErrors,
  pickFieldErrors,
  getSubmittedOptionRows,
} from '@/lib/validators/partner';
import type { WizardStep, BookingOptionFormInput } from '@/types/tour';
import { SOURCE_LOCALES } from '@/types/tour';
import type {
  PartnerTourWritePayload,
  PartnerBookingOptionInput,
  ContentSourceLocale,
  EnglishSourceInput,
} from '@/lib/api/types';

const steps: { id: WizardStep; labelKey: string }[] = [
  { id: 'source', labelKey: 'wizard.source' },
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
    adoptSavedOptionIds,
    addBookingOption,
    removeBookingOption,
    updateBookingOption,
  } = useTourWizardStore();

  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [errorMsg, setErrorMsg] = useState('');
  const [notice, setNotice] = useState('');
  // Successfully materialized draft id: retries update THIS tour instead of
  // creating duplicates after a partial success (created, submit failed).
  const [createdDraftId, setCreatedDraftId] = useState<number | null>(null);
  // FR021/US4: a pending over-long English alt draft stays local inside the
  // media step and must block Next/save until fixed or discarded.
  const [mediaAltValid, setMediaAltValid] = useState(true);
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
    if (step === 'source') {
      result = tourSourceStepSchema.safeParse(formData);
    } else if (step === 'details') {
      result = tourBasicDetailsSchema.safeParse(formData);
    } else if (step === 'media') {
      result = tourMediaStepSchema.safeParse(formData);
    } else if (step === 'pricing') {
      // Empty add-row placeholders carry no data and never block/submit.
      result = tourPricingStepSchema.safeParse({
        ...formData,
        options: getSubmittedOptionRows(formData.options),
      });
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
    // A pending invalid alt draft never reaches formData; hold Next so it
    // cannot be silently discarded by navigating away.
    if (currentStep === 'media' && !mediaAltValid) {
      setValidationErrors({ media: 'partner.tours.errors.altTextMax' });
      return;
    }
    if (validateStep(currentStep)) {
      // Leaving the media step unmounts its uploader, discarding any drafts.
      if (currentStep === 'media') {
        setMediaAltValid(true);
      }
      if (currentStepIdx < steps.length - 1) {
        setStep(steps[currentStepIdx + 1].id);
      }
    }
  };

  const handleBack = () => {
    if (currentStepIdx > 0) {
      // Going back unmounts the media uploader, discarding its drafts.
      if (currentStep === 'media') {
        setMediaAltValid(true);
      }
      setStep(steps[currentStepIdx - 1].id);
    }
  };

  // Canonical authored source: the fields live in the language selected at
  // the first screen, so the created tour declares that `source_locale` and
  // the nested `translations[<source>]` carries the content. Updates never
  // resubmit source_locale (immutable after creation); pricing, options,
  // availability, group size and media are preserved in every submission.
  // US4 truthful facts: an untouched unknown difficulty (restored null /
  // missing) is omitted — never invented as easy, never sent as null —
  // while a newly selected supported code travels as its code. Media carry
  // alt_text presence exactly (omitted stays absent, explicit null clears),
  // preserving URL identity, order, and cover flags.
  const sourceLocale: ContentSourceLocale = formData.source_locale ?? 'en';
  // The authored source fields, keyed by their own writing language —
  // created as the selected original language and maintained there.
  const sourcePatch: EnglishSourceInput = {
    title: formData.title,
    description: formData.description,
    highlights: formData.highlights,
    inclusions: formData.inclusions,
    exclusions: formData.exclusions,
    meeting_point: formData.meeting_point || null,
    cancellation_policy: formData.cancellation_policy || null,
    itinerary: formData.itinerary,
    important_information: formData.important_information,
  };
  const sourceTranslation: { en?: EnglishSourceInput; es?: EnglishSourceInput; it?: EnglishSourceInput } =
    sourceLocale === 'es'
      ? { es: sourcePatch }
      : sourceLocale === 'it'
        ? { it: sourcePatch }
        : { en: sourcePatch };

  // Authored booking options: an empty add-row placeholder is excluded.
  const submittedOptionRows = getSubmittedOptionRows(formData.options);
  const toOptionPayload = (row: BookingOptionFormInput): PartnerBookingOptionInput => ({
    // Persistent ids round-trip for edits; new rows arrive without one.
    ...(row.server_id !== undefined ? { id: row.server_id } : {}),
    title: row.title.trim(),
    pickup_instructions: row.pickup_instructions.trim() === '' ? null : row.pickup_instructions.trim(),
    guide_languages: [...new Set(row.guide_languages.map((code) => code.trim().toLowerCase()).filter(Boolean))],
    // UI shows currency major units; the contract stores integer cents.
    price_amount: toMinorUnits(row.price),
    group_size_max: row.capacity.trim() === '' ? null : Number(row.capacity),
    start_time: row.start_time.trim() === '' ? null : row.start_time.trim(),
    weekdays: row.weekdays.length > 0 ? [...new Set(row.weekdays)].sort((a, b) => a - b) : null,
  });

  const buildPayload = ({
    includeSourceLocale,
    sendOptionsUnconditionally = false,
  }: { includeSourceLocale: boolean; sendOptionsUnconditionally?: boolean } = { includeSourceLocale: true }): PartnerTourWritePayload => ({
    title: formData.title,
    description: formData.description,
    category: formData.category,
    destination: formData.destination,
    duration_value: Number(formData.duration_value) || 0,
    duration_unit: formData.duration_unit,
    ...(includeSourceLocale && formData.source_locale !== null ? { source_locale: formData.source_locale } : {}),
    ...(formData.difficulty_level ? { difficulty_level: formData.difficulty_level } : {}),
    meeting_point: formData.meeting_point || null,
    guide_languages: [...new Set(formData.languages.map((code) => code.trim().toLowerCase()).filter(Boolean))],
    cancellation_policy: formData.cancellation_policy || null,
    itinerary: formData.itinerary,
    translations: sourceTranslation,
    // Creation: only real rows travel (no options stays absent for legacy
    // products). Retries update the retained draft and always send the
    // full array — an emptied list retires rather than preserving rows.
    ...(sendOptionsUnconditionally || submittedOptionRows.length > 0
      ? { options: submittedOptionRows.map(toOptionPayload) }
      : {}),
    media: formData.media.map((item) => ({
      url: item.url,
      is_cover: item.is_cover,
      ...('alt_text' in item ? { alt_text: item.alt_text } : {}),
    })),
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
  // retries after a partial success never duplicate the tour. The original
  // writing language only travels on creation (it is immutable afterwards);
  // update paths send the full options array so an emptied list retires.
  // The owned write echoes the stored options — their persistent ids are
  // adopted onto the local rows so a later update EDITS those rows (never
  // duplicating them as id-less creates).
  const materializeDraft = async () => {
    const payload = buildPayload({
      includeSourceLocale: createdDraftId === null,
      sendOptionsUnconditionally: createdDraftId !== null,
    });
    if (createdDraftId !== null) {
      const updated = await updateTour(createdDraftId, payload);
      adoptSavedOptionIds(updated.data?.options ?? null);
      return updated.data.id;
    }
    const created = await createTour(payload);
    setCreatedDraftId(Number(created.data.id));
    adoptSavedOptionIds(created.data?.options ?? null);
    return created.data.id;
  };

  const handleSaveDraft = async () => {
    // Never partially save while a bad alt draft is pending: it was never
    // emitted into the payload and would be silently discarded.
    if (!mediaAltValid) {
      setValidationErrors({ media: 'partner.tours.errors.altTextMax' });
      return;
    }
    // The original writing language gates everything: a tour cannot be
    // materialized before the first screen is answered.
    if (formData.source_locale === null) {
      setValidationErrors({ source_locale: 'partner.tours.errors.sourceLocaleRequired' });
      return;
    }
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
    if (!mediaAltValid) {
      setValidationErrors({ media: 'partner.tours.errors.altTextMax' });
      return;
    }
    if (
      !validateStep('source') ||
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
      <p key={`${base}-${index}`} className="text-xs text-red-600 mt-1" role="alert">
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
      case 'source': {
        const contentAuthored =
          formData.title.trim() !== '' ||
          formData.description.trim() !== '' ||
          formData.highlights.length > 0 ||
          formData.inclusions.length > 0 ||
          formData.exclusions.length > 0 ||
          formData.important_information.length > 0 ||
          formData.meeting_point.trim() !== '' ||
          formData.cancellation_policy.trim() !== '' ||
          formData.itinerary.length > 0;
        // A saved draft retains its source even when the author clears every
        // field. An older unanswered local draft still needs a source choice.
        const sourceLocaleLocked =
          formData.source_locale !== null && (createdDraftId !== null || contentAuthored);
        return (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">{t('form.sourceLanguageHint')}</p>
            <div role="radiogroup" aria-label={t('form.sourceLanguageLabel')} className="grid gap-3 sm:grid-cols-3">
              {SOURCE_LOCALES.map((code) => (
                <button
                  key={code}
                  type="button"
                  role="radio"
                  aria-checked={formData.source_locale === code}
                  onClick={() => updateField('source_locale', code)}
                  disabled={isSubmitting || (sourceLocaleLocked && formData.source_locale !== code)}
                  className={`rounded-xl border p-4 text-left transition-colors ${
                    formData.source_locale === code
                      ? 'border-[#FFB800] bg-[#FFB800]/10'
                      : 'border-gray-200 hover:border-gray-300'
                  } disabled:cursor-not-allowed disabled:opacity-60`}
                >
                  <span className="block text-sm font-semibold text-[#0A2540]">
                    {t(`form.sourceLanguageLabels.${code}`)}
                  </span>
                  <span className="mt-1 block text-xs text-gray-500">
                    {t(`form.sourceLanguageNames.${code}`)}
                  </span>
                </button>
              ))}
            </div>
            {sourceLocaleLocked && (
              <p className="text-sm text-muted-foreground">
                {t('form.sourceLanguageLockedNotice', { language: t(`form.sourceLanguageNames.${formData.source_locale}`) })}
              </p>
            )}
            {validationErrors['source_locale'] && (
              <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['source_locale'])}</p>
            )}
          </div>
        );
      }
      case 'details':
        return (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              {t('form.sourceLanguageNotice', { language: t(`form.sourceLanguageNames.${sourceLocale}`) })}
            </p>
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
              <PartnerRichTextEditor
                id="description"
                value={formData.description}
                onChange={(val) => updateField('description', val)}
                placeholder={t('form.descriptionPlaceholder')}
                disabled={isSubmitting}
                aria-invalid={validationErrors['description'] || serverErrors['description'] ? true : undefined}
                aria-label={t('form.description')}
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
                  <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['category'])}</p>
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
                  <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['destination'])}</p>
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
                  <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['duration_value'])}</p>
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
            <ImageUploader
              media={formData.media}
              onChange={setMedia}
              disabled={isSubmitting}
              title={formData.title}
              onValidityChange={setMediaAltValid}
            />
            {validationErrors['media'] && (
              <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['media'])}</p>
            )}
          </div>
        );
      case 'pricing':
        return (
          <div className="space-y-4">
            <PricingTierForm disabled={isSubmitting} />
            {validationErrors['pricing_tiers'] && (
              <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['pricing_tiers'])}</p>
            )}
            <BookingOptionsForm
              disabled={isSubmitting}
              validationErrors={validationErrors}
              serverErrors={localizedServerErrors}
              sourceLocale={sourceLocale}
              rows={formData.options}
              onAdd={addBookingOption}
              onRemove={removeBookingOption}
              onUpdate={updateBookingOption}
            />
            {validationErrors['options'] && (
              <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['options'])}</p>
            )}
          </div>
        );
      case 'availability':
        return (
          <div className="space-y-4">
            <AvailabilityCalendar disabled={isSubmitting} />
            {validationErrors['availability_rules'] && (
              <p className="text-xs text-red-600 mt-1" role="alert">{formatError(validationErrors['availability_rules'])}</p>
            )}
          </div>
        );
      case 'review':
        return (
          <div className="space-y-4">
            <h3 className="font-semibold text-[#0A2540]">{t('wizard.review')} &amp; {t('wizard.submit')}</h3>
            <dl className="grid grid-cols-[140px_1fr] gap-y-2 text-sm">
              <dt className="text-gray-500">{t('form.sourceLanguageLabel')}:</dt>
              <dd>{t(`form.sourceLanguageNames.${sourceLocale}`)}</dd>
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
              <dt className="text-gray-500">{t('form.bookingOptions')}:</dt>
              <dd>{submittedOptionRows.length} defined</dd>
              <dt className="text-gray-500">{t('form.recurringSchedule')}:</dt>
              <dd>{formData.availability_rules.length} defined</dd>
            </dl>
            <TourContentPreview
              sourceLocale={sourceLocale}
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
      <h1 className="mb-6 text-2xl font-semibold text-bookly-navy">{t('createTour')}</h1>
      {/* Progress bar */}
      <div className="mb-8">
        <div className="flex items-center gap-2 mb-2">
          {steps.map((step, idx) => (
            <div key={step.id} className="flex min-w-0 items-center gap-2 flex-1">
              <div
                className={`w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-xs font-semibold ${
                  idx <= currentStepIdx
                    ? 'bg-[#FFB800] text-[#0A2540]'
                    : 'bg-gray-100 text-gray-400'
                }`}
              >
                {idx + 1}
              </div>
              <span title={t(step.labelKey)} className="hidden truncate sm:inline text-xs font-medium text-gray-600">{t(step.labelKey)}</span>
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
        <h2 className="mb-4 text-xl font-semibold text-bookly-navy">{t(steps[currentStepIdx].labelKey)}</h2>
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
            disabled={isSubmitting || !mediaAltValid}
          >
            <Save className="w-4 h-4 mr-1" />
            {isSubmitting ? t('wizard.saving') : t('wizard.saveDraft')}
          </Button>
          {currentStep === 'review' ? (
            <Button
              onClick={handleSubmitReview}
              disabled={isSubmitting || !mediaAltValid}
              className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold"
            >
              {isSubmitting ? t('wizard.submitting') : t('wizard.submit')}
              <ChevronRight className="w-4 h-4 ml-1" />
            </Button>
          ) : (
            <Button
              onClick={handleNext}
              disabled={isSubmitting || (currentStep === 'media' && !mediaAltValid)}
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
