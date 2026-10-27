const fs=require('node:fs'),path=require('node:path'),{createRequire}=require('node:module');
const cwd='F:/Travel Website/bookly travel/frontend',{chromium,expect}=createRequire(path.join(cwd,'package.json'))('@playwright/test');
const dir=path.resolve(cwd,'../specs/020-tour-detail-experience/evidence/final/visual');fs.mkdirSync(dir,{recursive:true});
const runtime=JSON.parse(fs.readFileSync(path.resolve(cwd,'../specs/020-tour-detail-experience/evidence/final/runtime/root-runtime-accepted.json'),'utf8'));
if(!runtime.passed)throw Error('Accepted current-source Docker runtime required');
const origin=process.env.SPEC020_VISUAL_ORIGIN||'http://localhost:8080',slug='hidden-gems-rome-walking-tour';
const report={startedAt:new Date().toISOString(),origin,runtime:runtime.container,sourceParity:runtime.sourceParity,scope:'Actual built application/public API and Docker CDN; anonymous, no fixture mutations or media adapters. Screenshots require root visual inspection; metrics alone are not manual acceptance.',cases:[]};
let browser;
(async()=>{
 browser=await chromium.launch({headless:true});
 for(const locale of ['en','es','it'])for(const width of [390,768,1024,1440]){
  const entry={locale,width,screenshots:[]};report.cases.push(entry);
  const context=await browser.newContext({viewport:{width,height:900}});
  try{
   await context.addCookies([{name:'bookly_cookie_consent',value:'true',url:origin}]);
   const page=await context.newPage(),api=await context.request.get(`${origin}/api/public/tours/${slug}?locale=${locale}`);
   if(!api.ok())throw Error(`Detail API HTTP${api.status()}`);const {data}=await api.json();
   const reviews=await context.request.get(`${origin}/api/public/tours/${slug}/reviews?page=1&per_page=5`);
   if(!reviews.ok())throw Error(`Reviews API HTTP${reviews.status()}`);const rows=(await reviews.json()).data;if(!rows.length)throw Error('Seeded genuine review scenario missing');
   await page.goto(`${origin}/${locale}/tours/${slug}`);const main=page.locator('main#main-content');
   await expect(main.getByRole('heading',{level:1})).toHaveText(data.title,{timeout:20000});
   await expect(main.locator('#reviews').getByText(rows[0].reviewer_name,{exact:true}).first()).toBeVisible({timeout:20000});
   await page.evaluate(()=>document.fonts.ready);
   const first=main.getByTestId('tour-gallery').getByRole('button').filter({has:page.locator('img')}).first();
   await expect.poll(()=>first.locator('img').evaluate(i=>i.complete&&i.naturalWidth>0),{timeout:20000}).toBe(true);
   entry.metrics=await page.evaluate(()=>{const main=document.querySelector('main#main-content');const card=main.querySelector('[data-testid="tour-booking-card-slot"]'),bar=main.querySelector('[data-testid="mobile-booking-bar"]');const box=e=>{if(!e)return null;const r=e.getBoundingClientRect();return{top:r.top,left:r.left,width:r.width,height:r.height}};return{documentWidth:document.documentElement.scrollWidth,viewportWidth:document.documentElement.clientWidth,pageHeight:document.documentElement.scrollHeight,h1:main.querySelectorAll('h1').length,card:box(card),bar:box(bar),images:Array.from(main.querySelectorAll('img')).map(i=>({alt:i.alt,lang:i.lang,loaded:i.complete&&i.naturalWidth>0})),mainCount:document.querySelectorAll('main').length};});
   if(entry.metrics.documentWidth>entry.metrics.viewportWidth)throw Error('Whole-page horizontal overflow');
   for(const [name,fullPage]of [['top',false],['full',true]]){const file=`${locale}-${width}-${name}.png`;await page.screenshot({path:path.join(dir,file),fullPage});entry.screenshots.push(file);}
   await main.locator('#reviews').scrollIntoViewIfNeeded();const lower=`${locale}-${width}-reviews.png`;await page.screenshot({path:path.join(dir,lower)});entry.screenshots.push(lower);
   if(width===390){
    const trigger=main.locator(`[data-testid="wishlist-button"][data-tour-id="${data.id}"]`);await trigger.click();await expect(page.getByRole('dialog')).toBeVisible();let file=`${locale}-${width}-wishlist.png`;await page.screenshot({path:path.join(dir,file)});entry.screenshots.push(file);await page.keyboard.press('Escape');await expect(trigger).toBeFocused();
    await first.click();await expect(page.getByRole('dialog')).toBeVisible();file=`${locale}-${width}-gallery.png`;await page.screenshot({path:path.join(dir,file)});entry.screenshots.push(file);await page.keyboard.press('Escape');await expect(first).toBeFocused();
    const edit=main.locator('[data-testid="mobile-booking-bar"] button');await expect(edit).toBeVisible();await edit.click();await expect(page.getByRole('dialog')).toBeVisible();file=`${locale}-${width}-booking.png`;await page.screenshot({path:path.join(dir,file)});entry.screenshots.push(file);await page.keyboard.press('Escape');await expect(edit).toBeFocused();
   }
   entry.runtimeChecksPassed=true;
  }catch(e){entry.error=e.message;process.exitCode=1;}finally{await context.close();fs.writeFileSync(path.join(dir,'root-visual-matrix.json'),JSON.stringify(report,null,2));}
 }
})().catch(e=>{report.error=e.message;process.exitCode=1}).finally(async()=>{if(browser)await browser.close();report.finishedAt=new Date().toISOString();report.runtimeChecksPassed=report.cases.length===12&&report.cases.every(c=>c.runtimeChecksPassed);fs.writeFileSync(path.join(dir,'root-visual-matrix.json'),JSON.stringify(report,null,2));console.log(JSON.stringify({cases:report.cases.length,passed:report.cases.filter(c=>c.runtimeChecksPassed).length,error:report.error}));});
