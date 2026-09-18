import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

# Wishlist add flow from the tour detail page.
#
# The heart button is a toggle (aria-pressed), so a naive double click adds
# then immediately removes the tour, and a blind single click breaks on
# reruns when the tour is already saved. The test therefore reads the
# current state and clicks only when the tour is not yet saved, ending in
# a saved state on every run.

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
        # -> Sign in as the seeded traveler account.
        await page.goto("http://localhost:3001/en/auth/login")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        elem = page.get_by_role("textbox", name="Email Address")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")

        elem = page.get_by_role("textbox", name="Password")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")

        elem = page.get_by_role("button", name="Sign In")
        await elem.click(timeout=10000)
        await expect(page).to_have_url(re.compile("/en"), timeout=15000)

        # Wait for the auth token to be persisted before navigating on.
        for _ in range(30):
            tok = await page.evaluate("() => localStorage.getItem('auth_token')")
            if tok:
                break
            await page.wait_for_timeout(500)
        assert tok, "traveler auth token was not persisted after sign in"

        # -> Open the tour detail page for 'Hidden Gems of Rome Walking Tour'.
        await page.goto("http://localhost:3001/en/tours/hidden-gems-rome-walking-tour")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass

        # -> Dismiss the cookie banner so it cannot intercept the heart click.
        elem = page.get_by_role("button", name="Accept")
        try:
            await elem.click(timeout=5000)
        except Exception:
            pass

        # -> Click the 'Save to wishlist' heart button only when the tour is
        #    not already saved (the button is a toggle: aria-pressed="true"
        #    means saved).
        elem = page.get_by_test_id("wishlist-button")
        await elem.wait_for(state="visible", timeout=10000)
        saved = await elem.get_attribute("aria-pressed")
        if saved != "true":
            await elem.scroll_into_view_if_needed()
            await elem.click(timeout=10000)
            await expect(elem).to_have_attribute("aria-pressed", "true", timeout=15000)

        # -> Open the 'Test User' account menu.
        elem = page.get_by_role("button", name="Test User T")
        await elem.click(timeout=10000)

        # -> Click the 'Wishlist' menu item to open the wishlist page.
        elem = page.get_by_role("menuitem", name="Wishlist")
        await elem.click(timeout=10000)

        # --> Assertions to verify final state

        # --> The 'Hidden Gems of Rome Walking Tour' appears in the wishlist.
        await page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0).scroll_into_view_if_needed()
        await expect(page.get_by_role("link", name="Hidden Gems of Rome Walking").nth(0)).to_be_visible(timeout=15000)
        await asyncio.sleep(2)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())