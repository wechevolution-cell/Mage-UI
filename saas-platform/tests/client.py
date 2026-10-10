"""Tiny HTTP test client (stdlib only) with cookies, CSRF extraction and Host header override."""
import http.cookiejar, urllib.request, urllib.parse, re, json

class Client:
    def __init__(self, base='http://127.0.0.1:8099', host=None):
        self.base = base
        self.host = host
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
        self.last = None
        self.csrf = None

    def req(self, method, path, data=None, files=None, headers=None, follow=True):
        url = self.base + path
        hdrs = {'User-Agent': 'e2e'}
        if self.host: hdrs['Host'] = self.host
        if headers: hdrs.update(headers)
        body = None
        if files:
            boundary = '----e2eboundary'
            parts = []
            for k, v in (data or {}).items():
                for vv in (v if isinstance(v, list) else [v]):
                    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{vv}\r\n'.encode())
            for k, (fname, content, ctype) in files.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{fname}"\r\nContent-Type: {ctype}\r\n\r\n'.encode() + content + b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode())
            body = b''.join(parts)
            hdrs['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        elif data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode()
            hdrs['Content-Type'] = 'application/x-www-form-urlencoded'
        r = urllib.request.Request(url, data=body, method=method, headers=hdrs)
        try:
            resp = self.opener.open(r)
            code, h, text = resp.status, resp.headers, resp.read()
        except urllib.error.HTTPError as e:
            code, h, text = e.code, e.headers, e.read()
        text = text.decode('utf-8', 'replace') if 'zip' not in (h.get('Content-Type') or '') else text
        self.last = (code, h, text)
        if isinstance(text, str):
            m = re.search(r'name="_csrf" value="([^"]+)"', text) or re.search(r'name="csrf-token" content="([^"]+)"', text)
            if m: self.csrf = m.group(1)
        if follow and code in (301, 302, 303) and h.get('Location'):
            loc = h['Location']
            p = urllib.parse.urlparse(loc)
            if p.netloc and self.host is not None and p.netloc.split(':')[0] != self.host.split(':')[0]:
                return code, h, text
            return self.req('GET', (p.path or '/') + ('?' + p.query if p.query else ''), follow=True)
        return code, h, text

    def get(self, path, **kw): return self.req('GET', path, **kw)
    def post(self, path, data=None, **kw):
        data = dict(data or {})
        if '_csrf' not in data and self.csrf: data['_csrf'] = self.csrf
        return self.req('POST', path, data, **kw)
    def json(self, path, data=None):
        c, h, t = self.post(path, data, headers={'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, follow=False)
        try: return c, json.loads(t)
        except Exception: return c, {'raw': t[:500]}

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
