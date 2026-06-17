# AI Handover

This file is the minimum context a new AI should read before changing this repo.

## What This App Is

eSIJIL is a Laravel 13 + Filament 5 application for:

- managing branches, participants, events, registrations, and certificate templates
- issuing event certificates as PDFs
- allowing public certificate lookup by `nokp`
- allowing public event registration through signed links

Admin UI lives at `/auth`.

Public flows live in [routes/web.php](routes/web.php).

## The Most Important Domain Fact

Do not assume there is a separate active certificate entity anymore.

Current state:

- there is no active `Certificate` model in `app/Models`
- there is no active `CertificateResource`
- the old `certificates` table was merged into `registrations`
- issued certificate state now lives directly on `Registration`

If you change certificate behavior, start with:

- [Registration.php](app/Models/Registration.php)
- [2026_04_26_065610_merge_certificates_into_registrations_table.php](database/migrations/2026_04_26_065610_merge_certificates_into_registrations_table.php)
- [RegistrationCertificateIssuer.php](app/Services/Certificates/RegistrationCertificateIssuer.php)
- [StoredCertificatePdf.php](app/Services/Certificates/StoredCertificatePdf.php)
- [PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)

## Current Mental Model

- `Participant` = a person
- `Event` = the event definition and certificate defaults
- `Registration` = participant joined event, plus any issued-certificate data
- `CertificateTemplate` = reusable design definition for rendering

“Issued Certificates” in the UI means registrations where certificate-related fields are present.

## Public Flows

### Certificate lookup

Handled by [CertificateLookupController.php](app/Http/Controllers/CertificateLookupController.php).

Key facts:

- lookup is by `nokp`
- POST `/semakan` is throttled
- successful lookup stores `certificate_lookup_participant_id` in session
- downloads are allowed only when that session participant matches the registration’s participant
- the download route is `/certificates/{registration}/download`

### Event registration

Handled by [EventRegistrationController.php](app/Http/Controllers/EventRegistrationController.php).

Key facts:

- public form requires a signed URL
- event must be `published`
- registration window must be open
- participant is created or updated by normalized `nokp`
- registration is deduplicated by `event_id + participant_id`
- certificate issuance happens immediately through `RegistrationCertificateIssuer`
- success/download access is protected by `event_registration_success_id` in session

## Certificate Rendering Rules

Certificate templates are designed with pdfme in the browser, but downloads are rendered server-side with DomPDF.

Important files:

- [config/certificates.php](config/certificates.php)
- [resources/js/certificate-template-designer.js](resources/js/certificate-template-designer.js)
- [app/Services/Certificates/PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)
- [resources/views/certificates/pdfme-dompdf.blade.php](resources/views/certificates/pdfme-dompdf.blade.php)
- [public/fonts/certificates](public/fonts/certificates)

Important behavior:

- downloads render from the current registration record
- templates are resolved **live** from the linked `CertificateTemplate` — there is no stored snapshot (the `certificate_template_snapshot` / `certificate_template_update_mode` columns were dropped), so editing a template retroactively changes already-issued certificates. This is intentional.
- preview and downloaded PDF can still differ slightly because `@pdfme/ui` and DomPDF do not measure text exactly the same way
- image fields are explicitly fitted in the server renderer to preserve pdfme-style aspect ratio handling more closely

If you change template behavior, inspect:

- [CertificateTemplate.php](app/Models/CertificateTemplate.php)
- [CertificateTemplateSeeder.php](database/seeders/CertificateTemplateSeeder.php)
- [Designer.php](app/Filament/Resources/CertificateTemplates/Pages/Designer.php)
- [PdfmeTemplateFactory.php](app/Services/Certificates/PdfmeTemplateFactory.php)
- [PdfmeTemplateLegacyAssetInliner.php](app/Services/Certificates/PdfmeTemplateLegacyAssetInliner.php)

## Admin Surface

The Filament panel is configured in [AuthPanelProvider.php](app/Providers/Filament/AuthPanelProvider.php).

Current resources:

- branches
- participants
- events
- registrations
- certificate templates

There is no standalone certificate resource.

### Authorization

Access is role-based (spatie/laravel-permission):

- `/auth` requires the `admin` or `staff` role (`User::canAccessPanel`).
- Resources are gated by `App\Policies\*` mapping abilities to `{resource}.{view|create|update|delete|forceDelete}` permissions.
- The `admin` role is a super-admin and bypasses every policy via `Gate::before` (see `AppServiceProvider`).
- `staff` may operate on branches, participants, events, registrations and certificate templates — but not Users, Application Settings, or Email Logs.
- Roles/permissions are seeded by `RolesAndPermissionsSeeder`; `admin@admin.com` is assigned `admin`.
- The last `admin` user cannot be deleted (guarded in the `User` model).

Useful resource areas:

- [app/Filament/Resources/Events](app/Filament/Resources/Events)
- [app/Filament/Resources/Registrations](app/Filament/Resources/Registrations)
- [app/Filament/Resources/CertificateTemplates](app/Filament/Resources/CertificateTemplates)

## Seeded Defaults

Default seeding currently:

- creates `admin@admin.com` / `password`
- imports legacy data
- normalizes branches
- ensures default certificate templates exist

Template defaults:

- `default-participation`
- `default-attendance`

Attendance slip currently reuses the participation layout with the title switched to `Slip Kehadiran`.

## Tests To Trust

When changing behavior, read the matching test first.

Primary feature tests:

- [CertificateLookupTest.php](tests/Feature/CertificateLookupTest.php)
- [EventRegistrationTest.php](tests/Feature/EventRegistrationTest.php)
- [EventResourceTest.php](tests/Feature/EventResourceTest.php)
- [CertificateTemplateManagementTest.php](tests/Feature/CertificateTemplateManagementTest.php)
- [DomainConsistencyTest.php](tests/Feature/DomainConsistencyTest.php)

What they protect:

- public lookup flow
- signed registration flow
- event admin form and relation managers
- template seeding and snapshot behavior
- enum casting and template/type consistency

## Local Dev Commands

Initial setup:

```bash
composer setup
```

Daily dev:

```bash
composer dev
```

Tests:

```bash
php artisan test --compact
```

Formatting for PHP edits:

```bash
vendor/bin/pint --dirty --format agent
```

## Common Mistakes To Avoid

- Do not reintroduce a separate certificate domain model unless the product explicitly changes.
- Do not document or code against a `CertificateResource`; it does not exist in the current app.
- Do not assume event registration is public-listable; there is no public `/events` index.
- Do not bypass `nokp` normalization rules in the request classes.
- Do not assume Node is required for certificate downloads; the default DomPDF renderer needs no Node. Only the optional `pdfme` renderer (toggled in Application Settings) shells out to Node.
- Do not reintroduce a template snapshot or `certificate_template_update_mode`; templates resolve live by design (those columns were dropped).
- Do not change public route semantics without updating the session-based authorization checks and tests.
- Do not give panel access without a role: `/auth` requires the `admin` or `staff` role, resources are gated by policies, and the `admin` role bypasses all policies via `Gate::before`. Settings and Email Logs are admin-only.
