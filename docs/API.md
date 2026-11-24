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
| `plan_limit_reached` | 402 | Plan limit (vehicles, drivers, users, monthly deliveries) reached |
| `plan_feature_unavailable` / `subscription_inactive` | 402 | Feature not in plan, or subscription expired |
| `assignment_conflict`, `duplicate_external_reference`, `idempotency_conflict`, `delivery_invoiced`, … | 409 | State conflict — message explains it |
| `invalid_transition`, `invalid_coordinates`, `stale_location`, … | 422 | Business-rule violation |
| `maps_provider_unavailable` | 503 | Routing/optimisation without a routing-capable provider (`none`, or OSRM disabled with `OSRM_URL=`; `osm` otherwise routes key-less) |


## Conventions

- **Authentication:** the SPA calls `GET /sanctum/csrf-cookie`, then uses cookies. The driver app and other user clients send `device_name` on login and use `Authorization: Bearer <token>`. Integration clients use an **API client token** (created in *Settings → Integrations*).
- **Tenant:** resolved server-side. Optional header `X-Company: <company public id>` selects among the user's own companies.
- **Never trusted from the client:** `company_id`, `driver_id` on GPS/driver endpoints, `created_by`, totals and amounts derived from items. Such fields are ignored or `prohibited`.
- **Money:** decimals in requests (`"60.00"`), returned as `{ amount, cents, currency, formatted }` where relevant. Stored as integer cents.
- **Dates:** ISO-8601 in UTC; reports accept `range` presets (`today`, `yesterday`, `last_7_days`, `last_30_days`, `this_month`, `last_month`, `this_year`) or `from`/`to`, interpreted in the company timezone.
- **Lists:** `?page=&per_page=` (max 100), filters as query params, response `meta.pagination`.
- **Rate limits:** `api` 300/min per user, `auth` 10/min per IP + 5/min per email (relaxed in `local`), `sensitive` 10/min, `tracking` 60/min per driver, `public-tracking` 30/min per IP, `integrations` 300/min per client.

## Delivery state machine

```
draft → pending → assigned → accepted → picked_up → in_transit → arrived → delivered
                    ↘ pending (unassign / reject)        ↘ failed → returned | pending
any pre-pickup state → cancelled
```

`PATCH /deliveries/{id}/status` `{ status, latitude?, longitude?, notes?, failure_reason? }` — only transitions in `DeliveryStatus::allowedTransitions()` succeed; every change writes `delivery_status_histories`, an activity log entry, a broadcast event and (if configured) a webhook. Delivering requires a proof (photo/signature) and, when the delivery has an OTP, a verified `POST /deliveries/{id}/otp`.

## GPS ingestion

`POST /tracking/location` (driver token/session, `tracking.report_location`)

```json
{ "latitude": 33.5883, "longitude": -7.6324, "speed": 42.5, "heading": 180, "accuracy": 8, "recorded_at": "2026-11-24T10:15:00Z" }
```

The driver and vehicle come from the authenticated user — `driver_id`, `vehicle_id`, `company_id` are **prohibited**. Rejected when the driver has no open shift or active delivery (`not_on_duty`), when the timestamp is in the future or too old (`stale_location`), or (0,0)/out-of-range coordinates. Duplicates (same `recorded_at`) are ignored, and points closer than `TRACKING_MIN_INTERVAL_SECONDS` are throttled. Positions older than `TRACKING_STALE_AFTER_SECONDS` are shown as *stale*.

## Integration API

Authenticate with the API client token: `Authorization: Bearer <token>`.

`POST /integrations/deliveries` (alias `/integrations/shipments`) — header **`Idempotency-Key`** required (≤120 chars). Replaying the same key returns the first response; the same key with a different body → `409 idempotency_conflict`. An `external_reference` already used by the company → `409 duplicate_external_reference`.

