
# TestSprite AI Testing Report(MCP)

---

## 1️⃣ Document Metadata
- **Project Name:** bookly travel
- **Date:** 2026-09-12
- **Prepared by:** TestSprite AI Team

---

## 2️⃣ Requirement Validation Summary

#### Test TC001 Sign in with valid traveler credentials
- **Test Code:** [TC001_Sign_in_with_valid_traveler_credentials.py](./TC001_Sign_in_with_valid_traveler_credentials.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/317b08c7-9b80-4089-acdb-73efb5161423
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC002 View booking confirmation after successful checkout
- **Test Code:** [TC002_View_booking_confirmation_after_successful_checkout.py](./TC002_View_booking_confirmation_after_successful_checkout.py)
- **Test Error:** TEST FAILURE

The checkout flow could not be completed — the contact and payment form failed to load after attempting to continue to payment.

Observations:
- The booking page displays an inline error: 'Something went wrong. Please try again.' above the 'Confirm & Pay' button.
- Clicking 'Confirm & Pay' did not reveal contact or payment input fields; only 2 input/select/textarea elements were found on the page and no payment fields or contact form were visible.
- No booking confirmation, booking reference, or payment status was shown (the flow did not reach the confirmation step).

Because the contact/payment form could not be reached due to the inline error, the test cannot proceed to create a booking or verify a confirmation. This is a functional failure of the checkout flow as presented in the UI.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/66286b2e-f573-453d-b02a-d7901ffee53b
- **Status:** ❌ Failed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC003 Register a traveler account
- **Test Code:** [TC003_Register_a_traveler_account.py](./TC003_Register_a_traveler_account.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/d8c6b471-63c4-45c3-9b7d-76ec3b4ebe31
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC004 Search tours from the homepage
- **Test Code:** [TC004_Search_tours_from_the_homepage.py](./TC004_Search_tours_from_the_homepage.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/5e87de3a-94ca-4882-810b-d45ba6536591
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC005 View tour details and start booking
- **Test Code:** [TC005_View_tour_details_and_start_booking.py](./TC005_View_tour_details_and_start_booking.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/aea8fae7-e4d5-4129-a698-bc784fdf3715
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC006 Review booking summary before paying
- **Test Code:** [TC006_Review_booking_summary_before_paying.py](./TC006_Review_booking_summary_before_paying.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/0a14658b-529e-4758-865c-203a4a9e6b00
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC007 Open a featured tour from the homepage
- **Test Code:** [TC007_Open_a_featured_tour_from_the_homepage.py](./TC007_Open_a_featured_tour_from_the_homepage.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/318a631b-9e43-43f3-9dc2-d062a0c34e0e
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC008 See invalid sign-in feedback
- **Test Code:** [TC008_See_invalid_sign_in_feedback.py](./TC008_See_invalid_sign_in_feedback.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/d9f27299-57c1-48d1-9dc3-a945e8130635
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC009 Create a new partner account and see onboarding status
- **Test Code:** [TC009_Create_a_new_partner_account_and_see_onboarding_status.py](./TC009_Create_a_new_partner_account_and_see_onboarding_status.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/6f4611be-abd8-40ec-a1f1-29376a808f30
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC010 Search tours with filters and sorting
- **Test Code:** [TC010_Search_tours_with_filters_and_sorting.py](./TC010_Search_tours_with_filters_and_sorting.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/184acd88-68f7-4569-971a-a8efc5be14af
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC011 Reach checkout from tour detail with selected trip options
- **Test Code:** [TC011_Reach_checkout_from_tour_detail_with_selected_trip_options.py](./TC011_Reach_checkout_from_tour_detail_with_selected_trip_options.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/dedc1d23-c976-40c4-ae47-273006bdd692
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC012 Open the partner dashboard and review workspace summary
- **Test Code:** [TC012_Open_the_partner_dashboard_and_review_workspace_summary.py](./TC012_Open_the_partner_dashboard_and_review_workspace_summary.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/f49cebfd-5d19-4333-be77-52e9b5a04856
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC013 View booking history and voucher access
- **Test Code:** [TC013_View_booking_history_and_voucher_access.py](./TC013_View_booking_history_and_voucher_access.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/5f1e8325-fd92-45bd-94c1-b0495b67c385
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC014 Update a booking status as a partner
- **Test Code:** [TC014_Update_a_booking_status_as_a_partner.py](./TC014_Update_a_booking_status_as_a_partner.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/95d2a0e6-6531-46ad-8bbd-40cc18406bcc
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC015 Save a tour to wishlist from tour detail
- **Test Code:** [TC015_Save_a_tour_to_wishlist_from_tour_detail.py](./TC015_Save_a_tour_to_wishlist_from_tour_detail.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/318c2d0b-18c3-49fe-8a70-97e0cb163225
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---


## 3️⃣ Coverage & Matching Metrics

- **93.33** of tests passed

| Requirement        | Total Tests | ✅ Passed | ❌ Failed  |
|--------------------|-------------|-----------|------------|
| ...                | ...         | ...       | ...        |
---


## 4️⃣ Key Gaps / Risks
{AI_GNERATED_KET_GAPS_AND_RISKS}
---