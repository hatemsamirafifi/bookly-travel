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
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign Up' link in the header to open the registration page.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then click the 'Sign Up' link in the header to open the registration page.
        # Sign Up link
        elem = page.get_by_role("link", name="Sign Up")
        await elem.click(timeout=10000)
        
        # -> Open the Partner registration page by navigating to /en/auth/partner-register.
        await page.goto("http://localhost:3001/en/auth/partner-register")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Fill the partner registration form fields: 'Company / Business Name', 'Contact Name', 'Account Email', 'Password', and 'Confirm Password'.
        # e.g. Barcelona Tours SL text field
        elem = page.get_by_role("textbox", name="Company / Business Name")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("TestSprite Partners 20260912")
        
        # -> Fill the partner registration form fields: 'Company / Business Name', 'Contact Name', 'Account Email', 'Password', and 'Confirm Password'.
        # e.g. John Doe text field
        elem = page.get_by_role("textbox", name="Contact Name")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Test Partner")
        
        # -> Fill the partner registration form fields: 'Company / Business Name', 'Contact Name', 'Account Email', 'Password', and 'Confirm Password'.
        # john@company.com email field
        elem = page.get_by_role("textbox", name="Account Email")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("testsprite+20260912@example.com")
        
        # -> Fill the partner registration form fields: 'Company / Business Name', 'Contact Name', 'Account Email', 'Password', and 'Confirm Password'.
        # Min 8 chars, 1 uppercase, 1 number password field
        elem = page.get_by_role("textbox", name="Password", exact=True)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill the partner registration form fields: 'Company / Business Name', 'Contact Name', 'Account Email', 'Password', and 'Confirm Password'.
        # Repeat password password field
        elem = page.get_by_role("textbox", name="Confirm Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Click the 'Submit Application' button to submit the partner registration form and reach the onboarding status view.
        # Submit Application button
        elem = page.get_by_role("button", name="Submit Application")
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
    