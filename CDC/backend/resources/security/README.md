# Security data files

`common-passwords.txt`: the 10,000 most common passwords, one per line, used by `App\Rules\SafePassword` (SEC-012).
Source: SecLists, `Passwords/Common-Credentials/10k-most-common.txt`
(https://github.com/danielmiessler/SecLists, MIT licence), downloaded unchanged on 2026-10-12.
To replace it, drop in another one-password-per-line file with the same name.
