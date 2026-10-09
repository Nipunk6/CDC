# Setting up the CDC Placement Portal on a new laptop

This guide gets the portal (Laravel backend + Next.js frontend + MySQL) running locally with demo data. It works on macOS and Windows; Windows commands are given where they differ.

---

## 1. Install the tools

| Tool | Version | Check with |
|---|---|---|
| Git | any recent | `git --version` |
| PHP | **8.2 or newer** | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | **20 or newer** (we use 24) | `node -v` |
| MySQL | 8.x or newer (MySQL is what the project uses) | `mysql --version` |

**Database:** the project is built and tested on **MySQL**. If you only have MariaDB (for example XAMPP ships MariaDB), it can work, but keep `DB_CONNECTION=mysql` in `.env`. With `DB_CONNECTION=mariadb` the migration that adds the `student` role is skipped and student accounts break.

**PHP extensions needed:** `pdo_mysql`, `mbstring`, `openssl`, `xml`, `ctype`, `json`, `fileinfo`, `zip`, `gd`, `curl`.
- **macOS (Homebrew):**
  ```bash
  brew install php composer node mysql git
  ```
  Then start MySQL with `brew services start mysql`.
- **Windows:** the easiest option is **Laragon** or **XAMPP** (both include PHP and MySQL). Then install Composer, Node.js and Git separately. In `php.ini`, make sure the extensions above are enabled (remove the `;` in front of them).

**PHP upload limits:** in `php.ini`, set `upload_max_filesize = 8M` and `post_max_size = 10M`. The Excel imports accept files up to 5 MB.

---

## 2. Get the code

The repository is private (`Nipunk6/CDC` on GitHub). Ask the owner to add your friend as a collaborator, then run:

```bash
git clone https://github.com/Nipunk6/CDC.git CDC-main
```

```bash
cd CDC-main/CDC
```

All commands below run from `CDC-main/CDC/backend` or `CDC-main/CDC/frontend`.

---

## 3. Create the database

Log in to MySQL with `mysql -u root -p` (on Laragon/XAMPP you can also use phpMyAdmin), then run:

```sql
CREATE DATABASE iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

You can use the `root` user locally, or create a separate user and give it access to this database.

---

## 4. Backend setup (`CDC/backend`)

```bash
cd backend
```

```bash
composer install
```

```bash
cp .env.example .env
```

On Windows use `copy .env.example .env`.

### 4.1 Fill in `backend/.env`

Open `backend/.env` and set these values. Everything else can stay as it is in the example.

| Variable | What to put locally | Notes |
|---|---|---|
| `APP_ENV` | `local` | |
| `APP_DEBUG` | `true` | Only for local use; production must be `false`. |
| `APP_URL` | `http://127.0.0.1:8000` | The backend address. |
| `FRONTEND_URL` | `http://127.0.0.1:3000` | Used in email links. |
| `FRONTEND_URLS` | `http://127.0.0.1:3000,http://localhost:3000` | Origins allowed by CORS. |
| `DB_CONNECTION` | `mysql` | MySQL only. |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | |
| `DB_DATABASE` | `iitism_placement` | The database from step 3. |
| `DB_USERNAME` / `DB_PASSWORD` | your local MySQL user and password | Laragon/XAMPP usually use `root` with an empty password. |
| `MAIL_MAILER` | **`log`** | Emails are written to `storage/logs/laravel.log` and **not sent**. Keep this on a test laptop so no real student gets mail. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | any, e.g. `cdc@example.test` / `"CDC Placement Portal"` | |
| `MAIL_BULK_BATCH_SIZE` | `100` | Students per BCC batch. |
| `QUEUE_CONNECTION` | `database` | Queued emails wait in the database for the worker (step 6). |
| `SESSION_DRIVER` | `database` | |
| `ADMIN_EMAIL` / `ADMIN_NAME` / `ADMIN_PASSWORD` | your own choice | The first super admin is created from these when you seed. Choose your own password; don't reuse a real one. |
| `PHP_CLI_SERVER_WORKERS` | `4` | Remove the `#` in front of it. Without this, the built-in PHP server can freeze when the frontend sends several requests at once. |

**Optional:**

| Variable | Default | Notes |
|---|---|---|
| `STUDENT_EMAIL_DOMAINS` | `iitism.ac.in` | Allowed institute email domains (comma separated). |
| `STUDENT_IMPORT_MAX_ROWS` | `10000` | Maximum rows in a student import. |
| `COMPANY_RECRUITER_VERIFY_TTL_MINUTES` | `30` | How long a recruiter's email verification link stays valid. |

**Leave these empty or as they are:**
- `MAIL_USERNAME` and `MAIL_PASSWORD`: only needed for real SMTP. Never put the CDC Gmail or institute mail password on a test laptop or in git.
- `REDIS_*`, `AWS_*` and `MEMCACHED_*`: not used.
- `APP_KEY`: generated in the next step.

### 4.2 Generate the key, create tables and load demo data

```bash
php artisan key:generate
```

```bash
php artisan migrate --seed
```

```bash
php artisan db:seed --class=Phase2DemoSeeder
```

```bash
php artisan storage:link
```

