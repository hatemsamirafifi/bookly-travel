const config = {
  testEnvironment: 'jest-environment-jsdom',
  setupFilesAfterEnv: ['./jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  transform: {
    '^.+\\.tsx?$': ['ts-jest', {
      tsconfig: 'tsconfig.json',
    }],
  },
  // Performance tooling uses Node's native runner, not jsdom/ts-jest.
  // Run it explicitly with npm run test:performance-tools.
  testPathIgnorePatterns: ['/node_modules/', '/tests/e2e/', '/.next/', '/scripts/lighthouse-audit.test.mjs$'],
  modulePathIgnorePatterns: ['<rootDir>/.next/'],
};

export default config;
