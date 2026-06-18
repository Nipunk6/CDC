# IIT ISM CDC Placement Portal - Complete Setup Guide

## 📋 Prerequisites Installation

### Step 1: Install XAMPP (PHP 8.2 + MySQL)
1. Download XAMPP from: https://www.apachefriends.org/download.html
2. Choose **PHP 8.2** version for macOS
3. Install XAMPP to `/Applications/XAMPP`
4. Start XAMPP Control Panel
5. Start Apache and MySQL services

### Step 2: Install Composer (PHP Dependency Manager)
```bash
# Download and install Composer globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify installation
composer --version
```

### Step 3: Install Node.js and NPM
1. Download Node.js LTS from: https://nodejs.org/
2. Install the package
3. Verify installation:
```bash
node --version  # Should be v18 or higher
npm --version
```

### Step 3.1: Install Ollama (Required for AI PDF Extraction)
```bash
# Install Ollama (macOS)
brew install ollama

# Verify installation
ollama --version
```

### Step 4: Install Git (if not already installed)
```bash
# Check if Git is installed
git --version

# If not installed, download from: https://git-scm.com/download/mac
# Or install via Homebrew:
brew install git
```

### Step 5: Install HeidiSQL Alternative (for macOS)
Since HeidiSQL is Windows-only, use one of these alternatives:
- **Sequel Ace** (Free): https://sequel-ace.com/
- **TablePlus** (Free tier): https://tableplus.com/
- **MySQL Workbench**: https://dev.mysql.com/downloads/workbench/
- **DBeaver**: https://dbeaver.io/

---

## 🚀 Project Setup

### Phase 1: Backend Setup (Laravel)

#### Step 1.1: Create Laravel Project
```bash
cd /path/to/CDC-main

# Create Laravel project in backend folder
composer create-project laravel/laravel backend

# Navigate to backend
cd backend
```

#### Step 1.2: Configure Database
1. Start XAMPP MySQL
2. Open phpMyAdmin: http://localhost/phpmyadmin
3. Create new database: `iitism_placement`

Or via command line:
```bash
# Add XAMPP MySQL to PATH temporarily
export PATH="/Applications/XAMPP/bin:$PATH"

# Create database
mysql -u root -e "CREATE DATABASE iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### Step 1.3: Configure Laravel Environment
```bash
cd backend
cp .env.example .env

# Edit .env file with these settings:
# APP_NAME="IIT ISM Placement Portal"
# APP_URL=http://localhost:8000
# 
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=iitism_placement
# DB_USERNAME=root
# DB_PASSWORD=
```

#### Step 1.4: Generate Application Key
```bash
php artisan key:generate
```

#### Step 1.5: Install Laravel Sanctum
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

#### Step 1.6: Test Backend
```bash
# Start Laravel development server
php artisan serve

# Should be running on: http://localhost:8000
```

---

### Phase 2: Frontend Setup (Next.js)

#### Step 2.1: Create Next.js Project
```bash
cd /path/to/CDC-main

# Create Next.js project with TypeScript
npx create-next-app@latest frontend --typescript --app --eslint --tailwind --src-dir=false --import-alias="@/*"

# Navigate to frontend
cd frontend
```

During setup, choose:
- ✅ TypeScript: Yes
- ✅ ESLint: Yes
- ✅ Tailwind CSS: Yes (we'll also use MUI)
- ✅ `src/` directory: No
- ✅ App Router: Yes
- ✅ Import alias: @/*

#### Step 2.2: Install MUI (Material-UI) v6.5
```bash
cd frontend

# Install MUI core and icons
npm install @mui/material@^6.5.0 @emotion/react @emotion/styled
npm install @mui/icons-material@^6.5.0

# Install MUI X components (DataGrid, etc.)
npm install @mui/x-data-grid@^7.0.0
```

#### Step 2.3: Install Additional Dependencies
```bash
# Authentication
npm install next-auth@beta

# HTTP client
npm install axios

