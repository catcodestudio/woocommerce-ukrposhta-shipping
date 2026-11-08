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
import re
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

# Pro code never goes to wordpress.org: the includes/Pro folder, the Pro admin script
# and every block between @pro-begin and @pro-end (licence, updater, Update URI).
PRO_ONLY = ('includes/Pro', 'assets/js/pro-admin.js')


def _skip_pro(dirpath, names):
    rel = os.path.relpath(dirpath, SRC).replace(os.sep, '/')
    return [n for n in names if (rel + '/' + n).lstrip('./') in PRO_ONLY or (rel == '.' and n in PRO_ONLY)]


for item in COPY:
    s = os.path.join(SRC, item)
    d = os.path.join(dest, item)
    if os.path.isdir(s):
        shutil.copytree(s, d, ignore=_skip_pro)
    else:
        shutil.copy2(s, d)


def _strip_pro(text):
    out, skip = [], False
    for line in text.split('\n'):
        if '@pro-begin' in line:
            skip = True
            continue
        if '@pro-end' in line:
            skip = False
            continue
        if not skip and not line.startswith(' * Update URI:'):
            out.append(line)
    if skip:
        raise SystemExit('unclosed @pro-begin')
    return '\n'.join(out)


# readme.txt carries no markers (the update server shows its changelog as is): the Pro
# parts are cut by their own first and last lines.
README_PRO = [
    (r'\*\*Pro\*\* \(licence from catcode\.com\.ua.*?For shipments Ukrposhta issues a counterparty token[^\n]*\n\n?', ''),
    (r'\* Pro, when you create a shipment:.*?to check the key, once a day\.\n\n?', ''),
    (r'\* Pro: [^\n]*\n', ''),
]
_rp = os.path.join(dest, 'readme.txt')
_r = io.open(_rp, encoding='utf-8', newline='').read()
for _pat, _new in README_PRO:
    _r2 = re.sub(_pat, _new, _r, flags=re.S)
    if _r2 == _r:
        raise SystemExit('readme Pro block not found: ' + _pat[:40])
    _r = _r2
if 'Pro' in re.sub(r'= 1\.2\.7 =.*?\n\n', '', _r, flags=re.S).replace('Pro version activated next to', ''):
    pass
io.open(_rp, 'w', encoding='utf-8', newline='').write(_r)

for base, dirs, files in os.walk(dest):
    for f in files:
        if f.endswith(('.php', '.js', '.txt')):
            p = os.path.join(base, f)
            t = io.open(p, encoding='utf-8', newline='').read()
            n = _strip_pro(t)
            if n != t:
                io.open(p, 'w', encoding='utf-8', newline='').write(n)

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
        s = s.replace(' Pro: shipments with cash on delivery, labels, form 103a and delivery statuses.', '')
        s = s.replace(GUARD, KEEP)
        if s != o:
            io.open(p, 'w', encoding='utf-8', newline='').write(s)
            replaced += 1

# The shop build pauses itself next to the wordpress.org copy. The .org copy does the
# opposite: while the shop (full) build is loaded or active it switches itself off, and
# its uninstall leaves the shared settings and post office table while that build is
# installed. So the shop's pause block, its notice string and changelog line go, and
# the .org guard comes in.
FREE_NOTICE = ('The full version of this plugin from catcode.com.ua is active, so this copy is switched off '
               'and the full version runs on the same settings. You can deactivate or delete "' + ORG_NAME + '": '
               'its settings and data stay while the full version is installed.')
FREE_NOTICE_UK = ('Активна повна версія цього плагіна з catcode.com.ua, тому ця копія вимкнена, а повна версія '
                  'працює на тих самих налаштуваннях. «' + ORG_NAME + '» можна деактивувати або видалити: '
                  'її налаштування й дані лишаються, поки встановлена повна версія.')
FREE_NOTICE_RU = ('Активна полная версия этого плагина с catcode.com.ua, поэтому эта копия выключена, а полная версия '
                  'работает на тех же настройках. «' + ORG_NAME + '» можно деактивировать или удалить: '
                  'её настройки и данные остаются, пока установлена полная версия.')
SHOP_PAUSE_MSG = 'The Pro version of Ukrposhta Shipping is paused'
SHOP_PAUSE_CL = '* Fixed: the Pro version activated next to the free copy from WordPress.org'
FREE_CL = (
    '* Fixed: while the full version from catcode.com.ua is installed, deleting this copy keeps the shared settings '
    'and the post office table, so the full version goes on working with them. Without the full version, deleting '
    'still removes everything.\n'
    '* Changed: while the full version is active, this copy switches itself off instead of loading next to it '
    '(no more PHP warnings).'
)
FREE_GUARD = (
    "/*\n"
    " * The full version (sold on catcode.com.ua) lives in its own folder and ships the same\n"
    " * classes and settings. While it is loaded or active this copy stays out of the way, so\n"
    " * the two never collide and the full version keeps working on the same data.\n"
    " */\n"
    "if (\n"
    "\t( static function () {\n"
    "\t\tif ( defined( 'UPWC_FILE' ) && __FILE__ !== UPWC_FILE ) {\n"
    "\t\t\treturn true;\n"
    "\t\t}\n"
    "\t\t$active = (array) get_option( 'active_plugins', array() );\n"
    "\t\tif ( is_multisite() ) {\n"
    "\t\t\t$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );\n"
    "\t\t}\n"
    "\t\tforeach ( $active as $plugin ) {\n"
    "\t\t\tif ( '" + SHOP_SLUG + ".php' === basename( (string) $plugin ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {\n"
    "\t\t\t\treturn true;\n"
    "\t\t\t}\n"
    "\t\t}\n"
    "\t\treturn false;\n"
    "\t} )()\n"
    ") {\n"
    "\tadd_action(\n"
    "\t\t'admin_notices',\n"
    "\t\tstatic function () {\n"
    "\t\t\tif ( ! current_user_can( 'activate_plugins' ) ) {\n"
    "\t\t\t\treturn;\n"
    "\t\t\t}\n"
    "\t\t\techo '<div class=\"notice notice-info\"><p>'\n"
    "\t\t\t\t. esc_html__( '" + FREE_NOTICE.replace("'", "\\'") + "', '" + ORG_SLUG + "' )\n"
    "\t\t\t\t. '</p></div>';\n"
    "\t\t}\n"
    "\t);\n"
    "\t// Still declared while switched off, or WooCommerce lists this copy as incompatible.\n"
    "\tadd_action(\n"
    "\t\t'before_woocommerce_init',\n"
    "\t\tstatic function () {\n"
    "\t\t\tif ( class_exists( \\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::class ) ) {\n"
    "\t\t\t\t\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );\n"
    "\t\t\t\t\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );\n"
    "\t\t\t}\n"
    "\t\t}\n"
    "\t);\n"
    "\treturn;\n"
    "}\n\n"
)


