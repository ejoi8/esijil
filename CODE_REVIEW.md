# eSIJIL — Code Review & Improvement Report

**Date:** 2026-06-17
**Scope:** Full codebase audit — performance, logic/correctness, tests, code style, pattern consistency, and architecture/security/maintainability.
**Method:** Five parallel reviewers, each auditing the whole codebase through one lens; highest-impact findings independently re-verified against source. Findings tagged with a **confidence** note where relevant; items marked *Verified* were re-read directly during compilation.

---

## 1. Executive summary

eSIJIL is a well-structured Laravel 13 + Filament 5 app. The code is **disciplined**: relation managers eager-load, heavy imports use `chunkById`, serial-number issuance is race-safe, the public-flow transaction is correctly scoped, SMTP passwords are encrypted, DomPDF is sandboxed (no remote assets + chroot), and Pint reports the codebase essentially clean (**1 file** flagged, auto-generated). Test coverage of the certificate/template surface is genuinely strong.

The weaknesses cluster in four areas:

1. **The "failing test" is a flaky test, not a code bug** — and the root cause (a randomized factory field) is a latent flake source for any test that inspects `source`.
2. **No authorization layer** — every authenticated admin can do everything, including managing other users and reading all email/PII.
3. **A privacy/secret leak** — signed registration URLs (with their HMAC signature) are sent to a third-party QR service.
4. **Documentation drift** — README/AI_HANDOVER still describe DB columns that were dropped, actively misleading future maintainers.

### Fix-first priority list

| # | Sev | Area | Finding | Effort |
|---|-----|------|---------|--------|
| L1 | 🔴 Critical | Test | Flaky test: `RegistrationFactory` randomizes `source`; assertion is a coin-flip | XS |
| S1 | 🟠 High | Security | No roles/authorization — any user manages Users + reads all PII/email logs | M |
| S2 | 🟠 High | Security | Signed registration URL (HMAC signature) leaked to `api.qrserver.com` | S |
| C1 | 🟠 High | Perf | Main Registrations admin table N+1s (no eager loading) | XS |
| B1 | 🟠 High | Logic | Public certificate download missing `certificate_type` null-guard (parity bug) | XS |
| P2 | 🟠 High | Perf | Certificate renderer reloads relations 3× per download (`load` vs `loadMissing`) | XS |
| P3 | 🟡 Med | Perf | Spatie settings read uncached from DB on every public request | XS |
| D1 | 🟡 Med | Docs | README/AI_HANDOVER document dropped columns as live | S |
| A1 | 🟡 Med | Data | Soft-delete vs unique constraint: public form silently restores deleted participants | M |
| A2 | 🟡 Med | Data | `RefreshLegacyEsijilSeeder` hard-deletes/truncates live tables, no transaction | S |

Legend: 🔴 Critical · 🟠 High · 🟡 Medium · 🟢 Low. Effort: XS (<15 min) · S (<1h) · M (half-day) · L (multi-day).

---

## 2. Logic & correctness

### B0 🔴 Critical — The failing test is flaky; root cause is a randomized factory field *(Verified — reproduced empirically by two independent reviewers: e.g. 6 isolated runs → PASS/PASS/FAIL/PASS/PASS/PASS)*
- **Where:** `database/factories/RegistrationFactory.php:45`, `app/Services/Certificates/RegistrationCertificateIssuer.php:24`, asserted at `tests/Feature/CertificateTemplateManagementTest.php:615`.
- **What:** The factory sets `'source' => fake()->randomElement(['public_form', 'admin'])`. The factory's `afterCreating` hook (`:19-25`) auto-issues a certificate, and `issueFor()` copies `$registration->source` into `certificate_metadata['source']` (`:24`). The test creates a registration **without pinning `source`**, then asserts `certificate_metadata.source === 'public_form'` — so it passes only ~50% of runs.
- **Verdict:** **The test is wrong, not the production code.** Real public registrations set `source = 'public_form'` (`EventRegistrationController.php:173`) and admin issuance defaults to `'admin'`, so production is correct.
- **Fix:** Pin the input in the test:
  ```php
  $registration = Registration::factory()->for($event)->create([
      'source' => 'public_form',
      'certificate_metadata' => null,
  ]);
  ```
  And **make the factory deterministic** — default `source` to `'public_form'` (add a `->source($x)` state if variety is needed). Randomizing a business-meaning field that tests assert on is a latent flake generator. Note `attendance_status` (`:42`) is randomized the same way (no test depends on it *yet*).
