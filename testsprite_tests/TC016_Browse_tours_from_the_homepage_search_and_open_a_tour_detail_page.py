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
        
        # -> Open the 'Hidden Gems of Rome Walking Tour' from the Featured Tours section on the homepage.
        # Next: 9/2/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.locator('a[href="/en/tours/hidden-gems-rome-walking-tour"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner to dismiss the cookie consent dialog.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The tour detail page for 'Hidden Gems of Rome Walking Tour' is open.
        # Assert-outcome: passed
        # Assert: The URL contains the tour slug for the detail page.
        await expect(page).to_have_url(re.compile("/en/tours/hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The URL contains the tour slug for the detail page."
        
        # --> Key tour information is visible including the booking card and an available date.
        await page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[1]/a").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The booking card's 'Book Now' button is visible.
        await expect(page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[1]/a").nth(0)).to_be_visible(timeout=15000), "The booking card's 'Book Now' button is visible."
        await page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[2]/div[2]/button[1]").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: A selectable date (e.g., 'Sep 2') is visible in the date selector.
        await expect(page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[2]/div[2]/button[1]").nth(0)).to_be_visible(timeout=15000), "A selectable date (e.g., 'Sep 2') is visible in the date selector."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    