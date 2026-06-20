<?php

use App\Models\Organization;
use Filament\Support\Icons\Heroicon;

return [
    'logging' => [
        'enabled' => true,
    ],

    'authorization' => [
        'ability' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multitenancy
    |--------------------------------------------------------------------------
    |
    | Email logs are written by a mail listener that usually runs outside of a
    | Filament request (for example, in a queue worker), so Filament cannot
    | automatically associate each log with the active tenant. These options
    | let the package scope logs per-tenant and stamp the owning tenant itself.
    |
    | When 'enabled' is false (default) the Email Logs resource works in any
    | panel — single- or multi-tenant — and never crashes; logs are simply not
    | scoped. Set 'enabled' to true and provide your panel's tenant 'model' to
    | isolate logs per tenant. Run `php artisan migrate` afterwards to add the
    | tenant foreign-key column.
    |
    */
    'tenancy' => [
        'enabled' => true,

        // The tenant model used by the auth panel's ->tenant(Organization::class) call.
        'model' => Organization::class,

        // The foreign-key column added to the email_logs table.
        'column' => 'tenant_id',
    ],

    'navigation' => [
        'group' => 'System',
        'sort' => 1,
        'icon' => Heroicon::OutlinedEnvelope,

        // Show a navigation badge with the number of emails logged today.
        'badge' => false,
    ],
];
