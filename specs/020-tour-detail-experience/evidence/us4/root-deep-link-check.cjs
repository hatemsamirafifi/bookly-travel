const fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process');
const {createRequire}=require('node:module');
const cwd=path.resolve(__dirname,'../../../../frontend');
const {chromium,expect}=createRequire(path.join(cwd,'package.json'))('@playwright/test');
let app,browser;
const report={buildId:fs.readFileSync(path.join(cwd,'.next/BUILD_ID'),'utf8').trim(),scope:'Actual Rome public API; three independent fresh contexts, initial #reviews navigation; diagnostic only, no success mocks or fixture writes',runs:[]};
(async()=>{
 const probe=net.createServer();await new Promise(r=>probe.listen(0,'127.0.0.1',r));const port=probe.address().port;await new Promise(r=>probe.close(r));
 app=spawn(process.execPath,['node_modules/next/dist/bin/next','start','-H','127.0.0.1','-p',String(port)],{cwd,env:{...process.env,API_INTERNAL_URL:'http://127.0.0.1:8080',NEXT_TELEMETRY_DISABLED:'1'},stdio:'ignore'});
 const origin=`http://127.0.0.1:${port}`;
 await expect.poll(async()=>{try{return(await fetch(origin+'/favicon.ico')).status}catch{return 0}},{timeout:30000}).not.toBe(0);
 browser=await chromium.launch({headless:true});
 for(let n=0;n<3;n++){
  const context=await browser.newContext({viewport:{width:1280,height:900}});
  try{
   const page=await context.newPage();const run={samples:[],errors:[]};report.runs.push(run);
   page.on('pageerror',e=>run.errors.push(e.message));
   await context.addCookies([{name:'bookly_cookie_consent',value:'true',url:origin}]);
   await page.goto(origin+'/en/tours/hidden-gems-rome-walking-tour#reviews');
   for(const delay of [0,100,500,1500,5000]){
    if(delay)await new Promise(r=>setTimeout(r,delay));
    run.samples.push(await page.evaluate(()=>{
     const main=document.querySelector('main#main-content'),nav=main?.querySelector('nav[aria-label="Tour sections"]'),section=main?.querySelector('#reviews'),heading=section?.querySelector('h1,h2,h3');
     const box=e=>{if(!e)return null;const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height}};
     return {time:performance.now(),scrollY:window.scrollY,hash:location.hash,active:nav?.querySelector('a[aria-current]')?.getAttribute('href'),focus:document.activeElement?.tagName,focusText:document.activeElement?.textContent?.slice(0,80),nav:box(nav),section:box(section),heading:box(heading),headings:Array.from(main?.querySelectorAll('h2,h3')??[]).map(h=>({text:h.textContent?.slice(0,50),...box(h)}))};
    }));
   }
  }finally{await context.close();}
 }
})().catch(e=>{report.error=e.message;process.exitCode=1}).finally(async()=>{
 if(browser)await browser.close();if(app&&app.exitCode===null){app.kill();await new Promise(r=>{app.once('exit',r);setTimeout(r,5000)})}
 fs.writeFileSync(path.join(__dirname,'root-deep-link-check.json'),JSON.stringify(report,null,2));
 console.log(JSON.stringify({runs:report.runs.map(r=>({last:r.samples.at(-1),errors:r.errors})),error:report.error}));
});
