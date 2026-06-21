# eSIJIL

eSIJIL is a Laravel 13 + Filament 5, multi-tenant (per-organization) platform for the full event lifecycle — **register → attend → certify**: public event registration, QR-code attendance check-in, and verifiable digital certificates.

The app has two main surfaces:

- a Filament admin panel at `/auth` (multi-tenant, scoped to the active organization)
- a public surface for event registration, QR attendance scanning, certificate lookup, and indexable event/organizer pages

See [AI_HANDOVER.md](AI_HANDOVER.md) for a compact project brief aimed at future AI agents.

## Current Scope

The current application supports:

- multi-tenant organizations (each tenant owns its own events, participants, registrations and custom fields)
- event management with per-event **seat capacity** (an opt-in hard cap on public sign-ups)
- minimal participant records (full name, email, phone) extended by **custom fields** — IC / no. KP is a custom field, not a column
- registration management
- public event registration through signed links, with per-event custom fields
- **QR-code attendance check-in** via scanner stations (review/fast modes, hashed station PIN, member bypass)
- certificate template management with a pdfme-based designer
- certificate PDF generation, download, and public lookup/verification by email
- public, opt-in, indexable event landing pages, organizer profiles, and a Bahasa-Melayu content hub (SEO)
- application settings for SMTP mail and notification controls
- queued registration confirmation notifications
- email log viewing and resend support in Filament
- a high-volume demo data seeder (`esijil:seed-demo`)
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
- html5-qrcode (CDN) for the QR attendance scanner

## Main Surfaces

### Admin panel

The admin panel is defined by [AuthPanelProvider.php](app/Providers/Filament/AuthPanelProvider.php) and lives at `/auth`.

Current resources:

- `BranchResource`
- `ParticipantResource`
- `EventResource` (with relation managers for registrations, scanner stations and issued certificates)
- `RegistrationResource`
- `CertificateTemplateResource`
- `CustomFieldResource`
- `MemberResource`
- `EmailLogResource` from `ejoi8/filament-email-logs`

The panel is multi-tenant: it is scoped to the active organization, while a platform admin can access all organizations.

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

- `GET /` - landing page (platform marketing)
- `GET /robots.txt`, `GET /sitemap.xml` - route-served crawl directives + sitemap of indexable surfaces
- `GET /acara` - public events directory (lists all opt-in published events; searchable + paginated, with `schema.org/ItemList`)
- `GET /e/{event:slug}` - public, opt-in event landing page (only when the event is published and `listed`), with `schema.org/Event` JSON-LD
- `GET /o/{organization}` - public organizer / issuer profile listing the org's opt-in events
- `GET /panduan`, `GET /panduan/{slug}` - Bahasa-Melayu content hub (SEO guides)
- `GET /semakan` - certificate lookup form
- `POST /semakan` - lookup submit, throttled by `certificate-lookup`
- `GET /semakan/keputusan` - lookup result page
- `GET /semakan/sijil/{serial}` - public certificate verification (noindex)
- `GET /certificates/{registration}/download` - download a registration certificate after a valid lookup session
- `GET /scan/{stationToken}` - scanner station page (QR check-in)
- `GET /r/{publicToken}` - participant attendance pass / status (noindex)
- `GET /events/{event}/register` - signed event registration page
- `POST /events/{event}/register` - signed event registration submit, throttled by `event-registration`
- `GET /registrations/{registration}/success` - registration success page
- `GET /registrations/{registration}/certificate` - certificate download for the current registration session

API routes ([routes/api.php](routes/api.php)):

- `POST /api/scan` - stateless check-in scan (station-token auth; `confirm=false` identifies only, `confirm=true` records the check-in), throttled by `scan`
- `POST /api/scan/verify` - up-front station-PIN check for the scanner gate, throttled by `scan-verify`

## Domain Model

Core models:

- `Organization` - the tenant; all tenant-owned models belong to one organization (users belong to many)
- `Branch` - participant grouping / directory metadata
- `Participant` - a person who can join events; a minimal record (full name, email, phone) extended by custom fields
- `Event` - an event with schedule, status, registration window, optional seat `capacity`, an optional certificate template, and an opt-in public `listed` flag
- `Registration` - the participant-to-event record, including attendance and issued certificate data
- `ScannerStation` - a per-event QR check-in device authorized by a token (+ optional hashed PIN)
- `CustomField` - per-organization / per-event custom fields for participants, registrations and events
- `CertificateTemplate` - reusable certificate template metadata, schema, and `pdfme_template`

Important current state:

- there is no active `Certificate` model or `certificates` table in the current application state
- issued document state is stored directly on `registrations`
- event relation managers still expose “Issued Certificates”, but they are filtered registration records

Certificate-related columns currently stored on `registrations` include:

- `certificate_template_id`
- `cert_serial_number`
- `certificate_issued_at`
- `certificate_metadata`

The merge from the older separate certificate table is captured in [2026_04_26_065610_merge_certificates_into_registrations_table.php](database/migrations/2026_04_26_065610_merge_certificates_into_registrations_table.php).

