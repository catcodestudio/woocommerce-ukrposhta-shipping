# -*- coding: utf-8 -*-
"""Minimal .po -> .mo compiler.

msgfmt is not installed on this machine and the catalogue has no plural forms,
so a straight hash-less .mo (which gettext accepts) is enough.
"""
import io
import re
import struct

ESCAPES = [('\\\\', '\x00ESC\x00'), ('\\n', '\n'), ('\\t', '\t'), ('\\"', '"')]


def _unescape(s):
    for a, b in ESCAPES:
        s = s.replace(a, b)
    return s.replace('\x00ESC\x00', '\\')


def parse_po(path):
    entries = {}
    msgid = msgstr = None
    target = None
    for raw in io.open(path, encoding='utf-8'):
        line = raw.rstrip('\n')
        if line.startswith('#') or not line.strip():
            if msgid is not None and msgstr is not None:
                entries[msgid] = msgstr
                msgid = msgstr = target = None
            continue
        m = re.match(r'^msgid "(.*)"$', line)
        if m:
            if msgid is not None and msgstr is not None:
                entries[msgid] = msgstr
            msgid, msgstr = _unescape(m.group(1)), None
            target = 'id'
            continue
        m = re.match(r'^msgstr "(.*)"$', line)
        if m:
            msgstr = _unescape(m.group(1))
            target = 'str'
            continue
        m = re.match(r'^"(.*)"$', line)
        if m and target:
            if target == 'id':
                msgid += _unescape(m.group(1))
            else:
                msgstr += _unescape(m.group(1))
    if msgid is not None and msgstr is not None:
        entries[msgid] = msgstr
    return entries


def compile_po(po_path, mo_path):
    entries = parse_po(po_path)
    items = sorted((k.encode('utf-8'), v.encode('utf-8')) for k, v in entries.items())
    n = len(items)
    keystart = 7 * 4 + 16 * n
    offsets, ids, strs = [], b'', b''
    for k, v in items:
        offsets.append((len(ids), len(k), len(strs), len(v)))
        ids += k + b'\0'
        strs += v + b'\0'
    valuestart = keystart + len(ids)
    koffsets, voffsets = [], []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    out += struct.pack('i' * len(koffsets), *koffsets)
    out += struct.pack('i' * len(voffsets), *voffsets)
    out += ids + strs
    io.open(mo_path, 'wb').write(out)
    return n


if __name__ == '__main__':
    import sys
    print(compile_po(sys.argv[1], sys.argv[2]), 'entries')
