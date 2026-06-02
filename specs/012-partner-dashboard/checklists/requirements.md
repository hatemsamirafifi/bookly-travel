# Partner Dashboard Implementation Checklist

## Foundation
- [x] Configure Next.js route group `(partner)`
- [x] Create PartnerAuthGuard to protect routes
- [x] Implement Partner Layout (Sidebar + Header)
- [x] Set up API client with Bearer token injection

## Dashboard Overview
- [ ] Build Analytics Summary Cards component
- [ ] Build Bookings Chart component
- [ ] Build Notifications Badge & Panel
- [ ] Assemble Dashboard page

## Tour Management
- [ ] Build Tour List with status filters
- [ ] Build Tour Wizard (Basic Info step)
- [ ] Build Tour Wizard (Media step with signed upload)
- [ ] Build Tour Wizard (Pricing step)
- [ ] Build Tour Wizard (Availability step)
- [ ] Build Tour Wizard (SEO step)
- [ ] Implement save draft logic
- [ ] Implement submit for review logic

## Booking Management
- [ ] Build Booking List with filters
- [ ] Build Booking Detail view
- [ ] Implement "Mark Completed" action
- [ ] Implement "Request Cancellation" modal

## Review Management
- [ ] Build Review List
- [ ] Build Response form
- [ ] Wire up submit/edit response logic

## Profile & Settings
- [ ] Build Profile form (company info)
- [ ] Build Notification Settings toggles
- [ ] Build Payout Info form with IBAN validation

## Final Polish
- [ ] Ensure WCAG 2.1 AA accessibility (Lighthouse test)
- [ ] Verify i18n coverage (EN/ES/IT)
- [ ] Verify mobile responsiveness (390px, 780px)
- [ ] Run Playwright E2E tests
