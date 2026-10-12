const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {chromium,expect}=require('/app/node_modules/@playwright/test');
const output='/tmp/spec019-manual-progress-final-20261011';
const adopted='spec019-legacy-rehearsal-6328890a73';
async function main(){
 fs.mkdirSync(output,{recursive:true});
 const browser=await chromium.launch({headless:true,args:['--disable-gpu','--no-sandbox','--disable-setuid-sandbox']});
 const observations=[];
 try{
  for(const locale of ['en','es','it']){
   const copy=JSON.parse(fs.readFileSync(`/app/messages/${locale}.json`,'utf8')).partner.tours;
   for(const width of [390,768,1024,1440]){
    const context=await browser.newContext({baseURL:'http://nginx',storageState:'/app/tests/.auth/partner.json',viewport:{width,height:900},reducedMotion:'reduce'});
    const page=await context.newPage();
    let status=422;let release;let entered;
    await page.route('**/api/partner/tours',async route=>{
     if(route.request().method()!=='POST'){await route.continue();return;}
     entered?.();await new Promise(resolve=>{release=resolve});
     await route.fulfill({status,contentType:'application/json',body:JSON.stringify(status===201?{data:{id:990019}}:{message:'Controlled manual response',errors:status===422?{'translations.en.title':['The English title is required.']}:undefined})});
    });
    await page.goto(`/${locale}/partner/tours/create`);
    await expect(page.getByRole('heading',{level:1})).toHaveText(copy.createTour);
    const snap=async(state)=>{await page.screenshot({path:path.join(output,`${locale}-${width}-${state}.png`)});};
    await snap('empty');
    await page.getByRole('button',{name:copy.wizard.nextStep,exact:true}).click();
    const summary=page.getByRole('alert').first();await expect(summary).toBeVisible();await expect(summary).toBeFocused();
    assert.equal(await summary.getAttribute('aria-live'),'assertive');await snap('validation-error');
    const reduced=await page.evaluate(()=>{
     const row=document.querySelector('h1').nextElementSibling.firstElementChild;
     const boundary=row.getBoundingClientRect();
     const circles=[...row.querySelectorAll('div.rounded-full')].map(node=>{const r=node.getBoundingClientRect();return r.left>=boundary.left-1&&r.right<=boundary.right+1});
     return {scroll:getComputedStyle(document.documentElement).scrollBehavior,matched:matchMedia('(prefers-reduced-motion: reduce)').matches,overflow:document.documentElement.scrollWidth>innerWidth,animation:getComputedStyle(document.body).animationDuration,transition:getComputedStyle(document.querySelector('button')).transitionDuration,progress_circles_visible:circles};
    });
    assert.equal(reduced.matched,true);assert.equal(reduced.scroll,'auto');assert.equal(reduced.overflow,false);
    assert.equal(reduced.progress_circles_visible.length,5);assert.ok(reduced.progress_circles_visible.every(Boolean));
    assert.ok(reduced.animation.split(',').every(value=>parseFloat(value)<=0.001));assert.ok(reduced.transition.split(',').every(value=>parseFloat(value)<=0.001));
    for(const result of [422,403,201]){
     status=result;
     const pending=new Promise(resolve=>{entered=resolve});
     const save=page.getByRole('button',{name:copy.wizard.saveDraft,exact:true});
     await save.click();await pending;await expect(page.getByRole('button',{name:copy.wizard.saving,exact:true})).toBeDisabled();
     if(result===422){await snap('loading-disabled');}
     release();
     if(result===201){await expect(page.getByRole('status')).toHaveText(copy.wizard.saved);await snap('controlled-success');}
     else {await expect(page.getByRole('alert').first()).toBeVisible();await expect(save).toBeEnabled();await snap(result===422?'controlled-server-error':'controlled-denied-write');}
    }
    await page.goto(`/${locale}/tours/${adopted}`);
    await expect(page.getByRole('heading',{level:1})).toHaveAttribute('lang','en');
    await expect(page.locator('#itinerary')).toContainText('Meet the local guide');
    assert.equal(await page.locator('main [class*="aspect-[16/10]"]').first().locator('img').count(),0);
    await snap('actual-adopted-empty-media');
    observations.push({locale,width,actual_partner_page:true,validation_announcement_and_focus:true,reduced_motion:reduced,controlled_write_states:[422,403,201],actual_adopted_ssr:true,actual_database_write:false});
    await context.close();
   }
  }
 }finally{await browser.close();}
 fs.writeFileSync(path.join(output,'manifest.json'),JSON.stringify({observed_at:new Date().toISOString(),production:true,base_url:'http://nginx',observations,scope:'Read-only real SSR plus explicitly controlled browser POST responses; no API/database write, no live provider'},null,2)+'\n');
 console.log(JSON.stringify({passed:true,cases:observations.length,screenshots:fs.readdirSync(output).filter(n=>n.endsWith('.png')).length}));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1});
