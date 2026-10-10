"""End-to-end acceptance tests (stdlib only).

Usage:  php -S 127.0.0.1:8099 index.php   (installed platform, platform host 127.0.0.1)
        python3 tests/acceptance.py [--big]      --big also runs the 10,000-product XML import

Creates throwaway shops qa1/qa2 (deleted again at the end) and exercises: quota, import, toggle limits,
tenant isolation, impersonation, template switch, expiry → lock → renew, warnings, downgrade,
staff permissions, CSRF, cart/checkout, slug 301, 404 monitor, locked-shop exposure.
"""
import os, re, sqlite3, sys, time, json
sys.path.insert(0, os.path.dirname(__file__))
from client import Client

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, 'data')
SA = ('sa@example.com', 'Secret12345')
PW = 'QaOwner12345'
results = []

def check(name, ok, info=''):
    results.append((name, bool(ok), info))
    print(('PASS ' if ok else 'FAIL ') + name + (f'  [{info}]' if info and not ok else ''))

def pdb(): return sqlite3.connect(os.path.join(DATA, 'platform.sqlite'))
def sdb(sid): return sqlite3.connect(os.path.join(DATA, 'shops', f'shop_{sid}.sqlite'))
def q1(con, sql, args=()):
    r = con.execute(sql, args).fetchone(); return r[0] if r else None

def sa_login():
    c = Client(host='127.0.0.1')
    c.get('/superadmin/login')
    c.post('/superadmin/login', {'email': SA[0], 'password': SA[1]})
    return c

def shop_id(domain): return q1(pdb(), 'SELECT shop_id FROM shop_domains WHERE domain = ?', (domain,))

def cleanup(sa):
    for d in ('qa1.localhost', 'qa2.localhost'):
        sid = shop_id(d)
        if sid:
            sa.get(f'/superadmin/shops/{sid}')
            sa.post(f'/superadmin/shops/{sid}/delete', {'confirm_text': d})

def create_shop(sa, domain, plan, template):
    sa.get('/superadmin/shops/new')
    sa.post('/superadmin/shops/new', {'name': 'QA ' + domain, 'domain': domain, 'plan_id': plan, 'duration': 1, 'template_key': template,
        'status': 'active', 'admin_name': 'QA Owner', 'admin_email': 'owner@' + domain, 'admin_password': PW, 'demo_content': ''})
    return shop_id(domain)

def owner(domain, email=None, pw=PW):
    c = Client(host=domain)
    c.get('/admin/login')
    c.post('/admin/login', {'email': email or 'owner@' + domain, 'password': pw})
    return c

def run_import(c, content, filename, ctype, activate='file', budget=600):
    c.get('/admin/import')
    code, h, text = c.req('POST', '/admin/import/upload', {'_csrf': c.csrf}, files={'file': (filename, content, ctype)})
    m = re.search(r'/admin/import/(\d+)', c.last[2] if isinstance(c.last[2], str) else '') or re.search(r'action="/admin/import/(\d+)/confirm"', text)
    iid = int(m.group(1)) if m else None
    if not iid: return None, {}
    fields = ['sku', 'name', 'price', 'stock', 'category', 'active', 'description']
    c.post(f'/admin/import/{iid}/confirm', {**{f'map[{f}]': f for f in fields}, 'activate': activate, 'update_existing': '1'})
    t0, prog = time.time(), {}
    while time.time() - t0 < budget:
        code, js = c.json(f'/admin/import/{iid}/process')
        prog = js.get('progress', {})
        if prog.get('status') in ('done', 'completed', 'failed', 'cancelled'): break
    return iid, prog

