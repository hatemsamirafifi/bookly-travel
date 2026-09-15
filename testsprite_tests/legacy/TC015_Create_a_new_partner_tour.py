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
        
        # -> Open the 'Sign in' page (Login) at /en/auth/login so the partner can sign in.
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the cookie banner 'Accept' button, fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Click the cookie banner 'Accept' button, fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Click the cookie banner 'Accept' button, fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Click the cookie banner 'Accept' button, fill 'partner@bookly.test' into the Email Address field, fill 'password' into the Password field, then click the 'Sign In' button.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        

        
        # -> Open the 'Test Partner' account menu in the top-right to locate partner dashboard or Tours/Create Tour links.
        # Test Partner T button
        elem = page.get_by_role('button', name='Test Partner T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Dashboard' link in the account menu to open the partner dashboard.
        # Dashboard link
        elem = page.get_by_role('menuitem', name='Dashboard', exact=True)
        await elem.click(timeout=10000)
        
        # -> Open the 'Create Tour' page (navigate to the partner Create Tour page).
        await page.goto("http://localhost:3001/en/partner/tours/create")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Fill the 'Tour Title', 'Description' (>=100 chars), 'Destination', and 'Duration' fields and click the 'Save Draft' button.
        # e.g., Hidden Gems of Rome Walking Tour text field
        elem = page.locator('[id="title"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Testsprite Automated Tour")
        
        # -> Fill the 'Tour Title', 'Description' (>=100 chars), 'Destination', and 'Duration' fields and click the 'Save Draft' button.
        # Describe your tour in detail (min 100... text area
        elem = page.locator('[id="description"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("This is an automated test tour created by TestSprite. The description intentionally includes more than one hundred characters to satisfy the form validation during automated testing of tour creation and listing.")
        
        # -> Fill the 'Tour Title', 'Description' (>=100 chars), 'Destination', and 'Duration' fields and click the 'Save Draft' button.
        # e.g., Rome, Italy text field
        elem = page.locator('[id="destination"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Test City, Country")
        
        # -> Fill the 'Tour Title', 'Description' (>=100 chars), 'Destination', and 'Duration' fields and click the 'Save Draft' button.
        # number field
        elem = page.locator('[id="duration_value"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("3")
        
        # -> Fill the 'Tour Title', 'Description' (>=100 chars), 'Destination', and 'Duration' fields and click the 'Save Draft' button.
        # Save Draft button
        elem = page.get_by_role('button', name='Save Draft', exact=True)
        await elem.click(timeout=10000)
        
        # -> Open the 'Select a category' dropdown on the Details form so category options become visible.
        # Select a category button
        elem = page.get_by_role('button', name='Select a category', exact=True)
        await elem.click(timeout=10000)
        
        # -> Select the 'partner.tours.form.walking' option from the Category dropdown to set the Category field.
        # partner.tours.form.walking button
        elem = page.get_by_role('button', name='partner.tours.form.walking', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Save Draft' button then click the 'Next' button to proceed to the Media step.
        # Save Draft button
        elem = page.get_by_role('button', name='Save Draft', exact=True)
        await elem.click(timeout=10000)
        
        # -> Navigate to partner tours
        await page.goto("http://localhost:3001/en/partner/tours")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=10000)
        except Exception:
            pass
        
        # -> Open the tour card edit page
        elem = page.locator('a[aria-label="Edit tour"]').first
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        # Verify the tour edit page is loaded
        await page.wait_for_url("**/partner/tours/**/edit", timeout=10000)
        await expect(page.locator("body")).to_contain_text("Save Tour Details", timeout=15000)
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    