# Form handling and validation
npm install react-hook-form yup @hookform/resolvers

# Date handling
npm install date-fns
```

#### Step 2.4: Configure Frontend Environment
```bash
cd frontend

# Create .env.local manually (if not present)
cat > .env.local << 'EOF'
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
NEXTAUTH_URL=http://127.0.0.1:3000
NEXTAUTH_SECRET=dev-secret-change-me-please-1234567890
EOF
```

Generate NEXTAUTH_SECRET:
```bash
openssl rand -base64 32
```

#### Step 2.5: Test Frontend
```bash
npm run dev

# Should be running on: http://localhost:3000
```

---

### Phase 3: AI Extraction Setup (Ollama + PDF Parser)

The JD upload and auto-fill flow calls `POST /api/ml/extract-pdf` from the frontend uploader. This endpoint now runs a job-first extraction pipeline using Ollama and prompt files from `ml/prompts/`.

#### Step 3.1: Pull Required Ollama Model
```bash
# Start Ollama service (foreground)
ollama serve

# In a new terminal, pull the required model
ollama pull qwen2.5:7b

# Verify model is installed
ollama list
```

Required model:
- `qwen2.5:7b`

#### Step 3.2: Verify Backend AI Dependencies
The backend already uses `smalot/pdfparser` for text extraction.

```bash
cd backend
composer install
```

If you ever need to add it manually:
```bash
composer require smalot/pdfparser
```

#### Step 3.3: Confirm Backend Environment for Frontend Access
In `backend/.env`, ensure:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:3000
FRONTEND_URLS=http://127.0.0.1:3000,http://localhost:3000
```

#### Step 3.4: Run All Services Together
Open separate terminals and run:

```bash
# Terminal 1 - Backend API
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 - Frontend
cd frontend
npm run dev -- --hostname 127.0.0.1 --port 3000

# Terminal 3 - Ollama server
ollama serve
```

#### Step 3.5: Quick AI Health Check
1. Login as company user.
2. Open JNF/INF form.
3. Upload a text-based JD PDF (not scanned image).
4. Confirm extraction starts and section-wise form fields are auto-filled.

Expected behavior with current pipeline:
- Focus is on job details + selection process extraction.
- Company extraction is intentionally de-prioritized.
- If PDF is scanned image text, API returns a scanned-PDF message.
- You may still need manual review/corrections before submit.

---

## 🗂️ Project Structure After Setup

```
CDC-main/
├── backend/                    # Laravel 11
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── tests/
│   ├── .env
│   ├── artisan
│   ├── composer.json
│   └── composer.lock
│
├── frontend/                   # Next.js 15
│   ├── app/
│   ├── components/
│   ├── lib/
│   ├── public/
│   ├── types/
│   ├── .env.local
│   ├── next.config.ts
│   ├── package.json
│   ├── tsconfig.json
│   └── tailwind.config.ts
│
├── docs/
│   ├── IMPLEMENTATION.md
│   ├── API.md
│   └── USER_GUIDE.md
│
├── .git/
├── .gitignore
├── README.md
└── SETUP_GUIDE.md
```

---

## 🔧 Configure Git

### Create .gitignore
```bash
cd /path/to/CDC-main

# Root .gitignore
cat > .gitignore << 'EOF'
# IDEs
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Logs
*.log
logs/

# Environment files
.env
.env.local
.env.*.local

# Node modules
node_modules/

# Build outputs
dist/
build/
.next/
EOF
```

### Backend .gitignore
Laravel already includes a comprehensive .gitignore, but verify it includes:
```
/vendor
/node_modules
.env
.env.backup
```

### Frontend .gitignore
Next.js already includes a comprehensive .gitignore, but verify it includes:
```
/node_modules
/.next
/out
.env*.local
```

### Initial Git Commit
```bash
cd /path/to/CDC-main

git add .
git commit -m "Initial commit: Project structure with Laravel backend and Next.js frontend"
git push origin main
```

