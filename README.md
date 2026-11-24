# FleetFlow

**Multi-tenant fleet & delivery management SaaS.** Companies manage vehicles, drivers, customers, deliveries, dispatch, routes, live GPS tracking, a mobile driver app, maintenance, fuel, expenses, invoicing and reports — each in its own isolated workspace. A platform admin area manages companies, plans and subscriptions, and an integration API lets e-commerce stores push orders in and receive signed webhooks.

> **Status: Phases 1–15 are complete and verified** (Phase 16, production deployment, is intentionally not included). Nothing on screen is mock data: every number, chart and position comes from the database or from a real device.

| Layer | Stack |
| --- | --- |
| API | Laravel 12.73 · PHP 8.2+ (8.3 recommended) · MySQL 8 / MariaDB 10.6+ · Sanctum 4 · Spatie Permission 6 (teams mode) · PHPUnit 11 |
| SPA | React 19 · TypeScript · Vite 8 · Tailwind CSS 4 · Geist type · shadcn-style UI kit (Radix + CVA) · ⌘K command palette · React Router 7 · Zustand · React Hook Form + Zod · Recharts · Leaflet · jsPDF · Sonner · Vitest |
| Real-time | Laravel broadcasting on private tenant channels; Laravel Reverb + Echo when installed, polling fallback otherwise |
| Maps | All-Leaflet: OpenStreetMap/CARTO tiles, Nominatim geocoding and OSRM routing/optimisation — key-less by default. `MAPS_PROVIDER=mapbox/google` for vendor providers; `none` = disabled (straight-line fallback, labelled `straight_line`) |

---

## What's included

| Phase | Scope |
| --- | --- |
| 1 | Foundation: API envelope, Sanctum (SPA cookies + bearer tokens), tenancy core, design system, onboarding |
| 2 | Full schema: fleet, delivery, routing, tracking, finance and integration tables with tenant keys and indexes |
| 3 | Email verification, password reset, profile & password, team invitations, per-company roles |
| 4 | Vehicles: CRUD, photos, documents with expiry, status, vehicle types, driver assignment history, soft delete/restore |
| 5 | Drivers: profiles, licence validity, documents, weekly availability, shifts, performance |
| 6 | Customers & deliveries: addresses, items, charges, COD, OTP, status machine with full history, CSV export |
| 7 | Dispatch board & routes: eligibility checks (licence, capacity, maintenance, conflicts), assign/reassign, route planner with stops and provider optimisation |
| 8 | Live GPS: validated ingestion, dedupe/throttle, freshness (live/stale), history, geofences, broadcast events |
| 9 | Driver PWA (mobile): shift start/end, accept/reject, pickup → delivery, photo/signature/OTP proof, issues, expenses, background location |
| 10 | Maintenance (list + calendar, reminders) and fuel (efficiency per vehicle) |
| 11 | Expenses with approval flow, invoices from delivered orders, payments, balances |
| 12 | Dashboard & reports (deliveries, fleet, drivers, financial) with date presets, CSV/PDF export |
| 13 | SaaS: plans, trials, limits & feature gates, platform admin (companies, users, plans, subscriptions, analytics, activity, settings, health) |
| 14 | Integrations: API clients, idempotent order intake, signed webhooks with retries, public tracking page with rating |
| 15 | Hardening: rate limits, audit log, notifications, policies, error boundaries, 86 backend + 15 frontend tests, browser walkthrough |

---

## Repository layout

```
fleetflow/
├── backend/                          Laravel API at /api/v1
│   ├── app/
│   │   ├── Enums/                    DeliveryStatus (state machine), VehicleStatus, …
│   │   ├── Events/                   TenantBroadcastEvent + delivery/vehicle/assignment events
│   │   ├── Http/Controllers/Api/V1/  Fleet, Driver, Delivery, Dispatch, Route, Tracking, Finance,
│   │   │                             Reports, Team, Shared, Auth, Integration, Admin (thin)
│   │   ├── Http/Middleware/          ResolveTenant, EnsureUserToken, ResolveIntegrationClient, …
│   │   ├── Http/Requests|Resources/  Validation · output shaping (no internal ids for tenants)
│   │   ├── Jobs/ Notifications/      SendWebhook (backoff), queued database+broadcast notifications
│   │   ├── Models/ Policies/         BelongsToCompany global scope · per-record policies
│   │   ├── Services/                 All business logic, one folder per domain
│   │   └── Support/                  ApiResponse, Money (integer cents), DateRange, Geo, Uploads, Tenancy
│   ├── config/fleetflow.php          Role → permission matrix, tracking & maps settings
│   ├── routes/{api,channels,console}.php
│   └── tests/Feature/                86 feature tests, one folder per domain
├── frontend/
│   └── src/
│       ├── app/                      Router (lazy), guards, navigation
│       ├── components/{ui,charts,map,shared}
│       ├── features/<area>/          pages · components · api
│       ├── layouts/                  AuthLayout, AppLayout (company/admin), DriverApp layout
│       ├── hooks/ lib/ stores/ types/
│       └── index.css                 Design tokens (light + dark), chart palette, map styles
└── docs/                             ARCHITECTURE.md · API.md  (+ frontend/docs/DESIGN.md)
```

---

## Local setup

