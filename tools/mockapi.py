# Local stand-in for web/api/zebricek.php (same JSON shapes and rules), for testing only.
import json, sys, time, hashlib, secrets, re, unicodedata
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

ROOT, PORT = sys.argv[1], int(sys.argv[2])
H, ZA, BON, TOP = 10, 10, 5, 50
now = int(time.time())
DB = [  # local sample rows only
    {'id': 'a1', 'd': 'x1', 'j': 'Lea', 'b': 85, 's': 6, 't': now - 900},
    {'id': 'a2', 'd': 'x2', 'j': 'Kuba z 5.B', 'b': 70, 's': 5, 't': now - 800},
    {'id': 'a3', 'd': 'x3', 'j': 'Ema', 'b': 65, 's': 5, 't': now - 700},
    {'id': 'a4', 'd': 'x4', 'j': 'Tomáš', 'b': 40, 's': 3, 't': now - 600},
    {'id': 'a5', 'd': 'x5', 'j': 'Ďáblík_Ťuk', 'b': 25, 's': 2, 't': now - 500},
]
BAD = ['kurv','pica','kokot','debil','prdel','http','www']
NAME = re.compile(r'^[^\W_][\w .\-]{1,15}$', re.U)

def mozne(s, b):
    if s == 0: return b == 0
    return any(b == ZA*s + BON*(s-r) for r in range(1, min(s, H-s+1)+1))

def prehled(otisk):
    z = sorted(DB, key=lambda r: (-r['b'], -r['s'], r['t']))
    pub = lambda r, i: {'id': r['id'], 'misto': i+1, 'jmeno': r['j'], 'body': r['b'], 'spravne': r['s'], 'kdy': r['t']}
    ja = next((pub(r, i) for i, r in enumerate(z) if r['d'] == otisk), None)
    return {'ok': True, 'celkem': len(z), 'top': [pub(r, i) for i, r in enumerate(z[:TOP])], 'ja': ja}

class Handler(SimpleHTTPRequestHandler):
    def _json(self, code, data):
        body = json.dumps(data, ensure_ascii=False).encode()
        self.send_response(code); self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)
    def do_GET(self):
        u = urlparse(self.path)
        if u.path != '/api/zebricek.php': return super().do_GET()
        dev = parse_qs(u.query).get('dev', [''])[0]
        otisk = hashlib.sha256(dev.encode()).hexdigest() if re.fullmatch(r'[a-f0-9]{32}', dev) else ''
        self._json(200, prehled(otisk))
    def do_POST(self):
        if urlparse(self.path).path != '/api/zebricek.php': return self.send_error(405)
        n = int(self.headers.get('Content-Length', 0) or 0)
        try: v = json.loads(self.rfile.read(min(n, 4096)) or b'null')
        except Exception: v = None
        if not isinstance(v, dict): return self._json(400, {'ok': False, 'chyba': 'vstup'})
        if v.get('akce') == 'smazat': return self._json(403, {'ok': False, 'chyba': 'heslo'})
        dev = str(v.get('dev', ''))
        if not re.fullmatch(r'[a-f0-9]{32}', dev): return self._json(400, {'ok': False, 'chyba': 'zarizeni'})
        j = ' '.join(str(v.get('jmeno', '')).split())
        flat = ''.join(c for c in unicodedata.normalize('NFD', j.lower()) if c.isalnum() and unicodedata.category(c) != 'Mn')
        if not NAME.match(j) or any(w in flat for w in BAD): return self._json(422, {'ok': False, 'chyba': 'jmeno'})
        b, s = v.get('body'), v.get('spravne')
        if type(b) is not int or type(s) is not int or not mozne(s, b): return self._json(422, {'ok': False, 'chyba': 'body'})
        otisk = hashlib.sha256(dev.encode()).hexdigest()
        if any(r['d'] == otisk for r in DB):
            p = prehled(otisk); p.update(ok=False, chyba='uz-ulozeno'); return self._json(409, p)
        DB.append({'id': secrets.token_hex(6), 'd': otisk, 'j': j, 'b': b, 's': s, 't': int(time.time())})
        self._json(200, prehled(otisk))
    def log_message(self, *a): pass

ThreadingHTTPServer(('127.0.0.1', PORT), partial(Handler, directory=ROOT)).serve_forever()
