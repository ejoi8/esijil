<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AuthPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;

return [
    AppServiceProvider::class,
    AuthPanelProvider::class,
    PlatformPanelProvider::class,
];
