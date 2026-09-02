
# TestSprite AI Testing Report(MCP)

---

## 1️⃣ Document Metadata
- **Project Name:** bookly travel
- **Date:** 2026-09-02
- **Prepared by:** TestSprite AI Team

---

## 2️⃣ Requirement Validation Summary

#### Test TC001 View the booking checkout form
- **Test Code:** [TC001_View_the_booking_checkout_form.py](./TC001_View_the_booking_checkout_form.py)
- **Test Error:** TEST FAILURE

The checkout page was opened but did not show the booking details or payment section required to complete a purchase.

Observations:
- The page shows the header 'Complete Your Booking' and the message 'Please select a tour and date to continue.'
- No booking summary (booking reference, tour information, selected date/participants, or price breakdown) is present on the page.
- No payment section or Stripe payment placeholder is visible on the page.

- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/8f7f03bc-3b6f-4202-99c1-0cda6bd3b4ad
- **Status:** ❌ Failed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC002 Open booking checkout from the tour page
- **Test Code:** [TC002_Open_booking_checkout_from_the_tour_page.py](./TC002_Open_booking_checkout_from_the_tour_page.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/eec9aea0-da32-4824-88fc-3df5b6e38774
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC003 Complete a tour booking checkout
- **Test Code:** [TC003_Complete_a_tour_booking_checkout.py](./TC003_Complete_a_tour_booking_checkout.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/476efb7e-4a2f-42c8-8bdb-26b157718c86
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC004 View tour details and start booking
- **Test Code:** [TC004_View_tour_details_and_start_booking.py](./TC004_View_tour_details_and_start_booking.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/e18921de-c695-4406-a7e1-1bb96e172ee8
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC005 Complete a booking through payment and confirmation
- **Test Code:** [TC005_Complete_a_booking_through_payment_and_confirmation.py](./TC005_Complete_a_booking_through_payment_and_confirmation.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/b2aa0cc3-4732-49ee-8b0a-8078ce56800a
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC006 Sign in with valid credentials
- **Test Code:** [TC006_Sign_in_with_valid_credentials.py](./TC006_Sign_in_with_valid_credentials.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/4cf5404b-03b3-4e04-ac26-ef01e3f48557
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC007 View booking confirmation after checkout completion
- **Test Code:** [TC007_View_booking_confirmation_after_checkout_completion.py](./TC007_View_booking_confirmation_after_checkout_completion.py)
- **Test Error:** TEST BLOCKED

The booking confirmation page could not be reached — a 404 page was returned instead of a confirmation view.

Observations:
- Navigating to /en/booking/confirmation showed a 404 page with the text: 'This page could not be found.'
- No booking confirmation content or payment status elements are present on the page.
- The current tab URL indicates a redirect/login pattern (login?redirect=...), and the resulting page content is a 404, preventing the verification from running.

- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/74290351-26f0-426c-b51c-00555be682b4
- **Status:** BLOCKED
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC008 Log in as a traveler
- **Test Code:** [TC008_Log_in_as_a_traveler.py](./TC008_Log_in_as_a_traveler.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/a766e48c-b1bf-4368-9565-4bff4533e916
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC009 Log in and access traveler or partner areas
- **Test Code:** [TC009_Log_in_and_access_traveler_or_partner_areas.py](./TC009_Log_in_and_access_traveler_or_partner_areas.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/0d317792-dc02-4e5a-8db7-5c999623d82a
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC010 Register a new traveler account
- **Test Code:** [TC010_Register_a_new_traveler_account.py](./TC010_Register_a_new_traveler_account.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/babcea0b-af3e-4df0-b9e0-1eea7df87ed7
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC011 View the booking confirmation after payment is completed
- **Test Code:** [TC011_View_the_booking_confirmation_after_payment_is_completed.py](./TC011_View_the_booking_confirmation_after_payment_is_completed.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/69ed7ac3-c017-459d-84e6-f9c4646d28b0
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC012 Manage partner tours
- **Test Code:** [TC012_Manage_partner_tours.py](./TC012_Manage_partner_tours.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/275e2519-d354-43ca-bdfe-d6affe3c2465
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC013 Log in as an approved partner
- **Test Code:** [TC013_Log_in_as_an_approved_partner.py](./TC013_Log_in_as_an_approved_partner.py)
- **Test Error:** TEST FAILURE

Signing in as the approved partner did not open the partner area — the UI navigated to the traveler's 'My Bookings' page instead.

Observations:
- After signing in and clicking the 'Dashboard' link, the page at /en/my-bookings shows 'My Bookings' with traveler-focused actions (Browse Tours, View Wishlist, Edit Profile).
- No partner-specific dashboard UI or partner management links were visible.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/1afb2aa8-782f-4a89-9a96-7d7f21c8f815
- **Status:** ❌ Failed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC014 Find a tour through search and open its detail page
- **Test Code:** [TC014_Find_a_tour_through_search_and_open_its_detail_page.py](./TC014_Find_a_tour_through_search_and_open_its_detail_page.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/4f9da290-83a6-4eb7-ba5a-08a508f4ff0f
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC015 Create a new partner tour
- **Test Code:** [TC015_Create_a_new_partner_tour.py](./TC015_Create_a_new_partner_tour.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/dbf6657d-b984-4c55-95fd-10ad1dc65f76
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC016 Browse tours from the homepage search and open a tour detail page
- **Test Code:** [TC016_Browse_tours_from_the_homepage_search_and_open_a_tour_detail_page.py](./TC016_Browse_tours_from_the_homepage_search_and_open_a_tour_detail_page.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/aa3df197-4ff7-4d5a-920e-554c7822acaa
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC017 Create a traveler account
- **Test Code:** [TC017_Create_a_traveler_account.py](./TC017_Create_a_traveler_account.py)
- **Test Error:** TEST BLOCKED

The registration flow could not be completed because the UI is rate-limiting account creation attempts.

Observations:
- The registration form is visible with the fields populated (Full Name and Email were filled, Password entered).
- After submitting the form, a red error box displays the message: "Too Many Attempts." and no account-creation confirmation or success message appeared.

Because the UI itself is preventing new registrations with a throttling message, the test cannot proceed to verify success. A developer or test-environment reset is required to remove the rate limit or otherwise allow registration attempts to continue.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/81a0f80c-9fee-4687-8f7f-a24aedec401d
- **Status:** BLOCKED
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC018 Update a booking status
- **Test Code:** [TC018_Update_a_booking_status.py](./TC018_Update_a_booking_status.py)
- **Test Error:** TEST BLOCKED

The partner bookings list is empty — a booking cannot be opened or updated from this page.

Observations:
- The My Bookings page shows 'Total bookings 0', 'Upcoming 0', 'Completed 0'.
- The page displays the message 'No bookings yet - find a tour to start your adventure' and a 'Browse Tours' quick action; no booking entries are listed.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/715561aa-3654-4e9f-8936-62a2c2861ace
- **Status:** BLOCKED
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC019 Open booking details and download the voucher
- **Test Code:** [TC019_Open_booking_details_and_download_the_voucher.py](./TC019_Open_booking_details_and_download_the_voucher.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/d221d735-91e8-4c06-ac29-692627fe60d1
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC020 Search for tours from the homepage
- **Test Code:** [TC020_Search_for_tours_from_the_homepage.py](./TC020_Search_for_tours_from_the_homepage.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/c6cd4bc0-28b8-4635-aab5-06cd777986ef
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC021 Create and manage a partner tour listing
- **Test Code:** [TC021_Create_and_manage_a_partner_tour_listing.py](./TC021_Create_and_manage_a_partner_tour_listing.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/583d9496-1294-49e7-b8d0-ff3ff30bca87
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC022 View traveler bookings
- **Test Code:** [TC022_View_traveler_bookings.py](./TC022_View_traveler_bookings.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/61e6da55-88a4-4e91-9699-855da2d6d152
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC023 View a completed booking and retrieve its voucher
- **Test Code:** [TC023_View_a_completed_booking_and_retrieve_its_voucher.py](./TC023_View_a_completed_booking_and_retrieve_its_voucher.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/41cb9935-fe98-4ae4-ab35-a4786d15efd7
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC024 Filter and open a tour from search results
- **Test Code:** [TC024_Filter_and_open_a_tour_from_search_results.py](./TC024_Filter_and_open_a_tour_from_search_results.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/f7d8d5f7-77ba-474f-8b78-01de92397623
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC025 Change a partner booking status
- **Test Code:** [TC025_Change_a_partner_booking_status.py](./TC025_Change_a_partner_booking_status.py)
- **Test Error:** TEST BLOCKED

The test could not be run — no partner booking records are present to open or update.

Observations:
- The 'My Bookings' page shows 'Total bookings 0' and the message 'No bookings yet - find a tour to start your adventure'.
- No booking list items or controls were visible on the page to open a booking record or change its status.
- There is no UI on this page to create/import bookings as a partner, so the prerequisite booking data required for the test is not available.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/3a801ace-8455-4229-b742-4d96173b020f
- **Status:** BLOCKED
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC026 View and update partner bookings
- **Test Code:** [TC026_View_and_update_partner_bookings.py](./TC026_View_and_update_partner_bookings.py)
- **Test Error:** TEST BLOCKED

The test could not be run — no booking record is available on the partner bookings page to open and modify.

Observations:
- The My Bookings page shows zero bookings (Total bookings 0, Upcoming 0, Completed 0, Cancelled 0) and displays 'No bookings yet - find a tour to start your adventure'.
- No booking list items or booking rows are present on the page and there is no partner-facing UI to create a booking.
- A booking record is required to change status; without seeded booking data for this partner the test cannot proceed.
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/4e4859da-a56d-4f0f-be98-fca76e24e244
- **Status:** BLOCKED
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC027 Open booking details from the traveler bookings list
- **Test Code:** [TC027_Open_booking_details_from_the_traveler_bookings_list.py](./TC027_Open_booking_details_from_the_traveler_bookings_list.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/2927b134-b749-4f79-ad4a-65a5901dc7ea
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC028 Update partner tour details
- **Test Code:** [TC028_Update_partner_tour_details.py](./TC028_Update_partner_tour_details.py)
- **Test Error:** TEST FAILURE

The updated tour details saved in the partner admin, but the public tour page did not show the updated title or description after reload.

Observations:
- The partner edit flow showed a success banner after saving the tour details.
- After reloading the public tour page (Hidden Gems of Rome Walking Tour), the title remains 'Hidden Gems of Rome Walking Tour' and the 'About This Tour' description shows the original text ('Discover the secret corners of Rome...').
- A JavaScript error banner ('Cannot read properties of undefined (reading "forEach")') was present earlier on the edit page and may have prevented the public page from reflecting the saved changes.

- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/99e8ef98-7df8-4a5c-ac7d-15e37554a297
- **Status:** ❌ Failed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC029 Browse featured tours from the homepage
- **Test Code:** [TC029_Browse_featured_tours_from_the_homepage.py](./TC029_Browse_featured_tours_from_the_homepage.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/37f45921-d5f6-4d00-bd52-c2db2d6e7dd1
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---

#### Test TC030 View partner bookings list
- **Test Code:** [TC030_View_partner_bookings_list.py](./TC030_View_partner_bookings_list.py)
- **Test Visualization and Result:** https://www.testsprite.com/dashboard/mcp/tests/95d36e8b-83a1-564e-bd15-b8f309cc263f/test/fd615f79-39d6-4feb-b622-ffe672962e07
- **Status:** ✅ Passed
- **Analysis / Findings:** {{TODO:AI_ANALYSIS}}.
---


## 3️⃣ Coverage & Matching Metrics

- **73.33** of tests passed

| Requirement        | Total Tests | ✅ Passed | ❌ Failed  |
|--------------------|-------------|-----------|------------|
| ...                | ...         | ...       | ...        |
---


## 4️⃣ Key Gaps / Risks
{AI_GNERATED_KET_GAPS_AND_RISKS}
---