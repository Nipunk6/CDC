# CDC Portal API Reference (Summary)

Base URL (local): `http://localhost:8000/api`

## Authentication

### POST /auth/login
- Authenticates a user and returns token/session payload.

### POST /auth/forgot-password
- Sends a password reset link to the user email via Laravel SMTP (Symfony Mailer transport).
- Request body:
```json
{
	"email": "user@example.com"
}
```
- Success response always avoids account enumeration:
```json
{
	"message": "If the account exists, a password reset link has been sent."
}
```

### POST /auth/reset-password
- Resets password using token from reset email.
- Request body:
```json
{
	"token": "reset-token-from-email",
	"email": "user@example.com",
	"password": "NewStrongPass123",
	"password_confirmation": "NewStrongPass123"
}
```
- Success response:
```json
{
	"message": "Password reset successful. You can now sign in with your new password."
}
```

### POST /auth/admin/register
- Registers an admin user.

### POST /auth/company/register
- Registers a company account.

### POST /auth/logout
- Requires `auth:sanctum`.

### GET /auth/user
- Requires `auth:sanctum`.
- Returns authenticated user profile.

## Notifications (Authenticated)

### GET /auth/notifications
- Requires `auth:sanctum`.
- Returns notifications for current user.

### PATCH /auth/notifications/{notification}/read
- Requires `auth:sanctum`.
- Marks a single notification as read.

### PATCH /auth/notifications/read-all
- Requires `auth:sanctum`.
- Marks all user notifications as read.

## Admin APIs (auth:sanctum + role:admin)

### GET /admin/ping
- Role access check.

### GET /admin/dashboard
- Admin dashboard metrics.

### JNF Review
- GET /admin/jnfs
- GET /admin/jnfs/{jnf}
- PATCH /admin/jnfs/{jnf}/status

### INF Review
- GET /admin/infs
- GET /admin/infs/{inf}
- PATCH /admin/infs/{inf}/status

### Company Management
- GET /admin/companies
- GET /admin/companies/{company}
- PUT /admin/companies/{company}

## Company APIs (auth:sanctum + role:company)

### GET /company/ping
- Role access check.

### GET /company/dashboard
- Company dashboard metrics.

### Profile
- GET /company/profile
- PUT /company/profile

### Uploads
- POST /company/uploads

### JNF
- POST /company/jnfs/autosave
- Resource routes under /company/jnfs

### INF
- POST /company/infs/autosave
- Resource routes under /company/infs

## Notes
- Most routes return JSON.
- Non-2xx responses include an error message when available.
- API routes are rate-limited via `throttle:api`.
