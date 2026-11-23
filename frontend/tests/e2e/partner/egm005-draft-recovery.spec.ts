import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import {
  apiGet,
  apiPost,
  apiPut,
  LONG_DESCRIPTION,
  uniqueTitle,
} from './tour-content-helpers';

/**
 * EGM-005 Wave 3B — Real Authenticated Browser E2E Coverage (D1 & D2).
 *
 * Exercises the live Laravel API + PostgreSQL stack + built Next.js frontend
 * (no mocked server state):
 *  1. Cross-session draft recovery with Spanish source preservation
 *  2. Language-first unselected state preserved on server draft & resume
 *  3. Multi-tab optimistic concurrency conflict (HTTP 409 STALE_DRAFT_CONFLICT)
 *     and safe reload/recovery without silent overwrite
 *  4. D2 first-save idempotency replay after dropped response, option ID reuse,
 *     and backend source_locale immutability
 *
 * Serial mode prevents overlapping mutations against the shared partner@bookly.test
 * latest-draft slot across tests within the same project run.
 */
test.describe.configure({ mode: 'serial' });

const PARTNER_AUTH_STATE = 'tests/.auth/partner.json';

async function fillTourDescription(page: Page, html: string) {
  await page.waitForFunction(
    (val) => {
      interface TestEditor {
        setContent(content: string): void;
        fire?(e: string): void;
        dispatch?(e: string): void;
        initialized?: boolean;
      }
      const win = window as unknown as {
        hugerte?: { get: (id?: string) => TestEditor | TestEditor[] | null };
      };
      const res =
        win.hugerte?.get('description') ||
        (typeof win.hugerte?.get === 'function' ? win.hugerte.get() : null);
      const editor = Array.isArray(res) ? res[0] : res;
      if (editor && typeof editor.setContent === 'function' && editor.initialized) {
        editor.setContent(val);
        editor.fire?.('change');
        editor.fire?.('input');
        editor.fire?.('setcontent');
        editor.dispatch?.('change');
        const ta = document.getElementById('description') as HTMLTextAreaElement | null;
        if (ta) {
          ta.value = val;
          ta.dispatchEvent(new Event('input', { bubbles: true }));
          ta.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return true;
      }
      return false;
    },
    html,
    { timeout: 10000 }
  ).catch(async () => {
    const ta = page.locator('textarea#description');
    if (await ta.isVisible()) {
      await ta.fill(html);
    }
  });
}

async function openPartnerContext(
  browser: Browser,
  templateContext: BrowserContext,
  clearWizardBuffer = true
): Promise<{ context: BrowserContext; page: Page }> {
  const baseOptions = templateContext.browser()?.contexts()?.[0] ? {} : {};
  void baseOptions;
  const page0 = templateContext.pages()[0];
  const viewport = page0?.viewportSize() ?? undefined;
  const isMobile = (viewport?.width ?? 1280) < 768;

  const context = await browser.newContext({
    storageState: PARTNER_AUTH_STATE,
    ...(viewport ? { viewport } : {}),
    ...(isMobile ? { isMobile: true, hasTouch: true } : {}),
  });

  await context.addCookies([
    { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
  ]);

  if (clearWizardBuffer) {
    await context.addInitScript(() => localStorage.removeItem('partner-tour-wizard'));
  }

  const page = await context.newPage();
  return { context, page };
}

async function cleanupServerDraft(page: Page, draftId: number | null) {
  if (!draftId) return;
  try {
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));
    if (!token) return;
    await page.request.fetch(`${new URL(page.url()).origin}/api/partner/tours/drafts/${draftId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
  } catch {
    // Best-effort teardown.
  }
}

async function cleanupTour(page: Page, tourId: number | null) {
  if (!tourId) return;
  try {
    await apiPost(page, `/api/partner/tours/${tourId}/archive`, {});
  } catch {
    // Best-effort teardown.
  }
}

test.describe('EGM-005 Wave 3B — Server Draft Recovery & D2 Idempotency', () => {
  test('recovers a saved Spanish draft in a brand-new session with empty localStorage', async ({
    browser,
    context: baseContext,
  }) => {
    const title = uniqueTitle('EGM005 Borrador ES');
    let savedDraftId: number | null = null;

    const session1 = await openPartnerContext(browser, baseContext, true);
    let session2: { context: BrowserContext; page: Page } | null = null;

    try {
      await session1.page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });
      const picker = session1.page.getByRole('radiogroup', { name: 'Original writing language' });
      await expect(picker).toBeVisible();
      await picker.getByRole('radio', { name: /Spanish/i }).click();
      await session1.page.getByRole('button', { name: 'Next', exact: true }).click();

      await session1.page.getByLabel('Tour Title').fill(title);
      await session1.page.getByLabel('Destination').fill('Sevilla, España');

      const savePromise = session1.page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === '/api/partner/tours/drafts' &&
          resp.request().method() === 'POST'
      );
      await session1.page.getByTestId('wizard-save-progress').click();
      const saveResp = await savePromise;
      expect(saveResp.status()).toBe(200);
      const saveBody = await saveResp.json();
      savedDraftId = saveBody.data.id as number;
      expect(savedDraftId).toBeGreaterThan(0);
      expect(saveBody.data.source_locale).toBe('es');
      await expect(session1.page.getByText('Progress saved to server')).toBeVisible();

      // Fresh second session with completely empty localStorage must offer recovery banner.
      session2 = await openPartnerContext(browser, baseContext, true);
      await session2.page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });

      const banner = session2.page.getByTestId('draft-recovery-banner');
      await expect(banner).toBeVisible();
      await expect(banner).toContainText(title);

      const resumePromise = session2.page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/drafts/${savedDraftId}/resume` &&
          resp.request().method() === 'POST'
      );
      await banner.getByRole('button', { name: 'Resume draft' }).click();
      const resumeResp = await resumePromise;
      expect(resumeResp.status()).toBe(200);

      await expect(session2.page.getByLabel('Tour Title')).toHaveValue(title);
      await expect(session2.page.getByLabel('Destination')).toHaveValue('Sevilla, España');

      // Stepping back confirms Spanish stays locked as source_locale.
      await session2.page.getByRole('button', { name: 'Back', exact: true }).click();
      const resumedPicker = session2.page.getByRole('radiogroup', { name: 'Original writing language' });
      await expect(resumedPicker.getByRole('radio', { name: /Spanish/i })).toHaveAttribute('aria-checked', 'true');
      await expect(resumedPicker.getByRole('radio', { name: /English/i })).toBeDisabled();
      await expect(resumedPicker.getByRole('radio', { name: /Italian/i })).toBeDisabled();
    } finally {
      await cleanupServerDraft(session1.page, savedDraftId);
      if (session2) await session2.context.close().catch(() => {});
      await session1.context.close().catch(() => {});
    }
  });

  test('preserves unselected source_locale null across server draft save and fresh session resume', async ({
    browser,
    context: baseContext,
  }) => {
    let savedDraftId: number | null = null;
    const session1 = await openPartnerContext(browser, baseContext, true);
    let session2: { context: BrowserContext; page: Page } | null = null;

    try {
      await session1.page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });
      const picker = session1.page.getByRole('radiogroup', { name: 'Original writing language' });
      await expect(picker).toBeVisible();
      await expect(picker.getByRole('radio', { checked: true })).toHaveCount(0);

      const savePromise = session1.page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === '/api/partner/tours/drafts' &&
          resp.request().method() === 'POST'
      );
      await session1.page.getByTestId('wizard-save-progress').click();
      const saveResp = await savePromise;
      expect(saveResp.status()).toBe(200);
      const saveBody = await saveResp.json();
      savedDraftId = saveBody.data.id as number;
      expect(saveBody.data.source_locale).toBeNull();

      session2 = await openPartnerContext(browser, baseContext, true);
      await session2.page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });

      const banner = session2.page.getByTestId('draft-recovery-banner');
      await expect(banner).toBeVisible();
      await banner.getByRole('button', { name: 'Resume draft' }).click();

      const resumedPicker = session2.page.getByRole('radiogroup', { name: 'Original writing language' });
      await expect(resumedPicker).toBeVisible();
      await expect(resumedPicker.getByRole('radio', { checked: true })).toHaveCount(0);
    } finally {
      await cleanupServerDraft(session1.page, savedDraftId);
      if (session2) await session2.context.close().catch(() => {});
      await session1.context.close().catch(() => {});
    }
  });

  test('detects multi-tab 409 STALE_DRAFT_CONFLICT without overwriting newer server edits and recovers cleanly', async ({
    browser,
    context: baseContext,
  }) => {
    let savedDraftId: number | null = null;
    const tab1Title = uniqueTitle('Tab1 Winner');
    const tab2StaleTitle = uniqueTitle('Tab2 Stale Overwrite');

    const { context, page: tab1 } = await openPartnerContext(browser, baseContext, true);
    let tab2: Page | null = null;

    try {
      await tab1.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });
      await tab1.getByRole('radio', { name: /Spanish/i }).click();
      await tab1.getByRole('button', { name: 'Next', exact: true }).click();
      await tab1.getByLabel('Tour Title').fill(uniqueTitle('Shared Initial Title'));

      const createDraftPromise = tab1.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === '/api/partner/tours/drafts' &&
          resp.request().method() === 'POST'
      );
      await tab1.getByTestId('wizard-save-progress').click();
      const createdBody = await (await createDraftPromise).json();
      savedDraftId = createdBody.data.id as number;

      // Tab 2 opens the exact same server draft (?draft=<id>), sharing token T1.
      tab2 = await context.newPage();
      await tab2.goto(`/en/partner/tours/create?draft=${savedDraftId}`, { waitUntil: 'domcontentloaded' });
      await expect(tab2.getByLabel('Tour Title')).toBeVisible();

      // Tab 1 advances server state & rotates concurrency_token to T2.
      await tab1.getByLabel('Tour Title').fill(tab1Title);
      const tab1UpdatePromise = tab1.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/drafts/${savedDraftId}` &&
          resp.request().method() === 'PUT'
      );
      await tab1.getByTestId('wizard-save-progress').click();
      const tab1Update = await tab1UpdatePromise;
      expect(tab1Update.status()).toBe(200);

      // Tab 2 still holds stale token T1 and tries to save conflicting title -> must receive HTTP 409.
      await tab2.getByLabel('Tour Title').fill(tab2StaleTitle);
      const tab2ConflictPromise = tab2.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/drafts/${savedDraftId}` &&
          resp.request().method() === 'PUT'
      );
      await tab2.getByTestId('wizard-save-progress').click();
      const tab2Resp = await tab2ConflictPromise;
      expect(tab2Resp.status()).toBe(409);

      const conflictPanel = tab2.getByTestId('draft-conflict-panel');
      await expect(conflictPanel).toBeVisible();
      await expect(conflictPanel).toContainText(tab1Title);

      // Confirm DB was NOT overwritten by Tab 2's stale write.
      const serverCheck = await apiGet(tab1, `/api/partner/tours/drafts/${savedDraftId}`);
      expect(serverCheck.status).toBe(200);
      expect((serverCheck.json as { data: { payload: { title: string } } }).data.payload.title).toBe(tab1Title);

      // Load latest server draft in Tab 2, then make a fresh save cleanly (200).
      await conflictPanel.getByRole('button', { name: 'Load latest server draft' }).click();
      await expect(conflictPanel).toBeHidden();
      await expect(tab2.getByLabel('Tour Title')).toHaveValue(tab1Title);

      const recoveredTitle = uniqueTitle('Tab2 Clean Save After Reload');
      await tab2.getByLabel('Tour Title').fill(recoveredTitle);
      const cleanSavePromise = tab2.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/drafts/${savedDraftId}` &&
          resp.request().method() === 'PUT'
      );
      await tab2.getByTestId('wizard-save-progress').click();
      expect((await cleanSavePromise).status()).toBe(200);
    } finally {
      await cleanupServerDraft(tab1, savedDraftId);
      if (tab2) await tab2.close().catch(() => {});
      await context.close().catch(() => {});
    }
  });

  test('replays identical creation_id on dropped first-save response returning 200 without duplicating tour or option and enforces source_locale immutability', async ({
    browser,
    context: baseContext,
  }) => {
    const title = uniqueTitle('D2 Replay Tour');
    let createdTourId: number | null = null;
    let linkedDraftId: number | null = null;

    const { context, page } = await openPartnerContext(browser, baseContext, true);

    try {
      // Pre-seed only a cover media item so step navigation passes media validation cleanly,
      // while leaving source_locale null so the partner must pick Spanish on screen 1.
      await page.addInitScript(() => {
        if (!localStorage.getItem('d2_cover_seeded')) {
          localStorage.setItem('d2_cover_seeded', '1');
          localStorage.setItem(
            'partner-tour-wizard',
            JSON.stringify({
              state: {
                currentStep: 'source',
                formData: {
                  source_locale: null,
                  difficulty_level: 'easy',
                  media: [
                    {
                      id: 'd2-seed-cover',
                      url: 'https://cdn.example.com/d2-replay-cover.jpg',
                      is_cover: true,
                      sort_order: 0,
                    },
                  ],
                },
              },
              version: 0,
            })
          );
        }
      });

      await page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });
      await page.getByRole('radio', { name: /Spanish/i }).click();
      await page.getByRole('button', { name: 'Next', exact: true }).click();

      await page.getByLabel('Tour Title').fill(title);
      await fillTourDescription(page, `<p>${LONG_DESCRIPTION}</p>`);

      await page.getByRole('button', { name: 'Category', exact: true }).click();
      await page.getByRole('option', { name: 'Walking', exact: true }).click();
      await page.getByLabel('Destination').fill('Granada, España');
      await page.getByRole('spinbutton', { name: 'Duration' }).fill('3');

      // Advance Details -> Media (seeded cover) -> Pricing
      await page.getByRole('button', { name: 'Next', exact: true }).click();
      await page.getByRole('button', { name: 'Next', exact: true }).click();
      await page.getByRole('button', { name: 'Add Option' }).click();
      await page.getByLabel('Option title').fill('Grupo Reducido');
      await page.getByLabel('Option guide languages').fill('es, en');
      await page.getByLabel('Price per person').fill('55');

      // Simulate dropped HTTP response on first save AFTER forwarding to real PostgreSQL backend!
      let droppedOnce = false;
      let firstCreationIdHeader: string | undefined;
      let realCreatedStatus = 0;
      let resolveFirstDrop!: () => void;
      const firstDropDone = new Promise<void>((res) => {
        resolveFirstDrop = res;
      });

      await page.route('**/api/partner/tours', async (route) => {
        if (route.request().method() === 'POST' && !droppedOnce) {
          droppedOnce = true;
          firstCreationIdHeader = route.request().headers()['client-creation-id'];
          try {
            const forwarded = await route.fetch();
            realCreatedStatus = forwarded.status();
            if (forwarded.ok()) {
              const bodyJson = (await forwarded.json()) as { data?: { id?: number } };
              createdTourId = bodyJson?.data?.id ?? null;
            }
          } finally {
            await route.abort('failed').catch(() => {});
            resolveFirstDrop();
          }
          return;
        }
        await route.continue();
      });

      await page.getByRole('button', { name: 'Save Draft' }).click();
      await firstDropDone;
      await expect(page.getByTestId('wizard-error-summary')).toBeVisible();
      expect(droppedOnce).toBe(true);
      expect(realCreatedStatus).toBe(201);
      expect(firstCreationIdHeader).toMatch(/^[0-9a-f-]{36}$/i);
      expect(createdTourId).toBeGreaterThan(0);

      await page.unroute('**/api/partner/tours');

      // Retry clicking Save Draft: must replay exact same creation_id and receive HTTP 200 (idempotent)
      const replayPromise = page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === '/api/partner/tours' &&
          resp.request().method() === 'POST'
      );
      await page.getByRole('button', { name: 'Save Draft' }).click();
      const replayResp = await replayPromise;
      expect(replayResp.status()).toBe(200);
      expect(replayResp.request().headers()['client-creation-id']).toBe(firstCreationIdHeader);

      const replayBody = await replayResp.json();
      expect(replayBody.data.id).toBe(createdTourId);
      expect(replayBody.data.options).toHaveLength(1);
      const optionId = replayBody.data.options[0].id as number;
      expect(replayBody.draft?.id).toBeGreaterThan(0);
      expect(replayBody.draft?.concurrency_token).toBeTruthy();
      linkedDraftId = replayBody.draft.id as number;
      await expect(page.getByText('Draft saved')).toBeVisible();

      // Blocker #1 verification: clicking Save Progress right after Save Draft in the same tab succeeds (HTTP 200, no false 409!).
      const sameTabProgressPromise = page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/drafts/${linkedDraftId}` &&
          resp.request().method() === 'PUT'
      );
      await page.getByTestId('wizard-save-progress').click();
      expect((await sameTabProgressPromise).status()).toBe(200);
      await expect(page.getByText('Progress saved to server')).toBeVisible();

      // Next save sends PUT /api/partner/tours/{id} reusing existing option.id and omitting source_locale.
      const reusePutPromise = page.waitForResponse(
        (resp) =>
          new URL(resp.url()).pathname === `/api/partner/tours/${createdTourId}` &&
          resp.request().method() === 'PUT'
      );
      await page.getByRole('button', { name: 'Save Draft' }).click();
      const reusePut = await reusePutPromise;
      expect(reusePut.status()).toBe(200);
      const putPayload = reusePut.request().postDataJSON();
      expect(putPayload).not.toHaveProperty('source_locale');
      expect(putPayload.options[0].id).toBe(optionId);

      // Blocker #2 verification: fresh session with empty localStorage resumes the materialized draft tour with preserved option & server_id.
      const freshSession = await openPartnerContext(browser, baseContext, true);
      try {
        await freshSession.page.goto('/en/partner/tours/create', { waitUntil: 'domcontentloaded' });
        const resumeBanner = freshSession.page.getByTestId('draft-recovery-banner');
        await expect(resumeBanner).toBeVisible();
        await resumeBanner.getByRole('button', { name: 'Resume draft' }).click();

        await expect(freshSession.page.getByLabel('Option title')).toHaveValue('Grupo Reducido');
        await expect(freshSession.page.getByLabel('Price per person')).toHaveValue('55');

        const resumedSavePutPromise = freshSession.page.waitForResponse(
          (resp) =>
            new URL(resp.url()).pathname === `/api/partner/tours/${createdTourId}` &&
            resp.request().method() === 'PUT'
        );
        await freshSession.page.getByRole('button', { name: 'Save Draft' }).click();
        const resumedSavePut = await resumedSavePutPromise;
        expect(resumedSavePut.status()).toBe(200);
        expect(resumedSavePut.request().postDataJSON().options[0].id).toBe(optionId);
      } finally {
        await freshSession.context.close().catch(() => {});
      }

      // Confirm only ONE tour exists with this title in partner tours list.
      const listRes = await apiGet(page, '/api/partner/tours?per_page=100');
      const matches = ((listRes.json as { data: Array<{ id: number }> }).data ?? []).filter(
        (t) => t.id === createdTourId
      );
      expect(matches).toHaveLength(1);

      // Backend source_locale immutability on both materialized tour and server draft.
      const tourLocaleFlip = await apiPut(page, `/api/partner/tours/${createdTourId}`, {
        source_locale: 'en',
      });
      expect(tourLocaleFlip.status).toBe(422);

      const draftsRes = await apiGet(page, '/api/partner/tours/drafts');
      const matchingDraft = (
        (draftsRes.json as { data: Array<{ id: number; tour_id: number | null; concurrency_token?: string | null }> }).data ?? []
      ).find((d) => d.tour_id === createdTourId);
      expect(matchingDraft).toBeDefined();
      if (matchingDraft) {
        linkedDraftId = matchingDraft.id;
        const draftLocaleFlip = await apiPut(page, `/api/partner/tours/drafts/${linkedDraftId}`, {
          source_locale: 'en',
          ...(matchingDraft.concurrency_token ? { concurrency_token: matchingDraft.concurrency_token } : {}),
          payload: { title },
        });
        expect(draftLocaleFlip.status).toBe(422);
      }
    } finally {
      await page.unrouteAll({ behavior: 'ignoreErrors' }).catch(() => {});
      await cleanupServerDraft(page, linkedDraftId);
      await cleanupTour(page, createdTourId);
      await context.close().catch(() => {});
    }
  });
});
