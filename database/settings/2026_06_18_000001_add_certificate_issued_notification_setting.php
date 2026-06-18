<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.certificate_issued_enabled', true);
    }

    public function down(): void
    {
        $this->migrator->delete('notifications.certificate_issued_enabled');
    }
};
