# eSIJIL

eSIJIL is a Laravel 13 + Filament 5 application for managing events, participants, registrations, certificate templates, and certificate PDF issuance.

The app has two main surfaces:

- a Filament admin panel at `/auth`
- a public surface for certificate lookup and signed event registration

See [AI_HANDOVER.md](AI_HANDOVER.md) for a compact project brief aimed at future AI agents.

## Current Scope

The current application supports:

- branch management
- participant management
- event management
- registration management
- certificate template management with a pdfme-based designer
- certificate PDF generation and download
- public certificate lookup by `nokp`
- public event registration through signed links
- application settings for SMTP mail and notification controls
- queued registration confirmation notifications
- email log viewing and resend support in Filament
- legacy import and normalization seeders

## Tech Stack

- PHP 8.3
- Laravel 13
- Filament 5
- Livewire 4
- Pest 4
- Tailwind CSS 4
- Spatie Laravel Settings
- ejoi8 Filament Email Logs
- pdfme for template design
- DomPDF for server-side certificate PDF downloads

## Main Surfaces

### Admin panel

The admin panel is defined by [AuthPanelProvider.php](app/Providers/Filament/AuthPanelProvider.php) and lives at `/auth`.

Current resources:

- `BranchResource`
- `ParticipantResource`
- `EventResource`
- `RegistrationResource`
- `CertificateTemplateResource`
- `EmailLogResource` from `ejoi8/filament-email-logs`

Navigation groups:

- `Operations`
- `Directory`
- `Certificates`
- `Settings`

Settings pages and plugin resources:

- `Application Settings` - SMTP, sender, notification toggles, and test email actions
- `Email Logs` - logged outgoing emails with preview and resend support

### Public routes

Public routes live in [routes/web.php](routes/web.php).

Current public flows:

- `GET /` - landing page
- `GET /semakan` - certificate lookup form
- `POST /semakan` - lookup submit, throttled by `certificate-lookup`
- `GET /semakan/keputusan` - lookup result page
- `GET /certificates/{registration}/download` - download a registration certificate after a valid lookup session
- `GET /events/{event}/register` - signed event registration page
- `POST /events/{event}/register` - signed event registration submit
- `GET /registrations/{registration}/success` - registration success page
- `GET /registrations/{registration}/certificate` - certificate download for the current registration session

## Domain Model

Core models:

- `Branch` - participant grouping / directory metadata
- `Participant` - a person who can join events
- `Event` - an event with schedule, status, registration window, and default certificate settings
- `Registration` - the participant-to-event record, including issued certificate data
- `CertificateTemplate` - reusable certificate template metadata, schema, and `pdfme_template`

Important current state:

- there is no active `Certificate` model or `certificates` table in the current application state
- issued document state is stored directly on `registrations`
- event relation managers still expose “Issued Certificates”, but they are filtered registration records

Certificate-related columns currently stored on `registrations` include:

- `certificate_type`
- `certificate_template_id`
- `certificate_template_key`
- `cert_serial_number`
- `certificate_issued_at`
- `certificate_metadata`

The merge from the older separate certificate table is captured in [2026_04_26_065610_merge_certificates_into_registrations_table.php](database/migrations/2026_04_26_065610_merge_certificates_into_registrations_table.php).

## Business Rules

### Certificate types

The app supports two certificate types via [CertificateType.php](app/Enums/CertificateType.php):

- `participation_certificate`
- `attendance_slip`

Their seeded default template keys are:

- `default-participation`
- `default-attendance`

### Issuance model

The current domain assumes one issued document context per registration.

That means:

- a registration may carry certificate issuance data
- “issued certificate” behavior is represented through the registration record
- download and rendering work from `Registration`, not from a separate certificate entity

### Event publication and registration access

Public event registration requires:

- a valid signed URL
- event status `published`
- the current time to be within the configured registration window, if one is set

