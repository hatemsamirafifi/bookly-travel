import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {
  apiGet,
  apiPost,
  buildOrderedMedia,
  LONG_DESCRIPTION,
  ownedEnRow,
  seededOwnedTourId,
  uniqueTitle,
} from './tour-content-helpers';

/**
 * EGM-003 tour revision governance — real partner browser journey.
 *
 * Every partner step drives the REAL partner API and the REAL editor page in
 * the same origin (nginx) using the seeded partner storage state; the admin
 * steps sign in to the REAL existing Filament panel in a second browser
 * context. No approval request is ever mocked or fabricated: approvals,
 * change requests and rejections must run through the actual Filament
 * admin surfaces backed by the domain Actions.
 *
 * FIXTURE / SETUP REQUIREMENTS (reported for the orchestrator, nothing skipped):
 * - The `setup` project must run first (--grep 'authenticate as partner'); these
 *   tests run with --project=chromium-partner --no-deps after that.
 * - The seeded published owned tour (PartnerSeeder, exposed via
 *   hidden-gems-rome-walking-tour) must exist with its media rows; the
 *   partner-side stories use it so no extra fixture is required, and every
 *   attempt restores original commercial values and withdraws its proposals.
 * - A disposable `bookly_accept` database per run: the full admin journey
 *   creates a NEW tour per run through the real partner API and needs the
 *   admin@bookly.test super-admin to (1) approve its first publication and
 *   (2) exercise the EGM-003 revision review actions on the Filament tour page.
 *   Filament action labels are matched loosely from the visible UI copy; if
 *   the S3 admin UI names them differently, `filamentTourAction` below is the
 *   only helper that needs its matchers aligned.
 */

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';
const PARTNER_STORAGE_STATE = 'tests/.auth/partner.json';