What these do:
- `key:generate` writes `APP_KEY` into `.env`.
- `migrate --seed` creates all tables, the programme/branch catalogue, the default settings and the super admin from `ADMIN_EMAIL`/`ADMIN_PASSWORD`.
- `Phase2DemoSeeder` adds demo data: 2 placement cycles, 120 students, 3 companies, drives at different stages, offers, a notice, a survey and an Excel template. It sends no emails.
- `storage:link` makes company logos and policy PDFs viewable.

To start again from an empty database later, run:

```bash
php artisan migrate:fresh --seed
```

---

## 5. Frontend setup (`CDC/frontend`)

```bash
cd ../frontend
```

```bash
npm install
```

### 5.1 Create `frontend/.env.local`

This file is **not** in git, so create it by hand with these three lines:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
NEXTAUTH_URL=http://127.0.0.1:3000
NEXTAUTH_SECRET=<paste a new random secret here>
```

| Variable | Meaning |
|---|---|
| `NEXT_PUBLIC_API_URL` | The backend API address. It must end with `/api`. |
| `NEXTAUTH_URL` | The address you open the frontend on. |
| `NEXTAUTH_SECRET` | The key that protects login sessions. **Generate a new one for every laptop**; never copy the owner's. |
| `INTERNAL_PROXY_SECRET` | Optional locally. A shared secret the Next.js server uses to sign the real client IP for Laravel (login limits and logs). If you set it, put the **same** value in `backend/.env`. Generate with `openssl rand -hex 32`. |
| `CLIENT_IP_PROXY_HOPS` | Optional, default `1`. How many reverse proxies in front of Next.js add to `X-Forwarded-For`. |

Generate a secret with this command:

```bash
openssl rand -base64 32
```

On Windows without openssl, use:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('base64'))"
```

**Use the same address style everywhere:** either `127.0.0.1` or `localhost`, in both `.env` files and in the browser. Mixing them breaks logins.

---

## 6. Run the portal

Open **four terminals**.

**Terminal 1, backend API** (from `CDC/backend`):

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2, queue worker** for emails and big jobs (from `CDC/backend`):

```bash
php artisan queue:work
```

**Terminal 3, scheduler** for "Schedule For Later" drive openings (from `CDC/backend`). It is optional unless you test scheduled drives:

```bash
php artisan schedule:work
```

**Terminal 4, frontend** (from `CDC/frontend`):

```bash
npm run dev -- --hostname 127.0.0.1 --port 3000
```

Then open **http://127.0.0.1:3000**.

---

## 7. Demo logins (local only, created by `Phase2DemoSeeder`)

| Role | Login page | Username | Password |
|---|---|---|---|
| Admin (demo super admin) | Admin Login | `admin@cdc-demo.test` | `Admin@2026` |
| Admin (your own) | Admin Login | `ADMIN_EMAIL` from `.env` | `ADMIN_PASSWORD` from `.env` |
| Student | Student Login | roll number, e.g. `23JE0101` | `Student@2026` |
| Company recruiter | Recruiter Login | `hr@nimbus.demo` (also `hr@vertex.demo`, `hr@helix.demo`) | `Company@2026` |

These are test passwords for local demo data only. They don't exist in production.

---

## 8. Checking that everything works

**Backend tests** (from `CDC/backend`):

```bash
php -d memory_limit=2G vendor/bin/phpunit
```

About 670 tests. **14 failures are expected**: they are kept on purpose to track known issues from earlier QA and security audits. The tests use an in-memory SQLite database, so they never touch your MySQL data.

After running the tests, undo the evidence files the suite rewrites (from `CDC-main`):

```bash
git restore CDC/qa/evidence CDC/security/evidence
```

**Frontend checks** (from `CDC/frontend`):

```bash
npm run lint
```

```bash
npm run build
```

---

## 9. Common problems

| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USERNAME`/`DB_PASSWORD` in `backend/.env`. |
| `Unknown database 'iitism_placement'` | Create the database (step 3). |
| Login page spins or says "Invalid credentials" for demo users | Run the demo seeder (step 4.2). Check that `NEXT_PUBLIC_API_URL` ends with `/api` and that the backend is running. |
| `UntrustedHost` or session errors | `NEXTAUTH_URL` must match the address in the browser exactly (`127.0.0.1` vs `localhost`). |
| CORS errors in the browser console | Add the frontend address to `FRONTEND_URLS` in `backend/.env`, then restart `php artisan serve`. |
| Excel import fails for files over 2 MB | Raise `upload_max_filesize`/`post_max_size` in `php.ini` (step 1) and restart PHP. |
| Emails never "sent" in the email log | Start `php artisan queue:work`, or switch Admin → Settings → Mail to "Immediate". |
| Images or logos missing | Run `php artisan storage:link`. |
| `artisan test` runs out of memory | Use `php -d memory_limit=2G vendor/bin/phpunit` instead. |

---

## 10. Rules for working on the code

- **Never commit** `backend/.env`, `frontend/.env.local`, the `videos/` folder or any `.mp4`/`.mov` file. They are already in `.gitignore`.
- **Keep `MAIL_MAILER=log`** on any test laptop.
- **Project context:** read `CDC_PORTAL_CONTEXT.md` (repo root) for the full product, its rules and the code map.
- **Decisions:** read `CDC/PHASE2_DECISIONS.md` for every design decision.
