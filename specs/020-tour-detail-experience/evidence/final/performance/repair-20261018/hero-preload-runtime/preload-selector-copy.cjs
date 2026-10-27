'use strict';
/*
 * SPEC020 diagnostic-only preload (temporary measurement-engine selection).
 * Purpose: make the LHCI collect's node-runner resolve bare `lighthouse` to the
 * ALREADY-INSTALLED root engine (v12.8.2) instead of the nested @lhci copy (v12.1.0).
 *
 * Scope guard (narrow interception):
 *   (a) request === 'lighthouse' AND
 *   (b) parent.filename === frontend/node_modules/@lhci/cli/src/collect/node-runner.js
 * Every other resolution delegates to the original implementation unchanged.
 *
 * This is a TEMPORARY diagnostic selection: no app code, no dependency manifests,
 * no node_modules changes. Expires with this diagnostic run. Fail-closed: any
 * violated precondition aborts the process instead of silently misdirecting loads.
 */
const Module = require('node:module');
const fs = require('node:fs');
const path = require('node:path');

const NODE_RUNNER_PATH = 'F:\\Travel Website\\bookly travel\\frontend\\node_modules\\@lhci\\cli\\src\\collect\\node-runner.js';
const ROOT_LIGHTHOUSE_DIR = 'F:\\Travel Website\\bookly travel\\frontend\\node_modules\\lighthouse';
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
