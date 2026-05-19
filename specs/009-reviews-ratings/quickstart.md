# Quickstart: Reviews & Ratings

**Feature**: 009-reviews-ratings | **Date**: 2026-05-13

## Prerequisites

- Backend running with PostgreSQL, Redis (Docker: `docker compose up -d`)
- Frontend dev server (`npm run dev`)
- Existing tour data, completed bookings, and Sanctum authentication from prior specs
- Stripe not required for review system (only uses existing payment verification)

## Backend Setup

1. Run migrations from the backend container:
```bash
docker compose exec backend php artisan migrate
```

2. Verify the new tables exist:
```bash
docker compose exec backend php artisan tinker --execute="echo \App\Domains\Reviews\Models\Review::class;"
```

3. Add profanity keyword list:
   - Ensure `backend/storage/app/profanity_keywords.json` exists (sample provided in repo).

## Frontend Setup

1. Install dependencies (already present from prior specs):
```bash
npm install
```

2. No new packages required. Review components use existing React, React Hook Form, Zod, and next-intl.

3. Start dev server:
```bash
npm run dev
```

## Testing

### Backend Tests
```bash
docker compose exec backend php artisan test --filter=Reviews
```

### Frontend Tests
```bash
cd frontend && npx jest --testPathPattern="reviews"
```

## Key Files

| File | Purpose |
|------|---------|
| `backend/app/Domains/Reviews/Actions/SubmitReviewAction.php` | Create review with validation |
| `backend/app/Domains/Reviews/Actions/EditReviewAction.php` | Edit within 48-hour window |
| `backend/app/Domains/Reviews/Services/ProfanityFilterService.php` | Keyword-based content screening |
| `backend/app/Domains/Reviews/Models/Review.php` | Eloquent model (updating event prevents edits >48h) |
| `backend/app/Domains/Reviews/Models/ReviewAuditTrail.php` | Immutable audit entries |
| `frontend/src/components/reviews/ReviewForm.tsx` | Rating selector + comment field |
| `frontend/src/components/reviews/ReviewList.tsx` | Paginated review display |
| `frontend/src/components/reviews/AggregateRating.tsx` | Average rating display with stars |

## Development Flow

1. Create migrations → run them
2. Implement backend models → services → actions → controllers
3. Register routes in `routes/api/`
4. Write Pest tests → verify all pass
5. Implement frontend components
6. Add i18n keys to `messages/{locale}.json`
7. Integrate into tour detail page and booking detail page
8. Write Jest tests → verify all pass
