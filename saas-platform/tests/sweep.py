"""Crawl every Super Admin / Shop Admin link and the main storefront routes of every shop; report non-200s and PHP errors."""
import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from client import Client
LOG = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'data', 'logs', 'php-error.log')
start = os.path.getsize(LOG) if os.path.exists(LOG) else 0
bad = []

def crawl(c, prefix, seeds, limit=200):
    seen, queue = set(), list(seeds)
    while queue and len(seen) < limit:
        p = queue.pop(0)
        if p in seen: continue
        seen.add(p)
        code, h, t = c.get(p, follow=False)
        if code not in (200, 302) or (isinstance(t, str) and re.search(r'(Warning|Notice|Deprecated|Fatal error)</?b?>?:', t)): bad.append(f'{c.host}{p} {code}')
        if isinstance(t, str) and 'text/html' in (h.get('Content-Type') or ''):
            for href in re.findall(r'href="(' + prefix + r'[^"#?]*)"', t):
                if any(x in href for x in ('logout', 'export', '.csv', 'backups/', 'impersonate', 'preview')): continue
                if href not in seen: queue.append(href)
    return len(seen)

sa = Client(host='127.0.0.1'); sa.get('/superadmin/login'); sa.post('/superadmin/login', {'email': 'sa@example.com', 'password': 'Secret12345'})
print('superadmin pages:', crawl(sa, '/superadmin', ['/superadmin/']))
o = Client(host='atelier.localhost'); o.get('/admin/login'); o.post('/admin/login', {'email': 'owner@example.com', 'password': 'Owner12345x'})
print('shop admin pages:', crawl(o, '/admin', ['/admin/']))
for n in 'atelier agora kipos lumiere oikos karat stadio paidi ergo iasis'.split():
    c = Client(host=f'{n}.localhost')
    cnt = crawl(c, '/', ['/', '/products/', '/search/?q=a', '/cart/', '/wishlist/', '/blog/', '/account/login/', '/account/register/', '/sitemap.xml', '/robots.txt', '/llms.txt', '/feed/blog.xml'], limit=60)
    print(f'{n}: {cnt} storefront urls')
code = Client(host='atelier.localhost').get('/nope-404/', follow=False)[0]
if code != 404: bad.append(f'404 page returned {code}')
new = open(LOG).read()[start:] if os.path.exists(LOG) else ''
print('BAD:', bad or 'none'); print('PHP log entries:', new.strip()[:2000] or 'none')
