# FleetFlow

**Multi-tenant fleet & delivery management SaaS.** Companies manage vehicles, drivers, deliveries, dispatch, GPS tracking, maintenance, fuel, expenses and invoicing, each in its own isolated workspace.

> **Status: Phase 1 (Foundation) is complete and verified.** Modules from later phases appear in the sidebar as locked items labelled with their phase (P4, P6, …). Nothing on screen is mock data.

| Layer | Stack |
| --- | --- |
| API | Laravel 12.73 · PHP 8.2+ (8.3 recommended) · MySQL 8 / MariaDB 10.6+ · Sanctum 4.5 · Spatie Permission 6.25 (teams mode) · PHPUnit 11 |
| SPA | React 19 · TypeScript · Vite 8 · Tailwind CSS 4 · shadcn-style UI kit (Radix primitives + CVA) · React Router 7 · Axios · Zustand 5 · React Hook Form + Zod 4 · Recharts · Lucide · Sonner · Vitest |
| Planned | Laravel Reverb + Echo (Phase 8) · Mapbox or Google Maps behind a provider interface (Phases 7–8) |

---

## Repository layout

```
fleetflow/
├── backend/                         Laravel API, served at /api/v1
│   ├── app/
│   │   ├── Enums/                   CompanyStatus, CompanyRole, UserStatus, MembershipStatus
│   │   ├── Exceptions/              ApiExceptionRenderer (uniform JSON errors), DomainException
│   │   ├── Http/Controllers/Api/V1/ Auth, Company, System (thin controllers)
│   │   ├── Http/Middleware/         ResolveTenant, EnsureUserIsActive, EnsureSuperAdmin
│   │   ├── Http/Requests/Api/V1/    Form Request validation
│   │   ├── Http/Resources/          API Resources (never expose internal ids)
│   │   ├── Models/ + Concerns/      BelongsToCompany tenant trait
│   │   ├── Services/                Auth, Tenant (business logic + transactions), ActivityLogger
│   │   └── Support/                 ApiResponse envelope, RoleRegistry, Tenancy/TenantContext
│   ├── config/fleetflow.php         Role → permission matrix, tracking and maps settings
│   ├── database/{migrations,factories,seeders}
│   └── tests/                       25 feature tests (auth, onboarding, tenant isolation)
├── frontend/                        React SPA
│   └── src/
│       ├── app/                     App, router (lazy routes), guards, navigation config
│       ├── components/ui/           Design-system kit: Button, Input, Select, Field, Card, Badge,
│       │                            StatusBadge, Modal, ConfirmDialog, Dropdown, Tooltip, Table,
│       │                            Pagination, Skeleton, EmptyState, Alert, Avatar, PageHeader
│       ├── features/<area>/         api · components · pages
│       ├── layouts/                 AuthLayout, AppLayout (responsive sidebar + topbar)
│       ├── lib/ stores/ hooks/ types/
│       └── index.css                Design tokens (light + dark)
└── docs/                            ARCHITECTURE.md · API.md · DESIGN.md
```

---

## Local setup

### Prerequisites

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl` (XAMPP, Laragon or Herd all work)
- Composer 2
- MySQL 8 or MariaDB 10.6+
- Node.js **20+** and npm

### 1. Database

```sql
CREATE DATABASE fleetflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend

```bash
cd backend
composer install                # versions are pinned by composer.lock
cp .env.example .env            # then check the DB_* values (default root / empty password)
php artisan key:generate
php artisan migrate --seed      # schema, roles/permissions, dev accounts
php artisan serve               # http://127.0.0.1:8000
```

`composer dev` starts the API server, the queue listener and the scheduler together.

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                     # http://localhost:5173
```

In development, Vite proxies `/api`, `/sanctum` and `/storage` to `http://127.0.0.1:8000`. The browser therefore sees a single origin, and Sanctum cookie auth with CSRF works without CORS configuration.

### 4. Verify

Open **http://localhost:5173/system/status**. It makes real requests to check the API, the database, the Sanctum CSRF cookie and the auth guard.

### Development accounts (seeded, local only)

| Role | Email | Password |
| --- | --- | --- |
| Company Admin of *Atlas Logistics* | `owner@fleetflow.test` | `password` |
| Super Admin (platform) | `admin@fleetflow.test` | `password` |

You can also go through the full flow: **/register**, then create a company, then the dashboard.

---

## Environment configuration

| Key | Purpose |
| --- | --- |
| `DB_*` | MySQL connection |
| `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` | SPA origin(s) that get cookie sessions |
| `SANCTUM_TOKEN_EXPIRATION` | Lifetime of bearer tokens (driver app and integrations) |
| `QUEUE_CONNECTION` | `database` by default. Run `php artisan queue:work` |
| `MAPS_PROVIDER`, `MAPBOX_ACCESS_TOKEN`, `GOOGLE_MAPS_API_KEY` | Map/routing provider. Leave empty to keep it disabled; routing is never faked |
| `TRACKING_MIN_INTERVAL_SECONDS`, `TRACKING_STALE_AFTER_SECONDS`, `TRACKING_RETENTION_DAYS` | GPS ingestion rules (Phase 8) |
| `VITE_API_URL` (frontend) | `/api/v1` in dev. In production, the API origin |

Never commit `.env` files or real credentials.

---

## Tests & quality

```bash
cd backend && php artisan test          # 25 tests, SQLite in-memory
vendor/bin/pint --test                  # code style

cd frontend && npm test                 # 15 tests (Vitest + Testing Library)
npm run typecheck && npm run lint && npm run build
```

---

## Roadmap

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Foundation, API envelope, Sanctum, tenancy core, design system, onboarding | **Done** |
| 2 | Full ERD: vehicles, drivers, customers, deliveries, routes, tracking, finance tables | Next |
| 3 | Email verification, password reset, profile, team invitations, role management | |
| 4–16 | Fleet → Drivers → Deliveries → Dispatch/Routes → GPS (Reverb) → Driver PWA → Maintenance/Fuel → Finance → Reports → SaaS admin → Integrations → Hardening → Deployment | |

## Known constraints

- **Laravel Reverb is not installed yet.** It arrives in Phase 8 via `composer require laravel/reverb`. `BROADCAST_CONNECTION=log` until then.
- **Maps and routing need an API key** (Mapbox or Google). Without one, map features are shown as unavailable. They are never simulated.