```json
{
  "external_reference": "SHOP-10045", "source": "shopify",
  "customer": { "name": "Marjane Market", "email": "orders@example.com" },
  "pickup":   { "address": "Atlas Depot, Casablanca", "latitude": 33.6, "longitude": -7.5 },
  "dropoff":  { "address": "Maarif, Casablanca", "city": "Casablanca" },
  "recipient": { "name": "Hanna T.", "phone": "+212600000000" },
  "priority": "normal", "delivery_fee": "60.00", "cod_amount": "0",
  "items": [{ "description": "Boxes", "quantity": 2, "weight": 8 }]
}
```

`GET /integrations/deliveries/{externalReference}` returns status, timestamps and the public tracking URL. `POST …/cancel` cancels while still cancellable.

### Webhooks

Events: `delivery.created`, `delivery.status_updated`, `delivery.assigned`, `delivery.completed`, `delivery.failed`, `delivery.cancelled`.

Headers: `X-FleetFlow-Event`, `X-FleetFlow-Delivery` (unique event id — use it to dedupe), `X-FleetFlow-Signature: t=<unix>,v1=<hex>`.

Verify: `hex = HMAC_SHA256(secret, t + "." + raw_body)`, compare in constant time, and reject if `t` is older than 5 minutes. Non-2xx responses are retried with exponential backoff (2, 4, 8, … minutes, 6 attempts max); failed deliveries can be retried manually from *Settings → Integrations*.

## Real-time channels

Private channels (authorised in `routes/channels.php`): `company.{companyPublicId}` for staff with the matching permission and `driver.{driverId}` for that driver only. Events (broadcast names): `VehicleLocationUpdated`, `DeliveryStatusUpdated`, `DeliveryCompleted`, `DeliveryExceptionReported`, `DriverAssignmentUpdated`. Without Reverb the SPA polls the same REST endpoints.

## Endpoint catalogue

Generated from `php artisan route:list`. *auth* = session or user token, *tenant* = company resolved, backticks = required permission (record-level policies apply on top, e.g. drivers only reach their own deliveries).

### Authentication & account

| Method | Path | Access |
| --- | --- | --- |
| `POST` | `/auth/email/resend` | auth · throttle 6,1 |
| `POST` | `/auth/forgot-password` | public · throttle auth |
| `POST` | `/auth/login` | public · throttle auth |
| `POST` | `/auth/logout` | auth |
| `GET` | `/auth/me` | auth |
| `PUT` | `/auth/password` | auth · throttle sensitive |
| `PUT` | `/auth/profile` | auth |
| `POST` | `/auth/register` | public · throttle auth |
| `POST` | `/auth/reset-password` | public · throttle auth |
| `GET` | `/auth/verify/{id}/{hash}` | public · throttle auth |

### Company

| Method | Path | Access |
| --- | --- | --- |
| `POST` | `/companies` | auth |
| `GET` | `/companies/current` | auth · tenant |
| `PUT` | `/companies/current` | auth · tenant |
| `GET` | `/companies/current/activity` | auth · tenant |
| `GET` | `/companies/current/settings` | auth · tenant |
| `PUT` | `/companies/current/settings` | auth · tenant |
| `GET` | `/companies/current/subscription` | auth · tenant |
| `POST` | `/companies/{publicId}/switch` | auth |

### Team

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/team` | auth · tenant · `users.view` |
| `POST` | `/team/invitations` | auth · tenant · `users.invite` |
| `DELETE` | `/team/invitations/{invitation}` | auth · tenant · `users.invite` |
| `PATCH` | `/team/members/{user}` | auth · tenant · `users.manage` |

### Invitations

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/invitations/{token}` | public · throttle auth |
| `POST` | `/invitations/{token}/accept` | public · throttle auth |

