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
        
        # -> Fill 'Rome' into the 'Search tours, destinations, categories...' search field and wait for suggestions to appear.
        # Search tours, destinations, categories... search field
        elem = page.get_by_role("searchbox", name="Search tours")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Rome")
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Search' button to submit the search.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Search' button to submit the search.
        # Search button
        elem = page.get_by_role("button", name="Search")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> Search results page URL includes the query for "Rome".
        # Assert-outcome: passed
        # Assert: The browser URL contains the search query '/en/search?q=Rome'.
        await expect(page).to_have_url(re.compile("/en/search\\?q=Rome"), timeout=15000), "The browser URL contains the search query '/en/search?q=Rome'."
        
        # --> A matching tour card 'Hidden Gems of Rome Walking Tour' is displayed in the results.
        await page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The Hidden Gems of Rome tour card is visible in the results.
        await expect(page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0)).to_be_visible(timeout=15000), "The Hidden Gems of Rome tour card is visible in the results."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    