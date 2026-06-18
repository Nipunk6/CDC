# Production Environment Setup Checklist

Use this checklist before a production release.

## 1. Infrastructure

- [ ] Provision production MySQL/MariaDB database.
- [ ] Provision backend runtime (PHP 8.2+, Composer dependencies).
- [ ] Provision frontend runtime (Node 20+ for Next.js build/start).
- [ ] Configure process manager for backend and frontend services.
- [ ] Configure reverse proxy and TLS certificates.

## 2. Environment Variables

### Backend (`backend/.env`)

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` points to production API domain.
- [ ] `DB_CONNECTION=mysql`
- [ ] `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- [ ] `SESSION_DRIVER=database` (or production-appropriate)
- [ ] `CACHE_STORE` and `QUEUE_CONNECTION` configured for production.
- [ ] Mail transport variables configured (`MAIL_*`).
- [ ] Sanctum/cookie domains configured if using browser sessions.

### Frontend (`frontend/.env.local` or deployment env)

- [ ] `NEXT_PUBLIC_API_URL` points to production backend.
- [ ] `NEXTAUTH_URL` points to production frontend domain.
- [ ] `NEXTAUTH_SECRET` is set to a strong random value.

## 3. Security

- [ ] Enforce HTTPS and HSTS at proxy/CDN.
- [ ] Confirm frontend security headers are enabled.
- [ ] Confirm API rate limiting is enabled (`throttle:api`).
- [ ] Rotate default/local secrets before release.
- [ ] Validate file upload limits and allowed MIME types.

## 4. Database and App Preparation

Run from `backend/`:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- [ ] Apply all migrations successfully in production.
- [ ] Verify new performance indexes exist.
- [ ] Run seeders only if required for production baseline data.

## 5. Build and Verification

Run from repo root:

```bash
npm run backend:test
npm run frontend:lint
npm run frontend:build
```

Optional pre-release browser checks:

```bash
npm run frontend:test:e2e
npm run frontend:test:perf
```

- [ ] Backend tests pass.
- [ ] Frontend lint passes.
- [ ] Frontend production build passes.
- [ ] E2E smoke/perf checks pass or accepted with known exceptions.

## 6. Observability and Ops

- [ ] Centralized logs configured (backend + proxy + frontend).
- [ ] Error monitoring/alerting configured.
- [ ] Backup strategy for DB and uploaded files verified.
- [ ] Rollback procedure documented and tested.

## 7. Final Launch Gate

- [ ] Admin login + dashboard sanity check.
- [ ] Company login + JNF/INF submit sanity check.
- [ ] Status update workflow sanity check (admin -> company notification).
- [ ] Email delivery sanity check.
- [ ] Launch approval recorded.
