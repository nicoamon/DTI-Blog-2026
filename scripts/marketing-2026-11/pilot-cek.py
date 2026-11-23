#!/usr/bin/env python3
"""Mechanical gate for pilot humanizer rewrites of live posts (asli.html vs baru.html).
Usage: pilot-cek.py <dir-with-asli.html-and-baru.html> ...   exit 1 if any check fails."""
import re, sys, html
def tags(s):  return re.findall(r'<(?!!--)[^>]+>', s)                    # every tag incl. attributes, in order
def blocks(s): return re.findall(r'<!--.*?-->', s, re.S)                  # Gutenberg block comments (incl. FAQ JSON)
def heads(s): return [re.sub(r'\s+', ' ', h).strip() for h in re.findall(r'<h[1-6][^>]*>(.*?)</h[1-6]>', s, re.S)]
def links(s): return re.findall(r'<a\b[^>]*>(.*?)</a>', s, re.S)
def cells(s): return re.findall(r'<t[hd]\b[^>]*>(.*?)</t[hd]>', s, re.S)
def keep(s):  # the WHOLE protected box (tags and text), not just its tag/class pair
    return [m.group(0) for m in re.finditer(r'<(div|p)[^>]*class="[^"]*(?:dti-snippet-answer|dti-product-cta|dti-leadmagnet-cta|dti-related-links|dfig)[^"]*".*?</\1>', s, re.S)]
def text(s):  return html.unescape(re.sub(r'<[^>]+>', ' ', re.sub(r'<!--.*?-->|<style.*?</style>', '', s, flags=re.S)))
bad = 0
for d in sys.argv[1:]:
    a = open(d + '/asli.html').read(); b = open(d + '/baru.html').read(); ok = []
    chk = lambda name, cond: ok.append((name, cond))
    chk('tag+atribut identik', tags(a) == tags(b))
    chk('blok komentar identik', blocks(a) == blocks(b))
    chk('heading identik', heads(a) == heads(b))
    chk('tautan (href+anchor) identik', links(a) == links(b))
    chk('sel tabel identik', cells(a) == cells(b))
    chk('kotak terlindung identik (isi)', keep(a) == keep(b))
    wa, wb = len(text(a).split()), len(text(b).split())
    chk(f'panjang ±10% ({wa}→{wb})', abs(wb - wa) <= 0.10 * wa)
    new_dash = len(re.findall('[—–]', text(b))) - len(re.findall('[—–]', text(a)))
    chk('tanpa tanda pisah baru', new_dash <= 0 and len(re.findall('[—–]', text(b))) <= len(re.findall('[—–]', text(a))))
    chk('prosa berubah', text(a) != text(b))
    fails = [n for n, c in ok if not c]; bad += bool(fails)
    print(d.rstrip('/').split('/')[-1], 'LULUS' if not fails else 'GAGAL: ' + '; '.join(fails), '|', ' · '.join(n for n, c in ok if c and n.startswith('panjang')))
sys.exit(1 if bad else 0)
