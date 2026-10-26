'use strict';
/*
 * Focused resolution test for lighthouse-engine-selector.cjs (SPEC020 retained tooling).
 *
 * Runs a NATIVE TEMPORARY test process in C:/Users/HaTeM/AppData/Local/Temp/opencode/.
 * Inside that single process it calls the REAL installed Module._resolveFilename for six
 * independent resolution cases BEFORE the selector is armed, requires the retained
 * selector (arming it), and calls the same six cases AFTER. Interception is proven only
 * by case 1; every other case must resolve identically before/after (pass-through).
 * Selector stderr lines are captured as interception evidence (the selector emits
 * 'selected=' exactly once per interception).
 *
 * No audit, no browser, no network: this is module-resolution only. The only process
 * invocation beyond the test process is the HELP-ONLY `@lhci/cli/src/cli.js collect --help`
 * from frontend/, as allowed by the task; nothing else is run or collected.
 *
 * Writes: lighthouse-engine-selector.result.json (all values, hashes, statuses) into this
 * tooling directory. Exits 1 on any failing check instead of weakening assertions.
 */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');

const TOOLING_DIR = __dirname;
const REPO_ROOT_FALLBACK = path.resolve(TOOLING_DIR, '../../../../..');
const SELECTOR_PATH = path.join(TOOLING_DIR, 'lighthouse-engine-selector.cjs');
const RESULT_PATH = path.join(TOOLING_DIR, 'lighthouse-engine-selector.result.json');
const FRONTEND_DIR = path.join(REPO_ROOT_FALLBACK, 'frontend');
const NODE_RUNNER_PATH = path.join(FRONTEND_DIR, 'node_modules', '@lhci', 'cli', 'src', 'collect', 'node-runner.js');
const RELATIVE_LHCI_CLI = path.join('node_modules', '@lhci', 'cli', 'src', 'cli.js');

function sha256(buf) {
  return crypto.createHash('sha256').update(buf).digest('hex');
}

function fail(reason) {
  process.stderr.write('[focused-tests] FAIL: ' + reason + '\n');
  process.exit(1);
}

if (!fs.existsSync(SELECTOR_PATH)) fail('selector file missing: ' + SELECTOR_PATH);
if (!fs.existsSync(NODE_RUNNER_PATH)) fail('node-runner missing: ' + NODE_RUNNER_PATH);

const tempRoot = path.join(os.tmpdir(), 'opencode');
fs.mkdirSync(tempRoot, { recursive: true });
const tempDir = fs.mkdtempSync(path.join(tempRoot, 'spec020-engine-selector-test-'));

// Portable resolution check: FROM the retained tooling path, five '..' segments must
// land on the actual repository root. Verified against real markers on disk.
const resolvedRootFromTooling = path.resolve(TOOLING_DIR, '../../../../..');
const portability = {
  toolingDir: TOOLING_DIR,
  resolvedRootPath: resolvedRootFromTooling,
  fallbackRepoRoot: REPO_ROOT_FALLBACK,
  markers: {
    'frontend/package.json': fs.existsSync(path.join(resolvedRootFromTooling, 'frontend', 'package.json')),
    'frontend/node_modules/lighthouse/package.json': fs.existsSync(path.join(resolvedRootFromTooling, 'frontend', 'node_modules', 'lighthouse', 'package.json')),
    '.git': fs.existsSync(path.join(resolvedRootFromTooling, '.git')),
  },
};
const portabilityOk =
  resolvedRootFromTooling === REPO_ROOT_FALLBACK &&
  portability.markers['frontend/package.json'] &&
  portability.markers['frontend/node_modules/lighthouse/package.json'] &&
  portability.markers['.git'];
if (!portabilityOk) fail('portable repo-root resolution failed: ' + resolvedRootFromTooling);