`Event::publicRegistrationUrl()` creates a temporary signed route that expires 24 hours after the event ends, or 24 hours after it starts when no end time exists.

### Lookup throttling

Certificate lookup POST requests are throttled in [AppServiceProvider.php](app/Providers/AppServiceProvider.php):

- `5` requests per minute
- keyed by `ip + sha1(nokp)`

### NOKP normalization

Public lookup and registration normalize `nokp` to digits only in:

- [LookupCertificateRequest.php](app/Http/Requests/LookupCertificateRequest.php)
- [StoreEventRegistrationRequest.php](app/Http/Requests/StoreEventRegistrationRequest.php)

## Application Settings And Mail

Application-wide settings are managed in the Filament admin panel under `Settings > Application Settings`.

Settings are stored with Spatie Laravel Settings:

- [MailSettings.php](app/Settings/MailSettings.php) stores the default mailer, SMTP server, credentials, and global sender address.
- [NotificationSettings.php](app/Settings/NotificationSettings.php) stores notification feature toggles.

The page currently has these tabs:

- `Email` - mailer, SMTP, sender details, and a simple test email action.
- `General` - reserved for future app-wide settings.
- `Notifications` - registration notification controls and notification test actions.

Current notification controls:

- `Send registration confirmation` controls whether a participant receives a confirmation email after a new public registration.
- Manual `Send test notification` remains available for admins even when the live registration confirmation toggle is disabled.

Runtime mail configuration is applied by [MailSettingsConfigurator.php](app/Services/Mail/MailSettingsConfigurator.php) during application boot and before test email actions.

### Registration Confirmation Email

After a new public registration is created, the app sends [RegistrationSubmitted.php](app/Notifications/RegistrationSubmitted.php) to the participant when the notification toggle is enabled.

The notification is queued with:

- `ShouldQueue`
- `afterCommit()`
- `3` tries
- backoff intervals of `60`, `300`, and `900` seconds

This prevents SMTP/provider latency from blocking registration when a real queue connection is used.

### Mail Queue Setup

For reliable email delivery, use the database queue instead of `sync`.

Recommended `.env`:

```env
QUEUE_CONNECTION=database
```

The project already includes Laravel's default `jobs` and `failed_jobs` migrations. Run migrations after changing queue/database setup:

```bash
php artisan migrate
```

Run a queue worker locally:

```bash
php artisan queue:work --tries=3
```

Or use the existing development script:

```bash
composer dev
```

`composer dev` starts the Laravel server, Vite, and a queue listener.

If `QUEUE_CONNECTION=sync`, queued notifications run immediately inside the registration request. In that mode, an email provider failure can still cause the user to see an error page and Laravel will not retry the email later.

In production, keep a worker running with Supervisor or another process manager:

```bash
php artisan queue:work --tries=3 --backoff=60
```

Failed queued emails are recorded in `failed_jobs`. Use Laravel queue tooling to inspect and retry them:

```bash
php artisan queue:failed
php artisan queue:retry all
```

### Email Logs

Outgoing emails are logged by `ejoi8/filament-email-logs`.

The plugin is registered in [AuthPanelProvider.php](app/Providers/Filament/AuthPanelProvider.php), authorized for authenticated panel users, and shown under the `Settings` navigation group.

Email log migrations are loaded by the package and create/update the `email_logs` table. The admin panel exposes email log listing, preview, and resend actions.

## Certificate Flow

Certificate generation currently revolves around these classes:

- [RegistrationCertificateIssuer.php](app/Services/Certificates/RegistrationCertificateIssuer.php)
- [StoredCertificatePdf.php](app/Services/Certificates/StoredCertificatePdf.php)
- [PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)

Current behavior:

