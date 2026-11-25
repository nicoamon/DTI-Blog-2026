#!/usr/bin/env python3
"""Gate for pilot REWRITES (TULIS ULANG) of live posts: <dir> has baru.html + baru-meta.json.
Usage: cek-tulis-ulang.py <dir> ...   exit 1 on any failure. Checks links live over HTTPS (own site)."""
import os, re, sys, json, html, subprocess, urllib.parse
MODE2 = r'PTPN|Agrinas|TMS[ -]?BTN|CRM for Bank|\bAstra\b|Bank Saqu|FIF Group|negative EBITDA'
BASE = os.environ.get('BASE', 'https://dtisolution.id')  # e.g. BASE=https://dti.amon.id to check against staging
def same_url(a, b):
    # Redirects that keep the page (http→https, www→apex, added trailing slash) are fine.
    def n(u):
        p = urllib.parse.urlsplit(u); h = (p.hostname or '').lower()
        return (h[4:] if h.startswith('www.') else h, p.path.rstrip('/') or '/', p.query)
    return n(a) == n(b)
def status(u):
    # Follow redirects, but a link whose redirect lands on ANOTHER page (a post taken down → 301 to the
    # blog home or its twin) fails: the code becomes '200→<final url>'.
    u = u if u.startswith('http') else BASE + u
    if 'BASE' in os.environ:  # checking staging: absolute links to the live site go to BASE too, never mixed with prod
        u = re.sub(r'^https?://(?:www\.)?dtisolution\.id(?=/|$)', BASE.rstrip('/'), u, flags=re.I)
    code, _, final = subprocess.run(['curl', '-sL', '-o', '/dev/null', '--max-time', '15', '-w', '%{http_code} %{url_effective}', u], capture_output=True, text=True).stdout.partition(' ')
    return code if code != '200' or same_url(u, final) else code + '→' + final
sys.stdout.reconfigure(encoding='utf-8')  # output has '±', '–': never depend on the locale
if len(sys.argv) < 2: sys.exit('Usage: cek-tulis-ulang.py <dir> ...  (tanpa direktori = tidak ada yang diperiksa, bukan lulus)')
bad = 0
for d in sys.argv[1:]:
    b = open(d + '/baru.html', encoding='utf-8').read(); m = json.load(open(d + '/baru-meta.json', encoding='utf-8')); ok = []
    t = html.unescape(re.sub(r'<[^>]+>', ' ', b)); w = len(t.split())
    snip = re.search(r'class="dti-snippet-answer".*?</strong>(.*?)</div>', b, re.S)
    sw = len(html.unescape(re.sub('<[^>]+>', ' ', snip.group(1))).split()) if snip else 0
    hrefs = [x or y for x, y in re.findall(r'''href\s*=\s*(?:"([^"]*)"|'([^']*)')''', b, re.I)]  # both quote styles
    def host(h):
        try: return (urllib.parse.urlparse(h).hostname or '').lower()
        except ValueError: return ''  # malformed URL: not ours, and never a crash
    own = [h for h in hrefs if h.startswith('/') or host(h) in ('dtisolution.id', 'www.dtisolution.id')]
    dead = [h for h in own if status(h) != '200']
    ok += [('snippet 40–55 kata (%d)' % sw, 40 <= sw <= 55), ('panjang 800–1.600 (%d)' % w, 800 <= w <= 1600),
           ('≥3 H2', len(re.findall(r'<h2\b', b)) >= 3), ('tanpa H1 di isi', not re.search(r'<h1\b', b, re.I)),
           ('tabel atau gambar', bool(re.search(r'<table|<img', b))), ('tautan produk', any('/id/produk/' in h for h in hrefs)),
           ('CTA kontak', any('/id/kontak' in h for h in hrefs)), ('tanpa wa.me / request-demo', not any(('wa.me' in h or 'request-demo' in h) for h in hrefs)),
           ('semua tautan internal 200' + (' ' + str(dead) if dead else ''), not dead),
           ('tanpa tanda pisah', not re.search('[—–]', t)), ('Mode-2 bersih (HTML mentah + meta)', not re.search(MODE2, b + json.dumps(m, ensure_ascii=False), re.I)),
           ('tanpa FaceOffice (HTML mentah + meta)', not re.search(r'face\s*office', b + json.dumps(m, ensure_ascii=False), re.I)),
           ('post_title ≤70', 0 < len(m.get('post_title', '')) <= 70), ('rank_math_title ≤60', 0 < len(m.get('rank_math_title', '')) <= 60),
           ('rank_math_description ≤155', 0 < len(m.get('rank_math_description', '')) <= 155), ('focus keyword', bool(m.get('rank_math_focus_keyword')))]
    fails = [n for n, c in ok if not c]; bad += bool(fails)
    print(d.rstrip('/').split('/')[-1], 'LULUS' if not fails else 'GAGAL: ' + '; '.join(fails), f'| {w} kata · snippet {sw} · {m.get("post_title","")[:60]}')
sys.exit(1 if bad else 0)
