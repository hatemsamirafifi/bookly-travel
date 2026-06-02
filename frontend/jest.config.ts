import type { Config } from 'jest';

const config: Config = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['./jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  transform: {
    '^.+\\.tsx?$': ['ts-jest', {
      tsconfig: 'tsconfig.json',
    }],
  },
  testPathIgnorePatterns: ['/node_modules/', '/tests/e2e/', '/.next/'],
  modulePathIgnorePatterns: ['<rootDir>/.next/'],
};

export default config;
