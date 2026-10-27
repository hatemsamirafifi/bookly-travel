const fs=require('node:fs'),path=require('node:path'),{spawnSync}=require('node:child_process');
const root='F:/Travel Website/bookly travel',dir=path.join(root,'specs/020-tour-detail-experience/evidence/final/release');fs.mkdirSync(dir,{recursive:true});
const report={recordedAt:new Date().toISOString(),scope:'Read-only source/CI/dependency audit. No commit, push, PR, merge, workflow dispatch or deployment.',commands:[]};
function run(name,command,args){const result=spawnSync(command,args,{cwd:root,encoding:'utf8',timeout:60000});fs.writeFileSync(path.join(dir,`root-${name}.txt`),(result.stdout||'')+(result.stderr||''));report.commands.push({name,command,args,exitCode:result.status,error:result.error?.message});return{exitCode:result.status,stdout:(result.stdout||'').trim(),stderr:(result.stderr||'').trim()}}
const branch=run('branch','git',['branch','--show-current']).stdout,head=run('head','git',['rev-parse','HEAD']).stdout;
report.branch=branch;report.head=head;const status=run('working-tree','git',['status','--porcelain']);report.hasUncommittedWork=Boolean(status.stdout);
const repo='hatemsamirafifi/bookly-travel';
run('head-check-runs','gh',['api',`repos/${repo}/commits/${head}/check-runs`,'--jq','{total_count,check_runs:[.check_runs[]|{name,status,conclusion,head_sha,html_url}]}']);
run('head-status','gh',['api',`repos/${repo}/commits/${head}/status`,'--jq','{state,total_count,statuses:[.statuses[]|{context,state,target_url}]}']);
run('feature-prs','gh',['pr','list','--repo',repo,'--state','all','--head',branch,'--json','number,state,isDraft,headRefOid,baseRefName,url']);
run('spec019-predecessor','gh',['pr','view','29','--repo',repo,'--json','number,state,mergedAt,mergeCommit,url,baseRefName']);
const main=run('remote-main','gh',['api',`repos/${repo}/branches/main`,'--jq','.commit.sha']);
if(main.exitCode===0&&/^[a-f0-9]{40}$/.test(main.stdout)){report.remoteMain=main.stdout;run('main-ancestor-of-head','git',['merge-base','--is-ancestor',main.stdout,head]);run('shell-merged-into-main','git',['merge-base','--is-ancestor','4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27',main.stdout])}
report.currentWorkingTreeCI='Pending: source is uncommitted; any commit check does not establish CI for this working tree.';
report.targetDeployment='Not executed or verified; target environment was not supplied. Spec019 target rollout and monitoring require separate confirmation.';
report.rollback='No schema rollback needed for020; retain a compatible predecessor frontend image/source. No target image digest or exercised production rollback supplied.';
report.commitPolicy='Human explicitly requested implementation/tests without commits during LiveReview quota outage. Optional SpecKit commit hooks are intentionally not invoked.';
report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(dir,'root-readiness-audit.json'),JSON.stringify(report,null,2));
