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
        
        # -> Open the tour detail page for 'Hidden Gems of Rome Walking Tour' by navigating to /en/tours/hidden-gems-rome-walking-tour.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Select the date 'Sep 2' in the 'Select a Date' section, set participants to 2, and click the 'Book Now' button to proceed to checkout.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Select the date 'Sep 2' in the 'Select a Date' section, set participants to 2, and click the 'Book Now' button to proceed to checkout.
        # Sep 2 button
        elem = page.get_by_role('button', name='Wed, Sep 2', exact=True)
        await elem.click(timeout=10000)
        
        # -> Select the date 'Sep 2' in the 'Select a Date' section, set participants to 2, and click the 'Book Now' button to proceed to checkout.
        # Increase participants button
        elem = page.get_by_role('button', name='Increase participants', exact=True)
        await elem.click(timeout=10000)
        
        # -> Select the date 'Sep 2' in the 'Select a Date' section, set participants to 2, and click the 'Book Now' button to proceed to checkout.
        # Book Now link
        elem = page.get_by_role('link', name='Book Now', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The booking checkout page is displayed.
        # Assert-outcome: passed
        # Assert: The browser is on the booking checkout URL for the selected tour.
        await expect(page).to_have_url(re.compile("/en/booking\\?tour=hidden\\-gems\\-rome\\-walking\\-tour\\&participants=2\\&date=2026\\-09\\-02"), timeout=15000), "The browser is on the booking checkout URL for the selected tour."
        
        # --> The participants count on the checkout page is set to 2.
        # Assert-outcome: passed
        # Assert: Participants counter displays '2'.
        await expect(page.locator("xpath=/html/body/main/div/form/div[2]/div/span[1]").nth(0)).to_have_text("2", timeout=15000), "Participants counter displays '2'."
        
        # --> A 'Confirm & Pay' button is visible on the checkout page.
        await page.locator("xpath=/html/body/main/div/form/button").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The Confirm & Pay CTA is visible on the booking checkout page.
        await expect(page.locator("xpath=/html/body/main/div/form/button").nth(0)).to_be_visible(timeout=15000), "The Confirm & Pay CTA is visible on the booking checkout page."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    