def main(big=False):
    sa = sa_login()
    code, _, text = sa.get('/superadmin/')
    check('superadmin login', code == 200 and 'logout' in text.lower())
    cleanup(sa)

    s1 = create_shop(sa, 'qa1.localhost', 1, '02-agora')   # Starter: 20 active
    s2 = create_shop(sa, 'qa2.localhost', 2, '09-ergo')    # Business: 50 active, staff
    check('provision shops via wizard', s1 and s2, f'{s1} {s2}')
    if not (s1 and s2): return

    # --- Quota: import 100 active rows into a 20-limit shop ---------------------------------------
    o1 = owner('qa1.localhost')
    code, _, text = o1.get('/admin/')
    check('owner login', code == 200 and 'Πίνακας' in text)
    rows = ['sku,name,price,stock,category,active,description'] + [f'QA-{i:03d},Προϊόν δοκιμής {i},{10 + i}.50,5,Δοκιμές > Υπο,1,<p>Περιγραφή {i}</p>' for i in range(1, 101)]
    iid, prog = run_import(o1, ('\n'.join(rows)).encode(), 'qa.csv', 'text/csv')
    db1 = sdb(s1)
    total = q1(db1, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')
    active = q1(db1, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL')
    check('import 100 rows stored', total == 100, f'total={total} prog={prog}')
    check('quota: only 20 active after import', active == 20, f'active={active}')
    inactive_id = q1(db1, 'SELECT id FROM products WHERE is_active = 0 LIMIT 1')
    code, js = o1.json(f'/admin/products/{inactive_id}/toggle', {'active': '1'})
    check('quota: 21st activation blocked', js.get('ok') is False, str(js)[:200])
    check('quota: still 20 active', q1(sdb(s1), 'SELECT COUNT(*) FROM products WHERE is_active = 1') == 20)
    # Direct concurrent-safe path through bulk action also blocked
    o1.post('/admin/products/bulk', {'action': 'activate', 'ids[]': [inactive_id]})
    check('quota: bulk activation blocked', q1(sdb(s1), 'SELECT COUNT(*) FROM products WHERE is_active = 1') == 20)
    # storefront never shows more than limit
    sf = Client(host='qa1.localhost')
    code, _, text = sf.get('/products/?per=96')
    shown = len(set(re.findall(r'data-pid="(\d+)"', text)))
    check('storefront shows ≤ 20 products', code == 200 and 0 < shown <= 20, f'shown={shown}')

    # --- CSRF ---------------------------------------------------------------------------------------
    code, _, _ = o1.req('POST', f'/admin/products/{inactive_id}/toggle', {'active': '0'}, follow=False)
    check('CSRF: POST without token rejected', code in (400, 403, 419), f'code={code}')

    # --- Tenant isolation ---------------------------------------------------------------------------
    stolen = Client(host='qa2.localhost'); stolen.jar = o1.jar
    stolen.opener = o1.opener
    stolen.host = 'qa2.localhost'
    code, h, text = stolen.get('/admin/', follow=False)
    check('tenant isolation: qa1 session rejected on qa2 admin', code in (302, 403) and 'QA qa1' not in text, f'code={code} loc={h.get("Location")}')
    code, _, _ = stolen.get(f'/admin/products/{inactive_id}', follow=False)
    check('tenant isolation: qa1 product id not reachable via qa2', code in (302, 403, 404), f'code={code}')
    code, _, text = o1.get('/admin/', follow=True)
    check('tenant isolation: cross-shop session use terminates the session', 'Πίνακας ελέγχου' not in text)
    o1 = owner('qa1.localhost')
    o2 = owner('qa2.localhost')
    code, _, text = o2.get(f'/admin/products/{inactive_id}', follow=False)
    check('tenant isolation: qa2 owner gets 404 for qa1 product', code == 404 and 'Προϊόν δοκιμής' not in text, f'code={code}')
    # impersonation token for qa1 must not work on qa2
    sa.get(f'/superadmin/shops/{s1}')
    code, h, _ = sa.post(f'/superadmin/shops/{s1}/impersonate', follow=False)
    loc = h.get('Location', '')
    tok = re.search(r'token=([^&]+)', loc)
    if tok:
        x = Client(host='qa2.localhost')
        code, _, text = x.get('/admin/impersonate?token=' + tok.group(1))
        check('impersonation token bound to its shop (qa2 rejects)', 'Πίνακας ελέγχου' not in text or 'login' in text.lower())
        y = Client(host='qa1.localhost')
        code, _, text = y.get('/admin/impersonate?token=' + tok.group(1))
        check('impersonation works on own shop', code == 200 and 'Πίνακας' in text)
        w = Client(host='qa1.localhost')
        code, _, text = w.get('/admin/impersonate?token=' + tok.group(1))
        check('impersonation token single-use', 'Πίνακας ελέγχου' not in text)
    else:
        check('impersonation redirect', False, loc)

    # --- Template switch keeps data ------------------------------------------------------------------
    before = q1(sdb(s1), 'SELECT COUNT(*) FROM products')
    sa.get(f'/superadmin/shops/{s1}')
    sa.post(f'/superadmin/shops/{s1}/template', {'template_key': '05-oikos'})
    code, _, text = Client(host='qa1.localhost').get('/')
    check('template switch applied', '/templates/05-oikos/' in text, 'theme css not found')
    check('template switch keeps products', q1(sdb(s1), 'SELECT COUNT(*) FROM products') == before)
    sa.post(f'/superadmin/shops/{s1}/template', {'template_key': '02-agora'})

    # --- Cart & checkout -------------------------------------------------------------------------------
    pid, slug = sdb(s1).execute('SELECT id, slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1').fetchone()
    sf = Client(host='qa1.localhost')
    sf.get(f'/product/{slug}/')
    code, js = sf.json('/cart/add/', {'product_id': pid, 'qty': 2})
    check('cart add (ajax)', code == 200 and js.get('ok'), str(js)[:200])
    code, _, text = sf.get('/checkout/')
    check('checkout page', code == 200 and 'name="first_name"' in text, f'code={code}')
    zone = re.search(r'name="shipping_zone" value="([^"]+)"', text)
    pay = re.search(r'name="payment_method" value="([^"]+)"', text)
    form = {'first_name': 'Δοκιμή', 'last_name': 'Πελάτης', 'email': 'buyer@example.com', 'phone': '2101234567', 'address': 'Οδός 1', 'city': 'Αθήνα',
            'postcode': '10558', 'shipping_zone': zone.group(1) if zone else 'pickup', 'payment_method': pay.group(1) if pay else 'cod', 'accept_terms': '1'}
    code, h, text = sf.post('/checkout/', form)
    onum = re.search(r'/order/([A-Z0-9\-]+)/', text) or re.search(r'([A-Z]{2,}-\d[\w-]*)', text)
    orders = q1(sdb(s1), 'SELECT COUNT(*) FROM orders')
    check('checkout creates order', orders == 1, f'orders={orders} code={code} {re.findall(r"class=\"(?:alert|field-error|flash)[^\"]*\"[^>]*>([^<]+)", text)[:3]}')
    code, _, text = o1.get('/admin/orders')
    check('order visible in admin', code == 200 and 'Δοκιμή' in text)
    # double submit protection: posting again with empty cart must not create a 2nd order
    sf.post('/checkout/', form)
    check('no duplicate order on resubmit', q1(sdb(s1), 'SELECT COUNT(*) FROM orders') == 1)

    # --- Slug change → 301, 404 monitor --------------------------------------------------------------
    p = sdb(s1).execute('SELECT id, slug FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 1').fetchone()
    code, _, text = o1.get(f'/admin/products/{p[0]}')
    form = dict(re.findall(r'<input[^>]*name="([a-z_]+)"[^>]*value="([^"]*)"', text))
    form.update({'id': p[0], 'name': 'Μετονομασμένο προϊόν', 'slug': 'metonomasmeno-proion', 'price': '15', 'is_active': '1', 'create_redirect': '1'})
    code, _, text = o1.post('/admin/products/save', form)
    fl = re.findall(r'class="flash[^"]*"[^>]*>(.*?)</', text, re.S)
    code, h, _ = Client(host='qa1.localhost').get(f'/product/{p[1]}/', follow=False)
    check('slug change → 301 to new URL', code == 301 and 'metonomasmeno-proion' in h.get('Location', ''), f'code={code} loc={h.get("Location")} slug={q1(sdb(s1), "SELECT slug FROM products WHERE id = ?", (p[0],))} old={p[1]}')
    Client(host='qa1.localhost').get('/den-yparxei-auti-i-selida/')
    check('404 monitor logs missing URL', q1(sdb(s1), "SELECT COUNT(*) FROM not_found_log WHERE path LIKE '%den-yparxei%'") == 1)

    # --- Staff permissions (Business) ---------------------------------------------------------------
    o2.get('/admin/users/new')
    o2.post('/admin/users/save', {'name': 'Editor', 'email': 'editor@qa2.localhost', 'role': 'editor', 'status': 'active', 'password': PW})
    ed = owner('qa2.localhost', 'editor@qa2.localhost')
    c_pages = ed.get('/admin/pages', follow=False)[0]
    c_prod = ed.get('/admin/products', follow=False)[0]
    c_users = ed.get('/admin/users', follow=False)[0]
    check('staff: editor can open pages', c_pages == 200, c_pages)
    check('staff: editor blocked from products', c_prod == 403, c_prod)
    check('staff: editor blocked from users', c_users == 403, c_users)
    ed.post('/admin/users/save', {'name': 'Evil', 'email': 'evil@qa2.localhost', 'role': 'owner', 'status': 'active', 'password': PW})
    check('staff: editor cannot create owner', q1(sdb(s2), "SELECT COUNT(*) FROM users WHERE email = 'evil@qa2.localhost'") == 0)
    # Starter has no staff feature
    o1.post('/admin/users/save', {'name': 'X', 'email': 'x@qa1.localhost', 'role': 'editor', 'status': 'active', 'password': PW})
    check('staff: Starter plan cannot add staff', q1(sdb(s1), "SELECT COUNT(*) FROM users WHERE email = 'x@qa1.localhost'") == 0)

    # --- Downgrade never deletes ------------------------------------------------------------------
    rows = ['sku,name,price,stock,category,active'] + [f'B-{i:03d},Είδος {i},9.90,3,Γενικά,1' for i in range(1, 61)]
    run_import(o2, ('\n'.join(rows)).encode(), 'b.csv', 'text/csv')
    a_before = q1(sdb(s2), 'SELECT COUNT(*) FROM products WHERE is_active = 1')
    t_before = q1(sdb(s2), 'SELECT COUNT(*) FROM products')
    check('Business: 50 active of 60', a_before == 50, a_before)
    sa.get(f'/superadmin/shops/{s2}')
    sa.post(f'/superadmin/shops/{s2}/plan', {'plan_id': 1, 'downgrade_mode': 'shop_choose'})
    check('downgrade keeps all products', q1(sdb(s2), 'SELECT COUNT(*) FROM products') == t_before)
    code, _, text = Client(host='qa2.localhost').get('/products/?per=96')
    shown = len(set(re.findall(r'data-pid="(\d+)"', text)))
    check('downgrade: storefront capped at 20', shown <= 20 and shown > 0, shown)
    code, _, text = o2.get('/admin/products')
    check('downgrade: admin warned over limit', 'πάνω από το όριο' in text or 'over' in text.lower() or 'υπέρβαση' in text.lower())
    sa.post(f'/superadmin/shops/{s2}/plan', {'plan_id': 2, 'downgrade_mode': 'shop_choose'})

    # --- Expiry warnings, expiry, lock, renew ---------------------------------------------------------
    con = pdb()
    for days, label in ((10, '10'), (3, '3'), (1, '1')):
        con.execute("UPDATE subscriptions SET end_date = date('now','localtime', ?) WHERE shop_id = ? AND status = 'active'", (f'+{days} days', s1)); con.commit()
        Client(host='qa1.localhost').get('/')
        n = q1(pdb(), 'SELECT COUNT(*) FROM notifications WHERE shop_id = ? AND dedupe_key LIKE ?', (s1, f'warn:%:{label}'))
        check(f'warning at {days} days', n == 1, n)
    check('status expiring', q1(pdb(), 'SELECT status FROM shops WHERE id = ?', (s1,)) == 'expiring')
    owner_mails = q1(pdb(), "SELECT COUNT(*) FROM mail_log WHERE shop_id = ? AND to_email = 'owner@qa1.localhost' AND subject LIKE '%λήγει%'", (s1,))
    check('owner emailed once per warning (3 emails)', owner_mails == 3, owner_mails)
    con.execute("UPDATE subscriptions SET end_date = date('now','localtime','-1 day') WHERE shop_id = ? AND status = 'active'", (s1,)); con.commit()
    products_before = q1(sdb(s1), 'SELECT COUNT(*) FROM products')
    code, _, text = Client(host='qa1.localhost').get('/')
    st = q1(pdb(), 'SELECT status FROM shops WHERE id = ?', (s1,))
    check('expired → locked (grace 0)', st == 'locked', st)
    check('locked: storefront shows lock page (503)', code == 503 and 'Προϊόν δοκιμής' not in text, code)
    leaks = []
    for path in ('/products/', f'/product/{slug}/', '/sitemap.xml', '/llms.txt', '/feed/blog.xml', '/api/search/?q=Προϊόν', '/api/products/', '/search/?q=Προϊόν', '/cart/'):
        c, _, t = Client(host='qa1.localhost').get(path, follow=False)
        if c == 200 or 'Προϊόν δοκιμής' in t or 'QA-0' in t: leaks.append(f'{path}:{c}')
    check('locked: zero data exposure on public routes', not leaks, ', '.join(leaks))
    c, h, t = Client(host='qa1.localhost').get('/robots.txt')
    check('locked: robots.txt is 503 + Retry-After (temporary, index kept)', c == 503 and h.get('Retry-After'), c)
    sa.get(f'/superadmin/shops/{s1}')
    sa.post(f'/superadmin/shops/{s1}/renew', {'months': 1, 'mode': 'reset', 'paid': '1'})
    code, _, text = Client(host='qa1.localhost').get('/')
    check('renew unlocks shop', code == 200 and q1(pdb(), 'SELECT status FROM shops WHERE id = ?', (s1,)) == 'active')
    check('renew keeps all data', q1(sdb(s1), 'SELECT COUNT(*) FROM products') == products_before and q1(sdb(s1), 'SELECT COUNT(*) FROM orders') == 1)

    # --- Super Admin manual lock/suspend & admin login switch ------------------------------------------
    sa.post(f'/superadmin/shops/{s1}/status', {'status': 'suspended'})
    code, _, _ = Client(host='qa1.localhost').get('/')
    check('suspended: storefront closed', code == 503, code)
    code, _, text = owner('qa1.localhost').get('/admin/', follow=True)
    check('suspended: shop admin blocked', 'Πίνακας ελέγχου' not in text)
    sa.post(f'/superadmin/shops/{s1}/status', {'status': 'active'})
    check('reactivated', Client(host='qa1.localhost').get('/')[0] == 200)

    # --- 10,000 product XML import --------------------------------------------------------------------
    if big:
        items = ''.join(f'<product><sku>X{i:05d}</sku><name>Προϊόν XML {i}</name><price>{(i % 90) + 5}.90</price><stock>{i % 7}</stock>'
                        f'<category>Κατηγορία {i % 25} &gt; Υπο {i % 4}</category><active>{1 if i <= 30 else 0}</active><description>&lt;p&gt;Περιγραφή {i}&lt;/p&gt;</description></product>' for i in range(1, 10001))
        xml = f'<?xml version="1.0" encoding="UTF-8"?><products>{items}</products>'.encode()
        t0 = time.time()
        iid, prog = run_import(o2, xml, 'big.xml', 'application/xml', budget=1800)
        dt = time.time() - t0
        cnt = q1(sdb(s2), "SELECT COUNT(*) FROM products WHERE sku LIKE 'X%'")
        act = q1(sdb(s2), 'SELECT COUNT(*) FROM products WHERE is_active = 1')
        stored = q1(sdb(s2), 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')
        check('10k XML import processes every row', prog.get('processed') == 10000, f'prog={prog}')
        check('stored-product cap: exactly 10,000 stored (60 existing + 9,940 new, rest skipped)', stored == 10000 and cnt == 9940 and prog.get('skipped') == 60, f'stored={stored} new={cnt}')
        check('10k import respects active quota', act <= 50, act)
        print(f'     10k import took {dt:.1f}s in batches of 200 (each request bounded at ~6s)')
        code, _, text = o2.get('/admin/products?q=XML+5000')
        check('admin search across 10k', code == 200 and 'Προϊόν XML 5000' in text)
        t1 = time.time(); code, _, _ = o2.get('/admin/products?page=40'); dt2 = time.time() - t1
        check('admin product list page fast with 10k', code == 200 and dt2 < 2.0, f'{dt2:.2f}s')

    # --- One domain: storefront at /, shop admin at /admin, Super Admin at /superadmin ---------------
    import subprocess
    php_cfg = lambda code: subprocess.run(['php', '-r', "define('ROOT','" + ROOT + "'); $_SERVER['HTTP_HOST']='127.0.0.1'; require ROOT.'/app/bootstrap.php'; " + code], capture_output=True, text=True).stdout
    orig_hosts = php_cfg("echo json_encode(Config::get('platform_hosts'));")
    php_cfg("$c = require Config::path(); $c['platform_hosts'][] = 'one.localhost'; Config::write($c);")
    time.sleep(3)  # let the web server's OPcache revalidate config.php (revalidate_freq=2)
    try:
        code, h, _ = Client(host='one.localhost').get('/', follow=False)
        check('one-domain: platform host without shop → Super Admin', code == 302 and '/superadmin/' in h.get('Location', ''), code)
        sa1 = Client(host='one.localhost'); sa1.get('/superadmin/login'); sa1.post('/superadmin/login', {'email': SA[0], 'password': SA[1]})
        s3 = create_shop(sa1, 'one.localhost', 1, '03-kipos')
        check('one-domain: shop can use the platform domain', bool(s3))
        v = Client(host='one.localhost')
        code, _, t = v.get('/')
        check('one-domain: / is the storefront', code == 200 and 'QA one.localhost' in t, code)
        check('one-domain: /products/ storefront', v.get('/products/')[0] == 200)
        code, _, t = v.get('/admin/login')
        check('one-domain: /admin is the shop admin', code == 200 and 'Σύνδεση διαχείρισης' in t)
        code, _, t = v.get('/superadmin/login')
        check('one-domain: /superadmin is the Super Admin', code == 200 and 'Σύνδεση Super Admin' in t)
        code, _, t = sa1.get('/superadmin/')
        check('one-domain: Super Admin session works', code == 200 and 'Πίνακας ελέγχου' in t)
        ow = owner('one.localhost')
        code, _, t = ow.get('/admin/')
        check('one-domain: shop owner session works', code == 200 and 'Πίνακας ελέγχου' in t)
        code, h, _ = ow.get('/superadmin/', follow=False)
        check('one-domain: shop owner cannot open Super Admin', code == 302 and '/superadmin/login' in h.get('Location', ''), code)
        fresh_sa = Client(host='one.localhost'); fresh_sa.get('/superadmin/login'); fresh_sa.post('/superadmin/login', {'email': SA[0], 'password': SA[1]})
        code, h, _ = fresh_sa.get('/admin/', follow=False)
        check('one-domain: Super Admin session is not a shop admin session', code == 302 and '/admin/login' in h.get('Location', ''), code)
        code, _, t = Client(host='www.one.localhost').get('/superadmin/login')
        check('one-domain: www. variant reaches Super Admin', code == 200 and 'Σύνδεση Super Admin' in t)
        t = v.get('/robots.txt')[2]
        check('one-domain: robots.txt hides /superadmin/', 'Disallow: /superadmin/' in t)
        sa1.get(f'/superadmin/shops/{s3}')
        sa1.post(f'/superadmin/shops/{s3}/status', {'status': 'locked'})
        check('one-domain: locked shop → storefront 503', Client(host='one.localhost').get('/')[0] == 503)
        code, _, t = Client(host='one.localhost').get('/superadmin/login')
        check('one-domain: Super Admin still reachable while shop locked', code == 200 and 'Σύνδεση Super Admin' in t)
        sa1.post(f'/superadmin/shops/{s3}/delete', {'confirm_text': 'one.localhost'})
        code, h, _ = Client(host='one.localhost').get('/', follow=False)
        check('one-domain: after deleting the shop, / goes back to Super Admin', code == 302 and '/superadmin/' in h.get('Location', ''), code)
    finally:
        php_cfg("$c = require Config::path(); $c['platform_hosts'] = json_decode('" + orig_hosts.strip() + "', true); Config::write($c);")
        time.sleep(3)

    cleanup(sa)
    check('cleanup: test shops deleted', not shop_id('qa1.localhost') and not shop_id('qa2.localhost'))

    passed = sum(1 for r in results if r[1])
    print(f'\n{passed}/{len(results)} passed')
    return 0 if passed == len(results) else 1

if __name__ == '__main__':
    sys.exit(main('--big' in sys.argv))
