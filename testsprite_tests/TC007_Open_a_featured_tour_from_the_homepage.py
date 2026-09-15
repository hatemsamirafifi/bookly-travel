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
        
        # -> Click the 'Accept' button on the cookie consent banner to dismiss it.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie consent banner to dismiss it.
        # Next: 9/12/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.get_by_role("link", name="Hidden Gems of Rome Walking")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The tour detail page for 'Hidden Gems of Rome Walking Tour' is open.
        # Assert-outcome: passed
        # Assert: The browser navigated to the tour detail URL containing the tour slug.
        await expect(page).to_have_url(re.compile("/en/tours/hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The browser navigated to the tour detail URL containing the tour slug."
        
        # --> Tour booking controls and information are visible (Book Now button and participant control).
        await page.get_by_role("link", name="Book Now").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Book Now' button is visible on the tour page.
        await expect(page.get_by_role("link", name="Book Now").nth(0)).to_be_visible(timeout=15000), "The 'Book Now' button is visible on the tour page."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    