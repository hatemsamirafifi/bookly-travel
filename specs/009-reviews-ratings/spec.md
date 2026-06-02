# Feature Specification: Reviews & Ratings

**Feature Branch**: `009-reviews-ratings`  
**Created**: 2026-05-13  
**Status**: Draft  
**Input**: User description: "phase 9"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Submit a Review After Tour Completion (Priority: P1)

A traveler who has completed a booked tour wants to share their experience by submitting a rating and written review. They navigate to their booking history, select a completed tour, and fill out a review form with a star rating (1-5) and optional written feedback. The review is published and visible to other travelers browsing that tour.

**Why this priority**: Reviews are the core social proof mechanism that builds trust in the marketplace. Without reviews, travelers have no way to evaluate tour quality from peer experiences, which directly impacts booking conversion rates.

**Independent Test**: Can be fully tested by completing a booking, navigating to the booking detail page, and submitting a review. Delivers immediate value as the review appears on the tour detail page for other users.

**Acceptance Scenarios**:

1. **Given** a traveler has a booking with status "completed", **When** they navigate to the booking detail page and click "Write a Review", **Then** they see a review form with a 5-star rating selector and a text field for written feedback.
2. **Given** the traveler fills out the review form with a 4-star rating and a comment, **When** they submit the form, **Then** the review is saved, linked to both the booking and the tour, and a success confirmation is displayed.
3. **Given** a traveler with a booking not yet marked "completed", **When** they navigate to the booking detail page, **Then** the "Write a Review" option is not available.
4. **Given** a traveler has already submitted a review for a specific booking, **When** they return to that booking's detail page, **Then** they see their existing review and cannot submit a second one for the same booking.
5. **Given** a traveler submits a rating without written feedback, **When** the review is saved, **Then** the rating-only review is accepted and displayed with the star rating but no comment text.
6. **Given** a traveler submitted a review less than 48 hours ago, **When** they return to the booking detail page, **Then** they see an "Edit Review" button and can modify their rating and comment.
7. **Given** a traveler submitted a review more than 48 hours ago, **When** they return to the booking detail page, **Then** they see their review but the "Edit Review" option is no longer available.
8. **Given** a traveler edits their review, **When** the updated review is saved, **Then** it shows an "Edited" indicator alongside the original submission date.
9. **Given** a traveler has a completed booking with a tour date more than 30 days in the past, **When** they navigate to the booking detail page, **Then** the "Write a Review" option is not available.

---

### User Story 2 - View Reviews on Tour Detail Page (Priority: P1)

A traveler browsing tours wants to read reviews from previous participants to help them decide which tour to book. The tour detail page displays an aggregate rating (average stars) and a paginated list of individual reviews showing the reviewer's name, rating, date, and comment.

**Why this priority**: Reviews are useless if they can't be seen. Displaying reviews on the tour detail page is the primary discovery mechanism and directly enables informed booking decisions.

**Independent Test**: Can be fully tested by visiting a tour detail page that has reviews and verifying the rating summary and review list render correctly. Delivers standalone value even without the submission flow.

**Acceptance Scenarios**:

1. **Given** a tour has 10 reviews with an average rating of 4.2, **When** a traveler views the tour detail page, **Then** they see "4.2" displayed prominently with a visual star representation and the total review count "10 reviews".
2. **Given** the review section is loaded, **When** the traveler scrolls through the review list, **Then** each review shows the reviewer's first name, star rating, comment text (if provided), and submission date.
3. **Given** a tour has more than 5 reviews, **When** the traveler reaches the bottom of the initial review list, **Then** they can load more reviews (pagination or "Load More").
4. **Given** a tour has zero reviews, **When** a traveler views the tour detail page, **Then** they see a message indicating no reviews yet (e.g., "No reviews yet. Be the first!").

---

### User Story 3 - Partner Views Their Tour Reviews (Priority: P2)

A tour partner wants to see how travelers are rating their tours. They access a reviews dashboard showing all reviews for their tours, with aggregate ratings per tour and the ability to read individual reviews.

**Why this priority**: Partners need feedback to improve their tours. This is secondary to the traveler-facing review flow but essential for marketplace quality over time.