## Business Rules

### Certificate issuance

There is no certificate "type". An event issues a certificate if (and only if) it
has a `certificate_template_id` assigned; leaving it empty means the event issues
nothing. The chosen template is snapshotted onto each registration at sign-up, and
the same `certificate_template_id !== null` check gates every download and the
public verification page.

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
- keyed by `ip + sha1(email)`

## Attendance & QR check-in

Events with the `attendance` module use **scanner stations** — per-event scanning devices authorized by an unguessable token in the URL (`/scan/{stationToken}`). Scans are recorded through the stateless `/api/scan` endpoint.

- **Review vs Fast mode** — the scanner page (remembered per device) defaults to *Semak* (review): a scan shows the participant + their registration details and waits for a manual "Daftar Masuk"; *Auto* mode checks in instantly for busy gates.
- **Identify vs check-in** — `/api/scan` with `confirm=false` only identifies (no write); `confirm=true` records the check-in (idempotent). Identify reads are audit-logged (`scan.identify`).
- **Station PIN** — a station may carry a PIN. It is stored **hashed**, gates the scanner, and is enforced on every `/api/scan` call. `/api/scan/verify` checks it up front so a wrong PIN never opens the camera. Logged-in members of the event's organization **bypass** the prompt via a signed, station-scoped token, so the raw PIN is never sent to the client.
- **Scan match mode** — an event matches scans either by the platform QR token (`participant.public_token`) or an external id (`participant.external_id`, e.g. IC / staff card).

## Seat capacity (stock control)

Each event has an optional `capacity` — blank means unlimited. The **public** registration form consumes a seat per active (non-soft-deleted) registration and hard-blocks new sign-ups once full (the page shows "Baki X tempat" / "Pendaftaran penuh"). The admin panel and CSV import are **not** capped, a returning participant who already holds a seat is never blocked, and the check locks the event row to stay concurrency-safe.

## Public site & SEO

Beyond the registration/lookup flows, the public surface is built for organic discovery:

- **Events directory** (`/acara`) lists every opt-in published event across organizations (searchable, paginated, `schema.org/ItemList`). **Event landing pages** (`/e/{slug}`) and **organizer profiles** (`/o/{organization}`) are opt-in via the event's `listed` toggle (default off), carry `schema.org` JSON-LD, and appear in `/sitemap.xml`.
- **Content hub** (`/panduan`) — file-based Bahasa-Melayu guides ([app/Support/Guides.php](app/Support/Guides.php) + content views) with Article JSON-LD.
- `/robots.txt` and `/sitemap.xml` are route-served (so absolute URLs follow `APP_URL`); `/auth`, `/scan` and `/dev` are disallowed, and the noindexed verify/status/success pages are excluded.
- A config-gated "powered by {app}" referral link appears on the registration success page (`config('seo.referral_cta')`, env `SEO_REFERRAL_CTA`).
- Public branding derives from `config('app.name')`, so the public chrome is tenant-neutral and rename-ready.

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

For large, realistic demo data, use the dedicated command (bulk native inserts, no Eloquent):

```bash
php artisan esijil:seed-demo
```

It **TRUNCATES the tenant tables**, then seeds organizations, events (covering registration / attendance / certificate combinations and both scan match modes), participants, registrations, attendance and per-event custom fields. A super admin `admin@admin.com` / `password` is created and added to every demo org; demo scanner stations use the PIN `123456`. Flags: `--organizations`, `--events`, `--registrations`, `--participants`, `--force`.

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
- `tests/Feature/AttendanceScanTest.php`
- `tests/Feature/PublicSeoTest.php`
- `tests/Feature/MailSettingsTest.php`
- `tests/Feature/EventResourceTest.php`
- `tests/Feature/CertificateTemplateManagementTest.php`
- `tests/Feature/DomainConsistencyTest.php`

## Key Files

- [routes/web.php](routes/web.php)
- [routes/api.php](routes/api.php)
- [app/Models/Event.php](app/Models/Event.php)
- [app/Models/Registration.php](app/Models/Registration.php)
- [app/Http/Controllers/CertificateLookupController.php](app/Http/Controllers/CertificateLookupController.php)
- [app/Http/Controllers/EventRegistrationController.php](app/Http/Controllers/EventRegistrationController.php)
- [app/Http/Controllers/ScanController.php](app/Http/Controllers/ScanController.php)
- [app/Http/Controllers/SitemapController.php](app/Http/Controllers/SitemapController.php)
- [app/Console/Commands/SeedDemoData.php](app/Console/Commands/SeedDemoData.php)
- [app/Services/Certificates/PdfmeCertificateRenderer.php](app/Services/Certificates/PdfmeCertificateRenderer.php)
- [app/Filament/Resources/Events](app/Filament/Resources/Events)
- [app/Filament/Resources/CertificateTemplates](app/Filament/Resources/CertificateTemplates)