### Prerequisites

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl` (XAMPP, Laragon or Herd)
- Composer 2 · MySQL 8 or MariaDB 10.6+ · Node.js **20+**

### 1. Database

```sql
CREATE DATABASE fleetflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env            # check DB_* values
php artisan key:generate
php artisan migrate --seed      # schema, plans, roles, demo company & accounts
php artisan storage:link
composer dev                    # API server + queue worker + scheduler together
```

Or run them separately: `php artisan serve`, `php artisan queue:work`, `php artisan schedule:work`.
The queue is needed for notifications and webhooks; the scheduler runs reminders, GPS pruning, webhook retries and subscription expiry.

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                     # http://localhost:5173
```

Vite proxies `/api`, `/sanctum` and `/storage` to `http://127.0.0.1:8000`, so Sanctum cookie auth works without CORS setup.

### 4. Verify

Open **http://localhost:5173/system/status** — it checks the API, database, CSRF cookie and auth guard with real requests.

### Development accounts (seeded, local only — password `password`)

| Role | Email |
| --- | --- |
| Company Admin, *Atlas Logistics* | `owner@fleetflow.test` |
| Dispatcher | `dispatch@fleetflow.test` |
| Fleet manager | `fleet@fleetflow.test` |
| Accountant | `finance@fleetflow.test` |
| Driver (open `/driver` on a phone) | `driver@fleetflow.test` |
| Super Admin (platform) | `admin@fleetflow.test` |

The seed creates real records (6 vehicles, 5 drivers, 47 deliveries, fuel, maintenance and expenses). **No GPS positions are seeded**: vehicles show "No GPS data yet" until a driver starts a shift in the driver app and the browser shares its location.

---

## Optional services

### Real-time (Laravel Reverb)

Out of the box `BROADCAST_CONNECTION=log` and the SPA polls (dispatch 20 s, tracking 15 s). For push updates:

```bash
cd backend
composer require laravel/reverb
php artisan reverb:install       # writes REVERB_* keys to .env and sets BROADCAST_CONNECTION=reverb
php artisan reverb:start
```

Then copy `REVERB_APP_KEY`, host, port and scheme into `frontend/.env` as `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` and restart Vite. Echo switches on automatically when `VITE_REVERB_APP_KEY` is set.

### Maps & routing

Maps render with **Leaflet and OpenStreetMap/CARTO tiles — no API key anywhere**. Place search defaults to OpenStreetMap Nominatim (`MAPS_PROVIDER=osm`, the default), and **road distances, ETAs and route optimisation run on the key-less public OSRM endpoint** by default. Override `OSRM_URL` for a self-hosted/custom OSRM. Mapbox (`MAPS_PROVIDER=mapbox` + `MAPBOX_ACCESS_TOKEN`) and Google (`MAPS_PROVIDER=google` + `GOOGLE_MAPS_API_KEY`) remain available for higher precision. `MAPS_PROVIDER=none` disables everything (503 on optimise; straight-line fallback for distances). `VITE_MAPBOX_TOKEN` is no longer used.

---

## Environment configuration

| Key | Purpose |
| --- | --- |
| `DB_*` | Database connection |
| `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` | SPA origin(s) allowed cookie sessions |
| `SANCTUM_TOKEN_EXPIRATION` | Lifetime of bearer tokens (driver app, API) |
| `QUEUE_CONNECTION` | `database` by default |
| `MAIL_*` | Verification, password reset, invitations (`log` mailer locally) |
| `BROADCAST_CONNECTION`, `REVERB_*` | Real-time (see above) |
| `MAPS_PROVIDER`, `MAPBOX_ACCESS_TOKEN`, `GOOGLE_MAPS_API_KEY`, `OSRM_URL` | Maps provider (`osm` default — key-less Nominatim + OSRM; `mapbox`/`google` optional; `none` disables) |
| `TRACKING_MIN_INTERVAL_SECONDS`, `TRACKING_STALE_AFTER_SECONDS`, `TRACKING_RETENTION_DAYS` | GPS ingestion rules |
| `VITE_API_URL`, `VITE_REVERB_*` (frontend) | API base, optional Echo config |

Never commit `.env` files or real credentials.

---

## Tests & quality

```bash
cd backend
php artisan test                # 86 feature tests (SQLite in-memory)
vendor/bin/pint --test

cd frontend
npm test                        # 15 tests (Vitest + Testing Library)
npm run typecheck && npm run lint && npm run build
```

Covered rules include tenant isolation on every module, ignored `company_id`/`driver_id`/amount input, the delivery state machine, assignment eligibility and conflicts, GPS validation/dedupe/ownership, OTP and proof rules, expense approval separation, invoice totals, plan limits, idempotent integrations and webhook signatures.

---

## Security model (summary)

- One database, shared schema; every tenant row has `company_id`, enforced by a fail-closed global scope and forced on create.
- The tenant is resolved server-side from the user's memberships (`X-Company` can only select among them).
- Permissions are per company (Spatie teams); the SPA's menus are cosmetic — the API enforces everything.
- Drivers can only see and act on their own assignments; GPS identity comes from the authenticated user, never the payload.
- Money is stored as integer cents and computed server-side; totals from the client are ignored.
- Rate limits on auth, sensitive actions, tracking, public tracking and integrations; activity log for all mutations.

See `docs/ARCHITECTURE.md` and `docs/API.md` for details.

## Known constraints

- Laravel Reverb is optional and not installed by default (see above).
- Maps need no API key: Leaflet renders tiles and OSRM handles routing/optimisation; higher fidelity is opt-in via Mapbox/Google.
- Phase 16 (production deployment: servers, CI/CD, backups, monitoring) is not part of this delivery.
