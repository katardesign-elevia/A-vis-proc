// Checks the game before it goes live: node tools/test-riddles.js src/hra.html web
// - the game script has no syntax errors
// - every riddle has its picture, a sensible divider and an answer key that accepts its own answer
// - no riddle's answer is accidentally accepted by another riddle
// - sample kid answers are judged as expected (correct / close / true but not funny / miss)
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const [src = 'src/hra.html', web = 'web'] = process.argv.slice(2);
const html = fs.readFileSync(src, 'utf8');
const problems = [];

const script = html.slice(html.indexOf('<script>') + 8, html.lastIndexOf('</script>'));
try { new vm.Script(script, { filename: src }); } catch (e) { problems.push(`script: ${e.message}`); }

const start = html.indexOf('const POOL = [');
const POOL = vm.runInNewContext(html.slice(start + 13, html.indexOf('\n  ];', start) + 4));

// Same matching as the game: lowercase, no diacritics, a leading space means "start of a word".
const norm = s => ' ' + s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
  .replace(/[^a-z0-9]+/g, ' ').trim() + ' ';
function judge(p, text) {
  const n = norm(text), has = w => n.includes(w);
  const best = p.klic.filter(g => g.some(has)).length;
  if (best === p.klic.length) return 'ok';
  if (best > 0 || (p.skoro || []).some(has)) return 'close';
  return (p.vazne || []).some(has) ? 'serious' : 'miss';
}

const ids = new Set();
for (const p of POOL) {
  const where = `riddle "${p.id}"`;
  if (ids.has(p.id)) problems.push(`${where}: the id is used twice`);
  ids.add(p.id);
  for (const k of ['src', 'w', 'h', 'split', 'otazka', 'odpoved', 'klic']) if (p[k] === undefined) problems.push(`${where}: missing ${k}`);
  if (!fs.existsSync(path.join(web, p.src))) problems.push(`${where}: picture ${path.join(web, p.src)} is missing`);
  const [a, b] = p.split || [];
  if (!(a > p.w * .3 && b > a && b < p.w * .7)) problems.push(`${where}: split ${p.split} does not look like the middle divider`);
  if (p.klic && judge(p, p.odpoved) !== 'ok') problems.push(`${where}: its own answer "${p.odpoved}" is not accepted`);
  for (const q of POOL) if (q !== p && q.klic && judge(q, p.odpoved) === 'ok') problems.push(`${where}: its answer also counts for "${q.id}"`);
}

const SAMPLES = {
  zidle: [['protože by jinak skákala', 'ok'], ['aby nemusela hopsat', 'ok'], ['na dvou by spadla', 'close'], ['aby mohla tancovat', 'close'],
    ['aby nespadla', 'serious'], ['aby se na ni dalo sedět', 'serious'], ['protože je ze dřeva', 'miss']],
  veverka: [['hledá ořechy', 'ok'], ['protože si dělá zásoby', 'ok'], ['na zimu', 'close'], ['hledá jídlo', 'close'],
    ['protože umí šplhat', 'serious'], ['bydlí na stromech', 'serious'], ['protože je zrzavá', 'miss']],
  skolnik: [['protože bloudí po škole', 'ok'], ['protože se často ztrácí', 'ok'], ['neví kudy kam', 'ok'], ['aby našel cestu ven', 'ok'],
    ['protože ztrácí klíče', 'close'], ['plete si dveře', 'close'], ['protože musí odemykat všechny dveře', 'serious'], ['protože je školník', 'miss']],
};
let samples = 0;
for (const [id, list] of Object.entries(SAMPLES)) {
  const p = POOL.find(x => x.id === id);
  if (!p) continue;
  for (const [text, want] of list) {
    samples++;
    const got = judge(p, text);
    if (got !== want) problems.push(`riddle "${id}": "${text}" is judged ${got}, expected ${want}`);
  }
}

if (problems.length) {
  console.error(problems.map(s => '✗ ' + s).join('\n'));
  console.error(`\n${problems.length} problem(s) — not deploying.`);
  process.exit(1);
}
console.log(`✓ ${POOL.length} riddles, ${samples} sample answers, game script OK`);
