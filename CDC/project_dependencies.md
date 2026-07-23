# CDC Placement Portal — Full Setup Guide (Windows & macOS)

---

# 🍎 macOS Setup

## Step 1: Install Homebrew (Package Manager)

If you don't have Homebrew yet:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

After install, follow the on-screen instructions to add Homebrew to your PATH, then verify:

```bash
brew --version
```

---

## Step 2: Install System Prerequisites

### 2.1 — PHP (≥ 8.2)

```bash
brew install php
```

Verify:
```bash
php --version
# Should show PHP 8.2 or higher
```

### 2.2 — Composer (PHP Dependency Manager)

```bash
brew install composer
```

Or manually:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

Verify:
```bash
composer --version
```

### 2.3 — Node.js (≥ 18) & NPM

```bash
brew install node
```

Verify:
```bash
node --version   # Should be v18+
npm --version
```

### 2.4 — MySQL

```bash
brew install mysql
brew services start mysql
```

> [!TIP]
> By default, MySQL installs with user `root` and **no password**. If you need to set a password, run `mysql_secure_installation`.

Verify:
```bash
mysql --version
mysql -u root -e "SELECT 1;"   # Should return without error
```

### 2.5 — Git

```bash
# Usually pre-installed on macOS. If not:
brew install git
```

Verify:
```bash
git --version
```

---

## Step 3: Install All Prerequisites in One Command (macOS)

```bash
brew install php composer node mysql git && brew services start mysql
```

---

## Step 4: Clone & Setup the Project (macOS)

```bash
# Clone the repo
git clone https://github.com/Nipunk6/CDC ~/Desktop/CDC-main

# --- Backend Setup ---
cd ~/Desktop/CDC-main/CDC/backend
composer install
cp .env.example .env
php artisan key:generate

# Create database & run migrations
mysql -u root -e "CREATE DATABASE IF NOT EXISTS iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed
php artisan storage:link

# --- Frontend Setup ---
cd ~/Desktop/CDC-main/CDC/frontend
npm install
```

### One-Liner Version (macOS)

```bash
cd ~/Desktop/CDC-main/CDC/backend && composer install && cp -n .env.example .env; php artisan key:generate && mysql -u root -e "CREATE DATABASE IF NOT EXISTS iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && php artisan migrate && php artisan db:seed && php artisan storage:link && cd ~/Desktop/CDC-main/CDC/frontend && npm install && echo "✅ Setup complete!"
```

---

## Step 5: Run the Project (macOS)

Open **two terminal tabs/windows**:

**Terminal 1 — Backend API:**
```bash
cd ~/Desktop/CDC-main/CDC/backend && php artisan serve
# Runs on http://127.0.0.1:8000
```

**Terminal 2 — Frontend:**
```bash
cd ~/Desktop/CDC-main/CDC/frontend && npm run dev
# Runs on http://localhost:3000
```

---
---

# 🪟 Windows Setup

## Step 1: Install System Prerequisites

### 1.1 — PHP (≥ 8.2)

**Option A: Via XAMPP (Recommended — includes MySQL)**

1. Download XAMPP from https://www.apachefriends.org/download.html
2. Choose the **PHP 8.2+** version
3. Install to `C:\xampp`
4. Add PHP to your system PATH:
   - Open **Settings → System → About → Advanced system settings → Environment Variables**
   - Under **System Variables**, find `Path` → Edit → Add: `C:\xampp\php`
5. Open a **new** Command Prompt and verify:

```cmd
php --version
```

**Option B: Standalone PHP**

1. Download PHP from https://windows.php.net/download/
2. Choose **VS16 x64 Thread Safe** zip
3. Extract to `C:\php`
4. Copy `php.ini-development` to `php.ini`
5. In `php.ini`, uncomment these extensions (remove the `;`):
   ```ini
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=mysqli
   extension=openssl
   extension=pdo_mysql
   extension=zip
   ```
6. Add `C:\php` to your system PATH

---

### 1.2 — Composer

1. Download the installer from https://getcomposer.org/Composer-Setup.exe
2. Run the installer — it will auto-detect your PHP installation
3. Verify in a **new** Command Prompt:

