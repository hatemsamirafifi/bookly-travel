# EGM drift remediation report

Reviewed the 322 first-party contracts in the original bootstrap scope. The original report is the baseline. [Original drift report](./2026-11-10_DRIFT_REPORT.md).

| Finding | Before | After |
| --- | ---: | ---: |
| Confirmed documentation mismatch | 1 | 0 |
| Coverage gap | 71 | 0 |
| Contracts with findings | 31 | 0 |

## Remediation priorities

1. Resolve the booking header mismatch without narrowing the accepted API inputs. Add a database-free regression test for the observed behavior.
2. Document API routes, authorization, booking replay, payment recovery, and the session-cookie trust boundary from their implementations.
3. Name the reported public declarations and serialized values in their existing package contracts.
4. Rerun drift and link checks, then compare working-tree fingerprints with the pre-remediation snapshot.

The baseline contained one behavioral mismatch, four API contract gaps, 55 public declaration or serialized value gaps, and 12 other behavior or documentation gaps. The original evidence was rechecked against the current source.

## Booking idempotency compatibility

The original booking specification requires a client-generated UUID. The detailed contract names v4, which checkout generates. The server checks the hexadecimal UUID shape without checking the version or variant. A strict v4 check would reject previously accepted requests and could prevent replay with existing keys. Local evidence cannot establish external consumer usage. The EGM contract now records server behavior and advises callers to reuse the exact value. [Booking specification](./specs/007-tour-booking/spec.md#L115) · [Original API contract](./specs/007-tour-booking/contracts/booking-api.md#L18) · [Checkout generator](./frontend/src/components/booking/BookingForm.tsx#L756) · [Controller](./backend/app/Domains/Booking/Controllers/Public/BookingController.php#L29).

## Software defects and recovery gaps

- **Stripe request idempotency.** A local capture of the actual adapter and installed Stripe PHP transport observed the idempotency value in the body and no Idempotency-Key header for both intent creation and refunds. Stripe uses a request option that becomes that header. The capture establishes the absent header. Live Stripe outcomes were outside this check. [Adapter](./backend/app/Domains/Payment/Services/StripeService.php#L29) · [Stripe API](https://docs.stripe.com/api/idempotent_requests).
- **Post-commit webhook recovery.** The event record and handler changes commit before returned domain events are dispatched. If a listener fails after commit, redelivery finds the stored event ID and skips the handler, leaving the failed listener without redelivery recovery. [Webhook transaction and dispatch](./backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php#L42).

## Verification

- Reran the EGM checks across 322 contracts and 2949 local README links. The scan rechecked 939 source files, 56 table creation boundaries, and 21 serialized enum cases.
- PHPUnit passed four booking header cases with 14 assertions. The run used the Composer autoloader and bypassed the database-backed suite configuration.
- `git diff --check` and `docker compose config --quiet` passed.
- Compared all 102 documented method/path pairs with the fresh Laravel registry, including 52 public, 44 partner, and 6 admin registrations. The registry check covers route registration, while behavior was reviewed against the handler, request, and service source.
- Used OpenCode GLM 5.3 Flash Free for the API draft. The first run timed out during evidence collection. The resumed run wrote the endpoint sections, and the final review corrected the delegated claims against local source.

## Exact files changed

- [2026-11-10_DRIFT_REPORT_REMEDIATED.md](./2026-11-10_DRIFT_REPORT_REMEDIATED.md)
- [backend/app/Domains/Admin/Models/README.md](./backend/app/Domains/Admin/Models/README.md)
- [backend/app/Domains/Admin/Policies/README.md](./backend/app/Domains/Admin/Policies/README.md)
- [backend/app/Domains/Blog/Models/README.md](./backend/app/Domains/Blog/Models/README.md)
- [backend/app/Domains/Blog/Policies/README.md](./backend/app/Domains/Blog/Policies/README.md)
- [backend/app/Domains/Booking/Actions/README.md](./backend/app/Domains/Booking/Actions/README.md)
- [backend/app/Domains/Booking/Controllers/Public/README.md](./backend/app/Domains/Booking/Controllers/Public/README.md)
- [backend/app/Domains/Booking/Models/README.md](./backend/app/Domains/Booking/Models/README.md)
- [backend/app/Domains/Media/Models/README.md](./backend/app/Domains/Media/Models/README.md)
- [backend/app/Domains/Media/Policies/README.md](./backend/app/Domains/Media/Policies/README.md)
- [backend/app/Domains/Media/Services/README.md](./backend/app/Domains/Media/Services/README.md)
- [backend/app/Domains/Partner/Controllers/README.md](./backend/app/Domains/Partner/Controllers/README.md)
- [backend/app/Domains/Partner/Models/README.md](./backend/app/Domains/Partner/Models/README.md)
- [backend/app/Domains/Partner/Requests/README.md](./backend/app/Domains/Partner/Requests/README.md)
- [backend/app/Domains/Partner/Services/README.md](./backend/app/Domains/Partner/Services/README.md)
- [backend/app/Domains/Payment/Actions/README.md](./backend/app/Domains/Payment/Actions/README.md)
- [backend/app/Domains/Payment/Services/README.md](./backend/app/Domains/Payment/Services/README.md)
- [backend/app/Domains/Search/Support/README.md](./backend/app/Domains/Search/Support/README.md)
- [backend/app/Domains/Translation/Models/README.md](./backend/app/Domains/Translation/Models/README.md)
- [backend/app/Enums/README.md](./backend/app/Enums/README.md)
- [backend/app/Filament/Pages/README.md](./backend/app/Filament/Pages/README.md)
- [backend/app/Filament/Resources/README.md](./backend/app/Filament/Resources/README.md)
- [backend/app/Models/README.md](./backend/app/Models/README.md)
- [backend/routes/api/README.md](./backend/routes/api/README.md)
- [backend/tests/Unit/Booking/BookingIdempotencyHeaderTest.php](https://github.com/hatemsamirafifi/bookly-travel/blob/bd63592b3d549d792a9b2724feced077849b81f0/backend/tests/Unit/Booking/BookingIdempotencyHeaderTest.php)
- [frontend/src/app/api/auth/session/README.md](./frontend/src/app/api/auth/session/README.md)
- [frontend/src/components/tour/README.md](./frontend/src/components/tour/README.md)
- [frontend/src/lib/api/README.md](./frontend/src/lib/api/README.md)
- [frontend/src/lib/reviews/README.md](./frontend/src/lib/reviews/README.md)
- [frontend/src/lib/tours/README.md](./frontend/src/lib/tours/README.md)
- [frontend/src/lib/validators/README.md](./frontend/src/lib/validators/README.md)
- [frontend/src/lib/wishlist/README.md](./frontend/src/lib/wishlist/README.md)
- [frontend/src/types/README.md](./frontend/src/types/README.md)

## Findings by contract

### README.md

No drift detected in the checks applied to this contract.

### backend/app/Console/Commands/README.md

No drift detected in the checks applied to this contract.

### backend/app/Console/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Policies/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Requests/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Admin/Settings/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Auth/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Auth/Events/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Auth/Listeners/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Auth/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Auth/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Policies/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Requests/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Requests/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Blog/Transformers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Controllers/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Controllers/Partner/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/DTOs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Middleware/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Resources/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Booking/Transformers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/CMS/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/CMS/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/CMS/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/CMS/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/CMS/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/Policies/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Media/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Contracts/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Events/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Middleware/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Policies/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Requests/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Rules/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Partner/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Actions/WebhookHandlers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Contracts/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Controllers/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Controllers/Partner/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Events/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Listeners/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Payment/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Controllers/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/DTOs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Events/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Listeners/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Reviews/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/Support/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Search/Transformers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Translation/Contracts/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Translation/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Translation/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Translation/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Translation/Services/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Traveler/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Traveler/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Traveler/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Traveler/DTOs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Traveler/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Wishlist/Actions/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Wishlist/DTOs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Wishlist/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Domains/Wishlist/README.md

No drift detected in the checks applied to this contract.

### backend/app/Enums/README.md

No drift detected in the checks applied to this contract.

### backend/app/Events/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Components/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AdminRoleResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AdminRoleResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AdminUserResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AdminUserResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AuditLogResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AuditLogResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AuthorProfileResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AuthorProfileResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AvailabilityResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/AvailabilityResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogCategoryResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogCategoryResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogPostResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogPostResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogTagResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BlogTagResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BookingResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/BookingResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/CategoryResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/CategoryResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/ContentRevisionResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/ContentRevisionResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/DestinationResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/DestinationResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/FinancialLedgerResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/FinancialLedgerResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/GovernanceAuditResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/GovernanceAuditResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/MediaAssetResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/MediaAssetResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/PartnerResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/PartnerResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/PaymentResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/PaymentResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/ReviewResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/ReviewResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/StaticPageResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/StaticPageResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/TourResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/TourResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/TravelerResource/Pages/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Resources/TravelerResource/README.md

No drift detected in the checks applied to this contract.

### backend/app/Filament/Widgets/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Controllers/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Controllers/Public/Account/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Controllers/Public/Auth/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Controllers/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Controllers/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Middleware/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Requests/Admin/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Requests/Auth/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Requests/Public/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Requests/Public/Search/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Requests/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Resources/Concerns/README.md

No drift detected in the checks applied to this contract.

### backend/app/Http/Resources/README.md

No drift detected in the checks applied to this contract.

### backend/app/Jobs/README.md

No drift detected in the checks applied to this contract.

### backend/app/Listeners/README.md

No drift detected in the checks applied to this contract.

### backend/app/Mail/README.md

No drift detected in the checks applied to this contract.

### backend/app/Models/README.md

No drift detected in the checks applied to this contract.

### backend/app/Policies/README.md

No drift detected in the checks applied to this contract.

### backend/app/Providers/Filament/README.md

No drift detected in the checks applied to this contract.

### backend/app/Providers/README.md

No drift detected in the checks applied to this contract.

### backend/app/README.md

No drift detected in the checks applied to this contract.

### backend/app/Support/README.md

No drift detected in the checks applied to this contract.

### backend/bootstrap/README.md

No drift detected in the checks applied to this contract.

### backend/config/README.md

No drift detected in the checks applied to this contract.

### backend/database/factories/README.md

No drift detected in the checks applied to this contract.

### backend/database/migrations/README.md

No drift detected in the checks applied to this contract.

### backend/database/README.md

No drift detected in the checks applied to this contract.

### backend/database/seeders/README.md

No drift detected in the checks applied to this contract.

### backend/database/settings/README.md

No drift detected in the checks applied to this contract.

### backend/docker/README.md

No drift detected in the checks applied to this contract.

### backend/lang/en/README.md

No drift detected in the checks applied to this contract.

### backend/lang/es/README.md

No drift detected in the checks applied to this contract.

### backend/lang/it/README.md

No drift detected in the checks applied to this contract.

### backend/lang/README.md

No drift detected in the checks applied to this contract.

### backend/public/js/README.md

No drift detected in the checks applied to this contract.

### backend/public/README.md

No drift detected in the checks applied to this contract.

### backend/README.md

No drift detected in the checks applied to this contract.

### backend/resources/js/README.md

No drift detected in the checks applied to this contract.

### backend/resources/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/auth/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/booking/cancelled/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/booking/confirmed/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/booking/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/booking/voucher/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/application-received/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/approved/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/booking-cancelled/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/invitation/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/new-booking/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/reinstated/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/rejected/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/partner/suspended/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/emails/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/admin/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/components/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/infolists/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/pages/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/filament/widgets/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/README.md

No drift detected in the checks applied to this contract.

### backend/resources/views/voucher/README.md

No drift detected in the checks applied to this contract.

### backend/routes/api/README.md

No drift detected in the checks applied to this contract.

### backend/routes/README.md

No drift detected in the checks applied to this contract.

### docker/nginx/README.md

No drift detected in the checks applied to this contract.

### docker/README.md

No drift detected in the checks applied to this contract.

### frontend/README.md

No drift detected in the checks applied to this contract.

### frontend/scripts/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/forgot-password/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/login/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/partner-register/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/register/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/reset-password/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/auth/verify-email/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/partner-invite/[token]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/partner-invite/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/partner-register/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(auth)/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/analytics/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/bookings/[reference]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/bookings/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/onboarding/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/profile/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/reviews/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/[id]/availability/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/[id]/pricing/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/[id]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/create/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/partner/tours/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(partner)/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/blog/[slug]/preview/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/blog/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/blog/category/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/blog/category/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/blog/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/booking/confirmation/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/booking/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/categories/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/categories/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/destinations/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/destinations/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/pages/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/pages/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/privacy/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/search/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/terms/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/tours/[slug]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(public)/tours/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/my-bookings/[reference]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/my-bookings/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/my-reviews/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/profile/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/(traveler)/wishlist/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/[...notFound]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/[locale]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/api/auth/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/api/auth/session/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/api/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/v/[reference]/README.md

No drift detected in the checks applied to this contract.

### frontend/src/app/v/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/admin/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/auth/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/blog/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/booking/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/bookings/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/home/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/layout/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/my-bookings/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/analytics/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/bookings/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/layout/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/profile/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/reviews/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/partner/tours/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/profile/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/reviews/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/search/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/seo/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/shared/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/tour/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/traveler/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/ui/README.md

No drift detected in the checks applied to this contract.

### frontend/src/components/wishlist/README.md

No drift detected in the checks applied to this contract.

### frontend/src/hooks/README.md

No drift detected in the checks applied to this contract.

### frontend/src/i18n/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/api/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/auth/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/hooks/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/reviews/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/stores/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/stripe/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/tours/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/validators/README.md

No drift detected in the checks applied to this contract.

### frontend/src/lib/wishlist/README.md

No drift detected in the checks applied to this contract.

### frontend/src/README.md

No drift detected in the checks applied to this contract.

### frontend/src/types/README.md

No drift detected in the checks applied to this contract.
