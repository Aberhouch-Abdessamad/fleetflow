# Architecture

## Request pipeline

```
Request
 → statefulApi (Sanctum: cookie session + CSRF for the SPA; bearer tokens for other clients)
 → throttle:api (300/min per user or IP)
 → auth:sanctum → active (suspended users get 403)
 → tenant (ResolveTenant: company resolved from the user's memberships)
 → permission:* (Spatie, scoped to the resolved company)
 → Form Request (validation) → thin Controller → Service (transactions, events, audit)
 → API Resource → ApiResponse envelope
 Errors → ApiExceptionRenderer → { success:false, message, code, errors? }
```

## Multi-tenancy

FleetFlow uses a **single database with a shared schema**. Every tenant-owned row carries `company_id`.

| Concern | Mechanism |
| --- | --- |
| Which companies a user belongs to | `company_users` (unique `company_id + user_id`, status `active`/`disabled`) |
| Which company a request acts in | `ResolveTenant` middleware. The `X-Company` header (company **public ULID**) can only *select* among the user's own active memberships. Otherwise it uses `users.current_company_id`, then the first membership. Request input is never trusted. |
| Where the resolved tenant lives | `TenantContext` (scoped singleton). It also calls `setPermissionsTeamId()` so Spatie checks run inside that company. |
| Query isolation | `BelongsToCompany` trait. It adds a global scope `where company_id = tenant`. **If no tenant is set, the scope matches nothing (fail closed).** On create, `company_id` is forced to the tenant. On update, a `company_id` change is reverted. |
| Roles per company | Spatie **teams mode** with `team_foreign_key = company_id`. Role definitions are global (`company_id NULL`); assignments live in `model_has_roles.company_id`. The same user can be Company Admin in A and Driver in B. |
| Suspended / pending company | `ResolveTenant` returns **423** (`tenant_suspended`, `tenant_pending`) for every tenant route. `/auth/me` still works, so the SPA can explain the situation. |
| Platform admin | `users.is_super_admin` (not mass-assignable). It only passes `platform.*` gates and never bypasses tenant permissions implicitly. |
| IDs exposed to clients | Companies are exposed by ULID `public_id`; internal ids are never returned. |

## Roles and permissions

`config/fleetflow.php` is the single source of truth for the matrix (company_admin, fleet_manager, dispatcher, driver, accountant, customer). Sync it with `php artisan fleetflow:sync-roles` (the seeder also runs it). The frontend builds navigation from the permissions returned by `/auth/me` and is cosmetic only: the API enforces every rule.

## Tested security rules (foundation)

| Rule | Test |
| --- | --- |
| A foreign company can't be selected via header | `TenantIsolationTest::test_user_cannot_select_a_company_they_do_not_belong_to` |
| A user can't switch to a foreign workspace | `…test_user_cannot_switch_to_a_foreign_company` |
| `company_id` in body or query is ignored | `…test_request_body_company_id_is_ignored`, `BelongsToCompanyScopeTest` |
| Roles don't leak across companies | `…test_roles_are_scoped_per_company` |
| A suspended company is blocked | `…test_suspended_company_is_blocked_from_operating` |
| A disabled membership loses access | `…test_disabled_membership_loses_access` |
| The scope fails closed without a tenant | `BelongsToCompanyScopeTest::test_queries_only_see_the_current_tenant_and_fail_closed_without_one` |
| Privileged fields can't be mass-assigned | `AuthenticationTest::test_registration_ignores_privileged_fields`, `CompanyOnboardingTest::test_owner_can_update_company_but_cannot_move_status` |

## Domain services

Controllers only validate, authorise and delegate. Every write goes through a service in `app/Services/<Domain>` inside a DB transaction; side effects (broadcasts, notifications, webhooks) are dispatched **after commit**.

