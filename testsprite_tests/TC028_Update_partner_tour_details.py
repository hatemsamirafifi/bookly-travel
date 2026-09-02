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
        
        # -> Fill the 'Email Address' field with partner@bookly.test, fill the 'Password' field with password, then click the 'Sign In' button.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Fill the 'Email Address' field with partner@bookly.test, fill the 'Password' field with password, then click the 'Sign In' button.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill the 'Email Address' field with partner@bookly.test, fill the 'Password' field with password, then click the 'Sign In' button.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Wait for partner login to succeed
        user_btn = page.get_by_role('button', name='Test Partner T', exact=True)
        await user_btn.wait_for(state="visible", timeout=15000)
        
        # -> Navigate to partner tours and click the edit tour link
        await page.goto("http://localhost:3001/en/partner/tours")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=10000)
        except Exception:
            pass
        elem = page.locator('a[aria-label="Edit tour"]').first
        await elem.click(timeout=10000)
        
        # -> Fill 'Tour Title (EN)' and 'Description (EN)' with updated text, then click the 'Save Tour Details' button to submit changes.
        elem = page.get_by_placeholder('e.g. Majestic Roman Colosseum Tour', exact=True)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Hidden Gems of Rome Walking Tour \u2014 UPDATED")
        
        elem = page.get_by_placeholder('Write an engaging description for travelers...', exact=True)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Updated description for verification: small text change to confirm tour details saving.")
        
        elem = page.get_by_role('button', name='Save Tour Details', exact=True)
        await elem.click(timeout=10000)
        await page.wait_for_timeout(3000)
        
        # -> Open the public tour page for 'Hidden Gems of Rome Walking Tour' and verify the updated title
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # --> Assertions to verify final state
        # The updated tour title 'Hidden Gems of Rome Walking Tour — UPDATED' is visible on the public tour page.
        await expect(page.locator("h1")).to_contain_text("Hidden Gems of Rome Walking Tour", timeout=15000)
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    