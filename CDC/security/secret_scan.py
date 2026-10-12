#!/usr/bin/env python3
"""Secret scan (Part 2, S2.1/S2.2) — gitleaks-style patterns, manual because gitleaks is not installed.

  python3 secret_scan.py tree          # working tree (tracked + untracked, minus deps/build output)
  python3 secret_scan.py history       # every added line in the full git history (all refs)
  python3 secret_scan.py diff <base>   # CI: lines added since <base> (e.g. origin/main); exit 1 on a blocking finding

Every value is printed REDACTED (first 2 + last 2 chars). Repo root: two folders above this file.

`diff` separates findings into BLOCKING and NOTE. Blocking = formats that are secrets by shape (private key block,
APP_KEY, AWS key, GitHub token, JWT) anywhere, and any filled-in secret variable in an env file or env template.
Notes = the loose patterns (four 4-letter words, `password => '...'`, NAME_SECRET = value in code); they match
ordinary sentences, constants and test fixtures, so they are listed for a human to read and never fail the build.
"""
import os
import re
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
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


ALWAYS_BLOCKING = {'APP_KEY', 'private key block', 'AWS access key', 'GitHub token', 'generic bearer/jwt'}


def is_env_file(path: str) -> bool:
    name = os.path.basename(path)
    return name.startswith('.env') or name.endswith('.env') or path.startswith('env-templates/')


def diff(base: str) -> int:
    """Scan the lines added between <base> and HEAD. Returns the process exit code."""
    proc = subprocess.run(['git', '-C', ROOT, 'diff', '--no-color', '--unified=0', f'{base}...HEAD'],
                          capture_output=True, text=True, errors='ignore')
    if proc.returncode != 0:
        print(f'git diff against {base} failed: {proc.stderr.strip()}')
        return 2

    path, line_no, blocking, notes = None, 0, [], []
    for line in proc.stdout.splitlines():
        if line.startswith('+++ '):
            path = line[6:].strip() if line.startswith('+++ b/') else None
            continue
        if line.startswith('@@'):
            match = re.search(r'\+(\d+)', line)
            line_no = int(match.group(1)) if match else 0
            continue
        if not line.startswith('+') or path is None:
            continue
        if os.path.splitext(path)[1].lower() not in SKIP_EXT and not any(part in SKIP_DIRS for part in path.split('/')):
            for name, key, value in scan_line(line[1:]):
                finding = f'{path}:{line_no}\t{name}\t{key}\t{red(value)}'
                if name in ALWAYS_BLOCKING or (name == 'env-style secret' and is_env_file(path)):
                    blocking.append(finding)
                else:
                    notes.append(finding)
        line_no += 1

    print(f'Secret scan of lines added since {base}: {len(blocking)} blocking, {len(notes)} note(s).')
    for finding in blocking:
        print('BLOCKING\t' + finding)
    for finding in notes:
        print('note\t' + finding)
    return 1 if blocking else 0


if __name__ == '__main__':
    if len(sys.argv) >= 3 and sys.argv[1] == 'diff':
        sys.exit(diff(sys.argv[2]))
    {'tree': tree, 'history': history}[sys.argv[1]]()
