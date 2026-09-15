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
        # -> navigate
        await page.goto("http://localhost:3001")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Open the tour page for 'Hidden Gems of Rome Walking Tour' by navigating to /en/tours/hidden-gems-rome-walking-tour.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Book Now' button on the tour page to begin checkout.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Book Now' button on the tour page to begin checkout.
        # Book Now link
        elem = page.get_by_role("link", name="Book Now")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The tour detail page for 'hidden-gems-rome-walking-tour' was opened and showed the title, price, description, and reviews.
        # Assert-outcome: passed
        # Assert: The 'Change date' link href points to the tour's detail page.
        await expect(page.get_by_role("link", name="Change date").nth(0)).to_have_attribute("href", "/en/tours/hidden-gems-rome-walking-tour", timeout=15000), "The 'Change date' link href points to the tour's detail page."
        
        # --> The booking checkout page for 'hidden-gems-rome-walking-tour' is displayed and shows a Confirm & Pay CTA.
        # Assert-outcome: passed
        # Assert: The browser navigated to the booking URL for the selected tour.
        await expect(page).to_have_url(re.compile("/en/booking\\?tour=hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The browser navigated to the booking URL for the selected tour."
        # Assert-outcome: passed
        # Assert: The booking page displays a 'Confirm & Pay' button.
        await expect(page.locator("xpath=/html/body/main/div/form/button").nth(0)).to_have_text("Confirm & Pay", timeout=15000), "The booking page displays a 'Confirm & Pay' button."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    