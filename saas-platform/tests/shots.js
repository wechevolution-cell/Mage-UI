// Usage: node tests/shots.js <host:port> <outdir> [paths...]  — desktop + 390px screenshots, JS errors, overflow
const { chromium } = require('playwright');
const [host, out, ...paths] = process.argv.slice(2);
const list = paths.length ? paths : ['/', '/products/', '/product/palto-apo-malli/', '/cart/'];
(async () => {
  const b = await chromium.launch();
  for (const [w, h, tag] of [[1440, 900, 'd'], [390, 844, 'm']]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h } });
    const p = await ctx.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    p.on('response', r => { if (r.status() >= 400 && !r.url().includes('/nope')) errs.push(r.status() + ' ' + r.url()); });
    for (const path of list) {
      await p.goto('http://' + host + path, { waitUntil: 'networkidle' });
      await p.evaluate(() => { try { localStorage.setItem('cc:' + location.host, '"necessary"'); } catch (e) {} document.querySelector('[data-cookie]')?.setAttribute('hidden', ''); });
      const ov = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      if (ov > 0) errs.push(`overflow ${ov}px on ${path}`);
      const name = path.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home';
      await p.screenshot({ path: `${out}/${tag}-${name}.png`, fullPage: true });
    }
    console.log(tag, errs.length ? [...new Set(errs)].join('\n  ') : 'OK');
    await ctx.close();
  }
  await b.close();
})();
