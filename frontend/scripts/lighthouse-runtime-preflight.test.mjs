import assert from 'node:assert/strict';
import test from 'node:test';
import { cpuReady } from './lighthouse-runtime-preflight.mjs';

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
