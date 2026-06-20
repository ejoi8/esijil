# Phase E — Billing (subscription + usage overage)

> Implementation plan for the EventFlow billing module. Pick this up after
> choosing a payment gateway. Follows the conventions established in Phases A–D
> (DB-driven config, `BelongsToOrganization`, backed enums with `HasOptions` +
> `HasLabel`, queued side-effects, thin controllers, a test + Pint per increment).

Status: **not started.** Phases A–D (multi-tenancy, composable events, attendance,
CSV import + QR distribution) are done and green.

---

## 0. Decide first (blocks the gateway step, not the data model)

1. **Which Malaysian gateway?** ToyyibPay · Billplz · CHIP. All are FPX/RM-native.
   - Get **sandbox credentials** before writing the adapter.
   - **FPX has no true card-style recurring**, so renewals are reminder-driven:
     we create a fresh bill each period and confirm via webhook (not Laravel
     Cashier). Confirm whether CHIP subscriptions are worth using instead.
2. **Pricing shape:** per-module (registration / attendance / certificate priced
   separately) or bundled? `usage_records.metric` already supports per-module
   metering, so this is a pricing-config decision, not a schema one.
3. **Super-admin panel:** plan management + cross-tenant billing belong to the
   *platform owner*, but there is **no super-admin panel yet** (deferred in
   Phase A). Either (a) build a second Filament panel (`platform`) guarded by a
   platform-owner role, or (b) start with a role-gated section in the existing
   panel that bypasses tenant scoping. Recommend (a) when ready; (b) is the
   simpler interim.

The **data model, metering, and limits below are gateway-agnostic** — build them
first; the gateway adapter is the last, decision-dependent step.

---

## 1. Data model

Three tables. `plans` is **platform-level** (no `organization_id`). `subscriptions`
and `usage_records` are **org-owned** (use the `BelongsToOrganization` trait so
they get the `organization()` relationship + auto-fill, exactly like the other
tenant models).

### `plans` (platform-level, not tenant-scoped)
| Column | Type | Notes |
|---|---|---|
| id | id | |
| name | string | |
| code | string, unique | machine key (`free`, `pro`, …) |
| price_myr | decimal(10,2) | base recurring price |
| included_events | unsignedInteger | per period |
| included_attendees | unsignedInteger | per period |
| overage_rate_myr | decimal(8,4) | per extra attendee |
| interval | string | `PlanInterval` enum (monthly / annual) |
| is_active | boolean | |