```cmd
composer --version
```

---

### 1.3 — Node.js (≥ 18) & NPM

1. Download the **LTS** installer from https://nodejs.org/
2. Run the installer (includes NPM)
3. Verify in a **new** Command Prompt:

```cmd
node --version
npm --version
```

---

### 1.4 — MySQL

**If you installed XAMPP:** MySQL is already included. Start it from the XAMPP Control Panel.

**Standalone MySQL:**

1. Download from https://dev.mysql.com/downloads/installer/
2. Choose **MySQL Community Server**
3. During setup, set root password (or leave blank for local dev)
4. Add `C:\Program Files\MySQL\MySQL Server 8.0\bin` to your system PATH
5. Verify:

```cmd
mysql --version
```

---

### 1.5 — Git

1. Download from https://git-scm.com/download/win
2. Run the installer (use default settings)
3. Verify in a **new** Command Prompt:

```cmd
git --version
```

---

### 1.6 — GUI Database Client (Optional)

| Tool | Platform | Link |
|------|----------|------|
| HeidiSQL | Windows | https://www.heidisql.com/ |
| TablePlus | Windows/Mac | https://tableplus.com/ |
| DBeaver | All | https://dbeaver.io/ |
| MySQL Workbench | All | https://dev.mysql.com/downloads/workbench/ |

---

## Step 2: Clone & Setup the Project (Windows)

Open **Command Prompt** or **PowerShell**:

```cmd
:: Clone the repo
git clone https://github.com/Nipunk6/CDC %USERPROFILE%\Desktop\CDC-main

:: --- Backend Setup ---
cd %USERPROFILE%\Desktop\CDC-main\CDC\backend
composer install
copy .env.example .env
php artisan key:generate

:: Create database (adjust -u root -p if you set a password)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed
php artisan storage:link

:: --- Frontend Setup ---
cd %USERPROFILE%\Desktop\CDC-main\CDC\frontend
npm install
```

### One-Liner Version (Windows CMD)

```cmd
cd %USERPROFILE%\Desktop\CDC-main\CDC\backend && composer install && copy .env.example .env && php artisan key:generate && mysql -u root -e "CREATE DATABASE IF NOT EXISTS iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && php artisan migrate && php artisan db:seed && php artisan storage:link && cd %USERPROFILE%\Desktop\CDC-main\CDC\frontend && npm install && echo ✅ Setup complete!
```

### One-Liner Version (Windows PowerShell)

```powershell
cd "$env:USERPROFILE\Desktop\CDC-main\CDC\backend"; composer install; if (!(Test-Path .env)) { Copy-Item .env.example .env }; php artisan key:generate; mysql -u root -e "CREATE DATABASE IF NOT EXISTS iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; php artisan migrate; php artisan db:seed; php artisan storage:link; cd "$env:USERPROFILE\Desktop\CDC-main\CDC\frontend"; npm install; Write-Host "✅ Setup complete!"
```

---

## Step 3: Run the Project (Windows)

Open **two** Command Prompt / PowerShell windows:

**Terminal 1 — Backend API:**
```cmd
cd %USERPROFILE%\Desktop\CDC-main\CDC\backend
php artisan serve
:: Runs on http://127.0.0.1:8000
```

**Terminal 2 — Frontend:**
```cmd
cd %USERPROFILE%\Desktop\CDC-main\CDC\frontend
npm run dev
:: Runs on http://localhost:3000
```

---
---

# 📦 All Package Dependencies

## Backend (PHP / Composer)

These are automatically installed by `composer install`:

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^12.0 | Core Laravel framework |
| `laravel/sanctum` | ^4.3 | API token authentication |
| `laravel/tinker` | ^2.10.1 | REPL / debugging console |
| `smalot/pdfparser` | ^2.12 | PDF text extraction |
| `fakerphp/faker` | ^1.23 | Test data generation (dev) |
| `laravel/pail` | ^1.2.2 | Real-time log viewer (dev) |
| `laravel/pint` | ^1.24 | Code formatter (dev) |
| `laravel/sail` | ^1.41 | Docker dev environment (dev) |
| `mockery/mockery` | ^1.6 | Test mocking (dev) |
| `nunomaduro/collision` | ^8.6 | Error reporting (dev) |
| `phpunit/phpunit` | ^11.5.50 | Testing framework (dev) |

