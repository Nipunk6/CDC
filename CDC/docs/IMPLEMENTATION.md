# Implementation Roadmap - IIT ISM CDC Placement Portal

## 📊 Progress Overview

**Total Steps**: 100  
**Current Phase**: Phase 1 - Project Setup  
**Completed**: 0/100 (0%)

---

## Phase 1: Project Setup & Configuration ✅ IN PROGRESS

**Steps 1-10** | **Status**: In Progress

### ✅ Step 1: Initialize Backend Project
**Status**: In Progress  
**Task**: Create Laravel 11 project with Composer (PHP 8.2)

```bash
cd /Users/nipunkansal/Coding/cdc-placement-portal
composer create-project laravel/laravel backend
cd backend
php artisan --version
```

### ⏳ Step 2: Initialize Frontend Project
**Status**: Pending  
**Task**: Create Next.js 15 with TypeScript and MUI v6.5

```bash
npx create-next-app@latest frontend --typescript --app
cd frontend
npm install @mui/material@^6.5.0 @emotion/react @emotion/styled
```

### ⏳ Step 3: Setup Database
**Status**: Pending  
**Task**: Create MySQL database in XAMPP

```sql
CREATE DATABASE iitism_placement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### ⏳ Step 4: Configure Laravel Environment
**Status**: Pending  
**Task**: Configure .env file

```env
APP_NAME="IIT ISM Placement Portal"
DB_DATABASE=iitism_placement
DB_USERNAME=root
DB_PASSWORD=
```

### ⏳ Step 5: Setup Git Repository
**Status**: Pending  
**Task**: Initialize Git and create .gitignore

```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/Nipunk6/CDC.git
git push -u origin main
```

### ⏳ Step 6: Install Frontend Dependencies
**Status**: Pending  
**Task**: Install NextAuth, axios, react-hook-form, yup

```bash
npm install next-auth@beta axios react-hook-form yup @hookform/resolvers
npm install @mui/x-data-grid
```

### ⏳ Step 7: Install Backend Dependencies
**Status**: Pending  
**Task**: Install Laravel Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### ⏳ Step 8: Configure CORS
**Status**: Pending  
**Task**: Configure Laravel CORS

Edit `config/cors.php`:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:3000'],
'supports_credentials' => true,
```

### ⏳ Step 9: Setup MUI Theme
**Status**: Pending  
**Task**: Create custom theme with IIT ISM colors

Create `frontend/lib/theme.ts` with primary blue and secondary orange colors.

### ⏳ Step 10: Create Project Documentation
**Status**: Pending  
**Task**: Create README files

---

## ✅ Checkpoint 1: Verify Phase 1

Before proceeding to Phase 2, verify:

- [ ] Laravel project runs: `http://localhost:8000`
- [ ] Next.js project runs: `http://localhost:3000`
- [ ] Database created and accessible
- [ ] Git repository initialized and pushed to GitHub
- [ ] All dependencies installed
- [ ] CORS configured properly
- [ ] MUI theme working

---

## Phase 2: Authentication System ⏳ PENDING

**Steps 11-20** | **Status**: Pending

### Step 11: Create User Migration
Create users table with role enum (admin/company)

### Step 12: Create Company Migration
Create companies table with HR details

### Step 13: Setup Laravel Sanctum
Configure Sanctum for SPA authentication

### Step 14: Create Auth Controllers
AuthController and CompanyAuthController

### Step 15: Create Auth API Routes
Routes for register, login, logout, user

### Step 16: Create User & Company Models
Models with relationships

### Step 17: Setup NextAuth.js
Configure NextAuth with Credentials provider

### Step 18: Create Login Page UI
Professional login page with MUI

### Step 19: Create Company Registration Page
Registration form with validation

### Step 20: Implement Role-Based Middleware
Middleware for admin/company routes

---

## Phase 3: Database & Models ⏳ PENDING

**Steps 21-30** | **Status**: Pending

Detailed steps will be shown when Phase 2 is complete.

---

## Phase 4: Company Portal - Backend ⏳ PENDING

**Steps 31-40** | **Status**: Pending

---

## Phase 5: Company Portal - Frontend ⏳ PENDING

**Steps 41-50** | **Status**: Pending

---

## Phase 6: Admin Portal - Backend ⏳ PENDING

**Steps 51-60** | **Status**: Pending

---

## Phase 7: Admin Portal - Frontend ⏳ PENDING

**Steps 61-70** | **Status**: Pending

---

## Phase 8: Email & Notifications ⏳ PENDING

**Steps 71-80** | **Status**: Pending

---

## Phase 9: Testing & Refinement ⏳ PENDING

**Steps 81-90** | **Status**: Pending

---

## Phase 10: Final Polish & Deployment ⏳ PENDING

**Steps 91-100** | **Status**: Pending

---

## 📈 Progress Tracking

Track your progress using SQL queries:

```sql
-- View all pending tasks
SELECT * FROM todos WHERE status = 'pending' ORDER BY phase, id;

-- View current phase
SELECT * FROM todos WHERE phase = 1 ORDER BY id;

-- Mark step as done
UPDATE todos SET status = 'done', updated_at = CURRENT_TIMESTAMP WHERE id = 'step-01';

-- Get summary
SELECT 
    phase,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
FROM todos 
GROUP BY phase
ORDER BY phase;
```

---

## 🚀 Quick Commands Reference

### Backend Commands
```bash
# Start server
php artisan serve

# Run migrations
php artisan migrate

# Create controller
php artisan make:controller ControllerName

# Create model with migration
php artisan make:model ModelName -m
```

### Frontend Commands
```bash
# Start dev server
npm run dev

# Build for production
npm run build

# Run linting
npm run lint
```

---

## 📚 Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Next.js Documentation](https://nextjs.org/docs)
- [MUI Documentation](https://mui.com/material-ui/getting-started/)
- [NextAuth.js Documentation](https://next-auth.js.org/)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)

---

**Last Updated**: Phase 1 In Progress  
**Next Milestone**: Complete Phase 1 and Checkpoint 1
