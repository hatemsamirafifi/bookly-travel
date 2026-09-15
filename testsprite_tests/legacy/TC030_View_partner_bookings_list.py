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
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign In' link in the header to open the login page.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign In' link in the header to open the login page.
        # Sign In link
        elem = page.get_by_role('link', name='Sign In', exact=True)
        await elem.click(timeout=10000)
        
        # -> Fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Open the account menu by clicking the 'Test Partner' button in the header.
        # Test Partner T button
        elem = page.get_by_role('button', name='Test Partner T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Bookings' link in the partner account menu to open the partner bookings area.
        elem = page.get_by_role('menuitem', name='Bookings', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        await page.wait_for_url("**/partner/bookings**", timeout=10000)
        await expect(page.locator("body")).to_contain_text("BKO-TEST01", timeout=15000)
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    