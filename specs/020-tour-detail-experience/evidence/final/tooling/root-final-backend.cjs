const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const cwd='F:/Travel Website/bookly travel',dir=path.join(cwd,'specs/020-tour-detail-experience/evidence/final/backend');
fs.mkdirSync(dir,{recursive:true});
const report={startedAt:new Date().toISOString(),database:'bookly-test-postgres/bookly_test (disposable tmpfs)',stages:[]};
async function run(name,args){const fd=fs.openSync(path.join(dir,`root-${name}.txt`),'w');const startedAt=new Date().toISOString();const child=spawn('docker',args,{cwd,stdio:['ignore',fd,fd]});const exitCode=await new Promise((resolve,reject)=>{child.once('error',reject);child.once('exit',resolve)});fs.closeSync(fd);report.stages.push({name,args,startedAt,finishedAt:new Date().toISOString(),exitCode});fs.writeFileSync(path.join(dir,'root-regression-progress.json'),JSON.stringify(report,null,2));if(exitCode!==0)throw Error(`${name} exited ${exitCode}`);}
(async()=>{
 await run('test-services',['compose','-f','docker-compose.yml','-f','docker-compose.test.yml','up','-d','test-postgres','test-meilisearch']);
 await run('postgres-ready',['compose','-f','docker-compose.yml','-f','docker-compose.test.yml','exec','-T','test-postgres','sh','-c','attempt=0; until pg_isready -U bookly_test -d bookly_test; do attempt=$((attempt+1)); [ "$attempt" -lt 30 ] || exit 1; sleep 1; done']);
 await run('pest',['compose','exec','-T','laravel','php','-d','memory_limit=512M','vendor/bin/pest','--configuration=phpunit.pgsql.xml','tests/Feature/Search/TourDetailTest.php','tests/Feature/Search/TourAvailabilityLogicTest.php','tests/Feature/Search/AvailabilityResolutionRegressionTest.php','tests/Feature/Booking/CreateBookingTest.php','tests/Feature/Payment/','tests/Feature/Auth/','tests/Feature/Reviews/']);
 report.passed=true;
})().catch(e=>{report.error=e.message;report.passed=false;process.exitCode=1}).finally(async()=>{
 try{await run('test-services-stop',['compose','-f','docker-compose.yml','-f','docker-compose.test.yml','stop','test-postgres','test-meilisearch'])}catch(e){report.cleanupError=e.message;report.passed=false;process.exitCode=1}
 report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(dir,'root-regression-result.json'),JSON.stringify(report,null,2));
});
