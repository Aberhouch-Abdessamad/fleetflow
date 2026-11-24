# API reference: v1

Base URL: `/api/v1`. All responses use one envelope:

```json
{ "success": true,  "message": "…", "data": { }, "meta": { "pagination": { } } }
{ "success": false, "message": "…", "code": "validation_failed", "errors": { "field": ["…"] } }
```

| Code | HTTP | Meaning |
| --- | --- | --- |
| `validation_failed` | 422 | Field errors in `errors` |
| `unauthenticated` | 401 | No valid session or token |
| `forbidden` | 403 | Missing permission |
| `tenant_missing` | 403 | User has no company yet. Go to onboarding |
| `tenant_forbidden` | 403 | `X-Company` is not one of the user's companies |
| `tenant_suspended` / `tenant_pending` | 423 | Workspace is not operational |
| `account_suspended` | 403 | User account is suspended |
| `not_found` | 404 | Not found |
| `rate_limited` | 429 | Too many requests |

**Authentication:** the SPA calls `GET /sanctum/csrf-cookie` once, then uses cookies. Other clients send `device_name` on login and use `Authorization: Bearer <token>`.

**Tenant selection:** add the optional header `X-Company: <company public id>`.

---

### `GET /health` (public)
Real DB round-trip. Returns `{ app, api_version, environment, time, database:{ok,driver,version,latency_ms}, maps_provider, broadcasting }`, with 503 if the DB is down.

### `POST /auth/register` (guest · throttle:auth)
`{ name, email, phone?, password, password_confirmation }` → **201** `User`. Starts a session for SPA requests.

### `POST /auth/login` (guest · throttle:auth)
`{ email, password, device_name? }` → `{ user, token? }`. `token` is present only for token clients (when `device_name` is sent).

### `POST /auth/logout` (auth)
Revokes the current token and/or invalidates the session.

### `GET /auth/me` (auth · tenant optional)
```json
{ "id": 2, "name": "…", "email": "…", "email_verified": true, "is_super_admin": false, "status": "active",
  "current_company": { "id": "01…", "name": "…", "status": "active", "timezone": "…", "currency": "MAD" },
  "roles": ["company_admin"], "permissions": ["vehicles.manage", "…"],
  "companies": [{ "id": "01…", "name": "…", "status": "active" }] }
```
`roles` and `permissions` apply to the current company only.

### `POST /companies` (auth)
Onboarding. `{ name, contact_email, country(ISO-2), city, timezone, currency(ISO-3), phone?, address?, registration_number? }` → **201** `Company`. The caller becomes Company Admin and the workspace becomes their current one. `status`, `owner_id` and `company_id` are never accepted.

### `POST /companies/{publicId}/switch` (auth)
Sets the default workspace. Returns 404 unless the user is an active member.

### `GET /companies/current` (auth · tenant)
### `PUT /companies/current` (auth · tenant · `company.update`)
Partial update with the same fields as create.
