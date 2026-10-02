import assert from 'node:assert/strict';
import test from 'node:test';
import { assessHostResources, assessReport, busyProcessNames, collectAudits } from './lighthouse-audit.mjs';

test('rejects the observed Windows commit pressure before launching Chrome', () => {
  const result = assessHostResources({ availableMB: 2109, committedBytes: 30649327616, commitLimitBytes: 33724551168 });
  assert.equal(result.allowed, false);
  assert.ok(result.reasons.includes('commit-pressure'));
});

test('requires both physical and commit headroom, not just free RAM', () => {
  assert.equal(assessHostResources({ availableMB: 4096, committedBytes: 30 * 1024 ** 3, commitLimitBytes: 32 * 1024 ** 3 }).allowed, false);
  assert.equal(assessHostResources({ availableMB: 512, committedBytes: 10 * 1024 ** 3, commitLimitBytes: 32 * 1024 ** 3 }).allowed, false);
  assert.equal(assessHostResources({ availableMB: 4096, committedBytes: 10 * 1024 ** 3, commitLimitBytes: 32 * 1024 ** 3 }).allowed, true);
});

test('missing or malformed memory evidence cannot pass preflight', () => {
  for (const snapshot of [null, {}, { availableMB: -1 }, { availableMB: 4096, committedBytes: 0, commitLimitBytes: 0 }]) {
    assert.equal(assessHostResources(snapshot).allowed, false);
  }
});

const report = (performance = 0.95) => ({
  categories: { performance: { score: performance }, accessibility: { score: 1 }, 'best-practices': { score: 0.79 }, seo: { score: 0.92 } },
  environment: { benchmarkIndex: 1900 },
});

test('scores below 90 fail, even if another round passes', () => {
  assert.equal(assessReport(report(0.79)).passed, false);
  assert.equal(assessReport(report(0.9)).passed, true);
});

test('an incomplete or errored audit is never a score pass', () => {
  assert.equal(assessReport({ ...report(), runtimeError: { code: 'PAGE_HUNG' } }).passed, false);
  assert.equal(assessReport({ categories: {} }).passed, false);
  assert.equal(assessReport(report(), 1).passed, false);
  assert.equal(assessReport(report(1.1)).passed, false);
});

test('busy workload detection reports names only and excludes this runner', () => {
  assert.deepEqual(busyProcessNames([
    'node /app/node_modules/.bin/playwright test --workers=1',
    'node /app/node_modules/jest/bin/jest.js --runInBand',
    'sh -c next build',
    'node scripts/lighthouse-audit.mjs',
    'next-server (v16.2.3)',
  ]), ['Playwright', 'Jest', 'Next build']);
});

test('both rounds are serial and a failing first score cannot be replaced by later passes', async () => {
  let active = 0;
  let peak = 0;
  let calls = 0;
  const summary = await collectAudits(async () => {
    active++;
    peak = Math.max(peak, active);
    await Promise.resolve();
    active--;
    return { report: report(++calls === 1 ? 0.79 : 0.95) };
  });
  assert.equal(peak, 1);
  assert.equal(summary.results.length, 8);
  assert.equal(summary.passed, false);
  assert.equal(summary.notRun, 0);
});

test('execution failure records an incomplete gate and does not launch further audits', async () => {
  const summary = await collectAudits(async () => { throw new Error('allocation failed'); });
  assert.equal(summary.passed, false);
  assert.equal(summary.aborted, true);
  assert.equal(summary.notRun, 7);
});
