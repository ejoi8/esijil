<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\MailSettings;
use App\Support\Nokp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMailSettings();

        // The "admin" role is a super-admin: it bypasses every policy check.
        Gate::before(fn (?User $user, string $ability): ?bool => $user?->hasRole('admin') ? true : null);

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
