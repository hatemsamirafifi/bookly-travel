'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import Link from 'next/link';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import type { ContentGovernance, TourRevisionFieldRef, TourRevisionState } from '@/lib/api/types';

const REVISION_STATES: TourRevisionState[] = ['draft', 'proposed', 'approved', 'rejected', 'withdrawn', 'superseded'];

function asRevisionState(state: string | null | undefined): TourRevisionState | null {
  return REVISION_STATES.includes(state as TourRevisionState) ? (state as TourRevisionState) : null;
}

/** Contexts that answer with the exact submitted candidate; the initial
 *  context answers with the observed generation epoch instead. The correction
 *  context is treated like the published one (a candidate link), and the
 *  backend keeps the authority to refuse with a localized error when its
 *  contract differs — the UI never invents an approval. */
function needsSubmittedCandidate(context: string): boolean {
  return context !== 'initial_review';
}

function FieldRefs({ refs }: { refs: TourRevisionFieldRef[] }) {
  const t = useTranslations('partner.tours.revision');
  if (refs.length === 0) {
    return <p className="text-xs text-text-muted">{t('requestFieldsEmpty')}</p>;
  }
  return (
    <ul className="mt-1 space-y-1 text-xs text-text-muted">
      {refs.map((ref, index) => (
        <li key={`${ref.path}-${index}`} className="flex flex-wrap items-center gap-2">
          <code className="rounded bg-surface-alt px-1.5 py-0.5 text-xs text-primary">{ref.path}</code>
          {ref.note ? <span>{ref.note}</span> : null}
        </li>
      ))}
    </ul>
  );
}

interface OpenRequestItemProps {
  requestId: number;
  reason: string;
  fieldRefs: TourRevisionFieldRef[];
  respondsWithRevision: boolean;
  canRespond: boolean;
  responding: boolean;
  onRespond: (requestId: number, responseNote: string) => void;
}

function OpenRequestItem({ requestId, reason, fieldRefs, respondsWithRevision, canRespond, responding, onRespond }: OpenRequestItemProps) {
  const t = useTranslations('partner.tours.revision');
  const [note, setNote] = useState('');
  return (
    <li className="rounded-lg border border-border bg-surface p-4 space-y-2">
      <p className="text-sm text-primary">
        <span className="font-semibold">{`${t('requestReason')}: `}</span>
        {reason}
      </p>
      <div>
        <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">{t('requestFields')}</p>
        <FieldRefs refs={fieldRefs} />
      </div>
      <div className="space-y-1">
        <Label htmlFor={`revision-response-note-${requestId}`}>{t('responseNote')}</Label>
        <Textarea
          id={`revision-response-note-${requestId}`}
          value={note}
          onChange={(e) => setNote(e.target.value)}
          placeholder={t('responseNotePlaceholder')}
          rows={2}
          maxLength={2000}
        />
      </div>
      {respondsWithRevision && !canRespond && (
        <p className="text-xs text-text-muted">{t('respondNeedsSubmittedRevision')}</p>
      )}
      <Button
        type="button"
        size="sm"
        onClick={() => {
          if (respondsWithRevision && !canRespond) return;
          onRespond(requestId, note);
        }}
        disabled={respondsWithRevision && !canRespond}
        loading={responding}
      >
        {t('respond')}
      </Button>
    </li>
  );
}

export interface TourRevisionPanelProps {
  /** The owned governance object read from the tour (never null for a
   *  published tour; the page fails closed before rendering otherwise). */
  governance: ContentGovernance;
  isPublished: boolean;
  /** Live public page URL for the approved-content preview; proposed
   *  revisions intentionally have no public route. */
  publicUrl?: string | null;
  /** The candidate currently loaded in the editor, if any. */
  loadedCandidateId: number | null;
  loadedCandidateState: TourRevisionState | null;
  /** Submitted (proposed) candidate id linkable to published responses. */
  linkableCandidateId: number | null;
  changeSummary: string;
  changeSummaryError?: string;
  onChangeSummary: (value: string) => void;
  onSaveContent: () => void;
  onOperationalSave: () => void;
  onSubmitCandidate: () => void;
  onWithdraw: () => void;
  onRespond: (requestId: number, responseNote: string) => void;
  savingContent: boolean;
  savingOperational: boolean;
  submitting: boolean;
  withdrawing: boolean;
  responding: boolean;
  disabled?: boolean;
}

/**
 * EGM-003 revision workspace for the partner tour editor. Presentational:
 * the edit page owns the state, payload builders and HTTP calls. It shows
 * the approved public content, the current proposal, open administrator
 * change requests with mandatory reasons and field references, and the two
 * explicit save controls (content revision vs. operational-only) required
 * for mixed dirty fields.
 */
