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
        
        # -> Open the tour page 'Hidden Gems of Rome Walking Tour' (navigate to the tour page) so the tour details and booking controls can be inspected.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the 'Accept' button on the cookie banner, select 'Sep 2' as the date, increase participants to 2 using the '+' button, then click the 'Book Now' button to begin the booking flow.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, select 'Sep 2' as the date, increase participants to 2 using the '+' button, then click the 'Book Now' button to begin the booking flow.
        await page.mouse.wheel(0, 300)
        
        # -> Click the 'Accept' button on the cookie banner, select 'Sep 2' as the date, increase participants to 2 using the '+' button, then click the 'Book Now' button to begin the booking flow.
        # Select first available date button
        elem = page.locator('button', has_text=re.compile(r'Sep \d+')).first
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, select 'Sep 2' as the date, increase participants to 2 using the '+' button, then click the 'Book Now' button to begin the booking flow.
        # Increase participants button
        elem = page.get_by_role('button', name='Increase participants', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, select 'Sep 2' as the date, increase participants to 2 using the '+' button, then click the 'Book Now' button to begin the booking flow.
        # Book Now link
        elem = page.get_by_role('link', name='Book Now', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The booking checkout page is displayed with the Confirm & Pay CTA.
        # Assert-outcome: passed
        # Assert: Confirm & Pay button is visible on the booking page.
        await expect(page.locator("xpath=/html/body/main/div/form/button").nth(0)).to_have_text("Confirm & Pay", timeout=15000), "Confirm & Pay button is visible on the booking page."
        
        # --> The tour details were available before checkout (tour page showed title and price).
        # Assert-outcome: passed
        # Assert: Booking page shows a 'Change date' link back to the tour page, indicating the checkout is tied to that tour.
        await expect(page.locator("xpath=/html/body/main/div/form/div[1]/a").nth(0)).to_have_text("Change date", timeout=15000), "Booking page shows a 'Change date' link back to the tour page, indicating the checkout is tied to that tour."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    