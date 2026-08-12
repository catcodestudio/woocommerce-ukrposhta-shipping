# -*- coding: utf-8 -*-
"""Produce the wordpress.org build out of the shop build.

wordpress.org will not take a plugin whose name starts with somebody else's
trademark, and it wants the text domain to equal the directory slug. Both differ
from what we sell on catcode.com.ua, so the .org package is generated instead of
maintained by hand — there is exactly one codebase.

Usage: python wporg/build.py            (writes wporg/<slug>/ and the zip)
"""
import io
import os
import shutil
import sys
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.dirname(HERE)

SHOP_SLUG = 'ukrposhta-shipping-for-woocommerce'
ORG_SLUG = 'catcode-shipping-with-ukrposhta-for-woocommerce'
SHOP_NAME = 'Ukrposhta Shipping for WooCommerce'
ORG_NAME = 'CatCode Shipping with Ukrposhta for WooCommerce'

COPY = ['includes', 'assets', 'languages', 'readme.txt', 'uninstall.php',
        SHOP_SLUG + '.php']

dest = os.path.join(HERE, ORG_SLUG)
if os.path.isdir(dest):
    shutil.rmtree(dest)
os.makedirs(dest)

for item in COPY:
    s = os.path.join(SRC, item)
    d = os.path.join(dest, item)
    if os.path.isdir(s):
        shutil.copytree(s, d)
    else:
        shutil.copy2(s, d)

# main file carries the slug in its name
os.rename(os.path.join(dest, SHOP_SLUG + '.php'), os.path.join(dest, ORG_SLUG + '.php'))

# language catalogues are looked up by "<text-domain>-<locale>"
langs = os.path.join(dest, 'languages')
for f in sorted(os.listdir(langs)):
    if f.startswith(SHOP_SLUG):
        os.rename(os.path.join(langs, f), os.path.join(langs, ORG_SLUG + f[len(SHOP_SLUG):]))

# The module page on catcode.com.ua keeps its own slug — rewriting it would point
# Plugin URI at a 404, which is one of the things wordpress.org rejects builds for.
KEEP = 'https://catcode.com.ua/modules/' + SHOP_SLUG + '/'
GUARD = '\x00KEEP\x00'

replaced = 0
for base, dirs, files in os.walk(dest):
    for f in files:
        if not f.endswith(('.php', '.js', '.txt', '.po', '.pot')):
            continue
        p = os.path.join(base, f)
        s = io.open(p, encoding='utf-8', newline='').read()
        o = s
        s = s.replace(KEEP, GUARD)
        s = s.replace(SHOP_NAME, ORG_NAME).replace(SHOP_SLUG, ORG_SLUG)
        s = s.replace(GUARD, KEEP)
        if s != o:
            io.open(p, 'w', encoding='utf-8', newline='').write(s)
            replaced += 1

# the compiled catalogue stores the domain in its header; rebuild it from the .po
sys.path.insert(0, HERE)
from mo import compile_po  # noqa: E402

po = os.path.join(langs, ORG_SLUG + '-uk.po')
compile_po(po, po[:-3] + '.mo')

version = ''
main = io.open(os.path.join(dest, ORG_SLUG + '.php'), encoding='utf-8').read()
for line in main.splitlines():
    if line.strip().startswith('* Version:'):
        version = line.split(':', 1)[1].strip()
        break

out = os.path.join(HERE, '%s-%s.zip' % (ORG_SLUG, version))
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk(dest):
        for f in sorted(files):
            p = os.path.join(base, f)
            arc = os.path.relpath(p, HERE).replace(os.sep, '/')
            z.write(p, arc)

print('slug     :', ORG_SLUG)
print('version  :', version)
print('rewritten:', replaced, 'files')
print('zip      :', out)
