import { execFile } from 'node:child_process';
import { constants } from 'node:fs';
import { access, mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const routes = [
  ['home', '/en'],
  ['search', '/en/search?q=rome'],
  ['tour', '/en/tours/hidden-gems-rome-walking-tour'],
  ['blog', '/en/blog/hidden-gems-florence'],
];

export function assessHostResources(snapshot) {
  const { availableMB, committedBytes, commitLimitBytes } = snapshot ?? {};
  if (![availableMB, committedBytes, commitLimitBytes].every(Number.isFinite)
    || availableMB < 0 || committedBytes < 0 || commitLimitBytes <= 0 || committedBytes > commitLimitBytes) {
    return { allowed: false, reasons: ['missing-memory-evidence'] };
  }
  const reasons = [];
  // Conservative preflight headroom, not a claim that lower pressure guarantees speed.
  if (committedBytes / commitLimitBytes >= 0.85) reasons.push('commit-pressure');
  if (commitLimitBytes - committedBytes < 4096 * 1024 ** 2) reasons.push('low-commit-headroom');
  if (availableMB < 2048) reasons.push('low-physical-headroom');
  return { allowed: reasons.length === 0, reasons };
}

export function assessReport(report, exitCode = 0) {
  const scores = Object.fromEntries(['performance', 'accessibility', 'best-practices', 'seo']
    .map((key) => [key, report?.categories?.[key]?.score ?? null]));
  const complete = Object.values(scores).every((value) => Number.isFinite(value) && value >= 0 && value <= 1);
  return {
    passed: exitCode === 0 && !report?.runtimeError && complete && scores.performance >= 0.9,
    scores,
    benchmarkIndex: report?.environment?.benchmarkIndex ?? null,
    runtimeError: report?.runtimeError?.code ?? null,
  };
}

export function busyProcessNames(commands) {
  const patterns = [
    ['Playwright', /\bplaywright\s+test\b|[/\\]playwright[/\\]lib[/\\]worker/],
    ['Jest', /[/\\]jest[/\\]bin[/\\]jest|\bjest\s+--/],
    ['Next build', /\bnext\s+build\b/],
    ['Next dev', /\bnext\s+dev\b/],
    ['TypeScript', /\btsc\s+--noEmit\b/],
    ['ESLint', /[/\\]eslint[/\\]bin[/\\]eslint/],
    ['Lighthouse', /[/\\]lighthouse[/\\]cli[/\\]index/],
  ];
  return patterns.filter(([, pattern]) => commands.some((command) => pattern.test(command))).map(([name]) => name);
}

export async function collectAudits(execute) {
  const results = [];
  let aborted = false;
  for (let round = 1; round <= 2 && !aborted; round++) {
    for (const [page, path] of routes) {
      try {
        const { report, exitCode = 0 } = await execute({ page, path, round });
        results.push({ page, round, ...assessReport(report, exitCode) });
        // A low score is retained and does not cause selective retry. CLI errors halt.
        if (exitCode !== 0 || report?.runtimeError) { aborted = true; break; }
      } catch {
        results.push({ page, round, passed: false, runtimeError: 'AUDIT_EXECUTION_FAILED' });
        aborted = true;
        break;
      }
    }
  }
  return { passed: !aborted && results.length === 8 && results.every((result) => result.passed), aborted, notRun: 8 - results.length, results };
}

async function runningWorkloads() {
  const pids = (await readdir('/proc')).filter((name) => /^\d+$/.test(name));
  const commands = await Promise.all(pids.map((pid) => readFile(`/proc/${pid}/cmdline`, 'utf8').catch(() => '')));
  return busyProcessNames(commands.map((command) => command.replaceAll('\0', ' ')));
}

async function chromePath() {
  if (process.env.CHROME_PATH) return process.env.CHROME_PATH;
  const cache = '/root/.cache/ms-playwright';
  const versions = (await readdir(cache)).filter((name) => /^chromium-\d+$/.test(name))
    .sort((a, b) => Number(b.split('-')[1]) - Number(a.split('-')[1]));
  for (const version of versions) {
    const executable = resolve(cache, version, 'chrome-linux64/chrome');
    if (await access(executable, constants.X_OK).then(() => true, () => false)) return executable;
  }
  throw new Error('Installed full Chromium not found; no browser is downloaded automatically.');
}

async function main() {
  if (process.platform !== 'linux') throw new Error('Use scripts/Invoke-PerformanceAudit.ps1 for the Docker acceptance environment.');
  const host = JSON.parse(process.env.BOOKLY_AUDIT_HOST_METRICS ?? 'null');
  const resources = assessHostResources(host);
  if (!resources.allowed) throw new Error(`Windows resource preflight blocked: ${resources.reasons.join(', ')}. Free resources before measuring.`);
  const busy = await runningWorkloads();
  if (busy.length) throw new Error(`Finish concurrent gates first: ${busy.join(', ')}.`);
  await access(resolve('.next/BUILD_ID'));
  const baseURL = new URL(process.env.BOOKLY_AUDIT_BASE_URL ?? 'http://nginx');
  if (!['http:', 'https:'].includes(baseURL.protocol) || baseURL.username || baseURL.password) throw new Error('Audit origin must be HTTP(S), without credentials.');
  const startedAt = new Date().toISOString();
  const directory = resolve('.phase0-evidence/lighthouse', `run-${startedAt.replaceAll(/[:.]/g, '-')}`);
  await mkdir(directory, { recursive: true });
  const chrome = await chromePath();
  const summary = await collectAudits(async ({ page, path, round }) => {
    const concurrent = await runningWorkloads();
    if (concurrent.length) {
      console.error(`Concurrent gate started: ${concurrent.join(', ')}. Remaining audits are not run.`);
      throw new Error('Concurrent workload');
    }
    const output = resolve(directory, `${page}-${round}.json`);
    let exitCode = 0;
    try {
      await execFileAsync(process.execPath, [resolve('node_modules/lighthouse/cli/index.js'), new URL(path, baseURL).href,
        '--output=json', `--output-path=${output}`, '--chrome-flags=--headless --no-sandbox --disable-dev-shm-usage',
        '--only-categories=performance,accessibility,best-practices,seo', '--quiet'],
      { env: { ...process.env, CHROME_PATH: chrome }, timeout: 120000, maxBuffer: 1024 * 1024 });
    } catch { exitCode = 1; }
    const report = await readFile(output, 'utf8').then(JSON.parse).catch(() => null);
    console.log(JSON.stringify({ page, round, ...assessReport(report, exitCode) }));
    return { report, exitCode };
  });
  await writeFile(resolve(directory, 'summary.json'), JSON.stringify({ startedAt, finishedAt: new Date().toISOString(),
    host, baseURL: baseURL.origin, conditions: 'Production build; serial mobile audits; scheduler left running; no concurrent gates', ...summary }, null, 2));
  console.log(`Reports retained: ${directory}; not run: ${summary.notRun}; gate: ${summary.passed ? 'PASS' : 'FAIL'}`);
  process.exitCode = summary.passed ? 0 : 1;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === resolve(process.argv[1])) {
  main().catch((error) => { console.error(error.message); process.exitCode = 1; });
}
