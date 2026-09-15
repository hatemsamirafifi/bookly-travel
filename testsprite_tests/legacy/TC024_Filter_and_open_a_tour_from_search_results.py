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
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Search' link in the header to go to the search page.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Search' link in the header to go to the search page.
        # Search link
        elem = page.get_by_role('link', name='Search', exact=True)
        await elem.click(timeout=10000)
        
        # -> Fill 'Rome' into the search field and click the 'Search' button to apply the keyword filter.
        # Search tours, destinations, categories... search field
        elem = page.locator('[id="search-input"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Rome")
        
        # -> Fill 'Rome' into the search field and click the 'Search' button to apply the keyword filter.
        # Search button
        elem = page.get_by_role('button', name='Search', exact=True)
        await elem.click(timeout=10000)
        
        # -> Expand the 'Available Date' filter in the Filters panel so the date picker/inputs become visible.
        # Available Date button
        elem = page.get_by_role('button', name='Available Date', exact=True)
        await elem.click(timeout=10000)
        
        # -> Set the 'Available Date' filter to 2026-09-10 using the Available Date date picker.
        # Filter by available date date field
        elem = page.get_by_label('Filter by available date', exact=True)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("2026-09-10")
        
        # -> Set the Maximum price to '50' using the 'Maximum price' field and then open the 'Hidden Gems of Rome Walking Tour' result card.
        # Maximum price number field
        elem = page.get_by_label('Maximum price', exact=True)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("50")
        
        # -> Open the 'Hidden Gems of Rome Walking Tour' result card.
        elem = page.locator('a[href*="/tours/hidden-gems-rome-walking-tour"]').first
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The 'Hidden Gems of Rome Walking Tour' detail page is displayed with booking information.
        # Assert-outcome: passed
        # Assert: The browser is on the tour detail URL for the hidden-gems-rome-walking-tour.
        await expect(page).to_have_url(re.compile("/en/tours/hidden\\-gems\\-rome\\-walking\\-tour"), timeout=15000), "The browser is on the tour detail URL for the hidden-gems-rome-walking-tour."
        await page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[1]/a").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Book Now' button is visible on the tour detail page.
        await expect(page.locator("xpath=/html/body/main/div/div[2]/div[2]/div[1]/a").nth(0)).to_be_visible(timeout=15000), "The 'Book Now' button is visible on the tour detail page."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    