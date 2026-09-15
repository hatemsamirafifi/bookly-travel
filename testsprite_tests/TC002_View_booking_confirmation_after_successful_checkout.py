import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

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
        # -> Sign in as the seeded traveler account so the authenticated booking
        #    API can be used (bookings require a Sanctum token).
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        elem = page.get_by_role("textbox", name="Email Address")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")

        elem = page.get_by_role("textbox", name="Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")

        elem = page.get_by_role("button", name="Sign In")
        await elem.click(timeout=10000)
        await expect(page).to_have_url(re.compile("/en$|/en\\?|^http://localhost:3001/$"), timeout=15000)

        # -> navigate to the tour detail page for the seeded Rome walking tour.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        # -> Dismiss the cookie banner so it cannot cover the date picker.
        elem = page.get_by_role("button", name="Accept")
        try:
            await elem.click(timeout=5000)
        except Exception:
            pass

        # -> Select the first available date button in the availability section.
        # Dates are rendered as "Wed, Sep 30"-style buttons; pick the first one.
        elem = page.locator('button', has_text=re.compile(r'(Sep|Oct|Nov|Dec) \d+')).first
        await elem.wait_for(state="visible", timeout=10000)
        await elem.click(timeout=10000)

        # -> Click the 'Book Now' button to open the booking form.
        elem = page.get_by_role("link", name="Book Now", exact=True)
        await elem.click(timeout=10000)

        # -> On the booking page, click 'Confirm & Pay' to submit the booking and
        #    reveal the payment step. The local environment uses the deterministic
        #    test gateway (PAYMENT_GATEWAY=deterministic), so no card fields appear.
        elem = page.get_by_role("button", name="Confirm & Pay")
        await elem.click(timeout=10000)

        # -> The deterministic gateway form is shown.
        await expect(
            page.get_by_text("Local test gateway. No card or external charge is used.")
        ).to_be_visible(timeout=15000)

        # -> Click 'Complete test payment' to confirm the payment.
        elem = page.get_by_role("button", name="Complete test payment")
        await elem.click(timeout=10000)

        # --> Assertions to verify final state

        # --> The browser navigated to the booking confirmation page.
        # Assert: Page URL contains '/en/booking/confirmation' with a booking reference.
        await expect(page).to_have_url(re.compile("/en/booking/confirmation\\?ref="), timeout=15000)

        # --> The confirmation heading is visible.
        await expect(
            page.get_by_role("heading", name="Booking Confirmed!")
        ).to_be_visible(timeout=15000)

        # --> The payment status is shown as confirmed on the confirmation page.
        await expect(page.get_by_text("confirmed", exact=True)).to_be_visible(timeout=15000)
        await asyncio.sleep(2)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())