import { fireEvent, render, screen } from '@testing-library/react';
import { TourRevisionPanel } from '../TourRevisionPanel';
import type { ContentGovernance } from '@/lib/api/types';
import type { TourRevisionState } from '@/lib/api/types';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../../messages/en.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

const englishRevision = en.partner.tours.revision;

const publishedGovernance: ContentGovernance = {
  source_locale: 'en',
  generation_epoch: 3,
  approved_revision: {
    id: 12,
    revision_number: 3,
    kind: 'legacy_baseline',
    state: 'approved',
    payload_hash: 'hash-12',
    created_at: '2026-11-01T00:00:00Z',
  },
  active_candidate: null,
  open_requests: [],
};

const draftCandidateGovernance: ContentGovernance = {
  ...publishedGovernance,
  active_candidate: {
    id: 15,
    revision_number: 4,
    kind: 'partner_edit',
    state: 'draft',
    payload_hash: 'hash-15',
    base_revision_id: 12,
    submitted_at: null,
    change_summary: 'Corrected the meeting point',
  },
};

const proposedCandidateGovernance: ContentGovernance = {
  ...publishedGovernance,
  active_candidate: {
    id: 15,
    revision_number: 4,
    kind: 'partner_edit',
    state: 'proposed',
    payload_hash: 'hash-15',
    base_revision_id: 12,
    submitted_at: '2026-11-12T00:00:00Z',
    change_summary: 'Corrected the meeting point',
  },
};

const governanceWithOpenRequests: ContentGovernance = {
  ...publishedGovernance,
  open_requests: [
    {
      id: 9,
      context: 'published_review',
      state: 'open',
      reason: 'Please clarify the meeting point for travelers.',
      field_refs: [{ path: 'source.meeting_point', note: 'Add the exact gate number.' }],
      created_at: '2026-11-12T00:00:00Z',
    },
    {
      id: 11,
      context: 'initial_review',
      state: 'open',
      reason: 'Describe the itinerary.',
      field_refs: [{ path: 'source.itinerary' }],
      created_at: '2026-11-12T00:00:00Z',
    },
  ],
};

function baseProps(overrides: Record<string, unknown> = {}) {
  return {
    governance: publishedGovernance,
    isPublished: true,
    publicUrl: '/en/tours/demo-tour',
    loadedCandidateId: null as number | null,
    loadedCandidateState: null as TourRevisionState | null,
    linkableCandidateId: null as number | null,
    changeSummary: '',
    onChangeSummary: jest.fn(),
    onSaveContent: jest.fn(),
    onOperationalSave: jest.fn(),
    onSubmitCandidate: jest.fn(),
    onWithdraw: jest.fn(),
    onRespond: jest.fn(),
    savingContent: false,
    savingOperational: false,
    submitting: false,
    withdrawing: false,
    responding: false,
    ...overrides,
  };
}

type PanelProps = ReturnType<typeof baseProps>;

function renderPanel(props: PanelProps) {
  return render(
    <NextIntlClientProvider locale="en" messages={en}>
      <TourRevisionPanel {...props} />
    </NextIntlClientProvider>
  );
}

