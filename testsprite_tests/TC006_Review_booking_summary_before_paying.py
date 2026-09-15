import asyncio
import datetime
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
        # -> Open the seeded tour detail page directly.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        # -> Dismiss the cookie banner so it cannot intercept later clicks.
        elem = page.get_by_role("button", name="Accept")
        try:
            await elem.click(timeout=5000)
        except Exception:
            pass

        # -> Select the first available date in the 'Select a Date' section.
        # Dates render as "Sep 15"-style buttons; a hardcoded date would rot,
        # so pick whatever availability the backend currently offers.
        elem = page.locator('button', has_text=re.compile(r'(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d+')).first
        await elem.wait_for(state="visible", timeout=10000)
        await elem.click(timeout=10000)

        # -> Increase participants to 2 using the '+' button.
        elem = page.get_by_role("button", name="Increase participants")
        await elem.click(timeout=10000)

        # -> Click 'Book Now' to open the booking summary page.
        elem = page.get_by_role("link", name="Book Now", exact=True)
        await elem.click(timeout=10000)

        # --> Assertions to verify final state (all BEFORE any payment step)

        # --> The booking page URL carries the tour, party size, and date.
        await expect(page).to_have_url(re.compile("/en/booking"), timeout=15000)
        url = page.url
        assert "tour=hidden-gems-rome-walking-tour" in url, f"tour slug missing from {url}"
        assert "participants=2" in url, f"participant count missing from {url}"
        date_match = re.search(r"date=(\d{4}-\d{2}-\d{2})", url)
        assert date_match, f"no date query parameter in {url}"
        # The carried date must not be stale: the backend advertises its own
        # next bookable date, which is strictly future in backend time but
        # can equal the runner host's calendar date across timezone day
        # boundaries, so compare with >= (a rotted hardcoded date still fails).
        assert date_match.group(1) >= datetime.date.today().isoformat(), (
            f"date {date_match.group(1)} is in the past"
        )

        # --> The booking summary shows the selected date with a way to change it.
        await expect(
            page.get_by_text("Selected Date").first
        ).to_be_visible(timeout=15000)
        await expect(
            page.get_by_role("link", name="Change date").first
        ).to_be_visible(timeout=15000)

        # --> The booking summary shows the selected participant count.
        await expect(
            page.locator('[aria-labelledby="participants-label"]').nth(0)
        ).to_have_text("2", timeout=15000)

        # --> The price breakdown with the total is shown before payment.
        await expect(
            page.get_by_text(re.compile("Price Breakdown", re.IGNORECASE)).first
        ).to_be_visible(timeout=15000)
        await expect(
            page.get_by_text(re.compile("^Total$", re.IGNORECASE)).first
        ).to_be_visible(timeout=15000)

        # --> The 'Confirm & Pay' action is offered to proceed to payment.
        await expect(
            page.get_by_role("button", name=re.compile("Confirm & Pay", re.IGNORECASE))
        ).to_be_visible(timeout=15000)
        await asyncio.sleep(2)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())