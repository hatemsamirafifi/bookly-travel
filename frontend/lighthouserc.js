module.exports = {
  ci: {
    collect: {
      url: [
        '/en',
        '/en/search?q=rome',
        '/en/tours/hidden-gems-rome-walking-tour',
        '/en/blog/hidden-gems-florence',
      ].map((path) => `${process.env.LIGHTHOUSE_BASE_URL || 'http://localhost:3000'}${path}`),
      numberOfRuns: 3,
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
