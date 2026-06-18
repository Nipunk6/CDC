# CDC Portal Developer Guide

## Architecture
- Backend: Laravel 12 REST API with Sanctum auth
- Frontend: Next.js 16 App Router + NextAuth + MUI
- DB: SQLite for local dev, MySQL-ready config

## Repository Layout
- backend/: Laravel application and API
- frontend/: Next.js application
- docs/: implementation and operations documentation

## Local Development

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Password Reset Mail Setup (Gmail SMTP via Laravel)
The forgot-password flow uses Laravel Mail, which runs on Symfony Mailer internally.
No third-party SMTP provider is required.

1. Configure backend mail settings in `.env`:
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
2. In Gmail account security:
- Enable 2-Step Verification.
- Create an App Password.
- Use the 16-character App Password as `MAIL_PASSWORD`.
3. Clear cached config after updating `.env`:
```bash
php artisan optimize:clear
```
4. Trigger password reset from frontend at `/auth/forgot-password` and confirm mail lands in Gmail inbox/spam.

### Frontend
```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

## Validation Commands

### Backend
```bash
cd backend
php artisan test
composer audit --no-interaction
```

### Frontend
```bash
cd frontend
npm run lint
npm run build
npm run test:e2e:smoke
npm run test:perf -- --project=chromium
npm audit --omit=dev
```

## Auth & Roles
- NextAuth credentials provider handles frontend login flow.
- Backend uses Sanctum bearer tokens.
- Role middleware enforces `admin` and `company` route boundaries.

## API Conventions
- JSON request/response format.
- Proper non-2xx errors with message payload.
- Route groups by domain:
- `/auth/*`
- `/admin/*`
- `/company/*`

## Security Baseline
- Frontend headers configured in `frontend/next.config.ts`.
- API routes have `throttle:api` middleware.
- Dependency audits included in validation flow.

## Testing Strategy
- Laravel feature tests cover auth, role access, workflow transitions, and notifications.
- Playwright smoke tests validate route availability across Chromium/Firefox/WebKit.
- Performance baseline checks navigation timing thresholds on key public routes.

## Deployment Notes
- Build frontend with `npm run build`.
- Use production process manager/server for backend and frontend apps.
- Ensure environment variables are configured for API URL, auth secrets, mail, and DB.
- Run migrations before release.
- Follow the full release gate in `docs/PRODUCTION_CHECKLIST.md`.

## Common Issues
- Root-level `npm run ...` fails: use frontend directory or `npm --prefix /path/to/frontend run <script>`.
- MySQL unavailable locally: use SQLite for development/testing.
- Playwright browser install missing: run `npx playwright install chromium firefox webkit`.
- Forgot-password returns 503: SMTP credentials are invalid or blocked by Gmail security settings.
