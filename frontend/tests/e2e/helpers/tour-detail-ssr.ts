import { expect } from '@playwright/test';
import type { BrowserContext } from '@playwright/test';
import { createServer, type Server } from 'node:http';
import { spawn, type ChildProcess } from 'node:child_process';
import type { AddressInfo } from 'node:net';
import type { TourDetail } from '@/lib/api/types';

function childExited(child: ChildProcess): boolean {
  return child.exitCode !== null || child.signalCode !== null;
}

function listenServer(server: Server, port: number, host: string): Promise<void> {
  return new Promise<void>((resolve, reject) => {
    server.once('error', reject);
    server.listen(port, host, () => {
      server.off('error', reject);
      resolve();
    });
  });
}

async function freePort(): Promise<number> {
  const probe = createServer();
  await listenServer(probe, 0, '127.0.0.1');
  const port = (probe.address() as AddressInfo).port;
  await closeHttpServer(probe);
  return port;
}

export interface SsrHarness {
  mockPort: number;
  nextPort: number;
  stop: () => Promise<void>;
}

function closeHttpServer(server: Server): Promise<void> {
  return new Promise<void>((resolve, reject) => {
    server.closeAllConnections();
    try {
      server.close((error?: Error) => {
        if (error && (error as NodeJS.ErrnoException).code !== 'ERR_SERVER_NOT_RUNNING') reject(error);
        else resolve();
      });
    } catch (error) {
      // Only "nothing owned is listening" is an expected no-op; anything
      // else propagates instead of masking a real cleanup failure.
      if ((error as NodeJS.ErrnoException)?.code === 'ERR_SERVER_NOT_RUNNING') resolve();
      else reject(error as Error);
    }
  });
}

function waitForExit(child: ChildProcess, timeoutMs: number): Promise<void> {
  return new Promise<void>((resolve) => {
    if (childExited(child)) {
      resolve();
      return;
    }
    const onDone = (): void => {
      clearTimeout(timer);
      child.off('exit', onDone);
      child.off('close', onDone);
      resolve();
    };
    const timer = setTimeout(onDone, timeoutMs);
    child.once('exit', onDone);
    child.once('close', onDone);
  });
}

// Installed next start runs directly in this child; only this owned handle is terminated.
async function killOwned(child: ChildProcess): Promise<void> {
  if (childExited(child)) {
    return;
  }
  try {
    child.kill('SIGTERM');
  } catch (error) {
    if (!childExited(child)) throw error;
    return;
  }
  await waitForExit(child, 5000);
  if (!childExited(child)) {
    try {
      child.kill('SIGKILL');
    } catch (error) {
      if (!childExited(child)) throw error;
      return;
    }
    await waitForExit(child, 5000);
  }
  if (!childExited(child)) {
    throw new Error('Isolated Next server did not exit after SIGKILL');
  }
}

// A controlled status response the isolated Next server consumes through its
// real no-store detail read. Plain TourDetail readings keep the existing 200
// shape, so every current caller stays backwards compatible.
export type ControlledSsrStatus = {
  status: number;
  body?: unknown;
};

export type SsrReading = TourDetail | ControlledSsrStatus;