- **Bonus cleanup:** the literal `'public_registration'` fallback in `issueFor():24` is never reached in practice and is inconsistent with the three real sources (`legacy_import` / `public_form` / `admin`).

### B1 🟠 High — Public certificate download missing the `certificate_type` null-guard *(Verified)*
- **Where:** `app/Http/Controllers/CertificateLookupController.php:69-79`.
- **What:** This `download()` only checks session ownership. The other two download endpoints (`EventRegistrationController.php:83`, `AdminRegistrationCertificateDownloadController.php:15`) both `abort_unless($registration->certificate_type !== null, 404)`. Without it, a participant can hit `/certificates/{id}/download` for a registration with `certificate_type = null` (e.g. legacy imports); the renderer falls back to a blank-ish PDF **and silently persists `certificate_issued_at` + a serial number** for a registration the UI says has no certificate.
- **Fix:** Add `abort_unless($registration->certificate_type !== null, 404);` after the session check, matching the other two controllers.

### B2 🟡 Med — Certificate edit re-issues on every save, clobbering per-registration template overrides
- **Where:** `app/Filament/Resources/Registrations/Pages/EditRegistration.php:19-22` → `RegistrationCertificateIssuer::issueFor`.
- **What:** Any admin edit (even just `remarks`) calls `issueFor`, which `forceFill`s `certificate_type` / `certificate_template_id` / `certificate_template_key` from the **current event** (`RegistrationCertificateIssuer.php:17-27`). A deliberately-overridden per-registration template is silently reverted to the event's on an unrelated save.
- **Fix:** Only re-issue when relevant fields changed, or move re-issuance behind an explicit "Re-issue certificate" action.

### B3 🟡 Med — Null `starts_at` would fatal in `registrationLinkExpiresAt()`
- **Where:** `app/Models/Event.php:73-76` — `($this->ends_at ?? $this->starts_at)->copy()->addDay()`.
- **What:** The coalesce guards `ends_at` but not `starts_at`. If both are null (plausible via legacy import), `publicRegistrationUrl()` throws "call to copy() on null". `starts_at` is normally required, so this is defensive.
- **Fix:** `($this->ends_at ?? $this->starts_at ?? now())->copy()->addDay()`.

### B4 🟡 Med — Lookup download ignores event status (inconsistent with registration flow)
- **Where:** `CertificateLookupController.php:69-78` vs `EventRegistrationController` which gates on `EventStatus::Published`. Confidence: Medium — may be intended (certificates downloadable post-event).
- **What:** A participant can download a certificate for a `draft`/unpublished event via lookup. Route-model binding already excludes soft-deleted rows, so this is purely the event-status dimension.
- **Fix:** If certificates should only be downloadable for published/completed events, add an event-status guard; otherwise document that lookup intentionally ignores status.

### B5 🟢 Low — `event-registration` throttle keys on `fullUrl()` instead of event id
- **Where:** `app/Providers/AppServiceProvider.php:35-39`.
- **What:** Keying on `fullUrl()` (which includes the signature/expiry and any appended query param) is conceptually fragile. The signed-URL middleware blocks param tampering, so real-world exploitability is low, but it couples the throttle to URL shape.
- **Fix:** Key by `$request->ip().'|'.$request->route('event')?->id.'|'.sha1((string) $request->input('nokp'))`.

> **Confirmed correct (no action):** participant/registration upsert + `withTrashed()` restore + `QueryException` race fallback; serial-number generation (atomic conditional UPDATE + 5 retries against the unique index); notification fires only on `wasRecentlyCreated` and is dispatched outside the transaction with `afterCommit`; session-ownership `===` int comparisons; `nokp` normalization in both Form Requests; enum `fromMixed` null-fallbacks. The "refresh template on download" behavior (`PdfmeCertificateRenderer.php:59-76`) is intentional design (see A4).

