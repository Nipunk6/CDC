#!/usr/bin/env python3
"""A3.1 — login response + timing comparison (local only, 80 requests, paced under the 60/min IP bucket).

Variants (20 samples each, interleaved): unknown email, known email + wrong password, unknown roll, known roll +
wrong password. Known accounts are synthetic scale-test students (`27SC####`, `@scale.qa.test`), a different account per
sample so the per-account limiter (10/min) never triggers. Prints status/body per variant and the timing statistics.
"""
import json
import statistics
import time
import urllib.error
import urllib.request

API = 'http://127.0.0.1:8000/api/auth/login'
acc = json.load(open('/Users/admin/Desktop/CDC-main/CDC/security/evidence/timing_accounts.json'))


def login(body):
    req = urllib.request.Request(API, data=json.dumps(body).encode(), method='POST',
                                 headers={'Content-Type': 'application/json', 'Accept': 'application/json'})
    t = time.perf_counter()
    try:
        with urllib.request.urlopen(req) as r:
            status, text = r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        status, text = e.code, e.read().decode()
    return status, text, (time.perf_counter() - t) * 1000


variants = {
    'unknown email': lambda i: {'email': f'nobody{i}-{int(time.time())}@scale.qa.test', 'password': 'WrongPass1'},
    'known email, wrong pw': lambda i: {'email': acc['emails'][i], 'password': 'WrongPass1'},
    'unknown roll': lambda i: {'roll_no': f'99ZZ{i:04d}', 'password': 'WrongPass1'},
    'known roll, wrong pw': lambda i: {'roll_no': acc['rolls'][i], 'password': 'WrongPass1'},
}
results = {k: [] for k in variants}
bodies = {k: set() for k in variants}
for i in range(20):
    for name, make in variants.items():
        status, text, ms = login(make(i))
        results[name].append(ms)
        bodies[name].add(f'{status} {text}')
        time.sleep(1.05)  # stay under 60/min per IP

for name, times in results.items():
    print(f'{name:24} n={len(times)} mean={statistics.mean(times):7.1f} ms  median={statistics.median(times):7.1f} ms  '
          f'min={min(times):6.1f}  max={max(times):6.1f}  responses={sorted(bodies[name])}')
gap_email = statistics.mean(results['known email, wrong pw']) - statistics.mean(results['unknown email'])
gap_roll = statistics.mean(results['known roll, wrong pw']) - statistics.mean(results['unknown roll'])
print(f'timing gap (known - unknown): email {gap_email:.1f} ms, roll {gap_roll:.1f} ms')
