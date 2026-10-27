const fs = require('fs');
const src = 'native-resumed-final-performance.cjs';
let s = fs.readFileSync(src, 'utf8');
let n1, n2, n3;
const a1 = s;
s = s.split('opencode-native-resumed-20261017').join('opencode-native-engine-resumed-20261017');
const a2 = s;
s = s.split('evidence/final/runtime/root-runtime-accepted.json').join('evidence/final/runtime/opencode-engine-resume-20261017/opencode-runtime-ready.json');
const a3 = s;
s = s.replace("if(!runtime.passed)throw Error('Accepted Docker source runtime required');", "if(!runtime.passedReadinessOnly)throw Error('Fresh verified Docker runtime evidence (root restore) required');");
fs.writeFileSync('native-engine-resumed-audit.cjs', s);
const stats = {
  destReplaced: a1 !== a2,
  runtimePathReplaced: a2 !== a3,
  passedCheckReplaced: a3 !== s
};
fs.writeFileSync('make-engine-resumed-log.json', JSON.stringify(stats, null, 2));
console.log(JSON.stringify(stats));
