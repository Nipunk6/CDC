#!/usr/bin/env python3
"""Secret scan (Part 2, S2.1/S2.2) — gitleaks-style patterns, manual because gitleaks is not installed.

  python3 secret_scan.py tree      # working tree (tracked + untracked, minus deps/build output)
  python3 secret_scan.py history   # every added line in the full git history (all refs)

Every value is printed REDACTED (first 2 + last 2 chars). Repo root: /Users/admin/Desktop/CDC-main.
"""
import os
import re
import subprocess
import sys

ROOT = '/Users/admin/Desktop/CDC-main'
SKIP_DIRS = {'node_modules', 'vendor', '.next', '.git', 'storage', 'test-results', 'playwright-report', '_xfer'}
SKIP_EXT = {'.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.pdf', '.docx', '.xlsx', '.xls', '.zip', '.woff', '.woff2', '.ttf', '.lock', '.svg'}

PATTERNS = [
    ('env-style secret', re.compile(r'\b([A-Z0-9_]*(?:PASSWORD|SECRET|API_KEY|PRIVATE_KEY|ACCESS_KEY|TOKEN)[A-Z0-9_]*)\s*=\s*["\']?([^\s"\'#]{6,})')),
    ('APP_KEY', re.compile(r'\b(APP_KEY)\s*=\s*["\']?(base64:[A-Za-z0-9+/=]{20,})')),
    ('gmail app password (4x4)', re.compile(r'()\b([a-z]{4} [a-z]{4} [a-z]{4} [a-z]{4})\b')),
    ('private key block', re.compile(r'()(-----BEGIN [A-Z ]*PRIVATE KEY-----)')),
    ('AWS access key', re.compile(r'()\b(AKIA[0-9A-Z]{16})\b')),
    ('GitHub token', re.compile(r'()\b(gh[pousr]_[A-Za-z0-9]{30,})\b')),
    ('generic bearer/jwt', re.compile(r'()\b(eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,})')),
    ('password literal', re.compile(r'(["\']?password["\']?\s*[:=]>?\s*)["\']([^"\'\s]{6,})["\']', re.I)),
]

PLACEHOLDER = re.compile(r'^(null|true|false|env\(|\$|your|example|changeme|placeholder|secret$|password$|<|\{|\(|test|dummy|xxx)', re.I)


def red(v: str) -> str:
    return f'{v[:2]}…{v[-2:]} (len={len(v)})' if len(v) > 6 else '<short>'


def scan_line(line: str):
    for name, rx in PATTERNS:
        for m in rx.finditer(line):
            value = m.group(2)
            if PLACEHOLDER.match(value) or value.startswith('${'):
                continue
            yield name, (m.group(1) or '').strip(' =:\'"'), value


def tree():
    out = subprocess.run(['git', '-C', ROOT, 'check-ignore', '--stdin'], input='', capture_output=True, text=True)
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            if os.path.splitext(fn)[1].lower() in SKIP_EXT:
                continue
            path = os.path.join(dirpath, fn)
            rel = os.path.relpath(path, ROOT)
            ignored = subprocess.run(['git', '-C', ROOT, 'check-ignore', '-q', rel]).returncode == 0
            try:
                with open(path, encoding='utf-8', errors='ignore') as fh:
                    for no, line in enumerate(fh, 1):
                        for name, key, value in scan_line(line):
                            print(f'{rel}:{no}\t{"[git-ignored] " if ignored else ""}{name}\t{key}\t{red(value)}')
            except OSError:
                pass


def history():
    proc = subprocess.Popen(['git', '-C', ROOT, 'log', '--all', '-p', '--no-color', '--format=@@COMMIT %h %ad', '--date=short'],
                            stdout=subprocess.PIPE, text=True, errors='ignore')
    commit, path, seen = None, None, set()
    for line in proc.stdout:
        if line.startswith('@@COMMIT '):
            commit = line.split()[1]
            continue
        if line.startswith('+++ b/'):
            path = line[6:].strip()
            continue
        if not line.startswith('+') or line.startswith('+++'):
            continue
        if path and os.path.splitext(path)[1].lower() in SKIP_EXT:
            continue
        for name, key, value in scan_line(line[1:]):
            sig = (path, name, key, value)
            if sig in seen:
                continue
            seen.add(sig)
            print(f'{commit}\t{path}\t{name}\t{key}\t{red(value)}')


if __name__ == '__main__':
    {'tree': tree, 'history': history}[sys.argv[1]]()