> [!NOTE]
> You do NOT need to install these individually. `composer install` reads `composer.json` and installs everything into the `vendor/` folder.

---

## Frontend (Node.js / NPM)

These are automatically installed by `npm install`:

| Package | Version | Purpose |
|---------|---------|---------|
| `next` | 16.2.1 | React framework (SSR, routing) |
| `react` | 19.2.4 | UI component library |
| `react-dom` | 19.2.4 | React DOM rendering |
| `@mui/material` | ^6.5.0 | Material UI components |
| `@mui/icons-material` | ^6.5.0 | Material UI icon set |
| `@mui/x-data-grid` | ^8.28.1 | Advanced data tables |
| `@emotion/react` | ^11.14.0 | CSS-in-JS engine (MUI peer dep) |
| `@emotion/styled` | ^11.14.1 | Styled components (MUI peer dep) |
| `@emotion/cache` | ^11.14.0 | CSS cache (MUI peer dep) |
| `next-auth` | ^5.0.0-beta.30 | Authentication (NextAuth v5) |
| `axios` | ^1.14.0 | HTTP client for API calls |
| `react-hook-form` | ^7.72.0 | Performant form handling |
| `@hookform/resolvers` | ^5.2.2 | Validation resolver for forms |
| `yup` | ^1.7.1 | Schema-based validation |
| `date-fns` | ^4.1.0 | Date utility functions |
| `@hello-pangea/dnd` | ^18.0.1 | Drag and drop |
| `libphonenumber-js` | ^1.12.42 | Phone number validation |
| `react-pdf` | ^10.4.1 | PDF viewer in browser |
| `react-quill-new` | ^3.8.3 | Rich text (WYSIWYG) editor |
| `typescript` | ^5 | TypeScript compiler (dev) |
| `tailwindcss` | ^4 | Utility-first CSS (dev) |
| `@tailwindcss/postcss` | ^4 | Tailwind PostCSS plugin (dev) |
| `eslint` | ^9 | JavaScript linter (dev) |
| `eslint-config-next` | 16.2.1 | Next.js ESLint rules (dev) |
| `@playwright/test` | ^1.53.2 | E2E browser testing (dev) |
| `@types/node` | ^20 | Node.js type definitions (dev) |
| `@types/react` | ^19 | React type definitions (dev) |
| `@types/react-dom` | ^19 | React DOM type defs (dev) |

> [!NOTE]
> You do NOT need to install these individually. `npm install` reads `package.json` and installs everything into the `node_modules/` folder.

---

# 🧩 Environment Configuration

## Backend — `.env`

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_URL` | `http://127.0.0.1:8000` | Backend server URL |
| `FRONTEND_URL` | `http://127.0.0.1:3000` | CORS origin |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `iitism_placement` | |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | *(blank or your password)* | XAMPP default is blank |
| `MAIL_MAILER` | `smtp` | |
| `MAIL_HOST` | `smtp.gmail.com` | |
| `MAIL_PORT` | `587` | |

## Frontend — `.env.local`

| Variable | Value |
|----------|-------|
| `NEXT_PUBLIC_API_URL` | `http://127.0.0.1:8000/api` |
| `NEXTAUTH_URL` | `http://127.0.0.1:3000` |
| `NEXTAUTH_SECRET` | Generate with `openssl rand -base64 32` |

---

# ✅ Verification Checklist

| Check | Command |
|-------|---------|
| PHP installed | `php --version` (≥ 8.2) |
| Composer installed | `composer --version` |
| Node.js installed | `node --version` (≥ 18) |
| NPM installed | `npm --version` |
| MySQL running | `mysql -u root -e "SELECT 1;"` |
| Git installed | `git --version` |
| Backend deps installed | `ls vendor/autoload.php` (exists) |
| Frontend deps installed | `ls node_modules/.package-lock.json` (exists) |
| Backend serves | `php artisan serve` → http://127.0.0.1:8000 |
| Frontend serves | `npm run dev` → http://localhost:3000 |
