<?php

use App\Exceptions\CertificateRenderingException;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Log certificate render failures server-side (the message can carry
        // renderer internals) and show the user a friendly page instead.
        $exceptions->report(function (CertificateRenderingException $e): void {
            Log::error('certificate.render_failed', ['message' => $e->getMessage()]);
        })->stop();

        $exceptions->render(function (CertificateRenderingException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The certificate could not be generated.'], 503);
            }

            return response()->view('errors.certificate', [], 503);
        });
    })->create();
