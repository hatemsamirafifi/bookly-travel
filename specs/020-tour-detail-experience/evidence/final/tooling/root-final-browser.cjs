const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const root='F:/Travel Website/bookly travel',kind=process.argv[2]||'browser';
if(!['browser','a11y'].includes(kind))throw Error('Expected browser or a11y');
const dir=path.join(root,`specs/020-tour-detail-experience/evidence/final/${kind}`);fs.mkdirSync(dir,{recursive:true});
const runtime=JSON.parse(fs.readFileSync(path.join(root,'specs/020-tour-detail-experience/evidence/final/runtime/root-runtime-accepted.json'),'utf8'));
if(!runtime.passed)throw Error('Current source Docker runtime has not passed readiness/parity');
const stages=kind==='browser'?[
 ['public-mobile',['tests/e2e/tour-detail.spec.ts','--project=chromium','--project=mobile']],
 ['authenticated',['tests/e2e/booking.spec.ts','tests/e2e/checkout.spec.ts','tests/e2e/payment.spec.ts','tests/e2e/wishlist.spec.ts','--project=chromium-authed','--project=mobile-authed']],
]:[
 ['integrated-accessibility',['tests/e2e/a11y/tour-detail-a11y.spec.ts','tests/e2e/a11y/shared-shell-a11y.spec.ts','tests/e2e/a11y/checkout-a11y.spec.ts','tests/e2e/a11y/wishlist-a11y.spec.ts','tests/e2e/a11y/auth-nav-a11y.spec.ts','--project=a11y','--project=a11y-authed']],
];
const report={startedAt:new Date().toISOString(),runtime:runtime.container,sourceParity:runtime.sourceParity,scope:'Real built Docker app/API/owned fixtures plus explicitly controlled failure/SSR boundaries; no native media adapter',stages:[]};
(async()=>{for(const[name,tests]of stages){const output=`/tmp/spec020-final-${kind}-${name}`;const args=['compose','exec','-T','nextjs','node','node_modules/@playwright/test/cli.js','test',...tests,'--workers=1','--reporter=line',`--output=${output}`];const fd=fs.openSync(path.join(dir,`root-${name}.txt`),'w');const startedAt=new Date().toISOString();const child=spawn('docker',args,{cwd:root,stdio:['ignore',fd,fd]});const exitCode=await new Promise((resolve,reject)=>{child.once('error',reject);child.once('exit',resolve)});fs.closeSync(fd);report.stages.push({name,args,startedAt,finishedAt:new Date().toISOString(),exitCode,containerArtifacts:output});fs.writeFileSync(path.join(dir,'root-progress.json'),JSON.stringify(report,null,2));if(exitCode!==0)throw Error(`${name} exited ${exitCode}`)}report.passed=true})().catch(e=>{report.error=e.message;report.passed=false;process.exitCode=1}).finally(()=>{report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(dir,'root-result.json'),JSON.stringify(report,null,2))});