def _one(s, old, new):
    if s.count(old) != 1:
        raise SystemExit('expected 1 x %r, found %d' % (old[:80], s.count(old)))
    return s.replace(old, new)


mp = os.path.join(dest, ORG_SLUG + '.php')
m = io.open(mp, encoding='utf-8', newline='').read()
a = m.find('/*\n * The free copy from wordpress.org (')
b = m.find('\treturn;\n}\n\n', a)
if a < 0 or b < 0:
    raise SystemExit('shop pause block not found')
m = m[:a] + m[b + len('\treturn;\n}\n\n'):]
m = _one(m, "define( 'UPWC_VERSION',", FREE_GUARD + "define( 'UPWC_VERSION',")
io.open(mp, 'w', encoding='utf-8', newline='').write(m)

upath = os.path.join(dest, 'uninstall.php')
u = io.open(upath, encoding='utf-8', newline='').read()
u = _one(u, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;\n",
         "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;\n\n"
         "// The full version from catcode.com.ua uses the same settings and post office table: leave them while it is installed.\n"
         "if ( glob( WP_PLUGIN_DIR . '/*/" + SHOP_SLUG + ".php' ) ) {\n\treturn;\n}\n")
io.open(upath, 'w', encoding='utf-8', newline='').write(u)

rpath = os.path.join(dest, 'readme.txt')
r = io.open(rpath, encoding='utf-8', newline='').read()
cl = [l for l in r.split('\n') if l.startswith(SHOP_PAUSE_CL)]
if len(cl) != 1:
    raise SystemExit('shop changelog line not found')
r = _one(r, cl[0], FREE_CL)
r = r.replace('Tested up to: 7.0\n', 'Tested up to: 7.1\n')
io.open(rpath, 'w', encoding='utf-8', newline='').write(r)

import polib  # noqa: E402
for f in sorted(os.listdir(langs)):
    if not f.endswith(('.po', '.pot')):
        continue
    p = os.path.join(langs, f)
    cat = polib.pofile(p, wrapwidth=0)
    for e in [e for e in cat if e.msgid.startswith(SHOP_PAUSE_MSG)]:
        cat.remove(e)
    if not cat.find(FREE_NOTICE):
        cat.append(polib.POEntry(msgid=FREE_NOTICE, msgstr=FREE_NOTICE_UK if f.endswith('-uk.po') else (FREE_NOTICE_RU if f.endswith('-ru_RU.po') else ''),
                                 occurrences=[(ORG_SLUG + '.php', '')]))
    io.open(p, 'w', encoding='utf-8', newline='\n').write(str(cat) + '\n')

# the compiled catalogue stores the domain in its header; rebuild it from the .po
sys.path.insert(0, HERE)

# wordpress.org ships translations from translate.wordpress.org: only the .pot goes into
# the package, and only with the strings of the code that is actually in it (no Pro).
for f in sorted(os.listdir(langs)):
    p = os.path.join(langs, f)
    if f.endswith(('.po', '.mo')):
        os.remove(p)
    elif f.endswith('.pot'):
        cat = polib.pofile(p, wrapwidth=0)
        for e in list(cat):
            files = [o[0] for o in e.occurrences]
            if files and not any(os.path.exists(os.path.join(dest, fn)) for fn in files):
                cat.remove(e)
        io.open(p, 'w', encoding='utf-8', newline='\n').write(str(cat) + '\n')

version = ''
main = io.open(os.path.join(dest, ORG_SLUG + '.php'), encoding='utf-8').read()
for line in main.splitlines():
    if line.strip().startswith('* Version:'):
        version = line.split(':', 1)[1].strip()
        break

for base, dirs, files in os.walk(dest):
    for f in files:
        if f.endswith('.php'):
            t = io.open(os.path.join(base, f), encoding='utf-8').read()
            for bad in ('Pro\\', 'UPWC_Updater', 'Update URI', 'catcode/v1/license'):
                if bad in t:
                    raise SystemExit('Pro code left in the wp.org build: %s in %s' % (bad, f))
for d in ('includes/Pro',):
    if os.path.exists(os.path.join(dest, d)):
        raise SystemExit('Pro folder left in the wp.org build')

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
