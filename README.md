# IIT ISM Dhanbad - Career Development Centre (CDC) Placement Portal

A comprehensive placement portal enabling companies to register and submit Job Notification Forms (JNF) and Internship Notification Forms (INF), with an administrative interface for CDC to manage submissions.

## 🎯 Features

### Company Portal
- Company registration with HR details
- Secure authentication system
- Submit and manage JNF (Job Notification Forms)
- Submit and manage INF (Internship Notification Forms)
- Track submission status
- Email notifications

### Admin Portal
- Review and manage JNF/INF submissions
- Accept, reject, or request changes to forms
- Company verification and management
- Dashboard with analytics
- Email notifications for new submissions
- Export reports (CSV/Excel)

## 🛠️ Tech Stack

### Frontend
- **Next.js 16** - React framework with App Router
- **Material-UI (MUI) v6.5** - UI component library
- **NextAuth.js** - Authentication for React
- **TypeScript** - Type-safe JavaScript
- **React Hook Form + Yup** - Form handling and validation
- **Axios** - HTTP client

### Backend
- **Laravel 12** - PHP framework
- **MySQL/MariaDB** - Database
- **Laravel Sanctum** - API authentication
- **PHP 8.2** - Programming language
- **Composer** - Dependency management

### Development Tools
- **XAMPP** - Local PHP and MySQL environment
- **Postman** - API testing
- **Git** - Version control
- **HeidiSQL** - Database management

## 📁 Project Structure

```
cdc-placement-portal/
├── backend/                 # Laravel backend
│   ├── app/
│   ├── database/
│   ├── routes/
│   └── ...
├── frontend/                # Next.js frontend
│   ├── app/
│   ├── components/
│   ├── lib/
│   └── ...
├── docs/                    # Documentation
└── README.md
```

## 🚀 Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js 18+ and NPM
- MySQL 8.0+
- Git

### Backend Setup
1. Navigate to the backend directory and install dependencies:
   ```bash
   cd backend
   composer install
   ```

2. Configure environment variables:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Critical Configuration:** Open the newly created `backend/.env` file and make sure `PHP_CLI_SERVER_WORKERS=4` is uncommented/set. This enables concurrent request processing in the built-in PHP server, preventing timeouts and deadlocks when Next.js makes concurrent background API requests.

4. Set up the database, run migrations, and seed default portal users:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Critical Symbolic Link:** Connect the public storage folder to enable serving uploaded company logos:
   ```bash
   php artisan storage:link
   ```

6. Start the backend development server:
   ```bash
   php artisan serve
   ```

The backend API will be available at `http://localhost:8000`

### Forgot Password E2E (Real Gmail Delivery)
The password reset flow uses Laravel Mail (Symfony Mailer transport) through SMTP.

Update `backend/.env`:
```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail-account@gmail.com
MAIL_PASSWORD=your-16-char-gmail-app-password
MAIL_FROM_ADDRESS=your-gmail-account@gmail.com
MAIL_FROM_NAME="IIT ISM CDC Portal"
FRONTEND_URL=http://127.0.0.1:3000
```

Then run:
```bash
cd backend
php artisan optimize:clear
php artisan serve
```

Trigger from frontend:
- Open `/auth/forgot-password`
- Submit a registered user email
- Open reset link from Gmail and submit new password on `/auth/reset-password`

### Frontend Setup
```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

The frontend will be available at `http://localhost:3000`

## 📚 Documentation

- [Implementation Plan](./docs/IMPLEMENTATION.md)
- [API Documentation](./docs/API.md)
- [User Guide](./docs/USER_GUIDE.md)
- [Developer Guide](./docs/DEVELOPER_GUIDE.md)
- [Production Checklist](./docs/PRODUCTION_CHECKLIST.md)

## 🧰 Root Helper Scripts

From repo root:

```bash
npm run frontend:lint
npm run frontend:build
npm run frontend:test:e2e
npm run frontend:test:perf
npm run backend:test
```

## 🔒 Security

- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF protection
- Rate limiting
- Secure password hashing
- File upload validation
- Role-based access control

## 📧 Contact

For queries related to placements at IIT ISM Dhanbad:
- Website: https://www.iitism.ac.in
- Email: cdc@iitism.ac.in

## 📄 License

This project is developed for IIT ISM Dhanbad Career Development Centre.

## 🤝 Contributing

This is an internal project for IIT ISM Dhanbad. For contributions or issues, please contact the development team.

---

**Developed with ❤️ for IIT ISM Dhanbad**
