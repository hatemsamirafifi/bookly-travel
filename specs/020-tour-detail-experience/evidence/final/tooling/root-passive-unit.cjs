const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const root='F:/Travel Website/bookly travel',dir=path.join(root,'specs/020-tour-detail-experience/evidence/us4');
const suffix=process.argv[2];if(!['red','green'].includes(suffix))throw Error('Expected red or green');
const args=['compose','exec','-T','nextjs','node','node_modules/jest/bin/jest.js','--runInBand','--runTestsByPath','src/components/tour/__tests__/TourSectionNav.test.tsx'];
const startedAt=new Date().toISOString(),fd=fs.openSync(path.join(dir,`root-passive-rounding-${suffix}.txt`),'w');const child=spawn('docker',args,{cwd:root,stdio:['ignore',fd,fd]});child.on('exit',exitCode=>{fs.closeSync(fd);fs.writeFileSync(path.join(dir,`root-passive-rounding-${suffix}-result.json`),JSON.stringify({args,startedAt,finishedAt:new Date().toISOString(),exitCode},null,2));process.exitCode=exitCode||0});
