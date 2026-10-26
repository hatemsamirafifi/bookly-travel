const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const root='F:/Travel Website/bookly travel',dir=path.join(root,'specs/020-tour-detail-experience/evidence/final/performance');fs.mkdirSync(dir,{recursive:true});
const runtime=JSON.parse(fs.readFileSync(path.join(root,'specs/020-tour-detail-experience/evidence/final/runtime/root-runtime-accepted.json'),'utf8'));if(!runtime.passed)throw Error('Accepted Docker source runtime required');
const report={startedAt:new Date().toISOString(),runtime:runtime.container,conditions:'Serial production audits, no competing build/test/audit. Lighthouse resets browser storage for each cold navigation; server/optimizer filesystem caches are not flushed. The unchanged about:blank CPU readiness hook runs before audits; default Lighthouse mobile throttling remains configured.',stages:[]};
async function docker(name,args,{required=true}={}){const startedAt=new Date().toISOString();let output='';const fd=fs.openSync(path.join(dir,`root-${name}.txt`),'w');const child=spawn('docker',args,{cwd:root,stdio:['ignore','pipe','pipe']});for(const stream of[child.stdout,child.stderr])stream.on('data',chunk=>{fs.writeSync(fd,chunk);output+=chunk});const exitCode=await new Promise((resolve,reject)=>{child.once('error',reject);child.once('exit',resolve)});fs.closeSync(fd);report.stages.push({name,args,startedAt,finishedAt:new Date().toISOString(),exitCode});fs.writeFileSync(path.join(dir,'root-audits-progress.json'),JSON.stringify(report,null,2));if(required&&exitCode!==0)throw Error(`${name} exited ${exitCode}`);return {exitCode,output};}
(async()=>{
 await docker('resources',['stats','--no-stream','--format','{{.Name}} CPU={{.CPUPerc}} memory={{.MemUsage}}']);
 const chrome=await docker('chrome-path',['compose','exec','-T','nextjs','node','-e',"const fs=require('node:fs');const p=require('@playwright/test').chromium.executablePath();if(!fs.existsSync(p))throw Error('Installed Chrome missing');process.stdout.write(p)"]);
 const chromePath=chrome.output.trim();if(!chromePath.startsWith('/root/.cache/ms-playwright/'))throw Error('Unexpected installed browser path');report.chromePath=chromePath;
 const prefix=['compose','exec','-T','-e','LIGHTHOUSE_BASE_URL=http://nginx','-e',`CHROME_PATH=${chromePath}`,'nextjs','node'];
 const assetListProgram="console.log(JSON.stringify(require('node:fs').readdirSync('/app').filter(p=>/\\.(trace|devtoolslog)\\.json$/.test(p))))";
 const beforeAssets=await docker('prior-trace-list',['compose','exec','-T','nextjs','node','-e',assetListProgram]);
 const priorTraceNames=JSON.parse(beforeAssets.output);
 await docker('preflight-tests',[...prefix,'--test','scripts/lighthouse-runtime-preflight.test.mjs']);
 await docker('cpu-preflight',[...prefix,'scripts/lighthouse-runtime-preflight.mjs']);
 await docker('prior-reports-copy',['cp','bookly-frontend:/app/.lighthouseci',path.join(dir,'prior-container-reports')],{required:false});
 const collect=await docker('collect',[...prefix,'node_modules/@lhci/cli/src/cli.js','collect','--config=lighthouserc.js'],{required:false});
 const assertion=await docker('assert',[...prefix,'node_modules/@lhci/cli/src/cli.js','assert','--config=lighthouserc.js'],{required:false});
 await docker('reports-copy',['cp','bookly-frontend:/app/.lighthouseci',path.join(dir,'reports')]);
 const assets=await docker('trace-list',['compose','exec','-T','nextjs','node','-e',assetListProgram]);
 report.generatedAssets=JSON.parse(assets.output).filter(name=>!priorTraceNames.includes(name));
 const traceDir=path.join(dir,'traces');fs.mkdirSync(traceDir,{recursive:true});for(const name of JSON.parse(assets.output))await docker(`copy-${name}`,['cp',`bookly-frontend:/app/${name}`,path.join(traceDir,name)]);
 const reports=fs.readdirSync(path.join(dir,'reports')).filter(p=>/^lhr-\d+\.json$/.test(p)).map(name=>{const lhr=JSON.parse(fs.readFileSync(path.join(dir,'reports',name),'utf8'));return{name,url:lhr.requestedUrl,finalUrl:lhr.finalDisplayedUrl||lhr.finalUrl,fetchTime:lhr.fetchTime,runtimeError:lhr.runtimeError,httpStatusAudit:lhr.audits['http-status-code']?.score,categories:Object.fromEntries(Object.entries(lhr.categories).map(([key,value])=>[key,value.score])),settings:lhr.configSettings}});
 report.reports=reports;const groups=Map.groupBy(reports,r=>r.url);report.summary=Array.from(groups,([url,runs])=>({url,runs:runs.length,minimumPerformance:Math.min(...runs.map(r=>r.categories.performance??0))}));
 report.passed=collect.exitCode===0&&assertion.exitCode===0&&reports.length===18&&groups.size===6&&report.summary.every(g=>g.runs===3&&g.minimumPerformance>=0.9)&&reports.every(r=>!r.runtimeError&&r.httpStatusAudit===1)&&report.generatedAssets.filter(name=>name.endsWith('.trace.json')).length>=18&&report.generatedAssets.filter(name=>name.endsWith('.devtoolslog.json')).length>=18;
 if(!report.passed)process.exitCode=1;
})().catch(e=>{report.error=e.message;report.passed=false;process.exitCode=1}).finally(()=>{report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(dir,'root-audits-result.json'),JSON.stringify(report,null,2))});
