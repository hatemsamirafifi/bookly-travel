const fs = require('node:fs');
const assert = require('node:assert/strict');
const { JSDOM } = require('/app/node_modules/jsdom');
const root='/tmp/spec019-live-proof';
async function main(){
 const phase=process.argv[2];
 assert.ok(['rollback','current','live'].includes(phase));
 const origin='http://nextjs:3000';
 const buildId=fs.readFileSync('/app/.next/BUILD_ID','utf8').trim();
 assert.equal(buildId,phase==='rollback'?'RuGVVMvrRQc-BJGPcwMvv':'1h7I7N6dpRg2PtBQVfPBL');
 const ready=JSON.parse(fs.readFileSync(root+'/owned-fixture.json','utf8'));
 const adopted=JSON.parse(fs.readFileSync(root+'/owned-adoption-fixture.json','utf8'));
 const results=[];
 for(const fixture of [ready,adopted]){
  for(const locale of ['en','es','it']){
   const api=await fetch(`http://nginx/api/public/tours/${fixture.slug}?locale=${locale}`);
   assert.equal(api.status,200);const dto=(await api.json()).data;
   const response=await fetch(`${origin}/${locale}/tours/${fixture.slug}`);
   assert.equal(response.status,200);const html=await response.text();
   const dom=new JSDOM(html);const document=dom.window.document;
   const h1=document.querySelector('h1');assert.ok(h1);assert.equal(h1.textContent,dto.title);
   assert.equal(h1.getAttribute('lang'),dto.content_locale);
   const itinerary=document.querySelector('#itinerary');
   for(const day of dto.itinerary){assert.ok(itinerary?.textContent.includes(day.title));}
   const schema=JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent);
   assert.equal(schema.offers.price,'75.00');assert.equal(schema.offers.priceCurrency,'EUR');
   assert.equal(schema.aggregateRating,undefined);
   assert.equal(document.querySelector('link[rel="canonical"]').href,dto.seo.canonical_url);
   if(fixture.tour_id===49){assert.equal(dto.content_locale,'en');assert.equal(dto.itinerary_locale,'en');assert.deepEqual(dto.images,[]);assert.ok(schema.image===undefined||(Array.isArray(schema.image)&&schema.image.length===0));assert.deepEqual(dto.itinerary.map(day=>day.day),[1,2]);}
   else {assert.equal(dto.content_locale,locale);assert.equal(dto.itinerary_locale,locale);assert.deepEqual(dto.itinerary.map(day=>day.day),[7,7]);assert.equal(dto.images.length,1);}
   const facts={tour_id:fixture.tour_id,locale,http_status:response.status,title:h1.textContent,content_locale:dto.content_locale,itinerary_locale:dto.itinerary_locale,itinerary:dto.itinerary,price:dto.pricing.base_price,images:dto.images,canonical:dto.seo.canonical_url,hreflang:[...document.querySelectorAll('link[rel="alternate"][hreflang]')].map(el=>[el.hreflang,el.href])};
   if(phase==='current'){
    const old=JSON.parse(fs.readFileSync(`${root}/frontend-rollback-${fixture.tour_id}-${locale}.json`,'utf8'));
    assert.deepEqual(facts,old);
    assert.deepEqual(schema['@type'],['TouristTrip','Service']);
    assert.equal(schema.itinerary['@context'].name['@language'],dto.itinerary_locale);
   }
   fs.writeFileSync(`${root}/frontend-${phase}-${fixture.tour_id}-${locale}.json`,JSON.stringify(facts,null,2)+'\n');
   results.push({tour_id:fixture.tour_id,locale,actual_ssr_http:200,source_facts_match:true});
  }
 }
 fs.writeFileSync(`${root}/frontend-${phase}-result.json`,JSON.stringify({observed_at:new Date().toISOString(),phase,origin,build_id:buildId,results,actual_comparison:phase==='current'},null,2)+'\n');
 console.log(JSON.stringify({phase,actual_ssr_pages:results.length,assertions:'actual API -> HTML title/language/itinerary/media/canonical/hreflang/money; current also compares retained rollback facts',passed:true}));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1});
