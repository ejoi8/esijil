<?php

namespace App\Providers;

use App\Enums\CertificatePdfRenderer;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Certificates\CertificateGenerator;
use App\Services\Certificates\DompdfCertificateGenerator;
use App\Services\Certificates\PdfmeNodeCertificateGenerator;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\CertificateSettings;
use App\Settings\MailSettings;
use Ejoi8\FilamentEmailLogs\Support\EmailLogTenancy;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pick the certificate engine from settings (default DomPDF). Concretes
        // are resolved through the container so they stay mockable in tests, and
        // adding a new engine only means a new generator + enum case + arm here.
        $this->app->bind(CertificateGenerator::class, function ($app): CertificateGenerator {
            $engine = CertificatePdfRenderer::fromMixed($app->make(CertificateSettings::class)->renderer)
                ?? CertificatePdfRenderer::Dompdf;

            return $engine === CertificatePdfRenderer::Pdfme
                ? $app->make(PdfmeNodeCertificateGenerator::class)
                : $app->make(DompdfCertificateGenerator::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Local-dev only: trust any proxy so Laravel reads X-Forwarded-* (the
        // real public host + https) — needed for ngrok/tunnel access via Laragon.
        // Remove this (or scope to your proxy's IPs) before production.
        TrustProxies::at('*');

        $this->configureMailSettings();

        // Scope spatie permissions to the active Filament tenant (teams) so roles
        // apply per-organization. Resolved at tenant-set; tests set it directly.
        // Also stash the tenant key in Context so it rides along into queued jobs
        // (notification mail is queued and runs in a worker without a Filament
        // tenant) — the email-logs resolver below reads it back there.
        Event::listen(TenantSet::class, function (TenantSet $event): void {
            $tenantId = $event->getTenant()->getKey();

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
            Context::add('email_log_tenant_id', $tenantId);
        });

        // Tell filament-email-logs which tenant owns a logged email: the active
        // Filament tenant for synchronous sends, falling back to the Context value
        // captured at dispatch time for mail sent from a queue worker. getTenant()
        // can throw when no panel is bound (i.e. in the worker), so it is guarded
        // rather than relying on null-coalescing, which would not catch a throw.
        EmailLogTenancy::resolveUsing(function (): int|string|null {
            try {
                if ($tenant = Filament::getTenant()) {
                    return $tenant->getKey();
                }
            } catch (Throwable) {
                // No panel/tenant bound (queue worker, CLI) — fall through.
            }

            return Context::get('email_log_tenant_id');
        });

        // Platform admins bypass every policy everywhere; the per-organization
        // "admin" role bypasses within the current organization (team).
        Gate::before(fn (?User $user, string $ability): ?bool => ($user?->is_platform_admin || $user?->hasRole(UserRole::Admin->value)) ? true : null);

        RateLimiter::for('certificate-lookup', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip().'|'.sha1(mb_strtolower((string) $request->input('email'))));
        });

        RateLimiter::for('event-registration', function (Request $request): Limit {
            // The route model isn't bound yet at the throttle stage, so
            // route('event') is the raw id string.
            $eventKey = (string) $request->route('event');

            return Limit::perMinute(5)->by(
                $request->ip().'|event:'.$eventKey.'|'.sha1(mb_strtolower((string) $request->input('email'))),
            );
        });

        RateLimiter::for('certificate-download', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Per-station check-in throttle for the scan hot path.
        RateLimiter::for('scan', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) $request->input('station_token'));
        });
    }

    protected function configureMailSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = app(MailSettings::class);
            app(MailSettingsConfigurator::class)->apply($settings);
        } catch (QueryException) {
            // Database not available yet (e.g. during install/migrations) — skip.
        } catch (Throwable $exception) {
            // Unexpected failure (e.g. corrupt settings row or decryption error):
            // fall back to the .env mail config but surface the problem.
            Log::warning('mail.settings_boot_failed', ['message' => $exception->getMessage()]);
        }
    }
}