---

## 3. Performance

### C1 🟠 High — Main Registrations admin table has no eager loading (N+1) *(Verified)*
- **Where:** `app/Filament/Resources/Registrations/RegistrationResource.php` (no `getEloquentQuery()` override); table renders `event.title`, `participant.full_name`, `participant.nokp` (`RegistrationsTable.php:27-35`).
- **What:** The relation managers eager-load, but the **primary** Registrations screen doesn't — ~2 extra queries per row (≈20 per default page).
- **Fix:**
  ```php
  public static function getEloquentQuery(): Builder
  {
      return parent::getEloquentQuery()->with(['event', 'participant']);
  }
  ```

### P2 🟠 High — Certificate renderer reloads relations up to 3× per download
- **Where:** `app/Services/Certificates/PdfmeCertificateRenderer.php:35`, `:675`, `:109→:673`.
- **What:** `render()` calls `$registration->load(...)`, then `currentCertificateTemplateForRegistration()` calls `load(...)` again, and `resolveCurrentTemplateSchema()` triggers it a third time. `load()` (unlike `loadMissing()`) re-runs the queries each time; the download controllers already eager-loaded these relations.
- **Fix:** Use `loadMissing(...)` in both spots so relations load at most once.

### P3 🟡 Med — Spatie settings read uncached from DB on every request
- **Where:** `config/settings.php:75-76` (`SETTINGS_CACHE_ENABLED` defaults `false`); `.env` doesn't set it. `CertificateSettings` is resolved on every render; `NotificationSettings` on every registration.
- **Fix:** Set `SETTINGS_CACHE_ENABLED=true` (and `SETTINGS_CACHE_MEMO=true`). Broad win, trivial effort.

### P4 🟡 Med — Missing index on `registrations.certificate_type`
- **Where:** `database/migrations/2026_04_26_065610_merge_certificates_into_registrations_table.php:13-24` (only `cert_serial_number` unique; FK auto-indexes `certificate_template_id`).
- **What:** `certificate_type` is filtered by the `issuedCertificates()` relations on three models (`Event.php:70`, `Participant.php:41`, `CertificateTemplate.php:86`) and by the seeder backfill, but it's unindexed.
- **Fix:** Add `$table->index('certificate_type')` (consider composite `['event_id', 'certificate_type']`).

### P5 🟡 Med — DomPDF re-parses the full TTF font on every render
- **Where:** `PdfmeCertificateRenderer.php:593-627` (`Font::load($path)->parse()` on a ~1 MB TTF), `:274-287`, `:184-200`. The `$fontMetricCache` is per-instance and the renderer isn't a singleton.
- **Fix:** Cache font metrics in `Cache` keyed by font path + mtime — they never change at runtime.

### P6 🟡 Med — Node process spawned per certificate when pdfme renderer is selected
- **Where:** `PdfmeNodeCertificateGenerator.php:21-55`. Gated behind the non-default `pdfme` renderer.
- **Fix:** If pdfme is used in production, move generation to a queued job or a long-lived Node service; at minimum document that fork-per-render won't scale under concurrent downloads.

### P7 🟢 Low — Other items
- `LegacyEsijilSeeder::primeRegistrationImportCaches()` (`:178-197`) loads **all** participants + registrations into memory unchunked — fine for a one-off seeder, risky on a large legacy DB; prefer `select()` + `cursor()`.
- The certificate-designer JS bundle is **5.4 MB** (`public/build/assets/certificate-template-designer-*.js`), loaded only on the Designer page — code-split / ensure gzip/brotli.
- Legacy asset inlining (`PdfmeTemplateLegacyAssetInliner.php:142-159`) base64-encodes images per render until the template self-heals; prefer inlining at save-time (the Designer already does).

> **Confirmed correct:** Filament `->counts()` compiles to a single `withCount` subquery (not N+1); the public `store()` transaction excludes the slow rendering; serial-number issuance uses an atomic conditional UPDATE.

---

## 4. Tests

**Quality: B (good but uneven).** 15 files, ~81 tests, ~357 assertions. The certificate/template/designer surface is tested thoroughly (resolution precedence, designer save, duplication, font normalization, legacy inlining, DomPDF internals). Time is frozen with `Carbon::setTestNow`, Node/pdfme is mocked, factories are accurate, `RefreshDatabase` is applied per-file.