### Vehicles

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/vehicles` | auth · tenant · `vehicles.view` |
| `POST` | `/vehicles` | auth · tenant · `vehicles.manage` |
| `POST` | `/vehicles/{id}/restore` | auth · tenant · `vehicles.manage` |
| `GET` | `/vehicles/{vehicle}` | auth · tenant · `vehicles.view` |
| `PUT|POST` | `/vehicles/{vehicle}` | auth · tenant · `vehicles.manage` |
| `DELETE` | `/vehicles/{vehicle}` | auth · tenant · `vehicles.manage` |
| `POST` | `/vehicles/{vehicle}/assign` | auth · tenant · `vehicles.assign` |
| `GET` | `/vehicles/{vehicle}/documents` | auth · tenant · `vehicles.view` |
| `POST` | `/vehicles/{vehicle}/documents` | auth · tenant · `vehicles.manage` |
| `DELETE` | `/vehicles/{vehicle}/documents/{document}` | auth · tenant · `vehicles.manage` |
| `GET` | `/vehicles/{vehicle}/history` | auth · tenant · `vehicles.view` |
| `PATCH` | `/vehicles/{vehicle}/status` | auth · tenant · `vehicles.manage` |
| `POST` | `/vehicles/{vehicle}/unassign` | auth · tenant · `vehicles.assign` |

### Vehicle types

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/vehicle-types` | auth · tenant |
| `POST` | `/vehicle-types` | auth · tenant · `vehicles.manage` |

### Drivers

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/drivers` | auth · tenant · `drivers.view` |
| `POST` | `/drivers` | auth · tenant · `drivers.manage` |
| `GET` | `/drivers/{driver}` | auth · tenant · `drivers.view` |
| `PUT|POST` | `/drivers/{driver}` | auth · tenant · `drivers.manage` |
| `DELETE` | `/drivers/{driver}` | auth · tenant · `drivers.manage` |
| `GET` | `/drivers/{driver}/assignments` | auth · tenant · `drivers.view` |
| `GET` | `/drivers/{driver}/availability` | auth · tenant · `drivers.view` |
| `PUT` | `/drivers/{driver}/availability` | auth · tenant · `drivers.manage` |
| `GET` | `/drivers/{driver}/deliveries` | auth · tenant · `drivers.view` |
| `GET` | `/drivers/{driver}/documents` | auth · tenant · `drivers.view` |
| `POST` | `/drivers/{driver}/documents` | auth · tenant · `drivers.manage` |
| `GET` | `/drivers/{driver}/performance` | auth · tenant · `drivers.view` |
| `GET` | `/drivers/{driver}/shifts` | auth · tenant · `drivers.view` |
| `PATCH` | `/drivers/{driver}/status` | auth · tenant · `drivers.manage` |

### Customers

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/customers` | auth · tenant · `customers.view` |
| `POST` | `/customers` | auth · tenant · `customers.manage` |
| `GET` | `/customers/{customer}` | auth · tenant · `customers.view` |
| `PUT` | `/customers/{customer}` | auth · tenant · `customers.manage` |
| `DELETE` | `/customers/{customer}` | auth · tenant · `customers.manage` |
| `POST` | `/customers/{customer}/addresses` | auth · tenant · `customers.manage` |
| `PUT` | `/customers/{customer}/addresses/{address}` | auth · tenant · `customers.manage` |
| `DELETE` | `/customers/{customer}/addresses/{address}` | auth · tenant · `customers.manage` |
| `GET` | `/customers/{customer}/deliveries` | auth · tenant · `customers.view` |

