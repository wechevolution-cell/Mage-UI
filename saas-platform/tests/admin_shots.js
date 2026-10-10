// Usage: node tests/admin_shots.js <outdir>  — logs into Super Admin + a shop admin, screenshots key pages at 1440 and 390, reports JS errors/overflow
const { chromium } = require('playwright');
const out = process.argv[2] || '/tmp';
const sets = [
  ['127.0.0.1:8099', '/superadmin/login', 'sa@example.com', 'Secret12345', ['/superadmin/', '/superadmin/shops', '/superadmin/shops/1', '/superadmin/shops/new', '/superadmin/templates', '/superadmin/plans']],
  ['atelier.localhost:8099', '/admin/login', 'owner@example.com', 'Owner12345x', ['/admin/', '/admin/products', '/admin/products/1', '/admin/import', '/admin/homepage', '/admin/seo', '/admin/orders']],
];
(async () => {
  const b = await chromium.launch();
  for (const [host, login, email, pw, pages] of sets) {
    for (const [w, h, tag] of [[1440, 900, 'd'], [390, 844, 'm']]) {
      const ctx = await b.newContext({ viewport: { width: w, height: h } });
      const p = await ctx.newPage();
      const errs = [];
      p.on('pageerror', e => errs.push(e.message));
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      await p.goto('http://' + host + login);
      await p.fill('input[name=email]', email); await p.fill('input[name=password]', pw);
      await Promise.all([p.waitForNavigation(), p.click('button[type=submit], form button')]);
      for (const path of pages) {
        await p.goto('http://' + host + path, { waitUntil: 'networkidle' });
        const ov = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
        if (ov > 0) errs.push(`overflow ${ov}px on ${path}`);
        await p.screenshot({ path: `${out}/${host.split('.')[0].split(':')[0]}-${tag}-${path.replace(/[^a-z0-9]+/gi, '_')}.png`, fullPage: false });
      }
      console.log(host, tag, errs.length ? [...new Set(errs)].join('\n  ') : 'OK');
      await ctx.close();
    }
  }
  await b.close();
})();
