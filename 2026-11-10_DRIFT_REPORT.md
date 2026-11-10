# EGM drift report

Reviewed the 322 READMEs created or updated by the full-repository bootstrap on 2026-11-10. This is a static review of declarations, links, table and enum names, route definitions, and selected behavior. A section marked “No drift detected” had no discrepancy in the checks applied to it.

The review recorded 1 mismatches and 71 coverage findings across 31 contracts. A coverage finding identifies behavior or an interface present in source that the contract omits.

## README.md

No drift detected.

## backend/app/Console/Commands/README.md

No drift detected.

## backend/app/Console/README.md

No drift detected.

## backend/app/Domains/Admin/Actions/README.md

No drift detected.

## backend/app/Domains/Admin/Models/README.md

- **Coverage gap.** The Interface omits `StaticPage::hasCompleteLocale()`, `StaticPage::isLocaleEligible()`, `StaticPage::isPublished()`, `StaticPage::contentFor()`. [StaticPage.php](./backend/app/Domains/Admin/Models/StaticPage.php#L125)
- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_DRAFT = 'draft'`, `STATUS_PUBLISHED = 'published'`. [StaticPage.php](./backend/app/Domains/Admin/Models/StaticPage.php#L26)

## backend/app/Domains/Admin/Policies/README.md

- **Coverage gap.** The Interface omits `CategoryPolicy::forceDelete()`. [CategoryPolicy.php](./backend/app/Domains/Admin/Policies/CategoryPolicy.php#L53)
- **Coverage gap.** The Interface omits `TourPolicy::reject()`, `TourPolicy::unpublish()`, `TourPolicy::bulkPublish()`, `TourPolicy::bulkReject()`. [TourPolicy.php](./backend/app/Domains/Admin/Policies/TourPolicy.php#L61)

## backend/app/Domains/Admin/README.md

No drift detected.

## backend/app/Domains/Admin/Requests/README.md

No drift detected.

## backend/app/Domains/Admin/Services/README.md

No drift detected.

## backend/app/Domains/Admin/Settings/README.md

No drift detected.

## backend/app/Domains/Auth/Actions/README.md

No drift detected.

## backend/app/Domains/Auth/Events/README.md

No drift detected.

## backend/app/Domains/Auth/Listeners/README.md

No drift detected.

## backend/app/Domains/Auth/README.md

No drift detected.

## backend/app/Domains/Auth/Services/README.md

No drift detected.

## backend/app/Domains/Blog/Actions/README.md

No drift detected.

## backend/app/Domains/Blog/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Blog/Controllers/README.md

No drift detected.

## backend/app/Domains/Blog/Jobs/README.md

No drift detected.

## backend/app/Domains/Blog/Models/README.md

- **Coverage gap.** The Interface omits `BlogPost::coverMedia()`, `BlogPost::ogImage()`, `BlogPost::scopeFeatured()`, `BlogPost::isPublished()`, `BlogPost::isArchived()`, `BlogPost::isDraft()`, `BlogPost::rawContentFor()`, `BlogPost::isLocaleEligible()`, `BlogPost::contentFor()`, `BlogPost::readingTime()`. [BlogPost.php](./backend/app/Domains/Blog/Models/BlogPost.php#L137)
- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_DRAFT = 'draft'`, `STATUS_PUBLISHED = 'published'`, `STATUS_ARCHIVED = 'archived'`. [BlogPost.php](./backend/app/Domains/Blog/Models/BlogPost.php#L21)

## backend/app/Domains/Blog/Policies/README.md

- **Coverage gap.** The Interface omits `BlogCategoryPolicy::forceDelete()`. [BlogCategoryPolicy.php](./backend/app/Domains/Blog/Policies/BlogCategoryPolicy.php#L48)
- **Coverage gap.** The Interface omits `BlogPostPolicy::forceDelete()`. [BlogPostPolicy.php](./backend/app/Domains/Blog/Policies/BlogPostPolicy.php#L48)

## backend/app/Domains/Blog/README.md

No drift detected.

## backend/app/Domains/Blog/Requests/Public/README.md

No drift detected.

## backend/app/Domains/Blog/Requests/README.md

No drift detected.

## backend/app/Domains/Blog/Services/README.md

No drift detected.

## backend/app/Domains/Blog/Transformers/README.md

No drift detected.

## backend/app/Domains/Booking/Actions/README.md

- **Coverage gap.** The booking creation contract omits option selection rules. Tours with existing options require a selection, and a foreign or retired option is rejected on `tour_option_id`. Replays compare traveler, tour, option, date, and participant count and return a conflict if those fields differ. [Option resolution](./backend/app/Domains/Booking/Actions/CreateBookingAction.php#L266) [Replay comparison](./backend/app/Domains/Booking/Actions/CreateBookingAction.php#L310).
- **Coverage gap.** The partner transition contract omits the allowed target states `completed` and `no_show`, the requirement for the date to precede the current day, and the required starting state `confirmed`. [Transition guards](./backend/app/Domains/Booking/Actions/TransitionBookingStatusAction.php#L36).

## backend/app/Domains/Booking/Controllers/Admin/README.md

No drift detected.

## backend/app/Domains/Booking/Controllers/Partner/README.md

No drift detected.

## backend/app/Domains/Booking/Controllers/Public/README.md

- **Mismatch.** The booking contract says `Idempotency-Key` must contain a UUID v4. `BookingController::store` checks the hexadecimal 8-4-4-4-12 shape. The expression also accepted the version 1 example `123e4567-e89b-12d3-a456-426614174000`. Align the stated requirement with the intended server validation. [Header validation](./backend/app/Domains/Booking/Controllers/Public/BookingController.php#L29).
- **Coverage gap.** The booking endpoint contract omits the distinction between HTTP 201 on creation and 200 on an idempotent replay, and the `price_changed` flag is forced to `false` on replay. [Response mapping](./backend/app/Domains/Booking/Controllers/Public/BookingController.php#L54) [Price flag](./backend/app/Domains/Booking/Controllers/Public/BookingController.php#L69).
- **Coverage gap.** The controller contract omits that `TravelerBookingController::cancel` delegates the cancellation write and that `VoucherController::download` resolves an owner-scoped PDF path before returning an attachment. [Cancellation dispatch](./backend/app/Domains/Booking/Controllers/Public/TravelerBookingController.php#L69) [Voucher download](./backend/app/Domains/Booking/Controllers/Public/VoucherController.php#L26).

## backend/app/Domains/Booking/Controllers/README.md

No drift detected.

## backend/app/Domains/Booking/DTOs/README.md

No drift detected.

## backend/app/Domains/Booking/Jobs/README.md

No drift detected.

## backend/app/Domains/Booking/Middleware/README.md

No drift detected.

## backend/app/Domains/Booking/Models/README.md

- **Coverage gap.** The Interface omits `Booking::payment()`, `Booking::payments()`, `Booking::generateReference()`, `Booking::startDateTime()`, `Booking::formatPrice()`. [Booking.php](./backend/app/Domains/Booking/Models/Booking.php#L165)
- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_PENDING_PAYMENT = 'pending_payment'`, `STATUS_CONFIRMED = 'confirmed'`, `STATUS_COMPLETED = 'completed'`, `STATUS_CANCELLED = 'cancelled'`, `STATUS_NO_SHOW = 'no_show'`, `STATUS_EXPIRED = 'expired'`, `STATUS_CANCELLATION_REQUESTED = 'cancellation_requested'`. [Booking.php](./backend/app/Domains/Booking/Models/Booking.php#L29)
- **Coverage gap.** The invariant references `canTransitionTo` without listing the transition rules. The method allows `confirmed` to move to `completed`, `no_show`, `cancellation_requested`, or `cancelled`, `cancellation_requested` to move to `cancelled`, and `pending_payment` to move to `expired`. [Transition map](./backend/app/Domains/Booking/Models/Booking.php#L243).

## backend/app/Domains/Booking/README.md

No drift detected.

## backend/app/Domains/Booking/Resources/README.md

No drift detected.

## backend/app/Domains/Booking/Services/README.md

No drift detected.

## backend/app/Domains/Booking/Transformers/README.md

No drift detected.

## backend/app/Domains/CMS/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/CMS/Controllers/README.md

No drift detected.

## backend/app/Domains/CMS/Models/README.md

No drift detected.

## backend/app/Domains/CMS/README.md

No drift detected.

## backend/app/Domains/CMS/Services/README.md

No drift detected.

## backend/app/Domains/Media/Actions/README.md

No drift detected.

## backend/app/Domains/Media/Jobs/README.md

No drift detected.

## backend/app/Domains/Media/Models/README.md

- **Coverage gap.** The Interface omits `MediaAsset::missingImagePlaceholderSvg()`, `MediaAsset::missingImagePlaceholderUrl()`, `MediaAsset::previewUrl()`, `MediaAsset::publicUrl()`, `MediaAsset::isImage()`. [MediaAsset.php](./backend/app/Domains/Media/Models/MediaAsset.php#L103)
- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_PENDING = 'pending'`, `STATUS_READY = 'ready'`, `STATUS_FAILED = 'failed'`. [MediaAsset.php](./backend/app/Domains/Media/Models/MediaAsset.php#L56)

## backend/app/Domains/Media/Policies/README.md

- **Coverage gap.** The Interface omits `MediaAssetPolicy::forceDelete()`. [MediaAssetPolicy.php](./backend/app/Domains/Media/Policies/MediaAssetPolicy.php#L55)

## backend/app/Domains/Media/README.md

No drift detected.

## backend/app/Domains/Media/Services/README.md

- **Coverage gap.** The Interface omits `FileManagerService::breadcrumbs()`. [FileManagerService.php](./backend/app/Domains/Media/Services/FileManagerService.php#L242)

## backend/app/Domains/Partner/Actions/README.md

No drift detected.

## backend/app/Domains/Partner/Contracts/README.md

No drift detected.

## backend/app/Domains/Partner/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Partner/Controllers/README.md

- **Coverage gap.** The Interface omits `TourController::submitForReview()`, `TourController::archive()`. [TourController.php](./backend/app/Domains/Partner/Controllers/TourController.php#L132)
- **Coverage gap.** The Side Effects section records none, but `TourController` delegates tour creation, updates, draft persistence, review submission, and archiving to mutating service methods. [Create and update](./backend/app/Domains/Partner/Controllers/TourController.php#L46) [Draft save](./backend/app/Domains/Partner/Controllers/TourController.php#L96) [Review submission](./backend/app/Domains/Partner/Controllers/TourController.php#L132) [Archive](./backend/app/Domains/Partner/Controllers/TourController.php#L173).

## backend/app/Domains/Partner/Events/README.md

No drift detected.

## backend/app/Domains/Partner/Jobs/README.md

No drift detected.

## backend/app/Domains/Partner/Middleware/README.md

No drift detected.

## backend/app/Domains/Partner/Models/README.md

- **Coverage gap.** The Interface omits `Partner::unreadNotifications()`, `Partner::canTransitionTo()`, `Partner::canTransition()`, `Partner::removeToursFromDiscovery()`, `Partner::restoreToursToDiscovery()`. [Partner.php](./backend/app/Domains/Partner/Models/Partner.php#L83)

## backend/app/Domains/Partner/Policies/README.md

No drift detected.

## backend/app/Domains/Partner/README.md

No drift detected.

## backend/app/Domains/Partner/Requests/README.md

- **Coverage gap.** The Interface omits `TourContentRules::optionRules()`, `TourContentRules::optionIdPaths()`, `TourContentRules::normalizeOptions()`, `TourContentRules::castItineraryInts()`, `TourContentRules::forbiddenPresentPaths()`. [TourContentRules.php](./backend/app/Domains/Partner/Requests/TourContentRules.php#L271)

## backend/app/Domains/Partner/Rules/README.md

No drift detected.

## backend/app/Domains/Partner/Services/README.md

- **Coverage gap.** The Interface omits `TourService::submitForReview()`, `TourService::archiveTour()`, `TourService::saveDraft()`, `TourService::getLatestDraft()`. [TourService.php](./backend/app/Domains/Partner/Services/TourService.php#L499)
- **Coverage gap.** The Interface omits `TourTranslationService::withContentLock()`, `TourTranslationService::planSourceDispatches()`, `TourTranslationService::dispatchPlannedTranslations()`, `TourTranslationService::queueIfChanged()`, `TourTranslationService::reconcileTour()`, `TourTranslationService::hasCurrentDerivatives()`, `TourTranslationService::strings()`, `TourTranslationService::validateTranslatablePayload()`, `TourTranslationService::currentTranslation()`, `TourTranslationService::localizedOptionText()`, `TourTranslationService::publicStatus()`. [TourTranslationService.php](./backend/app/Domains/Partner/Services/TourTranslationService.php#L251)

## backend/app/Domains/Payment/Actions/README.md

- **Coverage gap.** The payment intent contract omits the retry branch. An existing charge with a stored `client_secret` returns it immediately. An existing intent without a stored secret is cancelled before a fresh intent is requested. [Retry handling](./backend/app/Domains/Payment/Actions/CreatePaymentIntentAction.php#L33).
- **Coverage gap.** The webhook contract omits which event types it handles and the behavior for an unsupported type. The dispatch covers payment intent outcomes, refunds, disputes, account updates, and three invoice events. Unsupported types are logged and return no domain events. [Event dispatch](./backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php#L68) [Unsupported event](./backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php#L85).

## backend/app/Domains/Payment/Actions/WebhookHandlers/README.md

No drift detected.

## backend/app/Domains/Payment/Contracts/README.md

No drift detected.

## backend/app/Domains/Payment/Controllers/Admin/README.md

No drift detected.

## backend/app/Domains/Payment/Controllers/Partner/README.md

No drift detected.

## backend/app/Domains/Payment/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Payment/Controllers/README.md

No drift detected.

## backend/app/Domains/Payment/Events/README.md

No drift detected.

## backend/app/Domains/Payment/Jobs/README.md

No drift detected.

## backend/app/Domains/Payment/Listeners/README.md

No drift detected.

## backend/app/Domains/Payment/Models/README.md

No drift detected.

## backend/app/Domains/Payment/README.md

No drift detected.

## backend/app/Domains/Payment/Services/README.md

- **Coverage gap.** The fee calculation contract omits integer half-up rounding and the overflow guard for large amounts with nonzero commission. [Fee calculation](./backend/app/Domains/Payment/Services/ApplicationFeeCalculator.php#L26).
- **Coverage gap.** The service contract lists `recordCharge` and `recordRefund` without documenting that both can persist a financial ledger entry through `firstOrCreate` and report whether the entry was created. [Charge ledger write](./backend/app/Domains/Payment/Services/LedgerService.php#L10) [Refund ledger write](./backend/app/Domains/Payment/Services/LedgerService.php#L36).

## backend/app/Domains/README.md

No drift detected.

## backend/app/Domains/Reviews/Actions/README.md

No drift detected.

## backend/app/Domains/Reviews/Controllers/Admin/README.md

No drift detected.

## backend/app/Domains/Reviews/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Reviews/Controllers/README.md

No drift detected.

## backend/app/Domains/Reviews/DTOs/README.md

No drift detected.

## backend/app/Domains/Reviews/Events/README.md

No drift detected.

## backend/app/Domains/Reviews/Listeners/README.md

No drift detected.

## backend/app/Domains/Reviews/Models/README.md

No drift detected.

## backend/app/Domains/Reviews/README.md

No drift detected.

## backend/app/Domains/Reviews/Services/README.md

No drift detected.

## backend/app/Domains/Search/Actions/README.md

No drift detected.

## backend/app/Domains/Search/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Search/Controllers/README.md

No drift detected.

## backend/app/Domains/Search/README.md

No drift detected.

## backend/app/Domains/Search/Support/README.md

- **Coverage gap.** The contract omits these serialized enum values `DurationBucket::HalfDay = 'half-day'`, `DurationBucket::FullDay = 'full-day'`, `DurationBucket::MultiDay = 'multi-day'`. [DurationBucket.php](./backend/app/Domains/Search/Support/DurationBucket.php#L14)

## backend/app/Domains/Search/Transformers/README.md

No drift detected.

## backend/app/Domains/Translation/Contracts/README.md

No drift detected.

## backend/app/Domains/Translation/Jobs/README.md

No drift detected.

## backend/app/Domains/Translation/Models/README.md

- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_MISSING = 'missing'`, `STATUS_QUEUED = 'queued'`, `STATUS_TRANSLATING = 'translating'`, `STATUS_COMPLETED = 'completed'`, `STATUS_OUTDATED = 'outdated'`, `STATUS_FAILED = 'failed'`, `STATUS_NEEDS_REVIEW = 'needs_review'`, `STATUS_MANUALLY_EDITED = 'manually_edited'`. [TranslationFieldState.php](./backend/app/Domains/Translation/Models/TranslationFieldState.php#L23)

## backend/app/Domains/Translation/README.md

No drift detected.

## backend/app/Domains/Translation/Services/README.md

No drift detected.

## backend/app/Domains/Traveler/Actions/README.md

No drift detected.

## backend/app/Domains/Traveler/Controllers/Public/README.md

No drift detected.

## backend/app/Domains/Traveler/Controllers/README.md

No drift detected.

## backend/app/Domains/Traveler/DTOs/README.md

No drift detected.

## backend/app/Domains/Traveler/README.md

No drift detected.

## backend/app/Domains/Wishlist/Actions/README.md

No drift detected.

## backend/app/Domains/Wishlist/DTOs/README.md

No drift detected.

## backend/app/Domains/Wishlist/Models/README.md

No drift detected.

## backend/app/Domains/Wishlist/README.md

No drift detected.

## backend/app/Enums/README.md

- **Coverage gap.** The contract omits these serialized enum values `PartnerStatus::Pending = 'pending'`, `PartnerStatus::Approved = 'approved'`, `PartnerStatus::Rejected = 'rejected'`, `PartnerStatus::Suspended = 'suspended'`. [PartnerStatus.php](./backend/app/Enums/PartnerStatus.php#L15)
- **Coverage gap.** The contract omits these serialized enum values `ReviewStatus::Visible = 'visible'`, `ReviewStatus::Hidden = 'hidden'`, `ReviewStatus::Flagged = 'flagged'`. [ReviewStatus.php](./backend/app/Enums/ReviewStatus.php#L12)
- **Coverage gap.** The contract omits these serialized enum values `TourStatus::Draft = 'draft'`, `TourStatus::PendingReview = 'pending_review'`, `TourStatus::Published = 'published'`, `TourStatus::Rejected = 'rejected'`, `TourStatus::Archived = 'archived'`. [TourStatus.php](./backend/app/Enums/TourStatus.php#L13)

## backend/app/Events/README.md

No drift detected.

## backend/app/Filament/Components/README.md

No drift detected.

## backend/app/Filament/Pages/README.md

- **Coverage gap.** The Interface omits `FileManagerPage::trashedAssets()`, `FileManagerPage::kpis()`, `FileManagerPage::assetUsageLabel()`. [FileManagerPage.php](./backend/app/Filament/Pages/FileManagerPage.php#L110)

## backend/app/Filament/README.md

No drift detected.

## backend/app/Filament/Resources/AdminRoleResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/AdminRoleResource/README.md

No drift detected.

## backend/app/Filament/Resources/AdminUserResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/AdminUserResource/README.md

No drift detected.

## backend/app/Filament/Resources/AuditLogResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/AuditLogResource/README.md

No drift detected.

## backend/app/Filament/Resources/AuthorProfileResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/AuthorProfileResource/README.md

No drift detected.

## backend/app/Filament/Resources/AvailabilityResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/AvailabilityResource/README.md

No drift detected.

## backend/app/Filament/Resources/BlogCategoryResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/BlogCategoryResource/README.md

No drift detected.

## backend/app/Filament/Resources/BlogPostResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/BlogPostResource/README.md

No drift detected.

## backend/app/Filament/Resources/BlogTagResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/BlogTagResource/README.md

No drift detected.

## backend/app/Filament/Resources/BookingResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/BookingResource/README.md

No drift detected.

## backend/app/Filament/Resources/CategoryResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/CategoryResource/README.md

No drift detected.

## backend/app/Filament/Resources/ContentRevisionResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/ContentRevisionResource/README.md

No drift detected.

## backend/app/Filament/Resources/DestinationResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/DestinationResource/README.md

No drift detected.

## backend/app/Filament/Resources/FinancialLedgerResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/FinancialLedgerResource/README.md

No drift detected.

## backend/app/Filament/Resources/GovernanceAuditResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/GovernanceAuditResource/README.md

No drift detected.

## backend/app/Filament/Resources/MediaAssetResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/MediaAssetResource/README.md

No drift detected.

## backend/app/Filament/Resources/PartnerResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/PartnerResource/README.md

No drift detected.

## backend/app/Filament/Resources/PaymentResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/PaymentResource/README.md

No drift detected.

## backend/app/Filament/Resources/README.md

- **Coverage gap.** The Interface omits `AdminRoleResource::getPages()`. [AdminRoleResource.php](./backend/app/Filament/Resources/AdminRoleResource.php#L147)
- **Coverage gap.** The Interface omits `AdminUserResource::canDelete()`, `AdminUserResource::getEloquentQuery()`, `AdminUserResource::flagOptions()`, `AdminUserResource::flagLabel()`, `AdminUserResource::form()`, `AdminUserResource::table()`, `AdminUserResource::getPages()`. [AdminUserResource.php](./backend/app/Filament/Resources/AdminUserResource.php#L74)
- **Coverage gap.** The Interface omits `AvailabilityResource::infolist()`, `AvailabilityResource::getRelations()`, `AvailabilityResource::getPages()`. [AvailabilityResource.php](./backend/app/Filament/Resources/AvailabilityResource.php#L121)
- **Coverage gap.** The Interface omits `BookingResource::getRelations()`, `BookingResource::getPages()`. [BookingResource.php](./backend/app/Filament/Resources/BookingResource.php#L326)
- **Coverage gap.** The Interface omits `FinancialLedgerResource::getEloquentQuery()`, `FinancialLedgerResource::table()`, `FinancialLedgerResource::infolist()`, `FinancialLedgerResource::getRelations()`, `FinancialLedgerResource::getPages()`. [FinancialLedgerResource.php](./backend/app/Filament/Resources/FinancialLedgerResource.php#L91)
- **Coverage gap.** The Interface omits `GovernanceAuditResource::getPages()`. [GovernanceAuditResource.php](./backend/app/Filament/Resources/GovernanceAuditResource.php#L184)
- **Coverage gap.** The Interface omits `MediaAssetResource::getGlobalSearchResultTitle()`, `MediaAssetResource::getGlobalSearchResultDetails()`, `MediaAssetResource::getPages()`. [MediaAssetResource.php](./backend/app/Filament/Resources/MediaAssetResource.php#L222)
- **Coverage gap.** The Interface omits `PartnerResource::table()`, `PartnerResource::infolist()`, `PartnerResource::getRelations()`, `PartnerResource::getPages()`. [PartnerResource.php](./backend/app/Filament/Resources/PartnerResource.php#L82)
- **Coverage gap.** The Interface omits `PaymentResource::canCreate()`, `PaymentResource::canEdit()`, `PaymentResource::canDelete()`, `PaymentResource::canDeleteAny()`, `PaymentResource::getEloquentQuery()`, `PaymentResource::table()`, `PaymentResource::infolist()`, `PaymentResource::getRelations()`, `PaymentResource::getPages()`. [PaymentResource.php](./backend/app/Filament/Resources/PaymentResource.php#L92)
- **Coverage gap.** The Interface omits `ReviewResource::infolist()`, `ReviewResource::canCreate()`, `ReviewResource::getRelations()`, `ReviewResource::getPages()`. [ReviewResource.php](./backend/app/Filament/Resources/ReviewResource.php#L253)
- **Coverage gap.** The Interface omits `StaticPageResource::infolist()`, `StaticPageResource::getPages()`. [StaticPageResource.php](./backend/app/Filament/Resources/StaticPageResource.php#L199)
- **Coverage gap.** The Interface omits `TourResource::table()`, `TourResource::infolist()`, `TourResource::getRelations()`, `TourResource::getPages()`. [TourResource.php](./backend/app/Filament/Resources/TourResource.php#L162)
- **Coverage gap.** The Interface omits `TravelerResource::getGlobalSearchResultTitle()`, `TravelerResource::getGlobalSearchResultDetails()`, `TravelerResource::canView()`, `TravelerResource::getEloquentQuery()`, `TravelerResource::getPages()`. [TravelerResource.php](./backend/app/Filament/Resources/TravelerResource.php#L273)

## backend/app/Filament/Resources/ReviewResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/ReviewResource/README.md

No drift detected.

## backend/app/Filament/Resources/StaticPageResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/StaticPageResource/README.md

No drift detected.

## backend/app/Filament/Resources/TourResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/TourResource/README.md

No drift detected.

## backend/app/Filament/Resources/TravelerResource/Pages/README.md

No drift detected.

## backend/app/Filament/Resources/TravelerResource/README.md

No drift detected.

## backend/app/Filament/Widgets/README.md

No drift detected.

## backend/app/Http/Controllers/Admin/README.md

No drift detected.

## backend/app/Http/Controllers/Public/Account/README.md

No drift detected.

## backend/app/Http/Controllers/Public/Auth/README.md

No drift detected.

## backend/app/Http/Controllers/Public/README.md

No drift detected.

## backend/app/Http/Controllers/README.md

No drift detected.

## backend/app/Http/Middleware/README.md

No drift detected.

## backend/app/Http/README.md

No drift detected.

## backend/app/Http/Requests/Admin/README.md

No drift detected.

## backend/app/Http/Requests/Auth/README.md

No drift detected.

## backend/app/Http/Requests/Public/README.md

No drift detected.

## backend/app/Http/Requests/Public/Search/README.md

No drift detected.

## backend/app/Http/Requests/README.md

No drift detected.

## backend/app/Http/Resources/Concerns/README.md

No drift detected.

## backend/app/Http/Resources/README.md

No drift detected.

## backend/app/Jobs/README.md

No drift detected.

## backend/app/Listeners/README.md

No drift detected.

## backend/app/Mail/README.md

No drift detected.

## backend/app/Models/README.md

- **Coverage gap.** The Interface omits `Tour::scopeBookable()`, `Tour::hasValidPricing()`, `Tour::hasOptionsConfigured()`, `Tour::supportsFieldTranslation()`, `Tour::activeOptions()`, `Tour::hasActiveOptions()`, `Tour::hasUpcomingAvailability()`, `Tour::lowestPriceAmount()`, `Tour::currency()`, `Tour::averageRating()`, `Tour::reviewCount()`, `Tour::allImageUrls()`, `Tour::upcomingAvailableDates()`, `Tour::operatesOnDate()`, `Tour::startTimeForDate()`, `Tour::guideLanguages()`, `Tour::translations()`, `Tour::translationStates()`, `Tour::displayTitle()`, `Tour::sourceLocale()`, `Tour::sourceTranslation()`, `Tour::derivativeLocalesFor()`, `Tour::derivativeLocales()`, `Tour::category()`, `Tour::partner()`, `Tour::formatPrice()`, `Tour::media()`, `Tour::pricingTiers()`, `Tour::options()`, `Tour::availabilityRules()`, `Tour::availabilityExceptions()`, `Tour::drafts()`, `Tour::bookings()`, `Tour::governanceAuditLogs()`, `Tour::reviewDistribution()`, `Tour::partnerRecord()`. [Tour.php](./backend/app/Models/Tour.php#L255)
- **Coverage gap.** The Interface omits `TourOption::guideLanguages()`. [TourOption.php](./backend/app/Models/TourOption.php#L146)
- **Coverage gap.** The contract omits these public lifecycle constants `STATUS_ACTIVE = 'active'`, `STATUS_RETIRED = 'retired'`. [TourOption.php](./backend/app/Models/TourOption.php#L29)
- **Coverage gap.** The Interface omits `User::adminPermission()`, `User::adminRoles()`, `User::authorProfile()`. [User.php](./backend/app/Models/User.php#L144)

## backend/app/Policies/README.md

No drift detected.

## backend/app/Providers/Filament/README.md

No drift detected.

## backend/app/Providers/README.md

No drift detected.

## backend/app/README.md

No drift detected.

## backend/app/Support/README.md

No drift detected.

## backend/bootstrap/README.md

No drift detected.

## backend/config/README.md

No drift detected.

## backend/database/factories/README.md

No drift detected.

## backend/database/migrations/README.md

No drift detected.

## backend/database/README.md

No drift detected.

## backend/database/seeders/README.md

No drift detected.

## backend/database/settings/README.md

No drift detected.

## backend/docker/README.md

No drift detected.

## backend/lang/en/README.md

No drift detected.

## backend/lang/es/README.md

No drift detected.

## backend/lang/it/README.md

No drift detected.

## backend/lang/README.md

No drift detected.

## backend/public/js/README.md

No drift detected.

## backend/public/README.md

No drift detected.

## backend/README.md

No drift detected.

## backend/resources/js/README.md

No drift detected.

## backend/resources/README.md

No drift detected.

## backend/resources/views/emails/auth/README.md

No drift detected.

## backend/resources/views/emails/booking/cancelled/README.md

No drift detected.

## backend/resources/views/emails/booking/confirmed/README.md

No drift detected.

## backend/resources/views/emails/booking/README.md

No drift detected.

## backend/resources/views/emails/booking/voucher/README.md

No drift detected.

## backend/resources/views/emails/partner/application-received/README.md

No drift detected.

## backend/resources/views/emails/partner/approved/README.md

No drift detected.

## backend/resources/views/emails/partner/booking-cancelled/README.md

No drift detected.

## backend/resources/views/emails/partner/invitation/README.md

No drift detected.

## backend/resources/views/emails/partner/new-booking/README.md

No drift detected.

## backend/resources/views/emails/partner/README.md

No drift detected.

## backend/resources/views/emails/partner/reinstated/README.md

No drift detected.

## backend/resources/views/emails/partner/rejected/README.md

No drift detected.

## backend/resources/views/emails/partner/suspended/README.md

No drift detected.

## backend/resources/views/emails/README.md

No drift detected.

## backend/resources/views/filament/admin/README.md

No drift detected.

## backend/resources/views/filament/components/README.md

No drift detected.

## backend/resources/views/filament/infolists/README.md

No drift detected.

## backend/resources/views/filament/pages/README.md

No drift detected.

## backend/resources/views/filament/README.md

No drift detected.

## backend/resources/views/filament/widgets/README.md

No drift detected.

## backend/resources/views/README.md

No drift detected.

## backend/resources/views/voucher/README.md

No drift detected.

## backend/routes/api/README.md

- **Coverage gap.** The `/api/public` section omits the HTTP method and path for 52 of 52 source-declared endpoints. Add an endpoint inventory or link to one. Examples include [POST /api/public/bookings](./backend/routes/api/public.php#L138), [POST /api/public/webhooks/stripe](./backend/routes/api/public.php#L148), [GET /api/public/v/{reference}](./backend/routes/api/public.php#L169), [GET /api/public/auth/me](./backend/routes/api/public.php#L82).
- **Coverage gap.** The `/api/partner` section omits the HTTP method and path for 44 of 44 source-declared endpoints. Add an endpoint inventory or link to one. Examples include [GET /api/partner/profile](./backend/routes/api/partner.php#L93), [PUT /api/partner/settings](./backend/routes/api/partner.php#L99), [GET /api/partner/financial-summary](./backend/routes/api/partner.php#L118), [POST /api/partner/bookings/{reference}/cancellation-request](./backend/routes/api/partner.php#L76).
- **Coverage gap.** The `/api/admin` section omits the HTTP method and path for 6 of 6 source-declared endpoints. Add an endpoint inventory or link to one. Examples include [GET /api/admin/audit/bookings/{reference}](./backend/routes/api/admin.php#L19), [GET /api/admin/financial-ledger](./backend/routes/api/admin.php#L22), [POST /api/admin/reviews/{review}/hide](./backend/routes/api/admin.php#L27), [POST /api/admin/reviews/{review}/reinstate](./backend/routes/api/admin.php#L28).
- **Coverage gap.** The public route contract omits the environment gate for deterministic payment confirmation and the separate `throttle:traveler` policy on authenticated session restoration at `GET /api/public/auth/me`. [Deterministic route gate](./backend/routes/api/public.php#L153) [Session restoration route](./backend/routes/api/public.php#L82).

## backend/routes/README.md

No drift detected.

## docker/nginx/README.md

No drift detected.

## docker/README.md

No drift detected.

## frontend/README.md

No drift detected.

## frontend/scripts/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/forgot-password/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/login/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/partner-register/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/register/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/reset-password/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/auth/verify-email/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/partner-invite/[token]/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/partner-invite/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/partner-register/README.md

No drift detected.

## frontend/src/app/[locale]/(auth)/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/analytics/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/bookings/[reference]/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/bookings/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/onboarding/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/profile/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/reviews/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/[id]/availability/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/[id]/pricing/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/[id]/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/create/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/partner/tours/README.md

No drift detected.

## frontend/src/app/[locale]/(partner)/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/blog/[slug]/preview/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/blog/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/blog/category/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/blog/category/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/blog/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/booking/confirmation/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/booking/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/categories/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/categories/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/destinations/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/destinations/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/pages/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/pages/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/privacy/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/search/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/terms/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/tours/[slug]/README.md

No drift detected.

## frontend/src/app/[locale]/(public)/tours/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/my-bookings/[reference]/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/my-bookings/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/my-reviews/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/profile/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/README.md

No drift detected.

## frontend/src/app/[locale]/(traveler)/wishlist/README.md

No drift detected.

## frontend/src/app/[locale]/[...notFound]/README.md

No drift detected.

## frontend/src/app/[locale]/README.md

No drift detected.

## frontend/src/app/api/auth/README.md

No drift detected.

## frontend/src/app/api/auth/session/README.md

- **Coverage gap.** The contract omits the session route’s trust boundary. `POST` stores the submitted `user` and `token` values in cookies without checking them against Laravel. `GET` checks cookie presence and parses the session JSON. [Read handler](./frontend/src/app/api/auth/session/route.ts#L7) [Write handler](./frontend/src/app/api/auth/session/route.ts#L24).

## frontend/src/app/api/README.md

No drift detected.

## frontend/src/app/README.md

No drift detected.

## frontend/src/app/v/[reference]/README.md

No drift detected.

## frontend/src/app/v/README.md

No drift detected.

## frontend/src/components/admin/README.md

No drift detected.

## frontend/src/components/auth/README.md

No drift detected.

## frontend/src/components/blog/README.md

No drift detected.

## frontend/src/components/booking/README.md

No drift detected.

## frontend/src/components/bookings/README.md

No drift detected.

## frontend/src/components/home/README.md

No drift detected.

## frontend/src/components/layout/README.md

No drift detected.

## frontend/src/components/my-bookings/README.md

No drift detected.

## frontend/src/components/partner/analytics/README.md

No drift detected.

## frontend/src/components/partner/bookings/README.md

No drift detected.

## frontend/src/components/partner/layout/README.md

No drift detected.

## frontend/src/components/partner/profile/README.md

No drift detected.

## frontend/src/components/partner/README.md

No drift detected.

## frontend/src/components/partner/reviews/README.md

No drift detected.

## frontend/src/components/partner/tours/README.md

No drift detected.

## frontend/src/components/profile/README.md

No drift detected.

## frontend/src/components/README.md

No drift detected.

## frontend/src/components/reviews/README.md

No drift detected.

## frontend/src/components/search/README.md

No drift detected.

## frontend/src/components/seo/README.md

No drift detected.

## frontend/src/components/shared/README.md

No drift detected.

## frontend/src/components/tour/README.md

- **Coverage gap.** The Interface omits `default BookingCard`. [BookingCard.tsx](./frontend/src/components/tour/BookingCard.tsx#L216)

## frontend/src/components/traveler/README.md

No drift detected.

## frontend/src/components/ui/README.md

No drift detected.

## frontend/src/components/wishlist/README.md

No drift detected.

## frontend/src/hooks/README.md

No drift detected.

## frontend/src/i18n/README.md

No drift detected.

## frontend/src/lib/api/README.md

- **Coverage gap.** The Interface omits `RateLimitError`. [client.ts](./frontend/src/lib/api/client.ts#L171)
- **Coverage gap.** The Interface omits `getLatestTourDraft`, `getBookings`, `getBooking`, `updateBookingStatus`, `requestBookingCancellation`, `getReviews`, `respondToReview`, `updateReviewResponse`, `getNotifications`, `markNotificationAsRead`, `markAllNotificationsAsRead`, `getAnalytics`, `getProfile`, `updateProfile`, `getPartnerSettings`, `updatePartnerSettings`, `getSignedUploadUrl`, `registerPartner`, `getOnboardingStatus`, `resubmitApplication`, `validateInviteToken`, `completeInvite`. [partner.ts](./frontend/src/lib/api/partner.ts#L102)
- **Coverage gap.** The Interface omits `getTravelerWishlist`, `addTravelerWishlistItem`, `getTravelerWishlistStatus`, `removeTravelerWishlistItem`, `mergeTravelerWishlist`, `getTravelerReviews`, `downloadTravelerBookingVoucher`. [traveler.ts](./frontend/src/lib/api/traveler.ts#L90)
- **Coverage gap.** The Interface omits `PartnerAvailabilityRuleInput`, `PartnerAvailabilityExceptionInput`, `PartnerBookingOptionInput`, `ContentSourceLocale`, `PartnerTourWritePayload`, `OwnedTranslationStatus`, `TourDraftSnapshot`, `TourItineraryDay`, `PricingInfo`, `AvailabilityInfo`, `ReviewsInfo`, `PublicOperatorSummary`, `SeoMetadata`, `Category`, `Destination`, `SearchParams`, `SearchResponse`, `CuratedRail`, `HomepageData`, `BlogCategorySummary`, `BlogAuthorSummary`, `BlogPostCard`, `BlogPostDetail`, `BlogListResponse`, `BlogDetailResponse`, `BlogCategoryResponse`, `PublicStaticPage`. [types.ts](./frontend/src/lib/api/types.ts#L127)
- **Coverage gap.** The transport contract describes the JSON `Accept` header without documenting caller precedence. `apiClient` sets `application/json` as the default, then spreads caller-provided headers over it. [Header construction](./frontend/src/lib/api/client.ts#L63).

## frontend/src/lib/auth/README.md

No drift detected.

## frontend/src/lib/hooks/README.md

No drift detected.

## frontend/src/lib/README.md

No drift detected.

## frontend/src/lib/reviews/README.md

- **Coverage gap.** The Interface omits `reinstateReview`. [review-api.ts](./frontend/src/lib/reviews/review-api.ts#L171)

## frontend/src/lib/stores/README.md

No drift detected.

## frontend/src/lib/stripe/README.md

No drift detected.

## frontend/src/lib/tours/README.md

- **Coverage gap.** The Interface omits `isSupportedLocale`, `areGroupBoundsValid`, `isParticipantCountValid`, `getOfferedFutureDates`, `isOfferedFutureDate`, `isBookableAvailability`, `resolveInitialDate`, `resolveInitialCount`, `isPriceAmountValid`, `isPriceCurrencySupported`, `getEstimateCents`, `formatOfferedDateUtc`, `buildCheckoutUrl`, `buildAuthContinuationUrl`, `buildRegisterContinuationUrl`, `getBookingInvalidReasons`, `getBookingSelectionView`, `ResolvedOption`, `hasStrayOptionParameter`, `resolveSelectedOption`, `BookingWithOptionInput`, `BookingWithOptionView`, `getBookingWithOptionView`. [booking-selection.ts](./frontend/src/lib/tours/booking-selection.ts#L76)
- **Coverage gap.** The Interface omits `hasUsableItinerary`, `hasDifficulty`, `hasGenuineReviewCount`, `isGenuineReviewScore`, `getDetailSections`, `getPresentSectionIds`, `shouldShowContentFallback`, `shouldShowItineraryFallback`, `getLanguageDisclosure`, `getGuideLanguageNames`, `resolveImageAltLocale`, `getContentLanguageName`, `getDetailPresentation`. [detail-presentation.ts](./frontend/src/lib/tours/detail-presentation.ts#L82)

## frontend/src/lib/validators/README.md

- **Coverage gap.** The Interface omits `bookingOptionSchema`, `tourBasicDetailsSchema`, `tourPublishSourceSchema`, `tourMediaStepSchema`, `tourPricingStepSchema`, `tourAvailabilityStepSchema`, `isPlaceholderOptionRow`, `getSubmittedOptionRows`, `tourSourceStepSchema`, `tourWizardSchema`, `toFormErrorPath`, `mapServerErrorsToFields`, `pickFieldErrors`, `localizeServerFieldError`, `localizeServerFieldErrors`. [partner.ts](./frontend/src/lib/validators/partner.ts#L123)

## frontend/src/lib/wishlist/README.md

- **Coverage gap.** The Interface omits `syncGuestWishlistToAccount`. [guestWishlist.ts](./frontend/src/lib/wishlist/guestWishlist.ts#L116)

## frontend/src/README.md

No drift detected.

## frontend/src/types/README.md

- **Coverage gap.** The Interface omits `OwnedTourOption`, `BookingParticipant`, `PartnerBooking`, `PartnerReview`, `TourSummary`, `ReviewResponse`, `Notification`, `PartnerProfile`, `PartnerPayoutInfo`, `PartnerSettings`, `AnalyticsSummary`, `AnalyticsBookingsPoint`, `AnalyticsResponse`, `DateRangeFilter`, `PaginatedPartnerResponse`, `SignedUploadUrl`, `OnboardingStatus`, `BusinessAddress`, `PartnerRegistrationPayload`, `PartnerRegistrationResponse`, `PartnerOnboardingStatus`, `ResubmitPayload`, `InviteValidationResponse`, `InviteCompletionPayload`. [partner.ts](./frontend/src/types/partner.ts#L71)
- **Coverage gap.** The Interface omits `SOURCE_LOCALES`, `WizardStep`, `WIZARD_STEPS`, `WIZARD_STEP_LABELS`, `DurationUnit`, `DifficultyLevel`, `TourFormData`, `PricingTierFormInput`, `AvailabilityRuleFormInput`, `AvailabilityExceptionFormInput`, `BookingOptionFormInput`, `TourListFilters`, `TourListResponse`, `ImageUploadState`, `INITIAL_TOUR_FORM_DATA`. [tour.ts](./frontend/src/types/tour.ts#L34)
- **Coverage gap.** The Interface omits `TravelerReview`. [traveler.ts](./frontend/src/types/traveler.ts#L108)