describe('TourRevisionPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('shows the persistent public-content notice, the approved revision and the live public link', () => {
    renderPanel(baseProps());

    expect(screen.getByText(englishRevision.heading)).toBeInTheDocument();
    expect(screen.getByText(englishRevision.publicNotice)).toBeInTheDocument();
    expect(screen.getByText(englishRevision.approvedRevision)).toBeInTheDocument();
    expect(screen.getByText('#3')).toBeInTheDocument();
    const liveLink = screen.getByRole('link', { name: englishRevision.viewLivePage });
    expect(liveLink).toHaveAttribute('href', '/en/tours/demo-tour');
    // No proposed public route is offered anywhere in the panel.
    expect(screen.queryByText(englishRevision.activeRevision)).not.toBeInTheDocument();
  });

  it('shows the active candidate state, number and change summary', () => {
    renderPanel(baseProps({ governance: draftCandidateGovernance }));

    expect(screen.getByText(englishRevision.activeRevision)).toBeInTheDocument();
    // The chip renders "№ · state" as one node; match the combined text.
    expect(screen.getByText(/#4 ·/)).toBeInTheDocument();
    expect(screen.getByText(/Draft \(not yet submitted\)/)).toBeInTheDocument();
    expect(screen.getByText('Corrected the meeting point')).toBeInTheDocument();
  });

  it('renders open change requests with the mandatory reason and field references', () => {
    renderPanel(baseProps({ governance: governanceWithOpenRequests }));

    expect(screen.getByText(englishRevision.openRequests)).toBeInTheDocument();
    expect(screen.getByText('Please clarify the meeting point for travelers.')).toBeInTheDocument();
    expect(screen.getByText('Add the exact gate number.')).toBeInTheDocument();
    // Wire field references render as the stored tier paths.
    expect(screen.getByText('source.meeting_point')).toBeInTheDocument();
    expect(screen.getByText('source.itinerary')).toBeInTheDocument();
    expect(screen.getByText('Describe the itinerary.')).toBeInTheDocument();
  });

  it('explains and disables the published-context response while no candidate is submitted', () => {
    const props = baseProps({ governance: governanceWithOpenRequests, linkableCandidateId: null });
    renderPanel(props);

    const respondButtons = screen.getAllByRole('button', { name: englishRevision.respond });
    expect(respondButtons).toHaveLength(2);
    // Only the published-context response is gated; the initial-context one
    // answers with the observed generation epoch instead of a revision.
    expect(respondButtons[0]).toBeDisabled();
    expect(respondButtons[1]).toBeEnabled();
    expect(screen.getByText(englishRevision.respondNeedsSubmittedRevision)).toBeInTheDocument();
    fireEvent.click(respondButtons[0]);
    expect(props.onRespond).not.toHaveBeenCalled();
  });

  it('enables the published-context response against the submitted candidate and forwards the note', () => {
    const props = baseProps({ governance: governanceWithOpenRequests, linkableCandidateId: 15 });
    renderPanel(props);

    const respondButtons = screen.getAllByRole('button', { name: englishRevision.respond });
    expect(respondButtons[0]).toBeEnabled();
    fireEvent.change(screen.getAllByLabelText(englishRevision.responseNote)[0], {
      target: { value: 'Meeting point corrected.' },
    });
    fireEvent.click(respondButtons[0]);
    expect(props.onRespond).toHaveBeenCalledWith(9, 'Meeting point corrected.');
  });

  it('forwards the typed response note for an initial-context request', () => {
    const props = baseProps({ governance: governanceWithOpenRequests, linkableCandidateId: null });
    renderPanel(props);

    fireEvent.change(screen.getAllByLabelText(englishRevision.responseNote)[1], {
      target: { value: 'Itinerary added.' },
    });
    fireEvent.click(screen.getAllByRole('button', { name: englishRevision.respond })[1]);
    expect(props.onRespond).toHaveBeenCalledWith(11, 'Itinerary added.');
  });

  it('binds the change-summary input to its callback', () => {
    const props = baseProps();
    renderPanel(props);

    fireEvent.change(screen.getByLabelText(englishRevision.changeSummary), {
      target: { value: 'Correct the meeting point' },
    });
    expect(props.onChangeSummary).toHaveBeenCalledWith('Correct the meeting point');
  });

  it('exposes the separate content-draft and operational-only save controls', () => {
    const props = baseProps();
    renderPanel(props);

    fireEvent.click(screen.getByRole('button', { name: englishRevision.saveContent }));
    fireEvent.click(screen.getByRole('button', { name: englishRevision.saveOperational }));
    expect(props.onSaveContent).toHaveBeenCalledTimes(1);
    expect(props.onOperationalSave).toHaveBeenCalledTimes(1);
  });

  it('offers submit and withdraw for a loaded draft candidate', () => {
    const props = baseProps({
      governance: draftCandidateGovernance,
      loadedCandidateId: 15,
      loadedCandidateState: 'draft',
    });
    renderPanel(props);

    const submit = screen.getByRole('button', { name: englishRevision.submit });
    const withdraw = screen.getByRole('button', { name: englishRevision.withdraw });
    expect(submit).toBeEnabled();
    expect(withdraw).toBeEnabled();
    fireEvent.click(submit);
    fireEvent.click(withdraw);
    expect(props.onSubmitCandidate).toHaveBeenCalledTimes(1);
    expect(props.onWithdraw).toHaveBeenCalledTimes(1);
  });

  it('shows the candidate loaded hint without offering submit once it is submitted', () => {
    renderPanel(baseProps({ governance: proposedCandidateGovernance, loadedCandidateId: 15, loadedCandidateState: 'proposed' }));

    expect(screen.queryByRole('button', { name: englishRevision.submit })).not.toBeInTheDocument();
    // A proposed candidate is at review; the panel still shows its state.
    expect(screen.getByText(/Under review/)).toBeInTheDocument();
  });

  it('disables actions while a mutation is in flight', () => {
    const props = baseProps({
      governance: draftCandidateGovernance,
      loadedCandidateId: 15,
      loadedCandidateState: 'draft',
      savingContent: true,
      submitting: true,
    });
    renderPanel(props);

    expect(screen.getByRole('button', { name: englishRevision.saveContent })).toBeDisabled();
    expect(screen.getByRole('button', { name: englishRevision.submit })).toBeDisabled();
  });

  it('hides the published-only actions for a never-public tour while keeping change requests', () => {
    renderPanel(baseProps({
      isPublished: false,
      publicUrl: null,
      governance: {
        ...governanceWithOpenRequests,
        approved_revision: null,
      },
    }));

    expect(screen.queryByText(englishRevision.publicNotice)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: englishRevision.saveContent })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: englishRevision.saveOperational })).not.toBeInTheDocument();
    expect(screen.queryByLabelText(englishRevision.changeSummary)).not.toBeInTheDocument();
    expect(screen.getByText(englishRevision.openRequests)).toBeInTheDocument();
  });
});


test.each(['rejected', 'withdrawn', 'superseded'] as const)('shows the latest %s review after refresh without treating it as an active candidate', (state) => {
  renderPanel(baseProps({ governance: {
    ...publishedGovernance,
    latest_revision: { id: 20, revision_number: 4, kind: 'partner_edit', state, base_revision_id: 12, payload_hash: 'last-hash' },
  } }));
  expect(screen.getByText(englishRevision.revisionState[state])).toBeVisible();
  expect(screen.queryByRole('button', { name: englishRevision.submit })).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: englishRevision.withdraw })).not.toBeInTheDocument();
  expect(screen.getByRole('link', { name: englishRevision.viewLivePage })).toHaveAttribute('href', '/en/tours/demo-tour');
});
