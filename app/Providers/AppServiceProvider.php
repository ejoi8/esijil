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
use App\Support\Nokp;
use Filament\Events\TenantSet;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
        $this->configureMailSettings();

        // Scope spatie permissions to the active Filament tenant (teams) so roles
        // apply per-organization. Resolved at tenant-set; tests set it directly.
        Event::listen(TenantSet::class, function (TenantSet $event): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($event->getTenant()->getKey());
        });

        // The "admin" role bypasses every policy check — within the current
        // organization (team), once the tenant is set.
        Gate::before(fn (?User $user, string $ability): ?bool => $user?->hasRole(UserRole::Admin->value) ? true : null);

        RateLimiter::for('certificate-lookup', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip().'|'.sha1(Nokp::digits($request->input('nokp'))));
        });

        RateLimiter::for('event-registration', function (Request $request): Limit {
            // The route model isn't bound yet at the throttle stage, so
            // route('event') is the raw id string.
            $eventKey = (string) $request->route('event');

            return Limit::perMinute(5)->by(
                $request->ip().'|event:'.$eventKey.'|'.sha1(Nokp::digits($request->input('nokp'))),
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
