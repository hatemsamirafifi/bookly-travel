'use strict';
/*
 * SPEC020 Lighthouse engine selector (retained, portable tooling).
 * Copied verbatim (only the two absolute directories and stale temporary-only
 * comments differ) from the TEMP diagnostic preload:
 *   C:/Users/HaTeM/.codex/tmp/bookly-spec020/lighthouse-repair-20261018/diag/preload.cjs
 *   SHA-256 3a7f2f5950206c186b935ba5dc867aca75c09525279cd7c941fa59f0eb605ed3
 *
 * Purpose: make the LHCI collect's node-runner resolve bare `lighthouse` to the
 * ALREADY-INSTALLED root engine (v12.8.2) instead of the nested @lhci copy (v12.1.0).
 * The shim is required because default LHCI engine selection resolves the nested
 * copy inside @lhci/cli/node_modules; the installed root engine handles this trace format.
 *
 * Scope guard (narrow interception):
 *   (a) request === 'lighthouse' (bare, no subpaths) AND
 *   (b) parent.filename === frontend/node_modules/@lhci/cli/src/collect/node-runner.js AND
 *   (c) no options object is passed to the resolution call.
 * Every other resolution delegates to the original implementation unchanged.
 *
 * Portability: the two previously-hardcoded absolute directories are derived from
 * path.resolve(__dirname, '../../../../..') (this file sits at
 * specs/020-tour-detail-experience/evidence/final/tooling/, five levels below the
 * repository root), so the selectors point at <repoRoot>/frontend/node_modules/...
 * regardless of the drive or checkout location.
 *
 * Status: retained engine-selection shim, not an audit modification. No app code,
 * no dependency manifests, no node_modules changes. Fail-closed: any violated
 * precondition aborts the process instead of silently misdirecting loads.
 */
const Module = require('node:module');
const fs = require('node:fs');
const path = require('node:path');

const REPO_ROOT = path.resolve(__dirname, '../../../../..');
const NODE_RUNNER_PATH = path.join(REPO_ROOT, 'frontend', 'node_modules', '@lhci', 'cli', 'src', 'collect', 'node-runner.js');
const ROOT_LIGHTHOUSE_DIR = path.join(REPO_ROOT, 'frontend', 'node_modules', 'lighthouse');
const EXPECTED_VERSION = '12.8.2';

function failClosed(reason) {
  process.stderr.write('[lh-engine-select] FAIL-CLOSED: ' + reason + '\n');
  process.exit(1);
}

const rootPkgPath = path.join(ROOT_LIGHTHOUSE_DIR, 'package.json');
let rootVersion = null;
try {
  rootVersion = JSON.parse(fs.readFileSync(rootPkgPath, 'utf8')).version;
} catch (e) {
  failClosed('cannot read root lighthouse package.json: ' + (e && e.message));
}
const rootCoreEntry = path.join(ROOT_LIGHTHOUSE_DIR, 'core', 'index.js');
const rootCliEntry = path.join(ROOT_LIGHTHOUSE_DIR, 'cli', 'index.js');
if (rootVersion !== EXPECTED_VERSION) failClosed('unexpected root lighthouse version ' + rootVersion + ' (expected ' + EXPECTED_VERSION + ')');
if (!fs.existsSync(rootCoreEntry)) failClosed('missing root lighthouse core entry: ' + rootCoreEntry);
if (!fs.existsSync(rootCliEntry)) failClosed('missing root lighthouse cli entry: ' + rootCliEntry);
if (!fs.existsSync(NODE_RUNNER_PATH)) failClosed('missing expected LHCI node-runner file: ' + NODE_RUNNER_PATH);

const originalResolveFilename = Module._resolveFilename;

Module._resolveFilename = function (request, parent, isMain, options) {
  if (
    request === 'lighthouse' &&
    parent &&
    typeof parent.filename === 'string' &&
    path.resolve(parent.filename) === path.resolve(NODE_RUNNER_PATH) &&
    !options
  ) {
    process.stderr.write(
      '[lh-engine-select] caller=' + parent.filename +
      ' request=' + request +
      ' selected=' + rootCoreEntry + ' v' + rootVersion + '\n'
    );
    return rootCoreEntry;
  }
  return originalResolveFilename.apply(this, arguments);
};

process.stderr.write(
  '[lh-engine-select] armed: root engine v' + rootVersion + ' at ' + ROOT_LIGHTHOUSE_DIR +
  ' (caller-gated to node-runner.js only)\n'
);
