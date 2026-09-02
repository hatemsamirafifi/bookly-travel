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
        
        # -> Type 'Hidden Gems of Rome' into the search field and click the 'Search' button to open the search results.
        # Search tours, destinations, categories... search field
        elem = page.locator('[id="search-input"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Hidden Gems of Rome")
        
        # -> Type 'Hidden Gems of Rome' into the search field and click the 'Search' button to open the search results.
        # Search button
        elem = page.get_by_role('button', name='Search', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Hidden Gems of Rome Walking Tour' result to open the tour detail page.
        # Next: 9/2/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.locator('a[href="/en/tours/hidden-gems-rome-walking-tour"]')
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The 'Hidden Gems of Rome Walking Tour' detail page is displayed after the search.
        await page.locator("xpath=/html/body/main/div/div[2]/div[1]/div/div/img").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The tour image (indicating the tour detail content) is visible on the page.
        await expect(page.locator("xpath=/html/body/main/div/div[2]/div[1]/div/div/img").nth(0)).to_be_visible(timeout=15000), "The tour image (indicating the tour detail content) is visible on the page."
        # Assert-outcome: passed
        # Assert: The current URL contains the seeded tour slug.
        await expect(page).to_have_url(re.compile("/en/tours/hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The current URL contains the seeded tour slug."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    