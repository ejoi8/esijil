<?php

use App\Providers\AppServiceProvider;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies stored smtp settings during application boot', function () {
    $settings = app(MailSettings::class);
    $settings->mailer = 'smtp';
    $settings->scheme = 'tls';
    $settings->host = 'smtp.example.test';
    $settings->port = 587;
    $settings->username = 'mailer@example.test';
    $settings->password = 'secret-password';
    $settings->from_address = 'noreply@example.test';
    $settings->from_name = 'eSIJIL Mailer';
    $settings->save();

    config([
        'mail.default' => 'array',
        'mail.mailers.smtp.url' => 'smtp://ignored.example.test',
        'mail.mailers.smtp.scheme' => null,
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2525,
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.from.address' => 'hello@example.com',
        'mail.from.name' => 'Laravel',
    ]);

    (new AppServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.url'))->toBeNull()
        ->and(config('mail.mailers.smtp.scheme'))->toBe('tls')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer@example.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret-password')
        ->and(config('mail.from.address'))->toBe('noreply@example.test')
        ->and(config('mail.from.name'))->toBe('eSIJIL Mailer');
});
