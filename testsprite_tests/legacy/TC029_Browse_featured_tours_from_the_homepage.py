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
        
        # -> Click the featured tour card 'Hidden Gems of Rome Walking Tour' to open its detail page.
        # Next: 9/2/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.locator('a[href="/en/tours/hidden-gems-rome-walking-tour"]')
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> A featured tour card could be opened and its detail page for 'Hidden Gems of Rome Walking Tour' was displayed with the expected title.
        # Assert-outcome: passed
        # Assert: The browser navigated to the tour detail page URL for the seeded tour.
        await expect(page).to_have_url(re.compile("/en/tours/hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The browser navigated to the tour detail page URL for the seeded tour."
        # Assert-outcome: passed
        await expect(page.locator("xpath=/html/body/main/div/div[2]/div[1]/div/div/img").nth(0)).to_have_attribute("alt", re.compile(r"Hidden Gems of Rome Walking Tour"), timeout=15000), "The tour title is present as the image alt text on the detail page."
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    