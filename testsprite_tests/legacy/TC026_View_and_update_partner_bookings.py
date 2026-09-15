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
        
        # -> Open the Login page (the site's 'Sign in' / Login screen).
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the 'Accept' button on the cookie banner, then sign in by filling the Email Address and Password fields and clicking the 'Sign In' button.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then sign in by filling the Email Address and Password fields and clicking the 'Sign In' button.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Click the 'Accept' button on the cookie banner, then sign in by filling the Email Address and Password fields and clicking the 'Sign In' button.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Click the 'Accept' button on the cookie banner, then sign in by filling the Email Address and Password fields and clicking the 'Sign In' button.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Open the account menu by clicking the 'Test Partner' button and select the 'Bookings' link from the menu.
        # Test Partner T button
        elem = page.get_by_role('button', name='Test Partner T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Bookings' menu item in the account menu to open the partner bookings page.
        elem = page.get_by_role('menuitem', name='Bookings', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        await expect(page).to_have_url(re.compile("/en/partner/bookings"), timeout=15000)
        await expect(page.locator("table")).to_be_visible(timeout=15000)
        await expect(page.get_by_text("BKO-TEST01")).to_be_visible(timeout=15000)
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    