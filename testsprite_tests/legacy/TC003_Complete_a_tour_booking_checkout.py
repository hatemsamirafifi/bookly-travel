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
        
        # -> Click the 'Hidden Gems of Rome Walking Tour' featured tour link to open its detail page.
        # Next: 9/2/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.locator('a[href="/en/tours/hidden-gems-rome-walking-tour"]')
        await elem.click(timeout=10000)
        
        # -> Accept cookies, select the date 'Wed, Sep 2', set Participants to 2, then click the 'Book Now' button to start checkout.
        # Accept button
        elem = page.locator('[id="rcc-confirm-button"]')
        await elem.click(timeout=10000)
        
        # -> Accept cookies, select the date 'Wed, Sep 2', set Participants to 2, then click the 'Book Now' button to start checkout.
        # Select first available date button
        elem = page.locator('button', has_text=re.compile(r'Sep \d+')).first
        await elem.click(timeout=10000)
        
        # -> Accept cookies, select the date 'Wed, Sep 2', set Participants to 2, then click the 'Book Now' button to start checkout.
        # Increase participants button
        elem = page.get_by_role('button', name='Increase participants', exact=True)
        await elem.click(timeout=10000)
        
        # -> Accept cookies, select the date 'Wed, Sep 2', set Participants to 2, then click the 'Book Now' button to start checkout.
        # Book Now link
        elem = page.get_by_role('link', name='Book Now', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Confirm & Pay' button to proceed to the payment step and show the payment/confirmation UI.
        # Confirm & Pay button
        elem = page.get_by_role('button', name='Confirm & Pay', exact=True)
        await elem.click(timeout=10000)
        
        # -> Reveal the contact details and payment area on the 'Complete Your Booking' page by scrolling down so the contact input fields and payment UI are visible.
        await page.mouse.wheel(0, 300)
        
        # -> Scroll up to reveal the contact details and payment UI on the 'Complete Your Booking' page so the contact fields and payment area become visible.
        await page.mouse.wheel(0, 300)
        
        # -> Scroll to the bottom of the 'Complete Your Booking' page to reveal the contact details and payment UI.
        await page.mouse.wheel(0, 300)
        
        # -> Click the 'Sign In' link to open the login form so the traveler can authenticate.
        # Sign In link
        elem = page.get_by_role('link', name='Sign In', exact=True)
        await elem.click(timeout=10000)
        
        # -> Fill 'you@example.com' into the Email Address field and 'Password123!' into the Password field, then click the 'Sign In' button to authenticate.
        # you@example.com email field
        elem = page.locator('[id="login-email"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("test@example.com")
        
        # -> Fill 'you@example.com' into the Email Address field and 'Password123!' into the Password field, then click the 'Sign In' button to authenticate.
        # password password field
        elem = page.locator('[id="login-password"]')
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Password123!")
        
        # -> Fill 'you@example.com' into the Email Address field and 'Password123!' into the Password field, then click the 'Sign In' button to authenticate.
        # Sign In button
        elem = page.locator('[id="login-submit"]')
        await elem.click(timeout=10000)
        
        # -> Click the 'Hidden Gems of Rome Walking Tour' featured tour to open its detail page.
        # Next: 9/2/2026 ♡ Rome, Italy · 3 hours Hidden... link
        elem = page.locator('a[href="/en/tours/hidden-gems-rome-walking-tour"]')
        await elem.click(timeout=10000)
        
        # -> Select the 'Sep 2' date, increase Participants to 2 using the '+' button, then click the 'Book Now' button to start checkout.
        # Select first available date button
        elem = page.locator('button', has_text=re.compile(r'Sep \d+')).first
        await elem.click(timeout=10000)
        
        # -> Select the 'Sep 2' date, increase Participants to 2 using the '+' button, then click the 'Book Now' button to start checkout.
        # Increase participants button
        elem = page.get_by_role('button', name='Increase participants', exact=True)
        await elem.click(timeout=10000)
        
        # -> Select the 'Sep 2' date, increase Participants to 2 using the '+' button, then click the 'Book Now' button to start checkout.
        # Book Now link
        elem = page.get_by_role('link', name='Book Now', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Confirm & Pay' button to advance the booking to the payment/confirmation area.
        # Confirm & Pay button
        elem = page.get_by_role('button', name='Confirm & Pay', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the '−' (Decrease participants) button to set participants to 1, then click the 'Confirm & Pay' button to retry advancing to the payment step.
        # Decrease participants button
        elem = page.get_by_role('button', name='Decrease participants', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the '−' (Decrease participants) button to set participants to 1, then click the 'Confirm & Pay' button to retry advancing to the payment step.
        # Confirm & Pay button
        elem = page.get_by_role('button', name='Confirm & Pay', exact=True)
        await elem.click(timeout=10000)
        
        # -> Click the 'Change date' link to open the date selector so a different available date can be chosen.
        # Change date link
        elem = page.get_by_role('link', name='Change date', exact=True)
        await elem.click(timeout=10000)
        
        # -> Select the 'Sep 3' date on the tour page and click the 'Book Now' button to start checkout for that date.
        # Select second available date button
        elem = page.locator('button', has_text=re.compile(r'Sep \d+')).nth(1)
        await elem.click(timeout=10000)
        
        # -> Select the 'Sep 3' date on the tour page and click the 'Book Now' button to start checkout for that date.
        # Book Now link
        elem = page.get_by_role('link', name='Book Now', exact=True)
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The booking page shows the selected participants and the Confirm & Pay action so checkout is ready to advance to payment.
        # Assert-outcome: passed
        # Assert: Participants count displays 1.
        await expect(page.locator("xpath=/html/body/main/div/form/div[2]/div/span[1]").nth(0)).to_have_text("1", timeout=15000), "Participants count displays 1."
        await page.locator("xpath=/html/body/main/div/form/button").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The Confirm & Pay button is visible on the booking page.
        await expect(page.locator("xpath=/html/body/main/div/form/button").nth(0)).to_be_visible(timeout=15000), "The Confirm & Pay button is visible on the booking page."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    