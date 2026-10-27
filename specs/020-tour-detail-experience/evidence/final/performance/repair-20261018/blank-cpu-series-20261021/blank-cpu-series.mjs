import fs from 'node:fs';
import crypto from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const TMP = 'C:/Users/HaTeM/.codex/tmp/bookly-spec020/lighthouse-repair-20261018/blank-cpu-series-20261021';
const ROOT = 'F:/Travel Website/bookly travel';
const HELPER_PATH = path.join(ROOT, 'frontend', 'scripts', 'lighthouse-runtime-preflight.mjs');
const PKG_PATH = path.join(ROOT, 'frontend', 'package.json');
const CHROME_PATH = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const ROUNDS = 6;
const BETWEEN_WAIT_MS = 10000;
const WATCHDOG_MS = 285000;
const LAUNCH_ARGS = ['--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking'];

const require = createRequire(PKG_PATH);
const puppeteer = require('puppeteer-core');

const helperSource = fs.readFileSync(HELPER_PATH);
const helperSha256 = crypto.createHash('sha256').update(helperSource).digest('hex');

const rawOut = (line) => { try { fs.writeSync(1, line + '\n'); } catch (e) { try { console.error('stdout-write-failed:', String(e && e.message || e)); } catch {} } };
const diag = (obj) => rawOut(JSON.stringify(obj));
const allHelperLines = [];
let activeRound = null;
console.log = (...args) => {
  const line = args.map((a) => (typeof a === 'string' ? a : String(a))).join(' ');
  allHelperLines.push(line);
  if (activeRound) activeRound.helperConsoleLogLines.push(line);
  rawOut(line);
};

const helperModule = await import(pathToFileURL(HELPER_PATH).href);
const { prepareBrowserCpu, cpuReady } = helperModule;

const startedAt = Date.now();
const startedAtUtc = new Date().toISOString();
let browser = null;
let finished = false;
let anyUnexpectedUrl = false;
const roundRecords = [];
const notRunRounds = [];

const sleep = (ms) => new Promise((res) => setTimeout(res, ms));

function filterUnexpected(urls) {
  return (Array.isArray(urls) ? urls : []).filter((u) => u !== 'about:blank');
}

function isSampleLine(line) {
  try {
    const o = JSON.parse(line);
    return !!o && typeof o === 'object' && Number.isFinite(o.attempt) && Number.isFinite(o.benchmarkIndex) && o.appVisited === false && o.url === 'about:blank';
  } catch {
    return false;
  }
}

async function snap(tag) {
  const s = {
    tag,
    atUtc: new Date().toISOString(),
    elapsedMs: Date.now() - startedAt,
    nodeVersion: process.version,
    nodePid: process.pid,
    helperSha256,
    platform: process.platform,
    browserPid: null,
    pageCount: null,
    pageUrls: null,
    loadavg: os.loadavg(),
    freeMemoryBytes: os.freemem(),
    resourceUsage: process.resourceUsage(),
  };
  try {
    s.browserPid = browser ? (browser.process?.()?.pid ?? null) : null;
  } catch (e) {
    s.browserPidError = String((e && e.message) || e);
  }
  try {
    const pages = await browser.pages();
    s.pageUrls = pages.map((p) => p.url());
    s.pageCount = pages.length;
  } catch (e) {
    s.pagesError = String((e && e.message) || e);
  }
  return s;
}

function finishSession(exitCode, reason, extra = {}) {
  if (finished) return;
  finished = true;
  const endedAtUtc = new Date().toISOString();
  const allSixRoundsFinished = roundRecords.length === ROUNDS && !anyUnexpectedUrl;
  const summary = {
    type: 'summary-json',
    diagnostic: 'blank-cpu-series',
    purpose: 'determine whether CPU readiness decays across repeated prepareBrowserCpu rounds on the SAME browser with NO application navigation (every page URL must remain about:blank)',
    completionSemantics: 'all six rounds finished = diagnostic completion; per-round pass/fail with exact errors recorded; completion is NOT a performance pass',
    startedAtUtc,
    endedAtUtc,
    totalElapsedMs: Date.now() - startedAt,
    exitCode,
    finishReason: reason,
    allSixRoundsFinished,
    roundsPassed: roundRecords.filter((r) => r.helperPassed === true).length,
    roundsFailed: roundRecords.filter((r) => r.helperPassed === false).length,
    anyUnexpectedUrl,
    appVisited: !anyUnexpectedUrl,
    meta: {
      nodeVersion: process.version,
      nodePid: process.pid,
      platform: process.platform,
      helperPath: HELPER_PATH,
      helperSha256,
      helperImportUrl: pathToFileURL(HELPER_PATH).href,
      puppeteerRequireBase: PKG_PATH,
      chromePath: CHROME_PATH,
      launchArgs: LAUNCH_ARGS,
      headless: true,
      maxCpuAttemptsPerRound: 30,
      cpuBudgetMsPerRound: 30000,
      requiredReadyBenchmark: 1500,
      allowedSampleRatio: 1.2,
      betweenRoundsWaitMs: BETWEEN_WAIT_MS,
      watchdogMs: WATCHDOG_MS,
      loadavgNote: 'os.loadavg() is recorded raw; on Windows it returns [0,0,0] meaning unavailable on this platform, not idle. No thermal or background-cause claim is made without evidence.',
      resourceUsageNote: 'process.resourceUsage() of the diagnostic Node process, recorded raw per snapshot.',
    },
    helperContract: {
      prepareBrowserCpuIsFunction: typeof prepareBrowserCpu === 'function',
      cpuReadyIsFunction: typeof cpuReady === 'function',
      helperConsoleSampleShape: 'one JSON line per sample: { attempt, benchmarkIndex, url: "about:blank", appVisited: false }',
    },
    rounds: roundRecords,
    notRunRounds,
    allHelperConsoleLogLinesVerbatim: allHelperLines,
    ...extra,
  };
  try {
    fs.writeFileSync(path.join(TMP, 'summary.json'), JSON.stringify(summary, null, 2));
  } catch (e) {
    diag({ type: 'summary-write-error', error: String((e && e.message) || e) });
  }
  rawOut(JSON.stringify(summary));
}

