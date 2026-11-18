import http from 'node:http';
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
const run=promisify(execFile);
const php=process.argv[2] || 'php';
const root=await fs.mkdtemp(path.join(os.tmpdir(),'soraglass-ai-test-'));
const requests=[];
let activeFolder='';
const sockets=new Set();
const server=http.createServer((req,res)=>{
 requests.push({method:req.method,url:req.url,finished:existsSync(path.join(activeFolder,'finished.json'))});
 if(req.url.includes('/hang')||req.url.includes('/stall/'))return;
 if(req.url.endsWith('/model')){
  req.resume();
  setTimeout(()=>{res.setHeader('Content-Type','application/json');res.end(JSON.stringify({choices:[{message:{content:JSON.stringify({title:'Fixture issue',desc:'Fixture',items:[{i:1,summary:'One'},{i:3,summary:'Two'}]})}}]}));},30);
 }else{
  setTimeout(()=>{res.setHeader('Content-Type','application/rss+xml');res.end(`<rss><channel><title>Fixture</title><item><title>Entry ${req.url}</title><link>https://example.com/${req.url.split('/').at(-1)}</link><description>Fixture</description><pubDate>${new Date().toUTCString()}</pubDate></item></channel></rss>`);},250);
 }
});
server.on('connection',s=>{sockets.add(s);s.on('close',()=>sockets.delete(s));});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const base=`http://127.0.0.1:${server.address().port}`;
const legacy=new Set();
async function invoke(mode,folder,endpoint=base){
 activeFolder=folder;
 await fs.mkdir(folder,{recursive:true});
 const {stdout,stderr}=await run(php,[path.join(import.meta.dirname,'test-ai-fixture.php'),mode,endpoint,folder],{timeout:15000});
 assert.equal(stderr,'');
 const value=JSON.parse(stdout);
 for(const file of value.paths||[])if(file&&path.dirname(file)===os.tmpdir()&&path.basename(file).startsWith('soraglass-ai-'))legacy.add(file);
 return value;
}
const failures=[];
async function check(name,fn){try{await fn();console.log(`PASS ${name}`);}catch(e){failures.push(name);console.log(`FAIL ${name}: ${e.message}`);}}
try{
 await check('Cold homepage returns before RSS/model work',async()=>{
  const dir=path.join(root,'cold'); const first=await invoke('page',dir);
  assert.ok(first.elapsed<.2,`page held for ${first.elapsed.toFixed(3)}s`);
  assert.equal(first.count,0);
  assert.equal(JSON.parse(await fs.readFile(path.join(dir,'finished.json'),'utf8')).session,1,'session must close before finishing response');
  assert.equal(requests.filter(x=>x.url.endsWith('/model')).length,1,'one background topic per request');
  assert.ok(requests.every(x=>x.finished),'network work must start after finishing the response');
  const second=await invoke('page',dir); assert.equal(second.count,1);
  const third=await invoke('page',dir); assert.equal(third.count,2);
  const n=requests.length; const fourth=await invoke('page',dir); assert.equal(fourth.count,3);assert.equal(requests.length,n);
  assert.equal(requests.filter(x=>x.url.includes('/feed/')).length,4,'reuse RSS across topics');
 });
 await check('GET total deadline does not retry through streams',async()=>{
  const n=requests.length;const v=await invoke('get',path.join(root,'get'));assert.ok(v.elapsed<.45,`GET took ${v.elapsed.toFixed(3)}s`);assert.equal(v.empty,true);assert.equal(requests.length-n,1);
 });
 await check('POST total deadline does not retry through streams',async()=>{
  const n=requests.length;const v=await invoke('post',path.join(root,'post'));assert.ok(v.elapsed<.45,`POST took ${v.elapsed.toFixed(3)}s`);assert.equal(v.empty,true);assert.equal(requests.length-n,1);
 });
 if(!failures.length){
  await check('Midnight rollover serves prior successful issues',async()=>{
   for(const mode of ['seed-old','seed-legacy-old']){
    const dir=path.join(root,'rollover-'+mode),endpoint=base+'/rollover-'+mode;await invoke(mode,dir,endpoint);const v=await invoke('page',dir,endpoint);assert.equal(v.count,3);assert.ok(v.elapsed<.2);
   }
  });
  await check('Concurrent homepage requests start a single generator',async()=>{
   const dir=path.join(root,'concurrent');const n=requests.filter(x=>x.url.endsWith('/model')).length;
   const values=await Promise.all([invoke('page',dir,base+'/concurrent'),invoke('page',dir,base+'/concurrent')]);assert.ok(values.every(x=>x.elapsed<.2));assert.equal(requests.filter(x=>x.url.endsWith('/model')).length-n,1);
  });
  await check('Unavailable FastCGI or cache never triggers network work',async()=>{
   const n=requests.length;for(const mode of ['page-no-fpm','no-cache']){const v=await invoke(mode,path.join(root,mode),base+'/'+mode);assert.ok(v.elapsed<.2);assert.equal(v.count,0);}assert.equal(requests.length,n);
  });
  await check('Stalled feeds stop within shared budget and cool down correct topic',async()=>{
   const dir=path.join(root,'stall');const seeded=await invoke('seed-two',dir,base+'/stall');
   const started=Date.now();const v=await invoke('page',dir,base+'/stall');assert.ok(v.elapsed<.2);assert.ok(Date.now()-started<7500);
   assert.equal(v.count,2);assert.ok((await fs.readFile(seeded.paths[2]+'.fail','utf8')).length>0);
   const n=requests.length;await invoke('page',dir,base+'/stall');assert.equal(requests.length,n,'cooldown prevents repeat work');
  });
 }
}finally{
 for(const s of sockets)s.destroy();await new Promise(r=>server.close(r));
 for(const file of legacy){await fs.rm(file,{force:true});await fs.rm(file+'.fail',{force:true});}
 // Only remove the absolute temporary directory this invocation created.
 const resolved=path.resolve(root);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep+'soraglass-ai-test-'));
 await fs.rm(resolved,{recursive:true,force:true});
}
if(failures.length)process.exitCode=1;
