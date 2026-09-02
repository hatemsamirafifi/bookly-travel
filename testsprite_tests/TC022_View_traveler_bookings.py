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
        
        # -> Open the Sign in page (navigate to /en/auth/login).
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Fill the 'Email Address' field with 'test@example.com' and submit the sign-in form by clicking the 'Sign In' button (after accepting cookies).
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Fill the 'Email Address' field with 'test@example.com' and submit the sign-in form by clicking the 'Sign In' button (after accepting cookies).
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")
        
        # -> Fill the 'Email Address' field with 'test@example.com' and submit the sign-in form by clicking the 'Sign In' button (after accepting cookies).
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill the 'Email Address' field with 'test@example.com' and submit the sign-in form by clicking the 'Sign In' button (after accepting cookies).
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Wait for login to complete
        user_btn = page.get_by_role('button', name='Test User T', exact=True)
        await user_btn.wait_for(state="visible", timeout=10000)
        
        # -> Navigate to the My Bookings page
        await page.goto("http://localhost:3001/en/my-bookings")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # --> Assertions to verify final state
        # The My Bookings page was opened (URL contains /en/my-bookings).
        await expect(page).to_have_url(re.compile("/en/my\\-bookings"), timeout=15000), "The current page URL contains '/en/my-bookings'."
        
        # A booking card for BKO-TEST01 is visible in the bookings list.
        await expect(page.locator('a[href*="/my-bookings/BKO-"]').first).to_be_visible(timeout=15000)
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    