function isControlledStatus(value: SsrReading): value is ControlledSsrStatus {
  return typeof value === 'object' && value !== null && typeof (value as { status?: unknown }).status === 'number';
}
// Local SSR harness (Spec 019 US2, extended Spec 020 US6): page routes fetch
// from the server during SSR, so browser route mocking cannot intercept them.
// An isolated Next process plus a local API fixture server exercise the real
// page/metadata path without modifying shared tour data. The isolated Next
// server-fetches through API_INTERNAL_URL on every load because the detail
// fetch uses explicit no-store. US6 additionally serves controlled non-200
// statuses so 404/410/recoverable/hostile reads render through the same real
// status mapping the production page uses.
export async function startSsrHarness(slug: string, readCurrent: () => SsrReading): Promise<SsrHarness> {
  let mock: Server | null = null;
  let next: ChildProcess | null = null;
  let spawnFailed = false;
  const cleanup = async (): Promise<void> => {
    try {
      if (next !== null && !spawnFailed) {
        await killOwned(next);
      }
    } finally {
      if (mock !== null) {
        await closeHttpServer(mock);
      }
    }
  };
  let stopped = false;
  const stop = async (): Promise<void> => {
    if (stopped) {
      return;
    }
    stopped = true;
    await cleanup();
  };
  try {
    mock = createServer((req, res) => {
      if (!req.url?.startsWith(`/api/public/tours/${slug}`)) {
        res.writeHead(404).end();
        return;
      }
      const current = readCurrent();
      if (isControlledStatus(current)) {
        res.writeHead(current.status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
        res.end(JSON.stringify(current.body ?? {}));
        return;
      }
      res.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
      res.end(JSON.stringify({ data: current }));
    });
    await listenServer(mock, 0, '127.0.0.1');
    const mockPort = (mock.address() as AddressInfo).port;
    const nextPort = await freePort();
    const child = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-p', String(nextPort), '-H', '127.0.0.1'], {
      cwd: process.cwd(),
      env: { ...process.env, API_INTERNAL_URL: `http://127.0.0.1:${mockPort}`, NEXT_TELEMETRY_DISABLED: '1' },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    next = child;
    let startupOutput = '';
    let spawnError: Error | null = null;
    child.on('error', (error: Error) => {
      spawnError = error;
      spawnFailed = true;
    });
    child.stdout.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
    child.stderr.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
    await expect.poll(async () => {
      if (spawnError !== null || childExited(child)) {
        throw new Error(`Isolated Next server exited${spawnError ? `: ${spawnError.message}` : ''}: ${startupOutput}`);
      }
      try {
        return (await fetch(`http://127.0.0.1:${nextPort}/favicon.ico`, { signal: AbortSignal.timeout(5000) })).status;
      } catch {
        return 0;
      }
    }, { timeout: 30000 }).not.toBe(0);
    return {
      mockPort,
      nextPort,
      stop,
    };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

// The actual boundary response the isolated Next server consumes. Asserting
// the served JSON (rather than the test's own fixture object) proves what
// SSR actually rendered the fallback/ready transition from.
export async function readServedDetail(mockPort: number, slug: string, locale: string): Promise<TourDetail> {
  const response = await fetch(`http://127.0.0.1:${mockPort}/api/public/tours/${slug}?locale=${locale}`);
  expect(response.status).toBe(200);
  return ((await response.json()).data) as TourDetail;
}

// Focused native media transport (Spec 020 US3 review): the Docker-only
// fixture CDN (cdn.bookly.test) cannot resolve on a native host, so every
// gallery image would fail and no loaded/failed distinction could be proven.
// When SPEC020_NATIVE_MEDIA_PROXY is exactly 'http://localhost:8080', image
// requests whose encoded source host is cdn.bookly.test are forwarded to
// that owned media server with Host: cdn.bookly.test, and the ACTUAL
// upstream response (status and bytes, including real 404s for missing
// paths) is fulfilled. Everything else continues untouched: no API,
// session, or production-config interception. Root sets the flag for
// intermediate native runs only; final Docker media/optimizer acceptance
// leaves it unset (no-op) against the real CDN. Adapter runs prove gallery
// behavior, never the production optimizer.
export async function enableNativeMediaProxy(context: BrowserContext): Promise<void> {
  const proxy = process.env.SPEC020_NATIVE_MEDIA_PROXY;
  if (proxy !== 'http://localhost:8080') return;
  await context.route('**/_next/image**', async (route) => {
    const source = new URL(route.request().url()).searchParams.get('url');
    const sourceUrl = source ? new URL(source) : null;
    if (sourceUrl?.hostname !== 'cdn.bookly.test') {
      await route.continue();
      return;
    }
    const upstream = await context.request.get(`${proxy}${sourceUrl.pathname}${sourceUrl.search}`, {
      headers: { Host: 'cdn.bookly.test' },
    });
    await route.fulfill({ response: upstream });
  });
}

// Raw status plus parsed body of the same boundary response, for controlled
// non-200 SSR fixtures. Proves which status the Next server actually mapped
// through its real client errors (404→NotFoundError, 410→GoneError, …).
export async function fetchServedResponse(
  mockPort: number,
  slug: string,
  locale: string,
): Promise<{ status: number; body: unknown }> {
  const response = await fetch(`http://127.0.0.1:${mockPort}/api/public/tours/${slug}?locale=${locale}`);
  const body = await response.json().catch(() => ({}));
  return { status: response.status, body };
}
