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
        
        # -> Open the login page by navigating to /en/auth/login
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Fill 'test@example.com' into the Email Address field, fill 'Password123!' into the Password field, and click the 'Sign In' button (after accepting cookies).
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Fill 'test@example.com' into the Email Address field, fill 'Password123!' into the Password field, and click the 'Sign In' button (after accepting cookies).
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")
        
        # -> Fill 'test@example.com' into the Email Address field, fill 'Password123!' into the Password field, and click the 'Sign In' button (after accepting cookies).
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill 'test@example.com' into the Email Address field, fill 'Password123!' into the Password field, and click the 'Sign In' button (after accepting cookies).
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Open the 'Test User' menu in the header so the 'My bookings' option can be selected to view the bookings list.
        # Test User T button
        elem = page.get_by_role('button', name='Test User T', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'My Bookings' menu item to open the bookings list page.
        # My Bookings link
        elem = page.get_by_role('menuitem', name='My Bookings', exact=True)
        await elem.click(timeout=10000)
        
        # -> Open the first booking card by clicking it to view the booking details.
        elem = page.locator('a[href*="/my-bookings/BKO-"]').first
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The My Bookings page was reached and displayed the user's bookings list.
        # Assert: Page URL contains /en/my-bookings indicating the bookings page was reached.
        await expect(page).to_have_url(re.compile("/en/my\\-bookings"), timeout=15000), "Page URL contains /en/my-bookings indicating the bookings page was reached."
        
        # --> A booking detail page is displayed showing the tour title 'Hidden Gems of Rome Walking Tour'.
        await expect(page.locator("xpath=/html/body/main/div/section/div[1]/div[2]/div[1]/div/a").nth(0)).to_contain_text("Hidden Gems of Rome Walking Tour", timeout=15000), "The booking detail displays the tour title 'Hidden Gems of Rome Walking Tour'."
        await asyncio.sleep(1)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    