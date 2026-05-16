#!/usr/bin/env python3
"""
Fix navigation consistency across all subpages of KJS Bad Segeberg website.
Replaces the incomplete dropdown--wide block with the full version (using ../ prefix).
"""

import re
import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# The correct full dropdown block (with ../ prefix for subpages)
CORRECT_BLOCK = '''<ul class="dropdown dropdown--wide" id="jaeger-dropdown">
          <div class="dropdown-header">KJS Bad Segeberg</div>
          <li><a href="../jaeger/vorstand.html">Vorstand</a></li>
          <li><a href="../jaeger/obleute.html">Obleute</a></li>
          <li><a href="../jaeger/hegeringe.html">Hegeringe</a></li>
          <li><a href="../jaeger/mitglied-werden.html">Mitglied werden</a></li>
          <li><a href="../jaeger/jaeger-werden.html">Jäger/in werden</a></li>
          <div class="dropdown-header">Aufgaben</div>
          <li><a href="../aufgaben/schiessen.html">Schießwesen</a></li>
          <li><a href="../aufgaben/hundeausbildung.html">Hundeausbildung</a></li>
          <li><a href="../aufgaben/schweisshunde.html">Schweißhundeführer</a></li>
          <li><a href="../aufgaben/jugend.html">Jugendarbeit</a></li>
          <li><a href="../aufgaben/jagdhorn.html">Jagdhornblasen</a></li>
          <li><a href="../aufgaben/naturschutz.html">Naturschutz</a></li>
          <li><a href="../aufgaben/jungwildrettung.html">Jungwildrettung</a></li>
        </ul>'''

# Regex: match from <ul class="dropdown dropdown--wide"...> to the FIRST </ul>
# that comes after at least one </li> or </div>
PATTERN = re.compile(
    r'<ul\s+class="dropdown\s+dropdown--wide"[^>]*>.*?</ul>',
    re.DOTALL
)

def should_process(filepath):
    """Determine if a file should be processed."""
    rel = os.path.relpath(filepath, BASE_DIR)
    parts = rel.split(os.sep)

    # Skip root index.html
    if rel == 'index.html':
        return False
    # Skip admin/
    if parts[0] == 'admin':
        return False
    # Skip redaktion/
    if parts[0] == 'redaktion':
        return False
    # Skip login.html and login/
    if rel == 'login.html' or parts[0] == 'login':
        return False
    return True

def fix_file(filepath):
    """Fix the nav dropdown in a single file. Returns True if changed."""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    if 'dropdown--wide' not in content:
        return False

    new_content, count = PATTERN.subn(CORRECT_BLOCK, content)

    if count == 0:
        print(f"  WARNING: Pattern not matched in {os.path.relpath(filepath, BASE_DIR)}")
        return False

    if new_content == content:
        print(f"  UNCHANGED (already correct): {os.path.relpath(filepath, BASE_DIR)}")
        return False

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    return True

def main():
    changed = []
    skipped = []

    for root, dirs, files in os.walk(BASE_DIR):
        # Skip hidden dirs
        dirs[:] = [d for d in dirs if not d.startswith('.')]

        for filename in files:
            if not filename.endswith('.html'):
                continue
            filepath = os.path.join(root, filename)
            if not should_process(filepath):
                continue

            if fix_file(filepath):
                changed.append(os.path.relpath(filepath, BASE_DIR))
            else:
                skipped.append(os.path.relpath(filepath, BASE_DIR))

    print(f"\n{'='*60}")
    print(f"CHANGED ({len(changed)} files):")
    for f in sorted(changed):
        print(f"  ✓ {f}")

    if skipped:
        print(f"\nSKIPPED / UNCHANGED ({len(skipped)} files):")
        for f in sorted(skipped):
            print(f"  - {f}")

    print(f"{'='*60}")

if __name__ == '__main__':
    main()