const watchdog = setTimeout(async () => {
  const extra = { watchdogFiredAtUtc: new Date().toISOString(), roundsRunAtWatchdog: roundRecords.length };
  try { if (browser) { await browser.close(); } } catch {}
  finishSession(2, 'watchdog-budget-exceeded', extra);
  process.exit(process.exitCode || 2);
}, WATCHDOG_MS);

try {
  browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: true,
    args: LAUNCH_ARGS,
  });
} catch (e) {
  finishSession(3, 'browser-launch-failed', { launchError: String((e && e.message) || e) });
  process.exit(process.exitCode || 3);
}

const launchedPid = browser.process?.()?.pid ?? null;
diag({ type: 'browser-launched', browserPid: launchedPid, atUtc: new Date().toISOString(), nodePid: process.pid, nodeVersion: process.version, helperSha256, headless: true, args: LAUNCH_ARGS });

const initialSnap = await snap('initial-after-launch');
if (filterUnexpected(initialSnap.pageUrls).length > 0) {
  anyUnexpectedUrl = true;
  finishSession(1, 'unexpected-url-at-launch-abort', { unexpectedUrlsAtLaunch: filterUnexpected(initialSnap.pageUrls), initialSnap });
  try { await browser.close(); } catch {}
  clearTimeout(watchdog);
  process.exit(process.exitCode || 1);
}

for (let i = 1; i <= ROUNDS; i++) {
  if (finished) {
    notRunRounds.push({ round: i, reason: 'session-finalized-before-start' });
    continue;
  }
  const r = { round: i, helperConsoleLogLines: [], helperPassed: null, error: null };
  activeRound = r;
  if (i > 1) {
    r.waitBetweenRoundsMs = BETWEEN_WAIT_MS;
    const waitedAtUtc = new Date().toISOString();
    await sleep(BETWEEN_WAIT_MS);
    r.waitedAt = { waitedAtUtc, resumedAtUtc: new Date().toISOString() };
  }
  if (finished) {
    activeRound = null;
    notRunRounds.push({ round: i, reason: 'session-finalized-before-round-start' });
    continue;
  }
  const roundStartClock = Date.now();
  r.startedAtUtc = new Date().toISOString();
  r.before = await snap('before');
  try {
    await prepareBrowserCpu(browser);
    r.helperPassed = true;
  } catch (e) {
    r.helperPassed = false;
    r.error = String((e && e.message) || e);
  }
  r.roundElapsedMs = Date.now() - roundStartClock;
  r.endedAtUtc = new Date().toISOString();
  r.after = await snap('after');
  r.unexpectedUrlsBefore = filterUnexpected(r.before.pageUrls);
  r.unexpectedUrlsAfter = filterUnexpected(r.after.pageUrls);
  r.appVisited = r.unexpectedUrlsAfter.length === 0;
  r.samples = r.helperConsoleLogLines.filter(isSampleLine);
  r.sampleValues = r.samples.map((line) => {
    try { return JSON.parse(line).benchmarkIndex; } catch { return null; }
  });
  try {
    r.cpuReadyOnRoundSamples = cpuReady(r.sampleValues);
  } catch (e) {
    r.cpuReadyOnRoundSamplesError = String((e && e.message) || e);
  }
  activeRound = null;
  roundRecords.push(r);
  diag({ type: 'round-done', round: i, helperPassed: r.helperPassed, error: r.error, sampleCount: r.sampleValues.length, firstSamples: r.sampleValues.slice(0, 3), lastSamples: r.sampleValues.slice(-3), cpuReadyOnRoundSamples: r.cpuReadyOnRoundSamples ?? null, appVisited: r.appVisited, pagesAfter: r.after.pageUrls, browserPid: r.after.browserPid, roundElapsedMs: r.roundElapsedMs });
  if (r.unexpectedUrlsAfter.length > 0) {
    anyUnexpectedUrl = true;
    finishSession(1, 'unexpected-url-abort', { abortedAtRound: i });
    try { await browser.close(); } catch {}
    clearTimeout(watchdog);
    process.exit(process.exitCode || 1);
  }
}

try { await browser.close(); } catch {}
clearTimeout(watchdog);
finishSession(0, roundRecords.length === ROUNDS ? 'completed' : 'round-loop-ended-early');
process.exit(process.exitCode || 0);