// ---- child runner source (executed inside the temporary process) ----
const RUNNER_SRC = `'use strict';
const Module = require('node:module');
const fs = require('node:fs');
const path = require('node:path');

const repoRoot = process.argv[2];
const mode = process.argv[3]; // 'before' | 'after'
const selectorPath = mode === 'after' ? process.argv[4] : null;

const frontendDir = path.join(repoRoot, 'frontend');
const nodeRunnerPath = path.join(frontendDir, 'node_modules', '@lhci', 'cli', 'src', 'collect', 'node-runner.js');
const unrelatedCallerPath = path.join(frontendDir, 'node_modules', '@lhci', 'cli', 'src', 'collect', 'collect.js');

function mkFixture(file) {
  return { filename: file, paths: Module._nodeModulePaths(path.dirname(file)) };
}

const allowedFixture = mkFixture(nodeRunnerPath);
const unrelatedFixture = mkFixture(unrelatedCallerPath);

function tryResolve(request, parent, isMain, options) {
  try {
    return { ok: true, value: Module._resolveFilename(request, parent, isMain, options) };
  } catch (e) {
    return { ok: false, code: e && e.code, message: e && e.message };
  }
}

function run(id, request, parent, isMain, options) {
  const r = tryResolve(request, parent, isMain, options);
  return {
    id,
    request,
    parentFilename: parent ? parent.filename : null,
    hasOptions: !!(options && Object.keys(options).length > 0),
    result: r,
  };
}

const cases = [
  run('1-allowed-caller-bare-lighthouse', 'lighthouse', allowedFixture, false, undefined),
  run('2-unrelated-caller-bare-lighthouse', 'lighthouse', unrelatedFixture, false, undefined),
  run('3-allowed-caller-subpath', 'lighthouse/core/index.js', allowedFixture, false, undefined),
  run('4-allowed-caller-with-options', 'lighthouse', allowedFixture, false, { paths: [path.join(frontendDir, 'node_modules')] }),
  run('5-missing-parent-bare-lighthouse', 'lighthouse', undefined, false, undefined),
  run('6-unrelated-builtin', 'node:path', allowedFixture, false, undefined),
];

if (mode === 'after' && selectorPath) {
  require(selectorPath);
  const before = JSON.parse(fs.readFileSync(process.argv[5], 'utf8'));
  // Arming message already went to stderr during require; emit the marker now for ordering clarity.
  process.stderr.write('[runner] armed-cases-start\\n');
  const rsp = [];
  const armThenRun = (c) => rsp.push({ before: c, after: null, id: c.id });
  cases.forEach(armThenRun);
  // real after-pass, case by case, AFTER the selector is armed:
  const armed = [
    run('1-allowed-caller-bare-lighthouse', 'lighthouse', allowedFixture, false, undefined),
    run('2-unrelated-caller-bare-lighthouse', 'lighthouse', unrelatedFixture, false, undefined),
    run('3-allowed-caller-subpath', 'lighthouse/core/index.js', allowedFixture, false, undefined),
    run('4-allowed-caller-with-options', 'lighthouse', allowedFixture, false, { paths: [path.join(frontendDir, 'node_modules')] }),
    run('5-missing-parent-bare-lighthouse', 'lighthouse', undefined, false, undefined),
    run('6-unrelated-builtin', 'node:path', allowedFixture, false, undefined),
  ];
  process.stdout.write(JSON.stringify({ repoRoot, mode, before, armedCases: armed }));
  process.stderr.write('[runner] armed-cases-end\\n');
} else {
  process.stdout.write(JSON.stringify({ repoRoot, mode, cases }));
}
`;

const runnerPath = path.join(tempDir, 'res-probe.cjs');
fs.writeFileSync(runnerPath, RUNNER_SRC, 'utf8');
const beforeFile = path.join(tempDir, 'before-cases.json');

