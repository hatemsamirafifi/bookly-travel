#!/usr/bin/env ts-node
/**
 * i18n completeness validator (T055 / SC-009)
 *
 * Loads en.json, es.json, and it.json from messages/, computes the symmetric
 * difference of top-level keys, and exits non-zero if any locale is missing
 * a key present in en.json or contains extra keys not in en.json.
 *
 * Usage:
 *   npx ts-node scripts/check-i18n-completeness.ts
 *   # Or add to package.json scripts:
 *   # "check:i18n": "ts-node scripts/check-i18n-completeness.ts"
 */

import * as fs from 'fs';
import * as path from 'path';

const I18N_DIR = path.resolve(__dirname, '../messages');
const REFERENCE_LOCALE = 'en';
const CHECK_LOCALES = ['es', 'it'];

type JsonDict = Record<string, unknown>;

function loadJson(locale: string): JsonDict {
  const filePath = path.join(I18N_DIR, `${locale}.json`);
  if (!fs.existsSync(filePath)) {
    console.error(`❌  Missing i18n file: ${filePath}`);
    process.exit(1);
  }
  return JSON.parse(fs.readFileSync(filePath, 'utf-8')) as JsonDict;
}

function getLeafKeys(obj: JsonDict, prefix = ''): Set<string> {
  const keys = new Set<string>();
  for (const [k, v] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${k}` : k;
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
      for (const sub of getLeafKeys(v as JsonDict, fullKey)) {
        keys.add(sub);
      }
    } else {
      keys.add(fullKey);
    }
  }
  return keys;
}

const reference = loadJson(REFERENCE_LOCALE);
const referenceKeys = getLeafKeys(reference);

let exitCode = 0;

for (const locale of CHECK_LOCALES) {
  const target = loadJson(locale);
  const targetKeys = getLeafKeys(target);

  const missing = [...referenceKeys].filter((k) => !targetKeys.has(k));
  const extra = [...targetKeys].filter((k) => !referenceKeys.has(k));

  if (missing.length === 0 && extra.length === 0) {
    console.log(`✅  ${locale}.json — ${targetKeys.size} keys — PASS`);
    continue;
  }

  console.error(`\n❌  ${locale}.json — FAIL`);

  if (missing.length > 0) {
    console.error(`   Missing keys (${missing.length}):`);
    for (const k of missing) {
      console.error(`     - ${k}`);
    }
  }

  if (extra.length > 0) {
    console.error(`   Extra keys not in en.json (${extra.length}):`);
    for (const k of extra) {
      console.error(`     + ${k}`);
    }
  }

  exitCode = 1;
}

if (exitCode === 0) {
  console.log(`\n✅  All ${CHECK_LOCALES.length} locales are complete and consistent with en.json.`);
} else {
  console.error(`\n❌  i18n completeness check FAILED. Fix the issues above before proceeding.`);
}

process.exit(exitCode);