### `subscriptions` (org-owned)
| Column | Type | Notes |
|---|---|---|
| id | id | |
| organization_id | foreignId (plain indexed, **no FK** — match A1's SQLite-safe pattern) | |
| plan_id | foreignId | |
| status | string | `SubscriptionStatus` (trialing / active / past_due / canceled) |
| period_start, period_end | timestamp | |
| gateway | string, nullable | which provider |
| gateway_ref | string, nullable | provider bill/subscription id |

### `usage_records` (org-owned)
| Column | Type | Notes |
|---|---|---|
| id | id | |
| organization_id | foreignId (plain indexed) | |
| event_id | foreignId, nullable | |
| metric | string | `UsageMetric` (registrations / attendees / certs / events) |
| qty | unsignedInteger | |
| period_start, period_end | timestamp, index | for rollups |

> **Migration note (learned in A1/B):** add `organization_id` as a plain indexed
> column **without** an inline FK — a constrained column triggers an SQLite table
> rebuild that drops expression unique indexes. Filament tenancy scopes via the
> Eloquent relationship, not a DB FK.

### Enums (in `app/Enums`, with `HasOptions` + `HasLabel`)
- `PlanInterval`: `Monthly`, `Annual`.
- `SubscriptionStatus`: `Trialing`, `Active`, `PastDue`, `Canceled`.
- `UsageMetric`: `Registrations`, `Attendees`, `Certs`, `Events`.

### Models
- `Plan` (HasFactory; **no** `BelongsToOrganization`).
- `Subscription`, `UsageRecord` (HasFactory + `BelongsToOrganization`).
- `Organization`: add `subscription(): HasOne` and `usageRecords(): HasMany`.

---

## 2. Metering — how `usage_records` get populated

Increment usage at the moments that matter, **via a small service** (e.g.
`App\Services\Billing\UsageMeter`) called from existing flows, dispatched to the
queue so hot paths stay lean (consistent with the registration/scan design):

- **registrations** — on public registration store + CSV import row.
- **attendees** — on first check-in (in `ScanController` when `present`).
- **certs** — when a certificate is first issued/downloaded.
- **events** — on event publish.

Keep it idempotent-ish: a metric row per (org, event, metric, period) that you
`increment`, or append rows and sum at rollup. Appending is simpler + auditable;
sum in the rollup. Prefer append + sum.

---

## 3. Plan limits & enforcement

A `App\Services\Billing\PlanGate` (or methods on `Organization`):
- `remainingEvents()`, `remainingAttendees()` for the current period.
- Soft warning in the panel (a banner/notification) as usage approaches the
  included quota; **hard cap** at event *publish* and registration thresholds
  (overage still billed, but caps prevent runaway free usage).
- Enforce in `EventForm`/publish + the public registration controller, gated so
  existing single-tenant PUSPANITA (give it an unlimited/internal plan) is
  unaffected.

---

## 4. Gateway integration (last; decision-dependent)

Custom adapter — **not Cashier** (FPX). Keep it behind a small contract so the
provider is swappable, mirroring the `CertificateGenerator` strategy we built:

```
App\Services\Billing\PaymentGateway          (interface)
  createBill(Subscription $sub, int $amountCents): GatewayBill   // returns redirect URL + ref
  verifyWebhook(Request $request): bool
  parseWebhook(Request $request): GatewayEvent                   // paid | failed + ref

App\Services\Billing\Gateways\ToyyibPayGateway implements PaymentGateway
App\Services\Billing\Gateways\BillplzGateway    implements PaymentGateway   // as chosen
```

- Bind the active gateway from a setting/env (like `CertificateSettings.renderer`
  picks the cert engine). Credentials in `config/services.php` + `.env` (do **not**
  commit keys).
- **Flow:** a period job aggregates `usage_records` → computes base + overage →
  `createBill()` → store `gateway_ref` on the subscription → redirect the org owner
  to pay (FPX) → **webhook** (`POST /api/billing/webhook/{gateway}`, signature-
  verified, no CSRF — put it in `routes/api.php` like `/api/scan`) updates the
  subscription status + queues a receipt email.
- Renewals: a scheduled command (`billing:renew`) creates next-period bills for
  due subscriptions; track state in `subscriptions` (no auto-charge on FPX).

---

## 5. Filament surfaces

- **Platform owner (super-admin panel — see §0.3):** `PlanResource` (CRUD plans),
  a tenants/subscriptions overview, usage rollups.
- **Org panel:** a read-only "Billing" page showing the current plan, period
  usage vs included quota, and a "Pay now / view invoice" action. Scope to the
  current tenant (it's org-owned, so tenancy handles it).

---

## 6. Build order (each step: migrate → green tests → Pint → commit)

1. **E1 — data + enums + models** (plans, subscriptions, usage_records; seed a
   default/internal plan and put PUSPANITA on it). Purely additive; suite stays
   green. *(Mirror A1: structure first, behaviour later.)*
2. **E2 — metering** (`UsageMeter` + hooks in registration / scan / cert / publish,
   queued). Tests assert usage rows accrue.
3. **E3 — limits + enforcement** (`PlanGate`, soft warnings + hard caps, with an
   unlimited internal plan so PUSPANITA is unaffected). Tests for cap behaviour.
4. **E4 — org billing page** (read-only plan + usage). Filament page test.
5. **E5 — gateway adapter + webhook + renewal command** (the chosen provider;
   credentials via config/env). Fake the gateway in tests; test the webhook
   handler + the rollup→bill math, not the live API.
6. **E6 — platform/super-admin panel** for plan management (or fold into E1 if
   doing the interim role-gated approach).

---

## 7. Testing strategy

- Data, metering, limits, rollup math, webhook handling: **fully testable** — do
  these thoroughly.
- The live gateway HTTP calls: **fake/mock** (`Http::fake()` + a fake gateway
  binding). Do **not** depend on the sandbox in the suite; verify the sandbox
  manually.
- Keep the multi-tenant test bootstrap (the `Filament::setTenant` in `tests/Pest.php`)
  — billing models are org-owned and will auto-fill via it.

---

## 8. Open questions to settle while building

- Per-module vs bundled pricing (affects `plans` columns / `PlanInterval` usage).
- CHIP subscriptions vs reminder-driven FPX renewals.
- Whether `attendees` is metered per check-in or per registered participant.
- Data residency: spec says DO Singapore; some MY gov clients may mandate
  in-country hosting — note for deployment (Phase F), not billing code.

---

## 9. Consistency checklist (do not drift from Phases A–D)

- [ ] `organization_id` = plain indexed column, no inline FK (SQLite-safe).
- [ ] Org-owned models use `BelongsToOrganization`; `plans` does not.
- [ ] Enums are backed + `implements HasLabel` + `use HasOptions`.
- [ ] All outbound mail + heavy work `dispatch()`ed to the queue.
- [ ] Stateless endpoints (webhook) in `routes/api.php`, no session/CSRF, throttled.
- [ ] One migration per logical change; `php artisan migrate` on dev after each.
- [ ] `vendor/bin/pint --dirty` + full suite green before each commit.
- [ ] Secrets in `.env` / `config/services.php`, never committed.
