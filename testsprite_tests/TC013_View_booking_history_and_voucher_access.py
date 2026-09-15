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
        
        # -> Click the 'Sign In' link (after accepting the cookie banner) to open the login page.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Sign In' link (after accepting the cookie banner) to open the login page.
        # Sign In link
        elem = page.get_by_role("link", name="Sign In")
        await elem.click(timeout=10000)
        
        # -> Fill the Email Address and Password fields and click the 'Sign In' button to submit the login form.
        # you@example.com email field
        elem = page.get_by_role("textbox", name="Email Address")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")
        
        # -> Fill the Email Address and Password fields and click the 'Sign In' button to submit the login form.
        # password password field
        elem = page.get_by_role("textbox", name="Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill the Email Address and Password fields and click the 'Sign In' button to submit the login form.
        # Sign In button
        elem = page.get_by_role("button", name="Sign In")
        await elem.click(timeout=10000)
        
        # -> Open the user menu by clicking the 'Test User' button in the header so the 'My Bookings' option can be selected.
        # Test User T button
        elem = page.get_by_role("button", name="Test User T")
        await elem.click(timeout=10000)
        
        # -> Click the 'My Bookings' item in the user menu to open the bookings list page.
        # My Bookings link
        elem = page.get_by_role("menuitem", name="My Bookings")
        await elem.click(timeout=10000)
        
        # -> Open the confirmed booking card labeled 'Tour confirmed Rome, Italy 12/1/2026' to view its booking details.
        # Tour confirmed Rome, Italy 12/1/2026 20... link
        elem = page.get_by_role("link", name="Tour Tour confirmed Rome, Italy 12/1/2026 20 participants €")
        await elem.click(timeout=10000)
        
        # -> Click the 'Download PDF Voucher' button and verify the voucher is accessible.
        # Download: Download PDF Voucher button
        elem = page.get_by_role("button", name="Download PDF Voucher")
        async with page.expect_download(timeout=30000) as dl_info:
            await elem.click(timeout=10000)
        download = await dl_info.value
        assert download.suggested_filename  # verify file was downloaded
        await download.save_as(f"./downloads/{download.suggested_filename}")
        
        # -> Final action — this is where the agent failed
        # Error observed by agent: Failed to click element 1447: Event handler browser_use.browser.watchdog_base.DefaultActionWatchdog.on_ClickElementEvent#0160(?▶ ClickElementEvent#25c0 🏃) timed out after 15.0s
        # Download: Download PDF Voucher button
        elem = page.get_by_role("button", name="Download PDF Voucher")
        async with page.expect_download(timeout=30000) as dl_info:
            await elem.click(timeout=10000)
        download = await dl_info.value
        assert download.suggested_filename  # verify file was downloaded
        await download.save_as(f"./downloads/{download.suggested_filename}")
        
        # --> Assertions to verify final state
        
        # --> Booking detail shows the tour title and provides a visible 'Download PDF Voucher' button.
        await page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The tour link is visible on the booking detail page.
        await expect(page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0)).to_be_visible(timeout=15000), "The tour link is visible on the booking detail page."
        await page.get_by_role("button", name="Download PDF Voucher").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Download PDF Voucher' button is visible on the booking detail page.
        await expect(page.get_by_role("button", name="Download PDF Voucher").nth(0)).to_be_visible(timeout=15000), "The 'Download PDF Voucher' button is visible on the booking detail page."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    