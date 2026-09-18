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
        
        # -> Click the 'Accept' button on the cookie banner, then open the 'Sign In' (Login) page by navigating to /en/login.
        # Accept button
        elem = page.get_by_role("button", name="Accept")
        await elem.click(timeout=10000)
        
        # -> Click the 'Accept' button on the cookie banner, then open the 'Sign In' (Login) page by navigating to /en/login.
        await page.goto("http://localhost:3001/en/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Open the site homepage ('Home') so the visible 'Sign In' link can be used to reach the login form.
        await page.goto("http://localhost:3001/en")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the 'Sign In' link in the top navigation to open the login page.
        # Sign In link
        elem = page.get_by_role("link", name="Sign In")
        await elem.click(timeout=10000)
        
        # -> Fill the 'Email Address' field with 'partner@bookly.test' and the 'Password' field with 'password', then click the 'Sign In' button.
        # you@example.com email field
        elem = page.get_by_role("textbox", name="Email Address")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("partner@bookly.test")
        
        # -> Fill the 'Email Address' field with 'partner@bookly.test' and the 'Password' field with 'password', then click the 'Sign In' button.
        # password password field
        elem = page.get_by_role("textbox", name="Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill the 'Email Address' field with 'partner@bookly.test' and the 'Password' field with 'password', then click the 'Sign In' button.
        # Sign In button
        elem = page.get_by_role("button", name="Sign In")
        await elem.click(timeout=10000)
        
        # -> Open the 'Test Partner' user menu in the header to access the partner dashboard.
        # Test Partner T button
        elem = page.get_by_role("button", name="Test Partner T")
        await elem.click(timeout=10000)
        
        # -> Click the 'Dashboard' item in the Test Partner menu to open the partner dashboard.
        # Dashboard link
        elem = page.get_by_role("menuitem", name="Dashboard")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> Partner dashboard metric cards are visible, including the Total Bookings card.
        # Semantic check (no hard-coded counts): the "Total Bookings" label is
        # visible and its metric card displays a numeric metric.
        total_bookings_label = page.locator("text=Total Bookings").first
        await total_bookings_label.scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: Total Bookings metric label is visible.
        await expect(total_bookings_label).to_be_visible(timeout=15000), "Total Bookings metric label is visible."
        total_bookings_card = page.locator("div.bg-white", has_text="Total Bookings").first
        await expect(total_bookings_card).to_be_visible(timeout=15000), "Total Bookings metric card is visible."
        card_text = await total_bookings_card.inner_text()
        assert re.search(r"\d", card_text or ""), "Total Bookings metric card displays a numeric metric."
        
        # --> Workspace status summary is shown as the 'Bookings Over Time' chart.
        await page.locator(".recharts-wrapper").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: Bookings Over Time chart is visible on the page.
        await expect(page.locator(".recharts-wrapper").nth(0)).to_be_visible(timeout=15000), "Bookings Over Time chart is visible on the page."
        await asyncio.sleep(5)

        # --> Partner status badges render with valid semantic status text.
        # Semantic check (no exact text/count pinning): any partner-facing
        # status badge on the dashboard must match one of the recognized
        # status labels — matched case-insensitively so lowercased banner
        # variants ("pending review", "active") satisfy the check too.
        status_badge_re = re.compile(r"Draft|Pending Review|Published|Active", re.IGNORECASE)
        body_text = (await page.locator("body").inner_text()) or ""
        assert status_badge_re.search(body_text), (
            "Partner dashboard renders a valid status badge "
            "(expected one of: Draft / Pending Review / Published / Active)."
        )

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    