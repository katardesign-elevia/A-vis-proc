// Screenshots in headless Chrome for checking layouts: node tools/screenshots.mjs steps.json <out-dir>
// steps.json = [{w,h,url?,js?,wait?,out?,log?}, …]; needs Google Chrome installed.
import { spawn } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
const steps = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const dir = process.argv[3];
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--disable-gpu', '--hide-scrollbars', '--remote-debugging-port=9333', `--user-data-dir=${dir}/cdp-prof`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver;
for (let k = 0; k < 50; k++) { try { ver = await (await fetch('http://127.0.0.1:9333/json/list')).json(); break; } catch (e) { await sleep(200); } }
const page = ver.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map();
ws.onmessage = m => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Page.enable'); await send('Runtime.enable');
for (const s of steps) {
  if (s.w) await send('Emulation.setDeviceMetricsOverride', { width: s.w, height: s.h, deviceScaleFactor: 1, mobile: s.w < 768 });
  if (s.url) { await send('Page.navigate', { url: s.url }); await sleep(s.loadWait || 1800); }
  if (s.js) { const r = await send('Runtime.evaluate', { expression: s.js, awaitPromise: true, returnByValue: true }); if (s.log) console.log(JSON.stringify(r.result?.result?.value ?? r.result?.exceptionDetails?.exception?.description)); }
  if (s.wait) await sleep(s.wait);
  if (s.out) { const r = await send('Page.captureScreenshot', { format: 'png' }); writeFileSync(`${dir}/${s.out}`, Buffer.from(r.result.data, 'base64')); console.log('shot', s.out); }
}
ws.close(); chrome.kill();