| Area | Key services and rules |
| --- | --- |
| Fleet | `VehicleService`, `VehicleAvailabilityService` (derived status: available / assigned / on_trip / maintenance / out_of_service), `VehicleAssignmentService` — one active vehicle per driver and driver per vehicle, enforced by unique `active_vehicle_id` / `active_driver_id` columns |
| Drivers | `DriverService` (licence validity, linked user), availability windows, `ShiftService` (one open shift) |
| Deliveries | `DeliveryService`, `ProofService` (reference numbers, items, charges in cents, optional OTP returned once), `DeliveryStatusService` — the **only** place status changes; row lock, `allowedTransitions()`, history row, proof/OTP checks, events |
| Dispatch | `AssignmentService` — eligibility (active driver, valid licence for the vehicle category, not in maintenance, capacity, time conflicts, max active deliveries), assign / reassign / unassign with lock |
| Routes | `RouteService` (stops, ordering, ETA), `MapsProvider` interface (`MapboxProvider`, `GoogleMapsProvider`, `OsmMapsProvider` — key-less OSM/Nominatim + OSRM; `NullMapsProvider` → 503 for `none`). Without a routing provider, distance is straight-line and flagged `distance_source = straight_line` |
| Tracking | `LocationService` (identity from the user, on-duty check, validation, dedupe `unique(driver_id, recorded_at)`, throttle, `last_*` on vehicle), geofence enter/exit events, scheduled retention pruning |
| Finance | `MaintenanceService` + `ReminderService`, `FuelService` (L/100 km from odometer deltas), `ExpenseService` (approval by someone other than the creator; source-linked expenses from fuel/maintenance are unique), `InvoiceService` (only delivered, un-invoiced deliveries of one customer; totals computed server-side; payments update balance) |
| Reports | `ReportService` (+ `Exporter` for CSV) — SQL aggregates over the company's data in its timezone; incomplete data (e.g. pending expenses) is flagged rather than hidden |
| SaaS | `SubscriptionService` — plan limits and features (`402`), trials, expiry via scheduler; admin actions are audit-logged |
| Integrations | `ExternalDeliveryService` (idempotency table, duplicate external refs), `WebhookDispatcher` + `SendWebhook` job (HMAC signature, exponential backoff) |

## Scheduler & queue

| Command | Schedule | Purpose |
| --- | --- | --- |
| `fleetflow:reminders` | daily | Document / licence / maintenance expiry notifications |
| `fleetflow:prune-locations` | daily | Delete GPS points older than `TRACKING_RETENTION_DAYS` |
| `fleetflow:retry-webhooks` | every minute | Retry due webhook deliveries |
| `fleetflow:expire-subscriptions` | hourly | End trials/subscriptions past their period |
| `fleetflow:sync-roles` | manual / seeder | Sync the permission matrix |

Notifications use the `database` + `broadcast` channels and are queued; `TenantDatabaseChannel` stamps `company_id` so a user only sees notifications of the current workspace.

## Files

Uploads (vehicle/driver documents, photos, delivery proofs, receipts) are stored on the private disk under `companies/{id}/…` and served only through `GET /api/v1/files/{kind}/{id}`, which re-checks tenant and permission. Nothing user-uploaded is publicly reachable.

## Frontend

- **`lib/http.ts`** is the single Axios instance (`withCredentials`, `withXSRFToken`, `X-Company` header). Errors are normalised to `ApiError` (`kind`, `status`, `code`, field `errors`). A 401 anywhere signs the user out.
- **Stores** (Zustand): `auth` (user, `can()`, `hasRole()`) and `theme`.
- **Guards:** `RequireGuest`, `RequireAuth`, `RequireCompany` (redirects to onboarding or shows a suspension notice) and `RequirePermission`.
- **Routes** are lazy-loaded per page.
- **Data hooks:** `useAsync` (stale responses ignored; data for previous parameters is never shown for new ones), `usePaged`, `useResource`, `useOptions`.
- **Real-time:** `lib/realtime.ts` subscribes with Echo when `VITE_REVERB_APP_KEY` is set; pages fall back to polling intervals.
- **Errors:** a route-level error boundary shows a recoverable screen instead of a blank page.
- **Driver app** (`/driver`) is a mobile-first layout using the same API with the driver's permissions; `useGpsTracker` posts positions only while on shift, with backoff on errors.