---

## ✅ Verification Checklist

### Backend
- [ ] PHP 8.2 installed
- [ ] Composer installed
- [ ] Laravel project created in `backend/`
- [ ] Database `iitism_placement` created
- [ ] `.env` configured
- [ ] Laravel Sanctum installed
- [ ] `php artisan serve` works (http://localhost:8000)

### Frontend
- [ ] Node.js 18+ installed
- [ ] Next.js project created in `frontend/`
- [ ] MUI v6.5 installed
- [ ] Additional dependencies installed
- [ ] `.env.local` configured
- [ ] `npm run dev` works (http://localhost:3000)

### AI Extraction
- [ ] Ollama installed
- [ ] `qwen2.5:7b` model pulled
- [ ] `ollama serve` running on `127.0.0.1:11434`
- [ ] Backend dependency `smalot/pdfparser` installed
- [ ] JD upload auto-fill works from company JNF/INF forms

### Recent Platform Changes (Important)
- [ ] Admin CSV download works for accepted JNF/INF in queue and detail pages
- [ ] Admin detail pages show structured preview only (raw JSON hidden)
- [ ] AI extraction focuses on job details + selection process
- [ ] AI-generated form keeps uploaded JD/ID metadata (no forced re-upload)

### Git
- [ ] Repository initialized
- [ ] .gitignore files created
- [ ] Initial commit made
- [ ] Pushed to GitHub

---

## 🚨 Troubleshooting

### Issue: "composer: command not found"
```bash
# Reinstall Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### Issue: "php: command not found"
```bash
# Add XAMPP PHP to PATH
export PATH="/Applications/XAMPP/bin:$PATH"

# Add to ~/.zshrc or ~/.bash_profile for permanent access
echo 'export PATH="/Applications/XAMPP/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

### Issue: MySQL Connection Failed
1. Ensure XAMPP MySQL is running
2. Check port 3306 is not blocked
3. Verify credentials in `.env` file
4. Try connecting via phpMyAdmin first

### Issue: Port 3000 or 8000 Already in Use
```bash
# For frontend (Next.js)
npm run dev -- -p 3001

# For backend (Laravel)
php artisan serve --port=8001
```

### Issue: AI extraction fails with "AI could not extract any information"
1. Check Ollama server is running: `ollama serve`
2. Check model exists: `ollama list` (must include `qwen2.5:7b`)
3. Confirm backend can reach Ollama at `http://127.0.0.1:11434`
4. Use a text-based PDF (scanned image PDFs are rejected)
5. Confirm prompt files exist under `ml/prompts/`

### Issue: AI extraction is very slow
1. First model run is usually slower (warm-up cost)
2. Keep Ollama service running in background
3. Close heavy apps if system RAM is low
4. Retry once; second run is usually faster

---

## 📞 Next Steps After Setup

Once all prerequisites are installed and verified:

1. **Proceed to Phase 2**: Authentication System Setup
2. **Review**: `/docs/IMPLEMENTATION.md` for detailed implementation steps
3. **Track Progress**: Use the SQL database to track completed steps

---

## 💡 Useful Commands

### Backend (Laravel)
```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Create a new controller
php artisan make:controller ControllerName

# Create a new model with migration
php artisan make:model ModelName -m

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run seeders
php artisan db:seed

# Create a new seeder
php artisan make:seeder SeederName
```

### Frontend (Next.js)
```bash
# Development server
npm run dev

# Production build
npm run build

# Start production server
npm run start

# Lint code
npm run lint
```

### Git
```bash
# Check status
git status

# Create new branch
git checkout -b feature-name

# Commit changes
git add .
git commit -m "Description of changes"

# Push changes
git push origin branch-name

# Pull latest changes
git pull origin main
```

---

## 🎯 Ready to Code!

Once everything is installed and verified, you're ready to start implementing the features!

**Next**: Proceed with Phase 2 - Authentication System (Steps 11-20)

Good luck! 🚀