### Deliveries

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/deliveries` | auth · tenant |
| `POST` | `/deliveries` | auth · tenant · `deliveries.create` |
| `GET` | `/deliveries/export` | auth · tenant |
| `GET` | `/deliveries/{delivery}` | auth · tenant |
| `PUT` | `/deliveries/{delivery}` | auth · tenant · `deliveries.update` |
| `DELETE` | `/deliveries/{delivery}` | auth · tenant · `deliveries.cancel` |
| `POST` | `/deliveries/{delivery}/assign` | auth · tenant · `deliveries.assign` |
| `POST` | `/deliveries/{delivery}/cancel` | auth · tenant |
| `GET` | `/deliveries/{delivery}/history` | auth · tenant |
| `POST` | `/deliveries/{delivery}/otp` | auth · tenant |
| `POST` | `/deliveries/{delivery}/proof` | auth · tenant · throttle 30,1 |
| `POST` | `/deliveries/{delivery}/reassign` | auth · tenant · `deliveries.assign` |
| `PATCH` | `/deliveries/{delivery}/status` | auth · tenant |

### Dispatch

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/dispatch/assignments` | auth · tenant · `dispatch.board` |
| `POST` | `/dispatch/assignments` | auth · tenant · `deliveries.assign` |
| `DELETE` | `/dispatch/assignments/{assignment}` | auth · tenant · `deliveries.assign` |
| `GET` | `/dispatch/available-drivers` | auth · tenant · `dispatch.board` |
| `GET` | `/dispatch/available-vehicles` | auth · tenant · `dispatch.board` |
| `GET` | `/dispatch/board` | auth · tenant · `dispatch.board` |
| `POST` | `/dispatch/check` | auth · tenant · `deliveries.assign` |

### Routes

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/routes` | auth · tenant · `routes.view` |
| `POST` | `/routes` | auth · tenant · `routes.manage` |
| `GET` | `/routes/{route}` | auth · tenant · `routes.view` |
| `PUT` | `/routes/{route}` | auth · tenant · `routes.manage` |
| `DELETE` | `/routes/{route}` | auth · tenant · `routes.manage` |
| `POST` | `/routes/{route}/complete` | auth · tenant · `routes.manage` |
| `POST` | `/routes/{route}/optimize` | auth · tenant · `routes.manage` · throttle 10,1 |
| `POST` | `/routes/{route}/start` | auth · tenant · `routes.manage` |
| `POST` | `/routes/{route}/stops` | auth · tenant · `routes.manage` |
| `DELETE` | `/routes/{route}/stops/{stop}` | auth · tenant · `routes.manage` |

### Tracking

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/tracking/deliveries/{delivery}` | auth · tenant |
| `POST` | `/tracking/location` | auth · tenant · `tracking.report_location` · throttle tracking |
| `GET` | `/tracking/public/{token}` | public · throttle public-tracking |
| `POST` | `/tracking/public/{token}/rating` | public · throttle public-tracking |
| `GET` | `/tracking/vehicles` | auth · tenant · `tracking.view` |
| `GET` | `/tracking/vehicles/{vehicle}` | auth · tenant · `tracking.view` |
| `GET` | `/tracking/vehicles/{vehicle}/history` | auth · tenant · `tracking.view` |

### Driver app

| Method | Path | Access |
| --- | --- | --- |
| `POST` | `/driver/assignments/{assignment}/respond` | auth · tenant · `deliveries.view_own` |
| `GET` | `/driver/dashboard` | auth · tenant · `deliveries.view_own` |
| `GET` | `/driver/deliveries/{delivery}` | auth · tenant · `deliveries.view_own` |
| `POST` | `/driver/deliveries/{delivery}/issue` | auth · tenant · `deliveries.view_own` |
| `GET` | `/driver/history` | auth · tenant · `deliveries.view_own` |
| `POST` | `/driver/shift/end` | auth · tenant · `deliveries.view_own` |
| `POST` | `/driver/shift/start` | auth · tenant · `deliveries.view_own` |

### Maintenance

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/maintenance` | auth · tenant · `maintenance.view` |
| `POST` | `/maintenance` | auth · tenant · `maintenance.manage` |
| `GET` | `/maintenance/reminders` | auth · tenant · `maintenance.view` |
| `POST` | `/maintenance/reminders/{reminder}/dismiss` | auth · tenant · `maintenance.manage` |
| `GET` | `/maintenance/{maintenance}` | auth · tenant · `maintenance.view` |
| `PUT|POST` | `/maintenance/{maintenance}` | auth · tenant · `maintenance.manage` |
| `DELETE` | `/maintenance/{maintenance}` | auth · tenant · `maintenance.manage` |

### Fuel

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/fuel-records` | auth · tenant |
| `POST` | `/fuel-records` | auth · tenant |

