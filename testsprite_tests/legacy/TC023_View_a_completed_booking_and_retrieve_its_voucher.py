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
        
        # -> Open the site's 'Log in' page (navigate to /en/auth/login) so the login form can be filled.
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the cookie banner 'Accept' button, fill the email field with 'test@example.com' and the password field with 'Password123!', then click the 'Sign In' button to log in.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the cookie banner 'Accept' button, fill the email field with 'test@example.com' and the password field with 'Password123!', then click the 'Sign In' button to log in.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")
        
        # -> Click the cookie banner 'Accept' button, fill the email field with 'test@example.com' and the password field with 'Password123!', then click the 'Sign In' button to log in.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Click the cookie banner 'Accept' button, fill the email field with 'test@example.com' and the password field with 'Password123!', then click the 'Sign In' button to log in.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Open the 'Test User' account menu and click the 'Bookings' link to view the bookings list.
        # Test User T button
        elem = page.get_by_role('button', name='Test User T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'My Bookings' link in the account dropdown to open the bookings list.
        # My Bookings link
        elem = page.get_by_role('menuitem', name='My Bookings', exact=True)
        await elem.click(timeout=10000)
        
        # -> Open the booking card to view the booking details page.
        elem = page.locator('a[href*="/my-bookings/BKO-"]').first
        await elem.click(timeout=10000)
        await page.wait_for_url("**/my-bookings/BKO-**", timeout=10000)
        
        # -> Click the 'Download PDF Voucher' button to verify the voucher can be accessed.
        # Download PDF Voucher button
        elem = page.get_by_role('button', name='Download PDF Voucher', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The booking detail page shows the booked tour title 'Hidden Gems of Rome Walking Tour'.
        # Assert-outcome: passed
        # Assert: The booking detail displays the tour title link.
        await expect(page.locator("xpath=/html/body/main/div/section/div[1]/div[2]/div[1]/div/a").nth(0)).to_contain_text("Hidden Gems of Rome Walking Tour", timeout=15000), "The booking detail displays the tour title link."
        
        # --> The voucher download was initiated and the download button shows 'Downloading...'.
        # Assert-outcome: passed
        # Assert: The Download PDF Voucher button shows 'Downloading...' indicating the voucher download started.
        await expect(page.locator("xpath=/html/body/main/div/aside/div[2]/button").nth(0)).to_have_text("Downloading...", timeout=15000), "The Download PDF Voucher button shows 'Downloading...' indicating the voucher download started."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    