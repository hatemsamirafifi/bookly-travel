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
        
        # -> Open the login page by navigating to /en/auth/login so the partner can sign in.
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Fill 'Email Address' with partner@bookly.test, fill 'Password' with password, then click the 'Sign In' button.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Fill 'Email Address' with partner@bookly.test, fill 'Password' with password, then click the 'Sign In' button.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Fill 'Email Address' with partner@bookly.test, fill 'Password' with password, then click the 'Sign In' button.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill 'Email Address' with partner@bookly.test, fill 'Password' with password, then click the 'Sign In' button.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Test Partner' button in the header to open the account menu and find the Partner dashboard or 'Manage tours' link.
        # Test Partner T button
        elem = page.get_by_role('button', name='Test Partner T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Dashboard' link in the account menu to open the partner dashboard.
        # Dashboard link
        elem = page.get_by_role('menuitem', name='Dashboard', exact=True)
        await elem.click(timeout=10000)
        
        # -> Navigate to the 'Partner tours' page (open URL /en/partner/tours) to access tour management.
        await page.goto("http://localhost:3001/en/partner/tours")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the '+ Create Tour' button in the top-right of the My Tours page to open the tour creation flow.
        # Create Tour button
        elem = page.get_by_role('button', name='Create Tour', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The Create Tour form is visible in the partner tours area.
        await page.locator("xpath=/html/body/div[2]/div/main/div/div/div[2]/div/div[1]/input").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Tour Title' input is visible on the create form.
        await expect(page.locator("xpath=/html/body/div[2]/div/main/div/div/div[2]/div/div[1]/input").nth(0)).to_be_visible(timeout=15000), "The 'Tour Title' input is visible on the create form."
        
        # --> Tour creation controls are available: 'Save Draft' and 'Next' buttons are visible.
        await page.locator("xpath=/html/body/div[2]/div/main/div/div/div[3]/div/button[1]").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Save Draft' button is visible.
        await expect(page.locator("xpath=/html/body/div[2]/div/main/div/div/div[3]/div/button[1]").nth(0)).to_be_visible(timeout=15000), "The 'Save Draft' button is visible."
        await page.locator("xpath=/html/body/div[2]/div/main/div/div/div[3]/div/button[2]").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Next' button is visible.
        await expect(page.locator("xpath=/html/body/div[2]/div/main/div/div/div[3]/div/button[2]").nth(0)).to_be_visible(timeout=15000), "The 'Next' button is visible."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    