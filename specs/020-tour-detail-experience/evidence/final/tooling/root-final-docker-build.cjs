const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),{spawn}=require('node:child_process');
const cwd='F:/Travel Website/bookly travel',dir=path.join(cwd,'specs/020-tour-detail-experience/evidence/final/runtime');fs.mkdirSync(dir,{recursive:true});
const report={startedAt:new Date().toISOString(),stages:[]};
const reuseLockedDependencies=process.argv.includes('--reuse-locked-deps');
if(reuseLockedDependencies){
 const previous=JSON.parse(fs.readFileSync(path.join(dir,'before-passive-rounding-fix/root-reviewed-container-source.json'),'utf8'));
 const lockHash=crypto.createHash('sha256').update(fs.readFileSync(path.join(cwd,'frontend/package-lock.json'))).digest('hex');
 if(previous.files['package-lock.json']!==lockHash)throw Error('Dependency lock changed; a fresh dependency volume is required');
 report.dependencyVolumeReuse={enabled:true,lockHash,reason:'Same verified lock and installed Linux dependencies; startup clears the owned .next volume and rebuilds production source.'};
}
function snapshot(){const before=JSON.parse(fs.readFileSync(path.join(cwd,'specs/020-tour-detail-experience/evidence/final/frontend/root-gates-after.json'),'utf8'));for(const relative of ['jest.config.mjs'])before[relative]=crypto.createHash('sha256').update(fs.readFileSync(path.join(cwd,'frontend',relative))).digest('hex');return Object.fromEntries(Object.keys(before).map(relative=>[relative,crypto.createHash('sha256').update(fs.readFileSync(path.join(cwd,'frontend',relative))).digest('hex')]))}
const before=snapshot();
async function docker(name,args,input){let output='';const fd=fs.openSync(path.join(dir,`root-${name}.txt`),'w'),startedAt=new Date().toISOString();const child=spawn('docker',args,{cwd,stdio:[input?'pipe':'ignore','pipe','pipe']});for(const stream of [child.stdout,child.stderr])stream.on('data',chunk=>{fs.writeSync(fd,chunk);output+=chunk});if(input)child.stdin.end(input);const exitCode=await new Promise((resolve,reject)=>{child.once('error',reject);child.once('exit',resolve)});fs.closeSync(fd);report.stages.push({name,args,startedAt,finishedAt:new Date().toISOString(),exitCode});fs.writeFileSync(path.join(dir,'root-docker-build-progress.json'),JSON.stringify(report,null,2));if(exitCode!==0)throw Error(`${name} exited ${exitCode}`);return output;}
async function status(url){try{return(await fetch(url,{signal:AbortSignal.timeout(5000)})).status}catch{return 0}}
(async()=>{
 await docker('image-build',['compose','build','nextjs']);
 await docker('frontend-recreate',['compose','up','-d','--no-deps','--force-recreate',...(reuseLockedDependencies?[]:['--renew-anon-volumes']),'nextjs']);
 const deadline=Date.now()+300000;let direct=0;while(Date.now()<deadline){direct=await status('http://127.0.0.1:3001/favicon.ico');if(direct===200)break;await new Promise(r=>setTimeout(r,2000))}if(direct!==200)throw Error('New frontend failed its five-minute production readiness deadline');
 let proxy=await status('http://127.0.0.1:8080/favicon.ico');if(proxy===502){await docker('nginx-dns-reload',['compose','exec','-T','nginx','nginx','-s','reload']);proxy=await status('http://127.0.0.1:8080/favicon.ico')}if(proxy!==200)throw Error(`Proxy readiness HTTP${proxy}`);report.readiness={direct,proxy};
 const program="const fs=require('node:fs'),crypto=require('node:crypto');const paths=JSON.parse(fs.readFileSync(0,'utf8'));console.log(JSON.stringify({node:process.version,next:require('next/package.json').version,buildId:fs.readFileSync('/app/.next/BUILD_ID','utf8').trim(),files:Object.fromEntries(paths.map(p=>[p,crypto.createHash('sha256').update(fs.readFileSync('/app/'+p.replaceAll('\\\\','/'))).digest('hex')]))}));";
 const raw=await docker('container-source',['compose','exec','-T','nextjs','node','-e',program],JSON.stringify(Object.keys(before)));
 const container=JSON.parse(raw.trim());const mismatches=Object.keys(before).filter(p=>before[p]!==container.files[p]);report.container={node:container.node,next:container.next,buildId:container.buildId};report.sourceParity={files:Object.keys(before).length,mismatches};report.sourceStable=JSON.stringify(before)===JSON.stringify(snapshot());if(mismatches.length||!report.sourceStable)throw Error('Source changed or container parity failed');
 await docker('container-identity',['inspect','--format','{{.Id}} {{.Image}}','bookly-frontend']);
 report.passed=true;
})().catch(e=>{report.error=e.message;report.passed=false;process.exitCode=1}).finally(async()=>{try{await docker('frontend-build-log',['compose','logs','--no-color','--tail','180','nextjs'])}catch(e){report.logError=e.message}report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(dir,'root-docker-build-result.json'),JSON.stringify(report,null,2))});
