import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

# Partner booking detail read path (PartnerBookingController::show).
#
# Fixture: BKO-TEST01 is a `confirmed` booking owned by the seeded partner
# (tour_date 2026-09-28). "Mark as completed" is intentionally NOT offered
# for it because the tour date is still in the future
# (`tour_date <= now()` is required by BookingDetail.canMarkCompleted), and
# requesting cancellation would destructively mutate a fixture other suites
# rely on. So this test covers the observable behavior: list, search, open
# the detail page, and verify the status badge the partner sees.

async def run_test():
    pw = None
    browser = None
    context = None

    try:
        # Start a Playwright session in asynchronous mode
        pw = await async_api.async_playwright().start()

        # Launch a Chromium browser in headless mode with custom arguments
        browser = await pw.chromium.launch(
            headless=True,
            args=[
                "--window-size=1280,720",
                "--disable-dev-shm-usage",
                "--ipc=host",
                "--single-process"
            ],
        )

        # Create a new browser context (like an incognito window)
        context = await browser.new_context()
        # Wider default timeout to match the agent's DOM-stability budget;
        # auto-waiting Playwright APIs (expect, locator.wait_for) inherit this.
        context.set_default_timeout(15000)

        # Open a new page in the browser context
        page = await context.new_page()

        # Interact with the page elements to simulate user flow
        # -> Sign in as the seeded partner account.
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        elem = page.get_by_role("textbox", name="Email Address")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")

        elem = page.get_by_role("textbox", name="Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")

        elem = page.get_by_role("button", name="Sign In")
        await elem.click(timeout=10000)
        await expect(page).to_have_url(re.compile("/en"), timeout=15000)

        # Wait for the auth token to be persisted before navigating on.
        for _ in range(30):
            tok = await page.evaluate("() => localStorage.getItem('auth_token')")
            if tok:
                break
            await page.wait_for_timeout(500)
        assert tok, "partner auth token was not persisted after sign in"

        # -> Open the partner menu by clicking the partner account button.
        elem = page.get_by_role("button", name="Test Partner T")
        await elem.click(timeout=10000)

        # -> Click the 'Bookings' menu item to open the partner bookings page.
        elem = page.get_by_role("menuitem", name="Bookings")
        await elem.click(timeout=10000)
        await expect(page).to_have_url(re.compile("/en/partner/bookings"), timeout=15000)

        # -> Filter the list by the fixture reference.
        elem = page.get_by_role("textbox", name="Search by reference...")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("BKO-TEST01")

        # -> The reference must be present in the bookings list.
        await expect(page.get_by_text("BKO-TEST01").first).to_be_visible(timeout=15000)

        # -> Open the booking detail page for BKO-TEST01.
        await page.goto("http://localhost:3001/en/partner/bookings/BKO-TEST01")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        # --> Assertions to verify final state

        # --> The booking detail page renders with its heading and reference.
        await expect(
            page.get_by_role("heading", name="Booking Details")
        ).to_be_visible(timeout=15000)
        await expect(page.get_by_text("BKO-TEST01").first).to_be_visible(timeout=15000)

        # --> The partner sees the booking status badge reading 'Confirmed'.
        await expect(
            page.get_by_text("Confirmed", exact=True).first
        ).to_be_visible(timeout=15000)
        await asyncio.sleep(2)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())