### Issues
- **T1 🔴** — The flaky test (see **B0**). *Fix the test, not the code.*
- **T2 🟢** — Dead boilerplate: delete `tests/Unit/ExampleTest.php` (tautological) and `tests/Feature/ExampleTest.php` (`GET / == 200`, already covered by `CertificateLookupTest.php:19-24`).
- **T3 🟡** — `RefreshDatabase` is global-commented in `tests/Pest.php:17`; each file opts in manually. Consider uncommenting the global to prevent a new file silently running without it.
- **T4 🟢** — `EventRegistrationTest.php:198` hand-computes the rate-limiter's internal cache key, duplicating `AppServiceProvider.php:35-39`. With `array` cache + `RefreshDatabase` the manual `clear()` is likely unnecessary.
- **T5 🟢** — Several near-duplicate "refreshes an issued certificate…" tests (`CertificateTemplateManagementTest.php:407-563`) are prime candidates for a Pest dataset.
- **T6 🟢** — Some `assertDontSee` assertions check random factory values (`venue = fake()->address()`), which is low-signal.

### Top missing tests (prioritized)
1. **Cross-participant 403** — valid lookup session for A, download B's certificate → 403 (`CertificateLookupController.php:73`).
2. **Certificate-lookup throttle** — 6× POST `/semakan` → 429 (untested; event-registration throttle *is* tested).
3. **Soft-delete restore on re-registration** — trashed participant+registration restored (not duplicated), no duplicate notification.
4. **`certificate_type === null` → 404** on both download controllers (and after fixing **B1**, on the lookup endpoint too).
5. **DomPDF renderer path** — with default renderer, `render()` returns `%PDF` *and* `PdfmeNodeCertificateGenerator::generate` is never called (`shouldNotReceive`).
6. **Signed-URL tampering** → 403 (distinct from the existing expiry/absence tests).
7. **Window edge cases** — closing-instant boundary, all-null window (open), null-`ends_at` expiry.
8. **Lookup `nokp` normalization** — search `900101-01-5555` resolves the participant (registration side is tested, lookup side isn't).
9. **Schema-guard** (optional) — assert `Schema::hasColumn` is `false` for the dropped `certificate_template_update_mode` / `certificate_template_snapshot` so they can't silently reappear.

---

## 5. Code style & pattern consistency

**Pint: essentially clean.** `vendor/bin/pint --test` flags **only one** file — `bootstrap/providers.php` (`fully_qualified_strict_types`, `single_line_after_imports`), which is auto-generated. *(Verified by running Pint.)* Note `.styleci.yml` disables `no_unused_imports`, and CI has no Pint step (see O3), so dead imports can accumulate unflagged.

### Duplication worth extracting
- **E1** — `nokp` digits-only normalization is duplicated in `LookupCertificateRequest::nokp()` and `StoreEventRegistrationRequest::nokp()`. Extract to a shared trait/helper (and reuse for the `nokp` format rule in **U1**).
- **E2** — Session-ownership `abort_unless(...403)` logic is repeated across `CertificateLookupController` and `EventRegistrationController`. Extract a small guard.
- **E3** — The `issuedCertificates()` `whereNotNull('certificate_type')` relation is defined identically on `Event`, `Participant`, and `CertificateTemplate` — fine, but a shared scope would centralize the "issued = has type" definition.

### Magic strings → enums/consts
- **E4** — `'public_form'`, `'admin'`, `'legacy_import'` (`source`), `'member'`/`'non_member'` (`membership_status`), `'registered'`/`'attended'`/`'no_show'` (`attendance_status`), and session keys (`certificate_lookup_participant_id`, `event_registration_success_id`) are bare strings scattered across controllers, factories, and seeders. Promote to backed enums / class constants (mirrors the existing `EventStatus`/`CertificateType` pattern — see **U4**).

### Filament resource consistency (all Low)
- **E5** — `Users` and `CertificateTemplates` resources have **no Infolist/View page** — unfinished scaffolding vs the other four resources. (The Users resource's *lack* of soft-delete UI is **correct** — `User` has no `SoftDeletes`.)
- **E6** — `CertificateTemplates` is the only resource setting explicit `$navigationLabel`/`$modelLabel`/`$pluralModelLabel` (`CertificateTemplateResource.php:24-28`) — set on all or none.
- **E7** — `CertificateTemplatesTable` is the only table without `defaultSort()` — add `->defaultSort('name')` for parity.
- **E8** — `CertificateTemplates` deviates from the View→Edit row-action convention (uses Replicate + Designer + Settings). Defensible (the Designer replaces edit/view) — add a code comment noting why.
- **E9** — `Registration` resource lacks `$recordTitleAttribute`.

---

## 6. Security

### S1 🟠 High — No authorization layer: every authenticated user has full CRUD on everything
- **Where:** `app/Models/User.php:35-38` (panel access only checks panel id); no policies/Gates; email-logs plugin explicitly `authorizeUsing(fn () => true)` (`AuthPanelProvider.php:55`).
- **What:** Any user can create/edit/delete other users (including the admin's password/email via `UserForm`), read all participant PII, delete events, and read every sent email. No protection against deleting the last user.
- **Fix:** Add a minimal role model (`is_admin` boolean or `spatie/laravel-permission`). Gate `UserResource`, `ManageApplicationSettings`, and the email-logs plugin behind an admin check; prevent deleting the last user.

### S2 🟠 High — Signed registration URL (with HMAC signature) leaked to third-party `api.qrserver.com` *(Verified)*
- **Where:** `EventInfolist.php:160` and `EventForm.php:201` — `'https://api.qrserver.com/v1/create-qr-code/?...&data='.rawurlencode($event->publicRegistrationUrl())`.
- **What:** The QR `<img>` src embeds the full signed URL (including the `signature` derived from `APP_KEY`). Every admin view transmits a working capability token + the registration link to a third party (and via the browser's network/Referer logs).
- **Fix:** Generate QR codes **locally** as inline `data:` URIs (`chillerlan/php-qrcode` is already in `composer.lock`, or `endroid/qr-code`) so the signed URL never leaves the server.

### S3 🟡 Med — `.env.example` ships `APP_ENV=local` + `APP_DEBUG=true` + `LOG_LEVEL=debug`
- **What:** `composer setup` copies `.env.example` → `.env` verbatim; if used in production unchanged it yields full stack traces (source/env/query leakage).
- **Fix:** Ship safe defaults (`production` / `false` / `warning`); document local overrides separately.

### S4 🟡 Med — No security headers anywhere
- **Where:** `bootstrap/app.php:13-15` (empty `withMiddleware`). No CSP/X-Frame-Options/X-Content-Type-Options/HSTS.
- **Fix:** Append a header middleware setting at least `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, and HSTS over TLS.

### S5 🟡 Med — No application logging of security-relevant events
- **What:** Zero `Log::`/`report()` calls in `app/`. Failed lookups, 403s, render failures, and the swallowed mail-boot exception are all silent — no audit trail for enumeration attempts.
- **Fix:** Add structured logging for render failures, repeated 403s, and the mail-boot catch. **Do not log raw `nokp`/PII** (note `MAIL_MAILER=log`, the default, writes full PII to `storage/logs`).

### S6 🟢 Low — `/dev/{id}` impersonation route
- **Where:** `routes/web.php:33-59`. *Verified `local`-gated with proper session rotation — good.* Two notes: it logs in any matched user with no further check (full-takeover vector on a LAN-reachable local box), and it redirects to `/auth/profile#{id}` which may 404 (the panel doesn't enable `->profile()`).
- **Fix:** Keep the env gate; optionally bind to `127.0.0.1`; verify/enable the profile route or redirect to the dashboard.

> **Confirmed safe:** SMTP password is encrypted via `MailSettings::encrypted()`; DomPDF has `setIsRemoteEnabled(false)` + chroot; throttle keys hash `nokp` rather than storing it raw.

---

## 7. Error handling & resilience

- **R1 🟡 Med** — `bootstrap/app.php:16-17` `withExceptions` is empty. pdfme/Node failures (`PdfmeNodeCertificateGenerator.php:35-54`) propagate as raw 500s — unlogged with debug off, or **leaking Node stderr to the public** with debug on. Add a `report`/`render` handler that logs server-side and returns a friendly message.
- **R2 🟡 Med** — `AppServiceProvider.php:42-55` `configureMailSettings()` catches **all** `Throwable` silently. Correct for the pre-migration boot case, but masks genuine misconfig (e.g. decryption failure on the SMTP password). Narrow the catch or log at `warning`.
- **R3 🟢 Low** — Queued `RegistrationSubmitted` requires a running worker (`QUEUE_CONNECTION=database`) — undocumented. If the worker is down, registrations succeed but emails silently never send and nothing surfaces `failed_jobs`. Document the production worker; consider a `failed()` handler.
- **R4 🟢 Low** — A structurally-invalid `pdfme_template` (e.g. missing `basePdf`) renders a **silent blank PDF** rather than erroring. Validate template shape before rendering.

---

## 8. Documentation drift

- **D1 🟡 Med** — `README.md:106,262` and `AI_HANDOVER.md:88,90,156,192` still describe `certificate_template_snapshot` and `certificate_template_update_mode` as live — but migration `2026_05_01_230738` **dropped both columns**. `AI_HANDOVER.md:192` even instructs agents "Do not ignore `certificate_template_update_mode`," now actively harmful. Replace with the real model: templates resolve **live** from the linked `CertificateTemplate`.
- **D2 🟢 Low** — README lists `certificate_file_path` as current state, but it's **never written** (see X1).
- **D3 🟢 Low** — ~70 markdown links in README/AI_HANDOVER use absolute WSL paths (`/mnt/c/laragon/www/esijil/...`) — broken everywhere. Convert to repo-relative paths.
- **D4 🟢 Low** — Docs say downloads "do not require Node," true only for the default DomPDF renderer; selecting the pdfme renderer **does** require Node. Document both paths.

---

## 9. Dead / unused code

- **X1 🟢** — `certificate_file_path` (column + `$fillable` + seeder) is **never assigned** — `StoredCertificatePdf` streams on the fly and only writes `certificate_issued_at`; a test even asserts it stays null. Drop the column (and docs) unless persisted PDFs are planned.
- **X2 🟢** — Delete both `ExampleTest.php` files (see T2).
- **X3 🟢** — `FilamentInfoWidget` import (`AuthPanelProvider.php:16`) is retained but only referenced in a commented-out line. Remove (or re-enable).
- **X4 🟢** — Re-enable `no_unused_imports` in `.styleci.yml` so the above can't recur.

---

## 10. Data integrity

- **A1 🟡 Med** — `participants.nokp` UNIQUE and `registrations (event_id, participant_id)` UNIQUE **count soft-deleted rows**, forcing the `withTrashed()` + `restore()` + `QueryException`-catch workaround in `EventRegistrationController.php:113-197`. Side effect: **a public registrant can silently un-delete a participant an admin removed**, and the restored row's name/email are overwritten by the submission. Decide on one model: drop `SoftDeletes` on these two, or make uniqueness `deleted_at`-aware. Document the restore-on-register behavior — it has authorization implications.
- **A2 🟡 Med** — `RefreshLegacyEsijilSeeder` (`:54-91`) hard-`DELETE`s registrations/events and `TRUNCATE`s mirrored tables **outside any transaction** and with no production guard. A mid-run failure leaves a partially-truncated DB. Wrap in `DB::transaction`, add a confirm/`--force` guard. (It's manual-invocation only — not in `DatabaseSeeder` — which limits blast radius.)
- **A3 🟢 Low** — Registrations `cascadeOnDelete` on event/participant FKs while those models soft-delete; a `forceDelete()` hard-wipes registrations + issued-cert state with no trail. Deleting a `CertificateTemplate` `nullOnDelete`s `certificate_template_id` on historical registrations, changing what they render (see A4). Consider blocking template deletion when referenced by issued certificates.
- **A4 🟡 Med (domain decision)** — Since the snapshot column was dropped, **issued certificates are not reproducible**: `resolveTemplate()` pulls the *current* template at download time and re-points the registration to the event's current template (`PdfmeCertificateRenderer.php:59-76`). Editing a template or swapping the event's template **retroactively changes already-issued certificates** (design, signature, text). If certificates are official documents, re-introduce a per-registration immutable snapshot at issuance. If live re-rendering is intended, document it prominently.

---

## 11. Domain / UX improvements

- **U1 🟡** — No `nokp` format validation. Both Form Requests only check `string|max:20` then strip non-digits, so `"abc"` → `""` is accepted and a 3-digit value passes — polluting the unique identity. Add a shared rule (`^\d{12}$` + optional `YYMMDD`/state-code checks); reject empty post-normalization.
- **U2 🟡** — No audit trail for certificate issuance/regeneration/downloads or who did them. Only `Event.created_by` exists. Consider `spatie/laravel-activitylog` or a lightweight events table (actor + action + timestamp).
- **U3 🟢** — No bulk certificate issuance / explicit "regenerate after template fix" action; issuance is one-at-a-time at registration. Add a Filament bulk action on the registrations/issued-certificates tables (ties to A4 versioning).
- **U4 🟢** — `attendance_status`, `membership_status`, `source` are uncast free-text strings (unlike `EventStatus`/`CertificateType`). Introduce backed enums + model casts for consistent validation/reporting (ties to E4).
- **U5 🟢** — No health indicator for the chosen renderer/external deps. After moving QR in-house (S2), add a "test render" action on the settings page (mirroring the existing test-email/test-notification actions) so admins can validate Node/pdfme before relying on it.

---

## 12. Config & ops

- **O1 🟡 Med** — `.env.example` omits the `LEGACY_DB_*` block (the `legacy` connection silently falls back to primary DB credentials — `config/database.php:67-85`) and `CERTIFICATE_PDFME_NODE_BINARY` (`config/certificates.php:5`). Add a commented block for both.
- **O2 🟡** — Document the required production queue worker (`queue:work` + Supervisor/systemd), or note `QUEUE_CONNECTION=sync` as a small-deployment alternative (ties to R3).
- **O3 🟢** — CI runs only `php artisan test` (`.github/workflows/tests.yml`). Add a `vendor/bin/pint --test` step (and optionally PHPStan/Larastan).
- **O4 ℹ️** — `composer.json` requires PHP `^8.3`; runtime is 8.5.6; CI matrix covers 8.3/8.4/8.5 — no real mismatch. Optionally pin `config.platform.php` for reproducible installs.

> **Confirmed present:** `/up` healthcheck (`bootstrap/app.php:11`).

---

## 13. What's already done well (keep it)

- Race-safe serial-number issuance (atomic conditional UPDATE + retry).
- Public `store()` transaction correctly scoped; slow rendering kept out of it.
- Idempotent participant/registration upsert with `withTrashed` restore + constraint-collision fallback.
- Notifications queued with `afterCommit`, sensible tries/backoff, dispatched post-transaction.
- SMTP password encrypted; DomPDF sandboxed (no remote assets + chroot); throttle keys hash `nokp`.
- Time frozen in time-sensitive tests; Node/pdfme mocked; accurate factories; consistent `RefreshDatabase`.
- Pint-clean codebase; consistent Filament resource scaffolding (Schemas/Tables/Pages split).

---

## Appendix — suggested sequencing

1. **Quick wins (XS, ~1 hour total):** B0 (flaky test + factory), B1 (null-guard), C1 (eager load), P2 (`loadMissing`), P3 (settings cache env), X2/X3 (delete dead code).
2. **Short (S, a few hours):** S2 (local QR), D1–D4 (doc fixes), P4 (index), A2 (seeder transaction), O1 (.env.example).
3. **Medium (M, half-day each):** S1 (roles/authorization), A1 (soft-delete vs unique decision), R1/R2 (exception handling + logging), the prioritized missing tests in §4.
4. **Domain decisions:** A4 (certificate immutability/snapshot), U1 (nokp validation), U2 (audit trail).

*This report consolidates five independent reviews. The Critical and High items in §2–§6 were re-verified directly against source during compilation; lower-severity items are reviewer-reported and noted where confidence is below high.*
