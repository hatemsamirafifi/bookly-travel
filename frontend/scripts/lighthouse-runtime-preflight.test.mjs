import assert from 'node:assert/strict';
import test, { mock } from 'node:test';
import { performance } from 'node:perf_hooks';
import { cpuReady, prepareBrowserCpu } from './lighthouse-runtime-preflight.mjs';

test('a stable desktop CPU permits the unchanged mobile slowdown', () => {
  assert.equal(cpuReady([1800, 1850]), true);
  assert.equal(cpuReady([1500, 1500]), true);
});

test('a slow or changing CPU must block the performance audit', () => {
  assert.equal(cpuReady([1400, 1450]), false);
  assert.equal(cpuReady([1500, 2000]), false);
});

test('startup settling requires two consecutive stable samples', () => {
  assert.equal(cpuReady([500, 1800]), false);
  assert.equal(cpuReady([500, 1800, 1850]), true);
});

test('missing or invalid measurements cannot accept the audit', () => {
  for (const samples of [undefined, [], [1800], [NaN, 1800, 1850], [Infinity, 1900], ['1800', 1850], [-1, 1800]]) {
    assert.equal(cpuReady(samples), false);
  }
});

// Deterministic fake browser for prepareBrowserCpu: url() is always controllable,
// evaluate() is never the real benchmark and never visits the application.
// onEvaluate is invoked after every evaluate so the fake monotonic clock can
// elapse per sample. The helper must time its budget on the monotonic clock
// (node:perf_hooks performance.now), never on wall-clock Date.now.
function fakeBrowser({ values, url = () => 'about:blank', onEvaluate }) {
  const state = { evaluateCalls: 0, pageCloses: 0, pagesCreated: 0 };
  const page = {
    url: () => url(),
    async evaluate() {
      const attempt = ++state.evaluateCalls;
      const value = typeof values === 'function' ? values(attempt) : values[attempt - 1];
      if (onEvaluate) onEvaluate();
      return value;
    },
    async close() { state.pageCloses++; },
  };
  const browser = {
    async newPage() { state.pagesCreated++; return page; },
  };
  return { browser, state };
}

// Deterministic fake monotonic clock: every legitimate evaluate advances fakeNow.
// Only performance.now is mocked (mock.method) and restored in finally. Date.now
// is left alone; a helper timing on wall clock must not pass these tests.
function withFakeMonotonicClock(msPerSample, fn) {
  return async () => {
    let fakeNow = 0;
    const advance = (ms = msPerSample) => { fakeNow += ms; };
    const nowMock = mock.method(performance, 'now', () => fakeNow);
    try {
      await fn(advance);
    } finally {
      nowMock.mock.restore();
    }
  };
}

test('a browser that settles late is accepted while the exact unchanged criteria stay the gate',
  withFakeMonotonicClock(1000, async (advance) => {
    const logs = [];
    const realLog = console.log;
    console.log = (line) => { logs.push(line); };
    try {
      const { browser, state } = fakeBrowser({ values: [700, 800, 900, 1400, 1450, 1800, 1850], onEvaluate: advance });
      await prepareBrowserCpu(browser);
      assert.equal(state.evaluateCalls, 7);
      assert.equal(state.pageCloses, 1);
      assert.equal(state.pagesCreated, 1);
      for (const line of logs) assert.equal(JSON.parse(line).url, 'about:blank');
      for (const line of logs) assert.equal(JSON.parse(line).appVisited, false);
    } finally {
      console.log = realLog;
    }
  }));

test('persistent low, unstable, or invalid values exhaust the bounded budget and always reject',
  withFakeMonotonicClock(1000, async (advance) => {
    for (const values of [
      Array.from({ length: 35 }, () => 1400),
      Array.from({ length: 35 }, (_, i) => (i % 2 === 0 ? 1500 : 1900)),
      Array.from({ length: 35 }, () => NaN),
    ]) {
      const { browser, state } = fakeBrowser({ onEvaluate: advance, values });
      await assert.rejects(prepareBrowserCpu(browser), /slow or unstable/i);
      assert.equal(state.evaluateCalls, 30);
      assert.equal(state.pageCloses, 1);
    }
  }));

test('the monotonic-deadline budget stops low sample taking even when attempts remain',
  withFakeMonotonicClock(2500, async (advance) => {
    const { browser, state } = fakeBrowser({
      onEvaluate: advance,
      values: (attempt) => (attempt <= 12 ? (attempt % 2 === 1 ? 1400 : 1450) : 1800),
    });
    await assert.rejects(prepareBrowserCpu(browser), /exhausted/i);
    assert.equal(state.evaluateCalls, 12);
    assert.equal(state.pageCloses, 1);
  }));

test('a sample whose evaluation pushes monotonic elapsed over the 30s budget must reject even if the pair would qualify',
  withFakeMonotonicClock(0, async (advance) => {
    const { browser, state } = fakeBrowser({
      // Both samples individually qualify: (1700, 1720) is >= 1500 with ratio <= 1.2.
      values: [1700, 1720],
      onEvaluate: (() => {
        let evaluated = 0;
        return () => {
          evaluated += 1;
          if (evaluated === 2) advance(31000);
        };
      })(),
    });
    advance(1000);
    await assert.rejects(prepareBrowserCpu(browser), /exhausted/i);
    assert.equal(state.evaluateCalls, 2);
    assert.equal(state.pageCloses, 1);
  }));

test('a fast, stable browser still passes on the first qualifying pair without extra samples',
  withFakeMonotonicClock(1000, async (advance) => {
    const { browser, state } = fakeBrowser({ onEvaluate: advance, values: [1700, 1750] });
    await prepareBrowserCpu(browser);
    assert.equal(state.evaluateCalls, 2);
    assert.equal(state.pageCloses, 1);
  }));

test('an application URL can never be visited by the CPU preflight',
  withFakeMonotonicClock(1000, async () => {
    const { browser, state } = fakeBrowser({ values: [1800, 1850], url: () => '/en/tours/hidden-gems-rome-walking-tour' });
    await assert.rejects(prepareBrowserCpu(browser), /must not visit the application/i);
    assert.equal(state.evaluateCalls, 0);
    assert.equal(state.pageCloses, 1);
  }));