- issuing a certificate writes certificate metadata onto the `registrations` row
- downloads render from the current `Registration`
- templates are resolved **live** from the linked `CertificateTemplate` at download time — there is no stored snapshot, so editing a template (or changing an event's template) retroactively changes already-issued certificates. This is intentional design.

Server-side PDF generation uses DomPDF, so certificate downloads do not require Node on the server. Node/npm is still used for building the Filament designer frontend assets.

Important rendering note:

- the Filament designer preview is rendered in-browser with `@pdfme/ui`
- downloaded certificates are rendered server-side by [PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php) through DomPDF
- saved templates are shared between both paths, but text layout can still differ slightly because the preview and download do not use the same rendering engine
- image fields are explicitly fitted with pdfme-style contain math in the server renderer so logos, signatures, and backgrounds preserve their aspect ratio more closely

Relevant config lives in [config/certificates.php](config/certificates.php).

## Template Management

Templates are managed through `CertificateTemplateResource` and its designer page.

Key points:

- templates store both legacy schema-style data and `pdfme_template`
- the Filament designer page is [Designer.php](app/Filament/Resources/CertificateTemplates/Pages/Designer.php)
- seeded defaults are maintained by [CertificateTemplateSeeder.php](database/seeders/CertificateTemplateSeeder.php)
- attendance slip defaults reuse the participation layout and replace the certificate title with `Slip Kehadiran`

Fonts used by the renderer/designer live in:

- [public/fonts/certificates](public/fonts/certificates)

Relevant rendering files:

- [resources/js/certificate-template-designer.js](resources/js/certificate-template-designer.js)
- [app/Services/Certificates/PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)
- [resources/views/certificates/pdfme-dompdf.blade.php](resources/views/certificates/pdfme-dompdf.blade.php)

## Seeders

The default [DatabaseSeeder.php](database/seeders/DatabaseSeeder.php) currently:

- creates `admin@admin.com` with password `password`
- runs `LegacyEsijilSeeder`
- runs `NormalizeLegacyBranchesSeeder`
- runs `CertificateTemplateSeeder`

Other seeders in the repo:

- `BranchSeeder`
- `DemoDataSeeder`
- `RefreshLegacyEsijilSeeder`

## Local Development

### Prerequisites

- PHP 8.3
- Composer
- Node.js and npm for frontend asset builds
- a configured database

Certificate rendering runs through DomPDF and does not require Node on the server.

### Initial setup

```bash
composer setup
```

This script currently:

- installs PHP dependencies
- creates `.env` if needed
- generates the app key
- runs migrations
- installs npm dependencies
- builds frontend assets

### Run the app

```bash
composer dev
```

This starts:

- `php artisan serve`
- `php artisan queue:listen --tries=1`
- `npm run dev`

For mail behavior closer to production, prefer running a worker manually in another terminal:

```bash
php artisan queue:work --tries=3
```

### Local admin shortcuts

In `local` environment only, the app exposes helper routes:

- `/dev/{id-or-email}` - sign in as a user
- `/dev-logout` - sign out and return to `/auth`

## Testing

This project uses Pest.

Run the full test suite:

```bash
php artisan test --compact
```

Useful focused files:

- `tests/Feature/CertificateLookupTest.php`
- `tests/Feature/EventRegistrationTest.php`
- `tests/Feature/MailSettingsTest.php`
- `tests/Feature/EventResourceTest.php`
- `tests/Feature/CertificateTemplateManagementTest.php`
- `tests/Feature/DomainConsistencyTest.php`

## Key Files

- [routes/web.php](routes/web.php)
- [app/Models/Event.php](app/Models/Event.php)
- [app/Models/Registration.php](app/Models/Registration.php)
- [app/Http/Controllers/CertificateLookupController.php](app/Http/Controllers/CertificateLookupController.php)
- [app/Http/Controllers/EventRegistrationController.php](app/Http/Controllers/EventRegistrationController.php)
- [app/Services/Certificates/PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)
- [app/Filament/Resources/Events](app/Filament/Resources/Events)
- [app/Filament/Resources/CertificateTemplates](app/Filament/Resources/CertificateTemplates)
