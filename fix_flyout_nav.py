#!/usr/bin/env python3
"""
fix_flyout_nav.py — Rebuild Jäger nav dropdown to flyout menu.
"""
import os
import re

BASE = '/Users/laurinnitschke/Desktop/kjs-bad-segeberg'

ROOT_BLOCK = '''\
<ul class="dropdown" id="jaeger-dropdown">
          <li class="has-sub"><a href="jaeger/index.html">KJS Bad Segeberg <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub">
              <li><a href="jaeger/vorstand.html">Vorstand</a></li>
              <li><a href="jaeger/obleute.html">Obleute</a></li>
              <li><a href="jaeger/hegeringe.html">Hegeringe</a></li>
              <li><a href="jaeger/mitglied-werden.html">Mitglied werden</a></li>
              <li><a href="jaeger/jaeger-werden.html">J&#228;ger/in werden</a></li>
            </ul>
          </li>
          <li class="has-sub"><a href="#">Aufgaben <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub">
              <li><a href="aufgaben/schiessen.html">Schie&#223;wesen</a></li>
              <li><a href="aufgaben/hundeausbildung.html">Hundeausbildung</a></li>
              <li><a href="aufgaben/schweisshunde.html">Schwei&#223;hundef&#252;hrer</a></li>
              <li><a href="aufgaben/jugend.html">Jugendarbeit</a></li>
              <li><a href="aufgaben/jagdhorn.html">Jagdhornblasen</a></li>
              <li><a href="aufgaben/naturschutz.html">Naturschutz</a></li>
              <li><a href="aufgaben/jungwildrettung.html">Jungwildrettung</a></li>
            </ul>
          </li>
          <li class="has-sub" id="weitere-themen-item" style="display:none;"><a href="#">Weitere Themen <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub" id="weitere-themen-sub"></ul>
          </li>
        </ul>'''

SUB_BLOCK = '''\
<ul class="dropdown" id="jaeger-dropdown">
          <li class="has-sub"><a href="../jaeger/index.html">KJS Bad Segeberg <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub">
              <li><a href="../jaeger/vorstand.html">Vorstand</a></li>
              <li><a href="../jaeger/obleute.html">Obleute</a></li>
              <li><a href="../jaeger/hegeringe.html">Hegeringe</a></li>
              <li><a href="../jaeger/mitglied-werden.html">Mitglied werden</a></li>
              <li><a href="../jaeger/jaeger-werden.html">J&#228;ger/in werden</a></li>
            </ul>
          </li>
          <li class="has-sub"><a href="#">Aufgaben <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub">
              <li><a href="../aufgaben/schiessen.html">Schie&#223;wesen</a></li>
              <li><a href="../aufgaben/hundeausbildung.html">Hundeausbildung</a></li>
              <li><a href="../aufgaben/schweisshunde.html">Schwei&#223;hundef&#252;hrer</a></li>
              <li><a href="../aufgaben/jugend.html">Jugendarbeit</a></li>
              <li><a href="../aufgaben/jagdhorn.html">Jagdhornblasen</a></li>
              <li><a href="../aufgaben/naturschutz.html">Naturschutz</a></li>
              <li><a href="../aufgaben/jungwildrettung.html">Jungwildrettung</a></li>
            </ul>
          </li>
          <li class="has-sub" id="weitere-themen-item" style="display:none;"><a href="#">Weitere Themen <span class="arrow-right">&#9658;</span></a>
            <ul class="dropdown dropdown--sub" id="weitere-themen-sub"></ul>
          </li>
        </ul>'''

# Root-level files
ROOT_FILES = {'index.html', 'impressum.html', 'datenschutz.html'}

# Excluded subdirectories
EXCLUDED_DIRS = {'admin', 'redaktion', 'login'}


def find_jaeger_ul_block(content):
    """
    Find the <ul ...id="jaeger-dropdown"...> block and return (start, end) indices.
    Uses stack counting to find the matching </ul>.
    Returns None if not found.
    """
    # Find the opening tag line containing id="jaeger-dropdown"
    pattern = re.compile(r'<ul[^>]*id=["\']jaeger-dropdown["\'][^>]*>', re.DOTALL)
    m = pattern.search(content)
    if not m:
        return None

    start = m.start()
    # Now count ul tags from this position
    pos = m.end()
    depth = 1  # we've opened one <ul>

    while pos < len(content) and depth > 0:
        next_open = content.find('<ul', pos)
        next_close = content.find('</ul>', pos)

        if next_close == -1:
            # No closing tag found
            return None

        if next_open != -1 and next_open < next_close:
            depth += 1
            pos = next_open + 3
        else:
            depth -= 1
            pos = next_close + 5  # len('</ul>') == 5

    if depth == 0:
        return (start, pos)
    return None


def process_file(filepath, is_root):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    result = find_jaeger_ul_block(content)
    if result is None:
        return False

    start, end = result
    replacement = ROOT_BLOCK if is_root else SUB_BLOCK
    new_content = content[:start] + replacement + content[end:]

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    return True


changed = []

# Process root-level files
for fname in ROOT_FILES:
    fpath = os.path.join(BASE, fname)
    if os.path.exists(fpath):
        if process_file(fpath, is_root=True):
            changed.append(fpath)
            print(f'  [ROOT] Updated: {fname}')
        else:
            print(f'  [ROOT] No jaeger-dropdown found: {fname}')

# Process subdirectory HTML files
for entry in os.scandir(BASE):
    if not entry.is_dir():
        continue
    dirname = entry.name
    if dirname.startswith('.') or dirname in EXCLUDED_DIRS or dirname in {'css', 'js', 'images', 'content'}:
        continue
    for root, dirs, files in os.walk(entry.path):
        # Skip excluded dirs in walk
        dirs[:] = [d for d in dirs if d not in EXCLUDED_DIRS and not d.startswith('.')]
        for fname in files:
            if not fname.endswith('.html'):
                continue
            fpath = os.path.join(root, fname)
            if process_file(fpath, is_root=False):
                changed.append(fpath)
                rel = os.path.relpath(fpath, BASE)
                print(f'  [SUB]  Updated: {rel}')
            else:
                rel = os.path.relpath(fpath, BASE)
                print(f'  [SUB]  No jaeger-dropdown: {rel}')

print(f'\nDone. {len(changed)} file(s) updated.')
