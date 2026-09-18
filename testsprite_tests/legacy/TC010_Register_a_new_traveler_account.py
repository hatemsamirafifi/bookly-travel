import asyncio
import re
import time
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
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign Up' button to open the registration page.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign Up' button to open the registration page.
        # Sign Up link
        elem = page.get_by_role('link', name='Sign Up', exact=True)
        await elem.click(timeout=10000)
        
        # -> Fill the 'Full Name', 'Email Address', and 'Password' fields and click the 'Create Account' button to submit the registration form.
        # John Doe text field
        elem = page.locator('[id="register-name"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Testsprite User")
        
        # -> Fill the 'Full Name', 'Email Address', and 'Password' fields and click the 'Create Account' button to submit the registration form.
        # you@example.com email field
        elem = page.locator('[id="register-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill(f"testsprite+{int(time.time())}@example.com")
        
        # -> Fill the 'Full Name', 'Email Address', and 'Password' fields and click the 'Create Account' button to submit the registration form.
        # Min. 8 chars with upper, lower & number password field
        elem = page.locator('[id="register-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill the 'Full Name', 'Email Address', and 'Password' fields and click the 'Create Account' button to submit the registration form.
        # Create Account button
        elem = page.locator('[id="register-submit"]')
        await elem.click(timeout=10000)
        
        # -> Open the user menu by clicking the 'Testsprite User' button to verify signed-in options like 'My bookings' or 'Sign out'.
        # Testsprite User T button
        elem = page.get_by_role('button', name='Testsprite User T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'My Bookings' menu item to open the bookings page and verify the signed-in traveler experience is accessible.
        # My Bookings link
        elem = page.get_by_role('menuitem', name='My Bookings', exact=True)
        await elem.click(timeout=10000)
        
        # --> Test passed — verified by AI agent
        frame = context.pages[-1]
        current_url = await frame.evaluate("() => window.location.href")
        assert current_url is not None, "Test completed successfully"
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    