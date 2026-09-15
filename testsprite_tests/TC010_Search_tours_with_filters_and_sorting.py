import asyncio
import datetime
import re
from playwright import async_api
from playwright.async_api import expect

# Search with filters and sorting against the REAL backend (seeded tour:
# hidden-gems-rome-walking-tour, Rome, EUR 45.00, bookable from tomorrow).
#
# The generator used a fixed past date (2026-09-12) the date input clamps
# away, and selected the empty sort option. This version drives the filters
# the way a real user can: tomorrow's date, a max-price ceiling, and the
# documented 'price_asc' sort value.

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

        # A bookable date: tomorrow (the filter input enforces min=today and
        # the booking API rejects today, so only a future date yields results).
        tomorrow = datetime.date.today() + datetime.timedelta(days=1)
        iso_date = tomorrow.isoformat()

        # Interact with the page elements to simulate user flow
        # -> Open the search page and dismiss the cookie banner.
        await page.goto("http://localhost:3001/en/search")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        elem = page.get_by_role("button", name="Accept")
        try:
            await elem.click(timeout=5000)
        except Exception:
            pass

        # -> Fill 'Rome' into the search field and run the search.
        elem = page.get_by_role("searchbox", name="Search tours")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Rome")

        elem = page.get_by_role("button", name="Search")
        await elem.click(timeout=10000)

        # -> Open the 'Available Date' filter section and set tomorrow.
        # NOTE: each filter drives a client-side navigation, so wait for the
        # URL to absorb one filter before touching the next control;
        # otherwise a fill can land mid-navigation and its change is lost.
        elem = page.get_by_role("button", name="Available Date")
        await elem.click(timeout=10000)

        elem = page.get_by_role("textbox", name="Filter by available date")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill(iso_date)
        await expect(page).to_have_url(re.compile(f"date={iso_date}"), timeout=15000)

        # -> Cap the price at 50 (the Rome tour costs 45.00, so it survives).
        elem = page.get_by_role("spinbutton", name="Maximum price")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("50")
        await expect(page).to_have_url(re.compile("price_max=50"), timeout=15000)

        # -> Sort 'Price: Low to High' (the control's real option value).
        # This applies immediately through its own navigation; do NOT click
        # the header 'Search' button afterwards — that submits only the
        # query text and would reset the filter parameters.
        elem = page.get_by_label("Sort by:")
        await elem.select_option("price_asc")
        await expect(page).to_have_url(re.compile("sort=price_asc"), timeout=15000)

        # --> Assertions to verify final state

        # --> Filtered results still include the Rome walking tour.
        await expect(page.locator("#main-content").nth(0)).to_contain_text(
            "Hidden Gems of Rome", timeout=15000
        )

        # --> The filter parameters survived in the URL (nothing reset them).
        await expect(page).to_have_url(re.compile(f"date={iso_date}"), timeout=15000)
        await expect(page).to_have_url(re.compile("price_max=50"), timeout=15000)
        await expect(page).to_have_url(re.compile("sort=price_asc"), timeout=15000)

        # --> The search input still carries the query 'Rome'.
        await expect(
            page.get_by_role("searchbox", name="Search tours").nth(0)
        ).to_have_value("Rome", timeout=15000)

        # --> The sort control kept the requested 'Price: Low to High' choice.
        await expect(page.get_by_label("Sort by:").nth(0)).to_have_value(
            "price_asc", timeout=15000
        )
        await asyncio.sleep(2)
    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())