async function addConsentCookies(context: BrowserContext): Promise<void> {
  await context.addCookies([
    { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
  ]);
}

test.beforeEach(async ({ context, page }) => {
  await addConsentCookies(context);
  await page.goto('/en/partner');
});

/** Sign in to the REAL Filament admin panel in its own context (same origin,
 *  Livewire login as the existing filament-admin.spec.ts). */
async function filamentLogin(adminPage: Page): Promise<void> {
  await adminPage.goto('/admin/login');
  const email = adminPage.getByLabel(/email address/i).first();
  await email.waitFor({ state: 'visible', timeout: 30000 });
  await email.fill(ADMIN_EMAIL);
  await adminPage.getByLabel(/password/i).first().fill(ADMIN_PASSWORD);
  await Promise.all([
    adminPage.waitForURL('**/admin', { timeout: 30000 }),
    adminPage.getByRole('button', { name: /sign in/i }).click(),
  ]);
  await expect(adminPage.locator('main')).toBeVisible();
}

/** Open the real Filament tour editor for a tour and trigger the approve /
 *  reject / request-changes action by its visible label (S3 Filament surface).
 *  Optional dialog text fills the mandatory reason textarea when the action
 *  opens a modal. */
async function filamentTourAction(
  adminPage: Page,
  tourId: number | string,
  actionMatcher: RegExp,
  dialogText?: string
): Promise<void> {
  if (actionMatcher.source === '^approve') {
    await adminPage.goto('/admin/tours?tableSortColumn=id&tableSortDirection=desc');
    const row = adminPage.getByRole('row').filter({ has: adminPage.getByRole('cell', { name: String(tourId), exact: true }) });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Publish', exact: true }).click();
    const dialog = adminPage.locator('.fi-modal-open .fi-modal-window');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(adminPage.getByText('Tour published', { exact: true })).toBeVisible();
    return;
  }
  await adminPage.goto(`/admin/tours/${tourId}/edit`);
  await adminPage.getByRole('button', { name: actionMatcher }).first().click();
  const dialog = adminPage.locator('.fi-modal-open .fi-modal-window');
  await expect(dialog).toBeVisible();
  if (dialogText) {
    await dialog.getByLabel(/Reason \(mandatory\)/).fill(dialogText);
    if (/request changes/i.test(actionMatcher.source)) {
      await dialog.getByLabel(/Field references \(one per line\)/).fill('source.title');
    }
  }
  await dialog.locator('button[type="submit"]').click();
  await expect(dialog).toBeHidden();
}

/** Full English-source + media + pricing tour created through the REAL API. */
async function createTourThroughPartnerApi(page: Page): Promise<number> {
  await page.goto('/en/partner');
  const title = uniqueTitle('EGM003 journey');
  const created = await apiPost(page, '/api/partner/tours', {
    title,
    description: LONG_DESCRIPTION,
    category: 'walking',
    destination: 'Rome, Italy',
    duration_value: 3,
    duration_unit: 'hour',
    difficulty_level: 'easy',
    guide_languages: ['en'],
    media: buildOrderedMedia(2, 'egm003-cover'),
    options: [
      { title: 'Standard group', pickup_instructions: 'Meet at the gate', guide_languages: ['en'], price_amount: 5000, group_size_max: 12 },
    ],
    translations: {
      en: {
        title,
        description: LONG_DESCRIPTION,
        highlights: ['Old-town route'],
        inclusions: ['Live guide'],
        exclusions: ['Lunch'],
        important_information: ['Comfortable shoes recommended'],
        meeting_point: 'Colosseum Main Entrance',
        cancellation_policy: 'Cancel up to 24 hours in advance for a full refund.',
        itinerary: [{ day: 1, title: 'Arrival', description: null, stops: [{ title: 'Meet the guide', description: null, duration_minutes: 30 }] }],
      },
    },
  });
  expect(created.status).toBe(201);
  const tourId = (created.json as { data: { id: number } }).data.id;
  const tier = await apiPost(page, `/api/partner/tours/${tourId}/pricing`, {
    name: 'Adult',
    price: 45,
    min_participants: 1,
    max_participants: 10,
  });
  expect(tier.status).toBe(201);
  const submit = await apiPost(page, `/api/partner/tours/${tourId}/submit`, {});
  expect(submit.status).toBe(200);
  return tourId;
}

function revisionRequestsCount(page: Page): number {
  return revisionCounters.get(page) ?? 0;
}

const revisionCounters = new WeakMap<Page, number>();

function trackRevisionRequests(page: Page): void {
  revisionCounters.set(page, 0);
  page.on('request', (request) => {
    const url = request.url();
    if (
      request.method() !== 'GET' &&
      (url.includes('/revisions') || url.includes('/revision-requests'))
    ) {
      revisionCounters.set(page, (revisionCounters.get(page) ?? 0) + 1);
    }
  });
}

test.describe('EGM-003 governance on the published editor (seeded published tour)', () => {
  test('surfaces the governance panel, the live public page, and the fail-closed option structure at every required width with Axe', async ({ page }) => {
    const tourId = await seededOwnedTourId(page);
    const widths = [390, 768, 1024, 1440] as const;
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/en/partner/tours/${tourId}/edit`);
      await expect(page.locator('main#main-content')).toBeVisible();
      // Persistent publish protection notice and the approved snapshot fact.
      await expect(page.getByText(/Travelers keep seeing the approved content/)).toBeVisible();
      await expect(page.getByText('Approved public content')).toBeVisible();
      // The live public route is offered; no proposed public route can exist.
      const liveLink = page.getByRole('link', { name: 'View live public page' });
      await expect(liveLink).toHaveAttribute('href', /\/tours\/.+$/);
      // Structural option review fails closed for real: no add/remove.
      await expect(page.getByRole('button', { name: 'Add Option' })).toHaveCount(0);
      await expect(page.getByText(/adding, removing or reordering booking options/i)).toHaveCount(1);
      // Authored text and price controls stay editable.
      await expect(page.getByLabel('Option title').first()).toBeEnabled();
      await expect(page.getByLabel('Price per person').first()).toBeEnabled();
      // Explicit separate saves exist.
      await expect(page.getByRole('button', { name: 'Save content changes for review' })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Save price/schedule changes only' })).toBeVisible();
      // No opaque editor-draft restore on a published tour.
      await expect(page.getByRole('button', { name: 'Restore saved draft' })).toHaveCount(0);
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    }
  });

  test('mixed dirty save makes no request, keeps values, then the explicit operational and content saves each land their own patch', async ({ page }) => {
    const tourId = await seededOwnedTourId(page);
    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(detail.status).toBe(200);
    const options = (detail.json as { data: { options: Array<{ id: number; price_amount: number }> } }).data.options;
    expect(options.length).toBeGreaterThan(0);
    const originalOption = options[0];
    const originalTitle = ownedEnRow(detail.json).title as string;
    try {

    trackRevisionRequests(page);
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(originalTitle);

    // BOTH domains dirty: source text (governed) + option price (commercial).
    await page.locator('#edit-source-title').fill(`${originalTitle} (revision draft)`);
    await page.getByLabel('Price per person').first().fill('77');
    const requestsBefore = revisionRequestsCount(page);
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await expect(page.getByText(/Save them separately with/)).toBeVisible();
    // NO request at all for a mixed save; every entered value is kept.
    expect(revisionRequestsCount(page)).toBe(requestsBefore);
    await expect(page.locator('#edit-source-title')).toHaveValue(`${originalTitle} (revision draft)`);
    expect(await page.getByLabel('Price per person').first().inputValue()).toBe('77');

    // Explicit operational save sends ONLY the commercial patch and keeps the
    // unsaved governed source title.
    const operationalPromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url().includes(`/api/partner/tours/${tourId}`) && response.ok()
    );
    await page.getByRole('button', { name: 'Save price/schedule changes only' }).click();
    const operational = await operationalPromise;
    expect(operational.status()).toBe(200);
    const operationalBody = (await operational.request().postDataJSON()) as { options: Array<Record<string, unknown>> };
    expect(Object.keys(operationalBody)).toEqual(['options']);
    expect(operationalBody.options[0].id).toBe(originalOption.id);
    expect(operationalBody.options[0].price_amount).toBe(7700);
    await expect(page.getByText(/Price\/schedule changes saved/)).toBeVisible();
    await expect(page.locator('#edit-source-title')).toHaveValue(`${originalTitle} (revision draft)`);

    // Explicit content save sends ONLY the governed patch to the revision
    // endpoint — never the legacy PUT.
    const revisionsBefore = revisionRequestsCount(page);
    const contentPromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/partner\/tours\/\d+\/revisions\/?$/.test(response.url()) &&
        response.status() === 201
    );
    await page.getByRole('button', { name: 'Save content changes for review' }).click();
    const content = await contentPromise;
    expect(content.status()).toBe(201);
    const contentBody = (await content.request().postDataJSON()) as {
      base_revision_id: number;
      translations: Record<string, { title: string }>;
      options?: unknown;
      media?: unknown;
    };
    expect(typeof contentBody.base_revision_id).toBe('number');
    expect(Object.values(contentBody.translations)[0].title).toBe(`${originalTitle} (revision draft)`);
    expect(contentBody.options).toBeUndefined();
    expect(contentBody.media).toBeUndefined();
    const revisionResponse = (await content.json()) as { data: { id: number; revision_number: number; state: string } };
    expect(revisionResponse.data.state).toBe('draft');
    await expect(
      page.getByText(new RegExp(`draft revision #${revisionResponse.data.revision_number}`))
    ).toBeVisible();
    expect(revisionRequestsCount(page)).toBe(revisionsBefore + 1);

    // Restore the commercial baseline of the seeded tour (net-zero fixture
    // impact), then withdraw the proposal through the real API.
    const restorePromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url().includes(`/api/partner/tours/${tourId}`) && response.ok()
    );
    await page.getByLabel('Price per person').first().fill(String(originalOption.price_amount / 100));
    await page.getByRole('button', { name: 'Save price/schedule changes only' }).click();
    await restorePromise;
    const withdraw = await apiPost(page, `/api/partner/tours/${tourId}/revisions/${revisionResponse.data.id}/withdraw`, {});
    expect(withdraw.status).toBe(200);
    const reread = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(ownedEnRow(reread.json).title).toBe(originalTitle);
    } finally {
      const revs = await apiGet(page, `/api/partner/tours/${tourId}/revisions`);
      if (revs.status === 200) {
        const rows = (revs.json as { data?: Array<{ id: number; state: string }> }).data ?? [];
        for (const rev of rows) {
          if (['draft', 'proposed'].includes(rev.state)) {
            await apiPost(page, `/api/partner/tours/${tourId}/revisions/${rev.id}/withdraw`, {});
          }
        }
      }
      const latest = await apiGet(page, `/api/partner/tours/${tourId}`);
      if (latest.status === 200) {
        const latestOpts = (latest.json as { data?: { options?: Array<{ price_amount: number }> } }).data?.options ?? [];
        if (latestOpts[0] && latestOpts[0].price_amount !== originalOption.price_amount) {
          await page.goto(`/en/partner/tours/${tourId}/edit`);
          await expect(page.getByLabel('Price per person').first()).toBeVisible();
          const cleanupSave = page.waitForResponse(
            (response) => response.request().method() === 'PUT' && response.url().includes(`/api/partner/tours/${tourId}`) && response.ok()
          );
          await page.getByLabel('Price per person').first().fill(String(originalOption.price_amount / 100));
          await page.getByRole('button', { name: 'Save price/schedule changes only' }).click();
          await cleanupSave;
        }
      }
    }
  });

  test("a saved candidate reloads with its full payload, and its immutable replacement returns a NEW id with the old one superseded", async ({ page }) => {
    const tourId = await seededOwnedTourId(page);
    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(detail.status).toBe(200);
    const options = (detail.json as { data: { options: Array<{ id: number; price_amount: number }> } }).data.options;
    expect(options.length).toBeGreaterThan(0);
    const originalPriceMajor = String(options[0].price_amount / 100);
    const originalTitle = ownedEnRow(detail.json).title as string;
    try {
      await page.goto(`/en/partner/tours/${tourId}/edit`);
      await expect(page.locator('#edit-source-title')).toHaveValue(originalTitle);

    // Governance read exposes the approved base for the proposal.
    const governanceBefore = (
      detail.json as {
        data: {
          content_governance?: {
            approved_revision?: { id: number };
            active_candidate?: { id: number } | null;
          };
        };
      }
    ).data.content_governance;
    const baseId = governanceBefore?.approved_revision?.id;
    expect(typeof baseId).toBe('number');

    const candidateTitle = uniqueTitle('EGM003 candidate');
    await page.locator('#edit-source-title').fill(candidateTitle);
    await page.locator('#edit-source-meeting-point').fill('Revised garden gate');
    await page.getByLabel('Option title').first().fill('Reviewed option text survives refresh');
    await page.locator('#revision-change-summary').fill('Updated candidate title and meeting gate');
    const createPromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/partner\/tours\/\d+\/revisions\/?$/.test(response.url()) &&
        response.status() === 201
    );
    await page.getByRole('button', { name: 'Save content changes for review' }).click();
    const created = await createPromise;
    const createdData = (await created.json()) as {
      data: { id: number; revision_number: number; state: string; payload_hash: string };
    };
    expect(createdData.data.state).toBe('draft');
    await expect(page.getByText(new RegExp(`draft revision #${createdData.data.revision_number}`))).toBeVisible();

    // Reload: the FULL candidate payload is restored through the revision
    // read (not the opaque editor snapshot), preserving the authored edits.
    await page.reload();
    await expect(page.locator('#edit-source-title')).toHaveValue(candidateTitle);
    await expect(page.locator('#edit-source-meeting-point')).toHaveValue('Revised garden gate');
    await expect(page.getByLabel('Option title').first()).toHaveValue('Reviewed option text survives refresh');
    await expect(page.locator('#revision-change-summary')).toHaveValue('Updated candidate title and meeting gate');
    await expect(page.getByText('Updated candidate title and meeting gate')).toBeVisible();
    await expect(page.getByLabel('Price per person').first()).toHaveValue(originalPriceMajor);

    // Immutable replacement: the PUT carries the observed CAS token and the
    // server answers with a NEW id; the first candidate becomes superseded.
    const oldId = createdData.data.id;
    const candidateTitleV2 = `${candidateTitle} v2`;
    await page.locator('#edit-source-title').fill(candidateTitleV2);
    const replacePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        response.url().includes(`/api/partner/tours/${tourId}/revisions/${oldId}`) &&
        response.ok()
    );
    await page.getByRole('button', { name: 'Save content changes for review' }).click();
    const replaced = await replacePromise;
    const replacedBody = (await replaced.request().postDataJSON()) as {
      base_revision_id: number;
      observed_payload_hash: string;
    };
    expect(replacedBody.observed_payload_hash).toBe(createdData.data.payload_hash);
    expect(replacedBody.base_revision_id).toBe(baseId);
    const replacedData = (await replaced.json()) as { data: { id: number; state: string } };
    expect(replacedData.data.state).toBe('draft');
    expect(replacedData.data.id).not.toBe(oldId);

    // The old reviewed id never mutated: it reads back superseded.
    const oldSnapshot = await apiGet(page, `/api/partner/tours/${tourId}/revisions/${oldId}`);
    expect(oldSnapshot.status).toBe(200);
    const oldBody = oldSnapshot.json as { data: { state: string } };
    expect(oldBody.data.state).toBe('superseded');

    // Public content is untouched while only proposals exist.
    const tour = (detail.json as { data: { slug: string } }).data;
    const publicBefore = await page.request.get(`/api/public/tours/${tour.slug}?locale=en`);
    const publicData = (await publicBefore.json()) as { data: { title: string } };
    expect(publicData.data.title).toBe(originalTitle);

    // Clean withdraw so the seeded tour has no lingering proposal.
    const withdraw = await apiPost(page, `/api/partner/tours/${tourId}/revisions/${replacedData.data.id}/withdraw`, {});
    expect(withdraw.status).toBe(200);
    } finally {
      const revs = await apiGet(page, `/api/partner/tours/${tourId}/revisions`);
      if (revs.status === 200) {
        const rows = (revs.json as { data?: Array<{ id: number; state: string }> }).data ?? [];
        for (const rev of rows) {
          if (['draft', 'proposed'].includes(rev.state)) {
            await apiPost(page, `/api/partner/tours/${tourId}/revisions/${rev.id}/withdraw`, {});
          }
        }
      }
    }
  });

  test('an unsaved editor shows keyboard-focused guidance and localized panels across the EN/ES/IT matrix', async ({ page }) => {
    const tourId = await seededOwnedTourId(page);
    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    const originalTitle = ownedEnRow(detail.json).title as string;

    // Keyboard: the mixed-save guidance region receives focus when it appears.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(originalTitle);
    await page.locator('#edit-source-title').fill(`${originalTitle} keyboard probe`);
    await page.getByLabel('Price per person').first().fill('99');
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    const guidance = page.getByRole('status').filter({ hasText: /Save them separately with/ });
    await expect(guidance).toBeVisible();
    await expect(guidance).toBeFocused();

    // Localized governance panels (EN/ES/IT, distributed widths) — reads only;
    // the typed probe above is never saved (the mixed-save path sends nothing).
    const cases = [
      { locale: 'en', width: 390, heading: 'Content governance' },
      { locale: 'es', width: 768, heading: 'Gobernanza de contenido' },
      { locale: 'it', width: 1024, heading: 'Governo dei contenuti' },
      { locale: 'en', width: 1440, heading: 'Content governance' },
    ] as const;
    for (const { locale, width, heading } of cases) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/${locale}/partner/tours/${tourId}/edit`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await expect(page.getByRole('heading', { name: heading })).toBeVisible();
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    }
  });
});

test.describe('EGM-003 full admin ↔ partner ↔ public journey (real Filament second context)', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('request changes → partner responds → exact revision approval → public old and new content', async ({ browser }) => {
    // Partner context: the REAL seeded partner session from partner.setup.ts;
    // admin context: REAL Filament login — never a fabricated approval route.
    const partnerContext = await browser.newContext({ storageState: PARTNER_STORAGE_STATE });
    const adminContext = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      isMobile: false,
      hasTouch: false,
      storageState: { cookies: [], origins: [] },
    });
    try {
      await addConsentCookies(partnerContext);
      const page = await partnerContext.newPage();
      const adminPage = await adminContext.newPage();

    // 1) Fresh tour through the REAL partner API, submitted for moderation.
    const tourId = await createTourThroughPartnerApi(page);
    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    const slug = (detail.json as { data: { slug: string } }).data.slug;
    const originalTitle = ownedEnRow(detail.json).title as string;

    // 2) Admin approves the first publication through the REAL Filament.
    await filamentLogin(adminPage);
    await filamentTourAction(adminPage, tourId, /^approve/i);
    const afterApproval = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect((afterApproval.json as { data: { status: string } }).data.status).toBe('published');

    // 3) Partner proposes a content revision and submits it for review.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(originalTitle);
    const proposedTitle = uniqueTitle('EGM003 approved edition');
    await page.locator('#edit-source-title').fill(proposedTitle);
    const submitPromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/partner\/tours\/\d+\/revisions\/?$/.test(response.url()) &&
        response.status() === 201
    );
    await page.getByRole('button', { name: 'Save content changes for review' }).click();
    const created = await submitPromise;
    const candidate = (await created.json()) as { data: { id: number } };
    const submitResponse = await apiPost(page, `/api/partner/tours/${tourId}/revisions/${candidate.data.id}/submit`, {});
    expect(submitResponse.status).toBe(200);

    // Public content stays the OLD approved content while under review.
    await expect
      .poll(async () => {
        const response = await page.request.get(`/api/public/tours/${slug}?locale=en`);
        const body = (await response.json()) as { data: { title: string } };
        return body.data.title;
      }, { timeout: 30000 })
      .toBe(originalTitle);

    // 4) Admin requests changes with a mandatory reason + field reference.
    await filamentTourAction(
      adminPage,
      tourId,
      /request changes|ask for changes|change request/i,
      'Please clarify the revised meeting point.'
    );

    // 5) Partner sees the mandatory reason + field reference and responds with
    // the exact submitted candidate.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.getByText(/Please clarify the revised meeting point\./)).toBeVisible({ timeout: 30000 });
    await expect(
      page.getByText('source.title').or(page.getByText('source.meeting_point')).first()
    ).toBeVisible();
    await page.getByLabel('Your response note (optional)').fill('Meeting point clarified in the revision.');
    const respondPromise = page.waitForResponse(
      (response) => response.request().method() === 'POST' && response.url().includes('/revision-requests/') && response.ok()
    );
    await page.getByRole('button', { name: 'Send response' }).click();
    await respondPromise;

    // 6) Admin approves the EXACT submitted revision through Filament.
    await filamentTourAction(adminPage, tourId, /approve revision|approve/i);

    // 7) The public page + API expose the NEW approved content.
    await expect
      .poll(async () => {
        const response = await page.request.get(`/api/public/tours/${slug}?locale=en`);
        const body = (await response.json()) as { data: { title: string } };
        return body.data.title;
      }, { timeout: 45000 })
      .toBe(proposedTitle);
    await page.goto(`/en/tours/${slug}`);
    await expect(page.getByRole('heading', { name: proposedTitle })).toBeVisible({ timeout: 30000 });

    } finally {
      await partnerContext.close().catch(() => {});
      await adminContext.close().catch(() => {});
    }
  });

  test('an administrator rejection keeps the approved public content and shows the rejected revision state', async ({ browser }) => {
    const partnerContext = await browser.newContext({ storageState: PARTNER_STORAGE_STATE });
    const adminContext = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      isMobile: false,
      hasTouch: false,
      storageState: { cookies: [], origins: [] },
    });
    try {
      await addConsentCookies(partnerContext);
      const page = await partnerContext.newPage();
      const adminPage = await adminContext.newPage();

    const tourId = await createTourThroughPartnerApi(page);
    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    const slug = (detail.json as { data: { slug: string } }).data.slug;
    const originalTitle = ownedEnRow(detail.json).title as string;

    await filamentLogin(adminPage);
    await filamentTourAction(adminPage, tourId, /^approve/i);
    expect((await apiGet(page, `/api/partner/tours/${tourId}`)).status).toBe(200);

    // Propose + submit a revision, then REJECT it through Filament.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    const proposedTitle = `${uniqueTitle('EGM003 rejected')} rejected`;
    await page.locator('#edit-source-title').fill(proposedTitle);
    const createPromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/partner\/tours\/\d+\/revisions\/?$/.test(response.url()) &&
        response.status() === 201
    );
    await page.getByRole('button', { name: 'Save content changes for review' }).click();
    const created = await createPromise;
    const candidate = (await created.json()) as { data: { id: number } };
    expect((await apiPost(page, `/api/partner/tours/${tourId}/revisions/${candidate.data.id}/submit`, {})).status).toBe(200);

    await filamentTourAction(
      adminPage,
      tourId,
      /reject/i,
      'The proposed meeting point is not operationally correct.'
    );

    // Partner sees the rejected revision state; public content keeps the old
    // approved title.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.getByText('Rejected', { exact: true }).first()).toBeVisible({ timeout: 30000 });
    const publicAfterReject = await page.request.get(`/api/public/tours/${slug}?locale=en`);
    const publicData = (await publicAfterReject.json()) as { data: { title: string } };
    expect(publicData.data.title).toBe(originalTitle);

    } finally {
      await partnerContext.close().catch(() => {});
      await adminContext.close().catch(() => {});
    }
  });
});