function spawnRunner(mode, selectorPathArg) {
  const args = [runnerPath, path.normalize(REPO_ROOT_FALLBACK), mode];
  if (mode === 'after') {
    args.push(SELECTOR_PATH, beforeFile);
  }
  const res = spawnSync(process.execPath, args, {
    cwd: tempDir,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  return res;
}

// baseline: real installed resolver WITHOUT the selector
const beforeSpawn = spawnRunner('before');
if (beforeSpawn.status !== 0) fail('before-runner exited ' + beforeSpawn.status + ':\n' + beforeSpawn.stderr);
let beforeJson;
try {
  beforeJson = JSON.parse(beforeSpawn.stdout);
} catch (e) {
  fail('cannot parse before-runner stdout: ' + e.message);
}
fs.writeFileSync(beforeFile, JSON.stringify(beforeJson.cases, null, 2), 'utf8');

// armed: same six cases with the retained selector loaded
const afterSpawn = spawnRunner('after');
if (afterSpawn.status !== 0) fail('after-runner exited ' + afterSpawn.status + ':\n' + afterSpawn.stderr);
let afterJson;
try {
  afterJson = JSON.parse(afterSpawn.stdout);
} catch (e) {
  fail('cannot parse after-runner stdout: ' + e.message);
}

// ---- compare the six cases ----
const comparisons = afterJson.armedCases.map((c, i) => {
  const before = beforeJson.cases[i];
  const after = c;
  const equal = JSON.stringify(before.result) === JSON.stringify(after.result);
  return {
    id: after.id,
    request: after.request,
    expectedInterception: i === 0,
    before: before.result,
    after: after.result,
    passThroughUnchanged: i !== 0,
    identicalBeforeAfter: equal,
  };
});

// case 1 must have CHANGED and pointed to the root 12.8.2 engine core entry
const rootCoreEntryExpected = path.join(FRONTEND_DIR, 'node_modules', 'lighthouse', 'core', 'index.js');
const case1 = comparisons[0];
const case1Changed = JSON.stringify(case1.before) !== JSON.stringify(case1.after);
const case1IsRootCore = case1.after.ok && case1.after.value &&
  path.resolve(case1.after.value) === path.resolve(rootCoreEntryExpected);
const case1BeforeWasNested = case1.before.ok && case1.before.value &&
  path.resolve(case1.before.value) === path.resolve(path.join(FRONTEND_DIR, 'node_modules', '@lhci', 'cli', 'node_modules', 'lighthouse', 'core', 'index.js'));
if (!case1Changed) fail('case 1 result did not change: ' + JSON.stringify(case1));
if (!case1IsRootCore) fail('case 1 after-value is not the root core entry: ' + JSON.stringify(case1));
if (!case1BeforeWasNested) fail('case 1 before-value is not the nested @lhci copy: ' + JSON.stringify(case1));
for (let i = 1; i < comparisons.length; i++) {
  if (!comparisons[i].identicalBeforeAfter) {
    fail('pass-through case changed: ' + comparisons[i].id + '\nbefore=' + JSON.stringify(comparisons[i].before) + '\nafter=' + JSON.stringify(comparisons[i].after));
  }
}
if (!case1BeforeWasNested) fail('case1BeforeWasNested unreachable');

// interception evidence: exactly one "selected=" line in the armed stderr, and armed marker ordering
const armedStderr = afterSpawn.stderr || '';
const selectedLines = armedStderr.split('\n').filter((l) => l.indexOf('[lh-engine-select] caller=') === 0);
const armedLines = armedStderr.split('\n').filter((l) => l.indexOf('[lh-engine-select] armed:') === 0);
const failClosedLines = armedStderr.split('\n').filter((l) => l.indexOf('[lh-engine-select] FAIL-CLOSED') === 0);
if (selectedLines.length !== 1) fail('expected exactly one selected= interception line, got ' + selectedLines.length + ':\n' + armedStderr);
if (armedLines.length !== 1) fail('expected exactly one armed line, got ' + armedLines.length);
if (failClosedLines.length !== 0) fail('unexpected FAIL-CLOSED lines:\n' + failClosedLines.join('\n'));
if (armedStderr.indexOf('[runner] armed-cases-start') === -1 || armedStderr.indexOf('[runner] armed-cases-end') === -1) fail('armed runner markers missing');
if (armedStderr.indexOf('[runner] armed-cases-start') < armedStderr.indexOf('[lh-engine-select] armed:')) fail('runner marker ordering wrong: armed-cases-start must come after selector arming');
if (armedStderr.indexOf('[lh-engine-select] caller=') > armedStderr.indexOf('[runner] armed-cases-end')) fail('selected= line must occur between armed-cases-start and armed-cases-end');

// ---- pin and actual-version checks (direct file reads against installed copies) ----
const rootLhDir = path.join(FRONTEND_DIR, 'node_modules', 'lighthouse');
const nestedLhDir = path.join(FRONTEND_DIR, 'node_modules', '@lhci', 'cli', 'node_modules', 'lighthouse');
const rootLhVersion = JSON.parse(fs.readFileSync(path.join(rootLhDir, 'package.json'), 'utf8')).version;
const nestedLhVersion = JSON.parse(fs.readFileSync(path.join(nestedLhDir, 'package.json'), 'utf8')).version;
const lhciCliVersion = JSON.parse(fs.readFileSync(path.join(FRONTEND_DIR, 'node_modules', '@lhci', 'cli', 'package.json'), 'utf8')).version;
const rootCoreEntryExists = fs.existsSync(rootCoreEntryExpected);
const rootCliEntryExists = fs.existsSync(path.join(rootLhDir, 'cli', 'index.js'));
const nodeRunnerExists = fs.existsSync(NODE_RUNNER_PATH);
const nodeVersion = process.version;
if (rootLhVersion !== '12.8.2') fail('installed root lighthouse version is ' + rootLhVersion + ', expected pin 12.8.2');
if (!rootCoreEntryExists) fail('root core entry missing');
if (!rootCliEntryExists) fail('root cli entry missing');
if (!nodeRunnerExists) fail('node-runner missing');

// ---- actual selected CLI/core recorded from the focused test process ----
const selectedCoreFromStderr = (selectedLines[0].match(/selected=([^ ]+) v([0-9.]+)/) || []);
const actualSelectedCore = selectedLines.length ? selectedLines[0].split(' selected=')[1].split(' v')[0] : null;
const actualSelectedCoreVersion = selectedLines.length ? (selectedLines[0].match(/ v([0-9.]+)$/) || [])[1] : null;
if (actualSelectedCore !== null && path.resolve(actualSelectedCore) !== path.resolve(rootCoreEntryExpected)) fail('selected core path mismatch: ' + actualSelectedCore);
if (actualSelectedCoreVersion && actualSelectedCoreVersion !== '12.8.2') fail('selected core version is not pinned 12.8.2: ' + actualSelectedCoreVersion);

// ---- help-only invocation (the ONLY process invocation of the real CLI) ----
const helpCmdline = 'node --require ../specs/020-tour-detail-experience/evidence/final/tooling/lighthouse-engine-selector.cjs ' + RELATIVE_LHCI_CLI + ' collect --help';
const lhciCliAbs = path.join(FRONTEND_DIR, RELATIVE_LHCI_CLI);
if (!fs.existsSync(lhciCliAbs)) fail('LHCI cli.js missing: ' + lhciCliAbs);
const helpBefore = fs.existsSync(path.join(FRONTEND_DIR, '.lighthouseci'));
const startIso = new Date().toISOString();
const helpSpawn = spawnSync(process.execPath, ['--require', path.join('..', 'specs', '020-tour-detail-experience', 'evidence', 'final', 'tooling', 'lighthouse-engine-selector.cjs'), RELATIVE_LHCI_CLI, 'collect', '--help'], {
  cwd: FRONTEND_DIR,
  encoding: 'utf8',
  stdio: ['ignore', 'pipe', 'pipe'],
});
const endIso = new Date().toISOString();
const helpStatus = {
  cmdline: helpCmdline,
  cwd: FRONTEND_DIR,
  exitCode: helpSpawn.status,
  signal: helpSpawn.signal,
  startIso,
  endIso,
  stdoutBytes: Buffer.byteLength(helpSpawn.stdout || '', 'utf8'),
  stderrBytes: Buffer.byteLength(helpSpawn.stderr || '', 'utf8'),
  sha256Stdout: sha256(Buffer.from(helpSpawn.stdout || '', 'utf8')),
  sha256Stderr: sha256(Buffer.from(helpSpawn.stderr || '', 'utf8')),
};
if (helpSpawn.status !== 0) fail('help-only invocation exit ' + helpSpawn.status + ':\n' + (helpSpawn.stderr || ''));

const helpSelectedLines = (helpSpawn.stderr || '').split('\n').filter((l) => l.indexOf('[lh-engine-select] caller=') === 0);
// The LHCI CLI bootstrap loads collect/node-runner.js (even for --help), and that file
// requires bare 'lighthouse' at module load — exactly one interception is the CORRECT
// selector behavior for the allowed-exact caller.
if (helpSelectedLines.length !== 1) fail('help-only invocation must intercept exactly once (node-runner bootstrap), got ' + helpSelectedLines.length + ':\n' + JSON.stringify(helpSelectedLines));
const helpArmedLines = (helpSpawn.stderr || '').split('\n').filter((l) => l.indexOf('[lh-engine-select] armed:') === 0);
if (helpArmedLines.length !== 1) fail('help-only invocation must arm exactly once: ' + JSON.stringify(helpArmedLines));
const helpFailClosed = (helpSpawn.stderr || '').split('\n').filter((l) => l.indexOf('[lh-engine-select] FAIL-CLOSED') === 0);
if (helpFailClosed.length !== 0) fail('unexpected FAIL-CLOSED in help invocation: ' + helpFailClosed.join('\n'));
// LHCI 0.14 prints a UTF-8 usage header like "\ncli.js collect\n\nRun Lighthouse and save
// the results ...\n\nOptions:\n  --help ..." — verify those real markers instead of guessing a 'lhci collect' banner.
const helpStdoutText = helpSpawn.stdout || '';
const lhciHelpMarked = helpStdoutText.indexOf('cli.js collect') !== -1 && helpStdoutText.indexOf('Options:') !== -1 && helpStdoutText.indexOf('--help') !== -1;
if (!lhciHelpMarked) fail('collect --help usage output not found in stdout');

// .lighthouseci side-effect guard: --help must not create collection directories
const lhciDirAfter = fs.existsSync(path.join(FRONTEND_DIR, '.lighthouseci'));
if (!helpBefore && lhciDirAfter) fail('collect --help unexpectedly created .lighthouseci directory');

// persist exact stdout/stderr captures
fs.writeFileSync(path.join(TOOLING_DIR, 'lighthouse-engine-selector.help-stdout.txt'), helpSpawn.stdout || '', 'utf8');
fs.writeFileSync(path.join(TOOLING_DIR, 'lighthouse-engine-selector.help-stderr.txt'), helpSpawn.stderr || '', 'utf8');

const result = {
  generatedAt: new Date().toISOString(),
  selector: {
    path: SELECTOR_PATH,
    sha256: sha256(fs.readFileSync(SELECTOR_PATH)),
    bytes: fs.readFileSync(SELECTOR_PATH).length,
  },
  tempTestDir: tempDir,
  portability,
  pinnedVersion: {
    expected: '12.8.2',
    installedRootLighthouseVersion: rootLhVersion,
    nestedLhciLighthouseVersion: nestedLhVersion,
    lhciCliVersion,
    nodeVersion,
    rootCoreEntryExists,
    rootCliEntryExists,
    nodeRunnerExists,
  },
  actualSelected: {
    core: actualSelectedCore,
    coreVersion: actualSelectedCoreVersion,
    cliResolvedFromInvocation: lhciCliAbs,
    cliVersion: lhciCliVersion,
  },
  sixResolutionCases: comparisons,
  interceptionEvidence: {
    armedSingleLine: armedLines[0],
    selectedSingleLine: selectedLines[0],
    failClosedLinesCount: failClosedLines.length,
    beforeRunnerStderr: beforeSpawn.stderr || '',
    afterRunnerStderr: armedStderr,
  },
  helpInvocation: {
    ...helpStatus,
    stderr: helpSpawn.stderr || '',
    stdout: helpSpawn.stdout || '',
    lighthouseciDirectoryBefore: helpBefore,
    lighthouseciDirectoryAfter: lhciDirAfter,
  },
  noPassClaim: 'This test exercises module resolution and a help-only CLI invocation only. No audit, no collection, no performance measurement, and no CI pass is claimed.',
};
fs.writeFileSync(RESULT_PATH, JSON.stringify(result, null, 2), 'utf8');
process.stderr.write('[focused-tests] OK: 6/6 cases verified; help-only invocation exit ' + helpStatus.exitCode + '\n');
process.exit(0);
