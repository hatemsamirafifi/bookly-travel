// LHCI joins the hook path to its working directory, even for absolute paths.
// eslint-disable-next-line @typescript-eslint/no-require-imports
const path = require('node:path');

module.exports = {
  ci: {
    collect: {
      url: [
        '/en',
        '/en/search?q=rome',
        '/en/tours/hidden-gems-rome-walking-tour',
        '/en/blog/hidden-gems-florence',
        '/es/tours/hidden-gems-rome-walking-tour',
        '/it/tours/hidden-gems-rome-walking-tour',
      ].map((path) => `${process.env.LIGHTHOUSE_BASE_URL || 'http://localhost:3000'}${path}`),
      numberOfRuns: 3,
      // Keep CPU/GC traces for diagnosing cold-navigation failures.
      settings: { saveAssets: true },
      chromePath: process.env.CHROME_PATH,
      puppeteerScript: path.relative(process.cwd(), path.join(__dirname, 'scripts/lighthouse-browser-ready.cjs')),
      puppeteerLaunchOptions: {
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9, aggregationMethod: 'pessimistic' }],
        'categories:accessibility': ['error', { minScore: 0.9 }],
        'categories:best-practices': ['error', { minScore: 0.9 }],
        'categories:seo': ['error', { minScore: 0.9 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
};
