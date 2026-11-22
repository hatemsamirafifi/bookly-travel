<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Admin\Actions\ApproveTourRevisionAction;
use App\Domains\Admin\Actions\ProposeTourCorrectionAction;
use App\Domains\Admin\Actions\RejectTourRevisionAction;
use App\Domains\Admin\Actions\RequestTourChangesAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Filament\Resources\TourResource;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\TourRevisionRequest;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    // Relationship writes happen before the main update in Filament's save flow.
    protected ?bool $hasDatabaseTransactions = true;

    /** Lock before getState() can persist any relationship changes. */
    protected function beforeValidate(): void
    {
        $locked = app(TourPublicationGuardService::class)->lockTourForMutation(
            $this->getRecord(), $this->data['partner_id'] ?? null,
        );
        $actor = User::find(auth()->id());
        Gate::forUser($actor)->authorize('update', $locked);
        $this->record = $locked;
        $this->form->model($locked);

        // EGM-003: the RAW submitted state is inspected against the locked row
        // BEFORE getState()/relationship persistence. Read-only controls alone
        // are not an authorization control.
        app(TourPublicationGuardService::class)->assertGeneralFormRefusesContentWrites(
            $this->data,
            app(TourPublicationGuardService::class)->governedPresentationValues($locked),
        );

        $submittedStatus = isset($this->data['status']) && is_string($this->data['status'])
            ? $this->data['status']
            : null;

        app(TourPublicationGuardService::class)
            ->assertGeneralSaveKeepsCurrentStatus($submittedStatus, $locked);
    }

    /**
     * Filament v3 mounts relationship state through its own form; the tour form
     * renders no translation control, so nothing is provided here and the raw
     * guard already refused any injected one.
     */
    public function form(Form $form): Form
    {
        return TourResource::form($form);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_revision')
                ->label('Approve revision')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Approve Proposed Revision')
                ->modalSubmitActionLabel('Approve revision')
                ->modalContent(fn (Tour $record): View => $this->revisionComparisonView($record))
                ->form([
                    Forms\Components\Hidden::make('revision_id')
                        ->required()
                        ->rules(['integer', 'min:1']),
                    $this->actionFeedback(),
                ])
                ->fillForm(function (Tour $record): array {
                    $candidate = TourContentRevision::query()
                        ->where('tour_id', $record->id)
                        ->where('state', TourContentRevision::STATE_PROPOSED)
                        ->first();

                    return [
                        'revision_id' => $candidate?->id,
                    ];
                })
                ->visible(function (Tour $record): bool {
                    if ($record->status !== 'published') {
                        return false;
                    }

                    if (! app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours')) {
                        return false;
                    }

                    return TourContentRevision::query()
                        ->where('tour_id', $record->id)
                        ->where('state', TourContentRevision::STATE_PROPOSED)
                        ->exists();
                })
                ->action(function (array $data, Tour $record): void {
                    try {
                        app(ApproveTourRevisionAction::class)->execute(
                            auth()->user(),
                            $record,
                            (int) $data['revision_id'],
                        );

                        Notification::make()
                            ->title('Revision approved and published')
                            ->success()
                            ->send();
                    } catch (TourRevisionException|ValidationException $exception) {
                        throw $this->actionValidationException($exception, 'revision_id');
                    }
                }),
            Actions\Action::make('reject_revision')
                ->label('Reject revision')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Reject Proposed Revision')
                ->modalSubmitActionLabel('Reject revision')
                ->modalContent(fn (Tour $record): View => $this->revisionComparisonView($record))
                ->form([
                    Forms\Components\Hidden::make('revision_id')
                        ->required()
                        ->rules(['integer', 'min:1']),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason (mandatory)')
                        ->required()
                        ->maxLength(2000)
                        ->helperText('Explain why this proposed revision is being rejected.'),
                    $this->actionFeedback(),
                ])
                ->fillForm(function (Tour $record): array {
                    $candidate = TourContentRevision::query()
                        ->where('tour_id', $record->id)
                        ->where('state', TourContentRevision::STATE_PROPOSED)
                        ->first();

                    return [
                        'revision_id' => $candidate?->id,
                    ];
                })
                ->visible(function (Tour $record): bool {
                    if ($record->status !== 'published') {
                        return false;
                    }

                    if (! app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours')) {
                        return false;
                    }

                    return TourContentRevision::query()
                        ->where('tour_id', $record->id)
                        ->where('state', TourContentRevision::STATE_PROPOSED)
                        ->exists();
                })
                ->action(function (array $data, Tour $record): void {
                    try {
                        app(RejectTourRevisionAction::class)->execute(
                            auth()->user(),
                            $record,
                            (int) $data['revision_id'],
                            (string) $data['reason'],
                        );

                        Notification::make()
                            ->title('Revision rejected')
                            ->success()
                            ->send();
                    } catch (TourRevisionException|ValidationException $exception) {
                        throw $this->actionValidationException($exception, 'reason');
                    }
                }),
            Actions\Action::make('request_changes')
                ->label('Request changes')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('gray')
                ->form(fn (Tour $record): array => $this->requestChangesForm($record))
                ->visible(function (Tour $record): bool {
                    if (! app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours')) {
                        return false;
                    }

                    if ($record->status === 'published' && $record->hasEligiblePublicOwner()) {
                        return true;
                    }

                    $isNeverPublic = $record->published_at === null && $record->published_revision_id === null;

                    return $isNeverPublic && in_array($record->status, ['draft', 'pending_review', 'rejected'], true);
                })
                ->action(function (array $data, Tour $record): void {
                    try {
                        app(RequestTourChangesAction::class)->execute(auth()->user(), $record, $data);
                        Notification::make()->title('Change request sent to the partner')->success()->send();
                    } catch (TourRevisionException|ValidationException $exception) {
                        throw $this->actionValidationException($exception, 'reason');
                    }
                }),
            Actions\Action::make('propose_correction')
                ->label('Propose correction')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->form($this->correctionForm())
                ->visible(fn (Tour $record) => $record->status === 'published'
                    && $record->hasEligiblePublicOwner()
                    && app(AdminAuthorizationService::class)->can(auth()->user(), 'correct_tour_content'))
                ->action(function (array $data, Tour $record): void {
                    try {
                        app(ProposeTourCorrectionAction::class)->execute(auth()->user(), $record, $data);
                        Notification::make()->title('Correction proposed for ordinary review')->success()->send();
                    } catch (TourRevisionException|ValidationException $exception) {
                        throw $this->actionValidationException($exception, 'reason');
                    }
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Render the read-only revision comparison modal view.
     */
    private function revisionComparisonView(Tour $record): View
    {
        $index = array_key_last($this->mountedActionsData ?? []);
        $capturedId = $index === null ? null : ($this->mountedActionsData[$index]['revision_id'] ?? null);
        $candidate = TourContentRevision::query()
            ->where('tour_id', $record->id)
            ->whereKey($capturedId)
            ->first();

        $base = $candidate?->base_revision_id
            ? TourContentRevision::query()
                ->where('tour_id', $record->id)
                ->whereKey($candidate->base_revision_id)
                ->first()
            : null;

        $requests = collect();

        if ($candidate !== null) {
            $requests = TourRevisionRequest::query()
                ->forTour((int) $record->id)
                ->where(function ($query) use ($candidate): void {
                    $query->where('revision_id', $candidate->id);

                    if ($candidate->base_revision_id !== null) {
                        $query->orWhere(function ($baseQuery) use ($candidate): void {
                            $baseQuery->where('base_revision_id', $candidate->base_revision_id)
                                ->whereIn('state', [TourRevisionRequest::STATE_OPEN, TourRevisionRequest::STATE_ANSWERED]);
                        });
                    }
                })
                ->orderBy('id')
                ->get();
        }

        return view('filament.tours.revision-comparison', [
            'tour' => $record,
            'candidate' => $candidate,
            'base' => $base,
            'requests' => $requests,
        ]);
    }

    private function actionFeedback(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('review_feedback')
            ->label('Review feedback')
            ->content(fn (): string => implode(' ', $this->getErrorBag()->all()))
            ->visible(fn (): bool => $this->getErrorBag()->isNotEmpty())
            ->extraAttributes(['role' => 'alert']);
    }

    private function actionValidationException(TourRevisionException|ValidationException $exception, string $fallback): ValidationException
    {
        $form = $this->getMountedActionForm();
        $prefix = $form?->getStatePath() ?? 'mountedActionsData.' . array_key_last($this->mountedActionsData ?? []);
        $fields = $form?->getFlatFields(withHidden: true) ?? [];
        $messages = $exception instanceof TourRevisionException
            ? ($exception->paths ?: [$fallback => $exception->getMessage()])
            : $exception->errors();
        $errors = [];
        foreach ($messages as $path => $values) {
            $field = str_starts_with($path, 'data.') ? substr($path, 5) : $path;
            if (str_starts_with($field, 'source.')) {
                $sourceSegment = explode('.', substr($field, 7))[0];
                $field = 'source_' . $sourceSegment;
            } elseif (str_starts_with($field, 'field_refs.')) {
                $field = 'field_refs';
            }
            if (! array_key_exists($field, $fields)) {
                $field = $fallback;
            }
            $key = "{$prefix}.{$field}";
            $errors[$key] = array_merge($errors[$key] ?? [], (array) $values);
        }

        return ValidationException::withMessages($errors);
    }

    /**
     * @return array<int, mixed>
     */
    private function requestChangesForm(Tour $record): array
    {
        $isNeverPublic = $record->published_at === null && $record->published_revision_id === null;

        if ($isNeverPublic) {
            $options = [
                TourRevisionRequest::CONTEXT_INITIAL_REVIEW => 'Initial content review',
            ];
            $default = TourRevisionRequest::CONTEXT_INITIAL_REVIEW;
        } else {
            $options = [
                TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW => 'Published content review',
                TourRevisionRequest::CONTEXT_CORRECTION => 'Exceptional correction request',
            ];
            $default = TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW;
        }

        return [
            Forms\Components\Select::make('context')
                ->label('Request type')
                ->options($options)
                ->default($default)
                ->required(),
            Forms\Components\Textarea::make('reason')
                ->label('Reason (mandatory)')
                ->required()
                ->maxLength(2000)
                ->helperText('Explain why the partner must change the content.'),
            Forms\Components\Textarea::make('field_refs')
                ->label('Field references (one per line)')
                ->required()
                ->helperText('Paths such as source.title, presentation.slug, options.<id>.title or media.<position>.alt_text.'),
            $this->actionFeedback(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function correctionForm(): array
    {
        return [
            Forms\Components\Section::make('Exceptional correction')
                ->description('The correction becomes a separate proposed revision reviewed under the ordinary review workflow; this action never publishes it.')
                ->schema([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason (mandatory)')
                        ->required()
                        ->maxLength(2000),
                    Forms\Components\Textarea::make('field_refs')
                        ->label('Declared canonical fields (one per line, source.* only)')
                        ->required(),
                    Forms\Components\TextInput::make('source_title')
                        ->label('New canonical source title')
                        ->maxLength(120)
                        ->helperText('Leave a field empty to keep its stored value. Only canonical source fields are correctionable in this slice.'),
                    Forms\Components\RichEditor::make('source_description')
                        ->label('New canonical source description')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('source_meeting_point')
                        ->label('New canonical meeting point')
                        ->maxLength(500),
                    Forms\Components\Textarea::make('source_cancellation_policy')
                        ->label('New canonical cancellation policy')
                        ->maxLength(2000)
                        ->rows(2),
                    Forms\Components\Textarea::make('source_highlights')
                        ->label('New canonical highlights (one per line)')
                        ->rows(3),
                    Forms\Components\Textarea::make('source_inclusions')
                        ->label('New canonical inclusions (one per line)')
                        ->rows(3),
                    Forms\Components\Textarea::make('source_exclusions')
                        ->label('New canonical exclusions (one per line)')
                        ->rows(3),
                    Forms\Components\Textarea::make('source_important_information')
                        ->label('New canonical important information (one per line)')
                        ->rows(3),
                    Forms\Components\Textarea::make('source_itinerary')
                        ->label('New canonical itinerary (JSON array)')
                        ->rows(4)
                        ->columnSpanFull(),
                    $this->actionFeedback(),
                ]),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Tour $record */
        $record = $this->getRecord()->fresh(['translations', 'partnerRecord']);

        // Retained EGM-002 atomic defense for the ownership path; canonical and
        // derivative writes are already refused at the raw-input boundary.
        app(TourPublicationGuardService::class)->assertPublishedTourEditIntegrity($record);
    }
}