export function TourRevisionPanel({
  governance,
  isPublished,
  publicUrl = null,
  loadedCandidateId,
  loadedCandidateState,
  linkableCandidateId,
  changeSummary,
  changeSummaryError,
  onChangeSummary,
  onSaveContent,
  onOperationalSave,
  onSubmitCandidate,
  onWithdraw,
  onRespond,
  savingContent,
  savingOperational,
  submitting,
  withdrawing,
  responding,
  disabled = false,
}: TourRevisionPanelProps) {
  const t = useTranslations('partner.tours.revision');
  const { approved_revision: approvedRevision, active_candidate: activeCandidate, open_requests: openRequests } = governance;
  const candidateState = asRevisionState(activeCandidate?.state);
  const loadedState = asRevisionState(loadedCandidateState);
  const latestRevision = governance.latest_revision;
  const latestState = asRevisionState(latestRevision?.state);

  return (
    <section aria-labelledby="tour-revision-heading" className="rounded-xl border border-border bg-surface p-5 space-y-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="tour-revision-heading" className="text-base font-bold text-primary">{t('heading')}</h2>
        {isPublished && publicUrl && (
          <Link
            href={publicUrl}
            className="text-sm font-semibold rounded-lg transition-colors px-3 py-1.5 border border-border text-primary hover:bg-surface-alt"
          >
            {t('viewLivePage')}
          </Link>
        )}
      </div>

      {isPublished && <p className="text-sm text-text-muted">{t('publicNotice')}</p>}

      <div className="grid gap-2 text-sm sm:grid-cols-2">
        {approvedRevision && (
          <div className="rounded-lg border border-trust/30 bg-trust/10 px-3 py-2">
            <p className="font-semibold text-primary">{t('approvedRevision')}</p>
            <p className="text-primary">#{approvedRevision.revision_number}</p>
          </div>
        )}
        {activeCandidate && (
          <div className="rounded-lg border border-accent/30 bg-accent/10 px-3 py-2">
            <p className="font-semibold text-primary">{t('activeRevision')}</p>
            <p className="text-primary">
              #{activeCandidate.revision_number} · {candidateState ? t(`revisionState.${candidateState}`) : activeCandidate.state}
            </p>
            {activeCandidate.change_summary ? (
              <p className="text-xs text-text-muted">{activeCandidate.change_summary}</p>
            ) : null}
          </div>
        )}
      </div>

      {!activeCandidate && latestRevision && latestState && ['rejected', 'withdrawn', 'superseded'].includes(latestState) && (
        <div className="rounded-lg border border-border bg-surface-alt px-3 py-2 text-sm text-primary">
          <p className="font-semibold">{t('latestReview')}</p>
          <p>#{latestRevision.revision_number} · <span>{t(`revisionState.${latestState}`)}</span></p>
        </div>
      )}

      {isPublished && (
        <div className="space-y-3 border-t border-border pt-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="revision-change-summary">{t('changeSummary')}</Label>
            <Input
              id="revision-change-summary"
              type="text"
              value={changeSummary}
              onChange={(e) => onChangeSummary(e.target.value)}
              placeholder={t('changeSummaryPlaceholder')}
              maxLength={500}
              aria-invalid={Boolean(changeSummaryError)}
              aria-describedby={changeSummaryError ? 'revision-change-summary-error' : undefined}
            />
            {changeSummaryError && (
              <p id="revision-change-summary-error" role="alert" className="text-xs font-medium text-error">
                {changeSummaryError}
              </p>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={onSaveContent} loading={savingContent} disabled={disabled}>
              {t('saveContent')}
            </Button>
            <Button type="button" variant="outline" onClick={onOperationalSave} loading={savingOperational} disabled={disabled}>
              {t('saveOperational')}
            </Button>
            {loadedCandidateId !== null && loadedState === 'draft' && (
              <Button type="button" variant="outline" onClick={onSubmitCandidate} loading={submitting} disabled={disabled}>
                {t('submit')}
              </Button>
            )}
            {loadedCandidateId !== null && (
              <Button type="button" variant="ghost" onClick={onWithdraw} loading={withdrawing} disabled={disabled}>
                {t('withdraw')}
              </Button>
            )}
          </div>
        </div>
      )}

      <div className="space-y-2 border-t border-border pt-4">
        <h3 className="text-sm font-bold text-primary">{t('openRequests')}</h3>
        {openRequests.length === 0 ? (
          <p className="text-sm text-text-muted">{t('openRequestsEmpty')}</p>
        ) : (
          <ul className="space-y-3">
            {openRequests.map((request) => {
              const responds = needsSubmittedCandidate(request.context);
              return (
                <OpenRequestItem
                  key={request.id}
                  requestId={request.id}
                  reason={request.reason}
                  fieldRefs={request.field_refs}
                  respondsWithRevision={responds}
                  canRespond={linkableCandidateId !== null}
                  responding={responding}
                  onRespond={onRespond}
                />
              );
            })}
          </ul>
        )}
      </div>
    </section>
  );
}
