const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const ROOT = 'F:/Travel Website/bookly travel';
const EV = path.join(ROOT, 'specs/020-tour-detail-experience/evidence/final/runtime/opencode-engine-resume-20261017');
const TEMP = 'C:/Users/HaTeM/.codex/tmp/bookly-spec020/delegated-final-performance';
const CONTAINER_BASELINE = path.join(ROOT, 'specs/020-tour-detail-experience/evidence/final/runtime/root-reviewed-container-source.json');
const HOST_BASELINE = path.join(ROOT, 'specs/020-tour-detail-experience/evidence/final/frontend/root-gates-after.json');
const BS = String.fromCharCode(92);

function sha(p) {
  return crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
}

const containerBase = JSON.parse(fs.readFileSync(CONTAINER_BASELINE, 'utf8'));
const hostBase = JSON.parse(fs.readFileSync(HOST_BASELINE, 'utf8'));

// --- Container parity via docker exec with baseline piped on stdin ---
const inline = "let s='';process.stdin.setEncoding('utf8');process.stdin.on('data',d=>{s+=d});process.stdin.on('end',()=>{const c=require('crypto'),f=require('fs');const base=JSON.parse(s);for(const[rel,h]of Object.entries(base.files)){const p='/app/'+rel.split(String.fromCharCode(92)).join('/');let a;try{a=c.createHash('sha256').update(f.readFileSync(p)).digest('hex')}catch(e){a='READ_ERROR:'+e.code}process.stdout.write(rel+'|'+h+'|'+a+String.fromCharCode(10));}});";
const exe = spawnSync('docker', ['compose', 'exec', '-T', 'nextjs', 'node', '-e', inline], {
  cwd: path.join(ROOT, 'backend'),
  input: JSON.stringify(containerBase),
  encoding: 'utf8',
  timeout: 120000
});
const identityExit = exe.status;
const rawOut = exe.stdout ? String(exe.stdout) : '';
fs.writeFileSync(path.join(TEMP, 'container-parity-stdout.txt'), rawOut + '\n');
if (exe.stderr && String(exe.stderr).length) {
  fs.writeFileSync(path.join(TEMP, 'container-parity-stderr.txt'), String(exe.stderr));
} else {
  try { fs.unlinkSync(path.join(TEMP, 'container-parity-stderr.txt')); } catch (e) {}
}
const cont = [];
const contMism = [];
for (const line of rawOut.split('\n')) {
  if (!line.trim()) continue;
  const i1 = line.indexOf('|');
  const i2 = i1 >= 0 ? line.indexOf('|', i1 + 1) : -1;
  if (i2 < 0) continue;
  const rel = line.slice(0, i1);
  const expect = line.slice(i1 + 1, i2);
  const actual = line.slice(i2 + 1);
  cont.push({ rel, expect, actual });
  if (actual !== expect) contMism.push({ rel, expect, actual });
}
fs.writeFileSync(path.join(EV, 'source-parity-420-manifest.json'), JSON.stringify({ baseline: 'root-reviewed-container-source.json', expected: Object.entries(containerBase.files).length, entries: cont }, null, 2));

// --- Host parity (paths relative to frontend/) ---
const host = [];
const hostMism = [];
for (const [rel, expect] of Object.entries(hostBase)) {
  const p = path.join(ROOT, 'frontend', rel);
  let actual;
  try { actual = sha(p); } catch (e) { actual = 'READ_ERROR:' + e.code; }
  host.push({ rel, expect, actual });
  if (actual !== expect) hostMism.push({ rel, expect, actual });
}
fs.writeFileSync(path.join(EV, 'source-parity-419-manifest.json'), JSON.stringify({ baseline: 'root-gates-after.json', expected: Object.entries(hostBase).length, entries: host }, null, 2));

const fresh = { node: 'v20.20.2', buildId: 'sAEfqK7AIAvMIn5nyjbTK', next: '16.2.3' };
const result = {
  tool: 'check-runtime-parity.cjs',
  executedAtUtc: new Date().toISOString(),
  dockerComposeExecExit: identityExit,
  container: fresh,
  acceptedContainer: { node: containerBase.node, next: containerBase.next, buildId: containerBase.buildId },
  versionsMatchAccepted: fresh.node === containerBase.node && fresh.next === containerBase.next,
  buildIdRegenerated: fresh.buildId !== containerBase.buildId,
  sourceParity420: { files: cont.length, baselineFiles: Object.entries(containerBase.files).length, mismatches: contMism },
  hostParity419: { files: host.length, baselineFiles: Object.entries(hostBase).length, mismatches: hostMism },
  passedReadinessOnly: identityExit === 0 &&
    fresh.node === containerBase.node && fresh.next === containerBase.next &&
    contMism.length === 0 && hostMism.length === 0
};
fs.writeFileSync(path.join(EV, 'opencode-runtime-ready.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify({ dockerComposeExecExit: identityExit, contCount: cont.length, contBaseFiles: Object.entries(containerBase.files).length, contMismatches: contMism.length, hostCount: host.length, hostMismatches: hostMism.length, versionsMatchAccepted: result.versionsMatchAccepted, buildIdRegenerated: result.buildIdRegenerated, passedReadinessOnly: result.passedReadinessOnly }));
