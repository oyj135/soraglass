import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
const run=promisify(execFile),php=process.argv[2]||'php',dir=await fs.mkdtemp(path.join(os.tmpdir(),'sg-ai-daily-test-'));
let calls=0,bad=false;
const server=http.createServer((req,res)=>{
 if(req.url==='/model'){calls++;req.resume();res.setHeader('Content-Type','application/json');res.end(JSON.stringify({choices:[{message:{content:JSON.stringify({topics:bad?[{theme:'重复',items:[{i:1}]},{theme:'重复',items:[{i:3}]}]:['工具与工作流','模型应用','开放研究','无效'].map((theme,n)=>({theme,title:theme,desc:'Fixture description',items:n===3?[{i:999,url:'https://untrusted.invalid'}]:[{i:1,summary:'A'},{i:3,summary:'B'}]}))})}}]}));}
 else{res.setHeader('Content-Type','application/rss+xml');res.end(`<rss><channel><item><title>Source ${req.url}</title><link>https://example.com${req.url}</link><description>Source fact</description><pubDate>${new Date().toUTCString()}</pubDate></item></channel></rss>`);}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r)); const base=`http://127.0.0.1:${server.address().port}`;
const invoke=async mode=>{const r=await run(php,[path.join(import.meta.dirname,'test-ai-fixture.php'),mode,base,dir],{timeout:15000});assert.equal(r.stderr,'');return JSON.parse(r.stdout)};
try{
 const first=await invoke('daily-page');assert.equal(first.count,0);assert.ok(first.elapsed<.2);assert.equal(calls,1);console.log('PASS automatic theme batch starts after the homepage response');
 const second=await invoke('daily-generate');assert.equal(second.issues.length,3);assert.equal(calls,1);assert.equal(new Set(second.issues.map(i=>i.theme)).size,3);assert.ok(second.issues.every(i=>i.items.every(p=>p.url.startsWith('https://example.com/'))));console.log('PASS daily cache avoids repeat calls and accepts only trusted candidate links');
 const history=await invoke('daily-history');assert.equal(history.dates.length,3);assert.equal(new Set(history.dates).size,3);assert.equal(history.count,9);console.log('PASS history contains distinct real generation dates and groups each day’s themes');
 const before=await fs.readFile(second.file,'utf8');bad=true;const invalid=await invoke('daily-force');assert.equal(invalid.issues.length,0);assert.equal(invalid.saved.length,3);assert.equal(await fs.readFile(second.file,'utf8'),before);console.log('PASS malformed or duplicate model themes preserve the last successful edition');
}finally{await new Promise(r=>server.close(r));assert.ok(path.resolve(dir).startsWith(path.resolve(os.tmpdir())+path.sep+'sg-ai-daily-test-'));await fs.rm(dir,{recursive:true,force:true});}