**Independent Test**: Can be tested by logging in as a partner, navigating to the reviews section, and verifying that only reviews for their tours appear, with correct aggregate calculations.

**Acceptance Scenarios**:

1. **Given** a partner has 3 tours with reviews, **When** they access their reviews dashboard, **Then** they see a summary listing each tour with its average rating and review count.
2. **Given** the partner clicks on a specific tour, **When** the detail view loads, **Then** they see all reviews for that tour ordered by most recent first.
3. **Given** a partner views a review, **When** they read the content, **Then** the reviewer's full name is not exposed (only first name and last initial).

---

### User Story 4 - Admin Moderates Reviews (Priority: P3)

An admin needs the ability to view and moderate reviews to ensure platform content quality. They can view all reviews across the platform, hide inappropriate reviews, and reinstate previously hidden ones.

**Why this priority**: Content moderation is important for platform trust but can start with reactive moderation (user reports) rather than proactive review. P3 reflects that the platform can launch with reviews visible and add moderation gradually.

**Independent Test**: Can be tested by logging in as an admin, accessing the review management interface, hiding a review, and verifying it no longer appears on the public tour detail page.

**Acceptance Scenarios**:

1. **Given** an admin accesses the review management panel, **When** they view the review list, **Then** all reviews across all tours are visible with filtering options (by tour, by status, by date range).
2. **Given** an admin identifies an inappropriate review, **When** they select "Hide", **Then** the review status changes to "hidden" and it no longer appears on the public tour detail page.
3. **Given** a review was hidden in error, **When** the admin selects "Reinstate", **Then** the review becomes visible on the tour detail page again.
4. **Given** moderation actions have been taken on a review, **When** an admin views the review detail in the management panel, **Then** they see a chronological audit trail showing who performed each action, what was done, when, and the optional reason.
5. **Given** a review contains content matching the profanity filter, **When** it is submitted, **Then** it appears in the admin review list with a "Flagged" status indicator, is filterable by flagged status, and remains publicly visible on the tour detail page until an admin chooses to hide it.

---

### Edge Cases

- What happens when a traveler's booking status changes from "completed" back to another status (e.g., due to a dispute)? Existing reviews remain but the traveler cannot submit additional reviews for that booking.
- How does the system handle a traveler submitting a review with extremely long text? Input must be capped at a reasonable character limit (e.g., 2000 characters).
- What happens when a tour is deleted or unpublished? Reviews remain in the database but are not publicly displayed while the tour is inactive. They reappear if the tour is republished.
- What happens when a traveler account is deleted? Their reviews are anonymized (shown as "Anonymous Traveler") but preserved to maintain rating integrity.
- How does the system prevent review manipulation (e.g., a partner creating fake bookings to review their own tours)? Only verified completed bookings with payment records can be reviewed.
- What happens when a review contains profanity or inappropriate content? An automated keyword filter scans the content on submission. If matched, the review is published immediately but flagged for admin attention. Admins can review flagged content from the management panel and decide to hide or keep it.
- What happens if a traveler attempts to edit a review after the 48-hour window? The system denies the edit and returns an error indicating the editing window has closed.
- When a review is edited, does it reset the aggregate rating? Yes — the aggregate rating recalculates immediately using the updated rating value. The edit history is preserved in the audit trail but not publicly visible.
- What happens if a traveler attempts to submit a review after the 30-day window? The system returns a validation error: "The review submission window has closed." The booking detail page shows the expired window and hides the review form.
- What happens when a traveler exceeds the review submission rate limit? The system returns HTTP 429 with a retry-after header. The UI displays a message: "Too many reviews submitted. Please wait before submitting another."

## Clarifications

### Session 2026-05-13

