<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Notification preferences are now per-organization (stored on
 * organizations.settings), so the global notification settings are removed.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('notifications.registration_submitted_enabled');
        $this->migrator->deleteIfExists('notifications.certificate_issued_enabled');
    }
};