### Expenses

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/expenses` | auth · tenant |
| `POST` | `/expenses` | auth · tenant |
| `GET` | `/expenses/export` | auth · tenant · `expenses.view` |
| `GET` | `/expenses/{expense}` | auth · tenant |
| `PUT|POST` | `/expenses/{expense}` | auth · tenant |
| `DELETE` | `/expenses/{expense}` | auth · tenant |
| `PATCH` | `/expenses/{expense}/approve` | auth · tenant |
| `PATCH` | `/expenses/{expense}/reject` | auth · tenant |

### Invoices

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/invoices` | auth · tenant · `invoices.view` |
| `POST` | `/invoices` | auth · tenant · `invoices.manage` |
| `GET` | `/invoices/billable` | auth · tenant · `invoices.view` |
| `GET` | `/invoices/{invoice}` | auth · tenant · `invoices.view` |
| `POST` | `/invoices/{invoice}/issue` | auth · tenant · `invoices.manage` |
| `POST` | `/invoices/{invoice}/payments` | auth · tenant · `payments.manage` |
| `POST` | `/invoices/{invoice}/void` | auth · tenant · `invoices.manage` |

### Financial

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/financial/reports` | auth · tenant |

### Reports

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/reports/{type}` | auth · tenant |

### Dashboard

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/dashboard` | auth · tenant |

### Notifications

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/notifications` | auth · tenant |
| `POST` | `/notifications/read-all` | auth · tenant |
| `GET` | `/notifications/unread-count` | auth · tenant |
| `POST` | `/notifications/{id}/read` | auth · tenant |

### Files

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/files/{kind}/{id}` | auth · tenant |

### Maps

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/maps/config` | auth · tenant |
| `GET` | `/maps/geocode` | auth · tenant · throttle 30,1 |
| `GET` | `/maps/reverse` | auth · tenant · throttle 30,1 |

### Integrations

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/integrations` | auth · tenant · `integrations.manage` |
| `POST` | `/integrations/clients` | auth · tenant · `integrations.manage` |
| `DELETE` | `/integrations/clients/{client}` | auth · tenant · `integrations.manage` |
| `POST` | `/integrations/clients/{client}/rotate` | auth · tenant · `integrations.manage` |
| `POST` | `/integrations/deliveries` | API client · throttle integrations |
| `GET` | `/integrations/deliveries/{externalReference}` | API client · throttle integrations |
| `POST` | `/integrations/deliveries/{externalReference}/cancel` | API client · throttle integrations |
| `POST` | `/integrations/shipments` | API client · throttle integrations |
| `GET` | `/integrations/webhook-deliveries` | auth · tenant · `integrations.manage` |
| `POST` | `/integrations/webhook-deliveries/{webhookDelivery}/retry` | auth · tenant · `integrations.manage` |
| `POST` | `/integrations/webhooks` | auth · tenant · `integrations.manage` |
| `DELETE` | `/integrations/webhooks/{webhook}` | auth · tenant · `integrations.manage` |

### Platform admin

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/admin/activity-logs` | super admin |
| `GET` | `/admin/companies` | super admin |
| `GET` | `/admin/companies/{company}` | super admin |
| `PATCH` | `/admin/companies/{company}/activate` | super admin |
| `PUT` | `/admin/companies/{company}/subscription` | super admin |
| `PATCH` | `/admin/companies/{company}/suspend` | super admin |
| `GET` | `/admin/dashboard` | super admin |
| `GET` | `/admin/health` | super admin |
| `GET` | `/admin/plans` | super admin |
| `POST` | `/admin/plans` | super admin |
| `PUT` | `/admin/plans/{plan}` | super admin |
| `GET` | `/admin/settings` | super admin |
| `PUT` | `/admin/settings` | super admin |
| `GET` | `/admin/subscriptions` | super admin |
| `GET` | `/admin/users` | super admin |
| `PATCH` | `/admin/users/{user}/status` | super admin |

### System

| Method | Path | Access |
| --- | --- | --- |
| `GET` | `/health` | public |