- Q: Can travelers edit or delete their review after submission? → A: Allow editing within 48 hours of submission, no deletion.
- Q: Is there a time limit after tour completion for submitting a review? → A: 30 days after tour date. Reviews must be submitted within 30 days of completion.
- Q: Should review submissions be rate-limited to prevent spam/abuse? → A: Rate limit of 10 reviews per hour per traveler.
- Q: Should admin moderation actions (hide/reinstate reviews) be logged in an audit trail? → A: Log all moderation actions with admin ID, timestamp, action, and optional reason.
- Q: How should the profanity/inappropriate content flagging work? → A: Automated keyword-based profanity filter flags reviews for admin review; publishes immediately (post-moderation).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow travelers to submit exactly one review per completed booking, consisting of a mandatory rating (1-5 stars) and an optional written comment (max 2000 characters).
- **FR-002**: System MUST validate that a traveler can only review a booking if: (a) the booking belongs to them, (b) the booking status is "completed", (c) the tour date is within the last 30 days, and (d) they have not already submitted a review for that booking.
- **FR-003**: System MUST calculate and display an aggregate rating (average of all publicly displayed reviews — status = visible or flagged) and total review count for each tour.
- **FR-004**: System MUST display paginated reviews on the tour detail page, each showing: reviewer first name, star rating, comment (if provided), and submission date.
- **FR-005**: System MUST allow partners to view reviews and aggregate ratings for their own tours only.
- **FR-006**: System MUST allow admins to view all reviews, filter by tour/status/date/flagged, and hide or reinstate individual reviews.
- **FR-007**: System MUST preserve reviews when a traveler account is deleted, anonymizing the reviewer name to "Anonymous Traveler" while retaining the rating and comment.
- **FR-008**: System MUST prevent reviews for bookings that lack a confirmed payment record, ensuring only genuine customers can leave reviews.
- **FR-009**: System MUST cap review comment length at 2000 characters and return a validation error for submissions exceeding this limit.
- **FR-010**: System MUST support multi-language review content — reviews submitted by travelers in their locale are stored with a locale tag and displayed to viewers in their original language.
- **FR-011**: System MUST allow travelers to edit their own review (rating and comment) within 48 hours of the original submission. After 48 hours, reviews become immutable. Deletion is never permitted. Edited reviews MUST display an "Edited" indicator.
- **FR-012**: System MUST enforce a rate limit of 10 review submissions per hour per traveler, returning a 429 status when exceeded.
- **FR-013**: System MUST log every admin moderation action (hide, reinstate) in an audit trail recording: admin ID, timestamp, action type, review ID, and optional reason. This log must be visible to other admins from the review management panel.
- **FR-014**: System MUST apply an automated keyword-based profanity filter to review submissions. Flagged reviews are published immediately but marked for admin attention in the review management panel with a "Flagged" status indicator.

### Key Entities *(include if feature involves data)*

- **Review**: Represents a traveler's rating and feedback for a completed tour. Key attributes: rating (1-5), comment (optional text), status (visible/hidden), locale, timestamps. Linked to a single Booking and Tour. A booking can have at most one review.
- **Tour Aggregate Rating**: A derived view of a tour's review summary: average rating, total review count, and rating distribution (count per star level). Updated whenever a review is added, hidden, or reinstated.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can submit a review in under 2 minutes from the booking detail page. (Qualitative UX target; not gated by automated testing.)
- **SC-002**: Tour detail pages display review data (aggregate rating + review list) in under 3 seconds.
- **SC-003**: At least 20% of completed bookings result in a submitted review within 30 days of tour completion. (Post-launch business KPI tracked via analytics; not a build-time gate.)
- **SC-004**: Partners can see all reviews for their tours in a single consolidated view without needing admin assistance.
- **SC-005**: Admins can moderate (hide/reinstate) a review with 2 clicks from the review management interface. (Qualitative UX target; not gated by automated testing.)
- **SC-006**: No review can be submitted without a verified completed booking and payment record (zero fraudulent reviews).

## Assumptions

- The existing booking status "completed" exists and is reliably set when a tour date has passed and the booking was confirmed. If this status transition is not yet automated, it is assumed the backend will mark bookings as "completed" after the tour date passes.
- The tour detail page already exists (from spec 006) and has a designated section where the review component can be integrated.
- Review submission uses the existing Sanctum-authenticated API layer — no new authentication infrastructure is needed.
- Star ratings use a standard 1-5 integer scale (no half-stars in v1).
- Review moderation follows a post-moderation model: reviews are published immediately and can be hidden retroactively by admins.
- Email notifications for new reviews (to partners) are out of scope for v1 and can be added in a future enhancement.
- Reporting functionality (flagging reviews as inappropriate by users) is out of scope for v1; admins handle moderation reactively.
