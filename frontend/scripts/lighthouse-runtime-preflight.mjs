import { access, readdir } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');
const { Launcher } = await import('chrome-launcher');
const { pageFunctions } = await import('lighthouse/core/lib/page-functions.js');

async function chromePath() {
  if (process.env.CHROME_PATH) return process.env.CHROME_PATH;
  const cache = '/root/.cache/ms-playwright';
  const versions = await readdir(cache).catch(() => []);
  for (const version of versions.filter((name) => /^chromium-\d+$/.test(name))
    .sort((a, b) => Number(b.split('-')[1]) - Number(a.split('-')[1]))) {
    const executable = resolve(cache, version, 'chrome-linux64/chrome');
    if (await access(executable).then(() => true, () => false)) return executable;
  }
  return Launcher.getFirstInstallation();
}

// Check the desktop CPU baseline before applying Lighthouse's unchanged 4x
// mobile slowdown. Only about:blank is visited; application caches stay cold.
export function cpuReady(samples) {
  if (!Array.isArray(samples) || samples.some((value) => !Number.isFinite(value) || value <= 0)) return false;
  const pair = samples.slice(-2);
  return pair.length === 2 && pair.every((value) => Number.isFinite(value) && value >= 1500)
    && Math.max(...pair) / Math.min(...pair) <= 1.2;
}

export async function prepareBrowserCpu(browser) {
  const page = await browser.newPage();
  try {
    const samples = [];
    for (let attempt = 1; attempt <= 5; attempt++) {
      if (page.url() !== 'about:blank') throw new Error('CPU preflight must not visit the application');
      const benchmarkIndex = await page.evaluate(pageFunctions.computeBenchmarkIndex);
      samples.push(benchmarkIndex);
      console.log(JSON.stringify({ attempt, benchmarkIndex, url: page.url(), appVisited: false }));
      if (cpuReady(samples)) return;
    }
    throw new Error('CPU baseline is slow or unstable; performance audit must not start');
  } finally {
    await page.close();
  }
}

async function main() {
  const browser = await puppeteer.launch({
    executablePath: await chromePath(),
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking'],
  });
  try {
    await prepareBrowserCpu(browser);
  } finally {
    await browser.close();
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  main().catch((error) => { console.error(error.message); process.exitCode = 1; });
}
