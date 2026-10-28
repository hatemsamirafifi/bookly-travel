/* eslint-disable @typescript-eslint/no-require-imports -- plain CJS on purpose: loaded via `node --require` before any ESM machinery */

'use strict';

// Loaded via `node --require` inside the isolated SSR harness Next child
// (see startSsrHarness). Two Docker-only hostnames would otherwise break
// native harness runs:
// 1. cdn.bookly.test — the fixture CDN. The image optimizer's upstream fetch
//    fails with ENOTFOUND, which it surfaces as HTTP 500 instead of the owned
//    upstream status, so gallery loaded/failed distinctions collapse.
// 2. nginx — Next rewrites are baked into .next/routes-manifest.json at build
//    time (inside Docker, API_INTERNAL_URL=http://nginx), so browser-facing
//    same-origin /api/* proxies target http://nginx regardless of the child's
//    env. Redirecting the host keeps those proxies on the harness mock.
// Both redirect to the harness mock server, which serves the same fixture
// media bytes the Docker CDN would and relays tour review reads to the real
// application stack. Everything else is untouched.
const net = require('node:net');

const REDIRECTED_HOSTS = new Set(['cdn.bookly.test', 'nginx']);
const MOCK_PORT = Number(process.env.BOOKLY_MOCK_PORT || 0);

if (MOCK_PORT > 0 && !net.connect.__booklyCdnRedirect) {
  const redirect = function redirect(original) {
    const patched = function connect(options, ...rest) {
      if (
        options &&
        typeof options === 'object' &&
        !Array.isArray(options) &&
        typeof options !== 'string' &&
        (REDIRECTED_HOSTS.has(options.hostname) || REDIRECTED_HOSTS.has(options.host))
      ) {
        options = { ...options, host: '127.0.0.1', hostname: '127.0.0.1', port: MOCK_PORT };
      }
      return original.call(this, options, ...rest);
    };
    patched.__booklyCdnRedirect = true;
    return patched;
  };
  net.connect = redirect(net.connect);
  net.createConnection = redirect(net.createConnection);
}
