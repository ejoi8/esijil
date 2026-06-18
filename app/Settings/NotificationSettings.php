<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class NotificationSettings extends Settings
{
    public bool $registration_submitted_enabled = true;

    public bool $certificate_issued_enabled = true;

    public static function group(): string
    {
        return 'notifications';
    }
}
