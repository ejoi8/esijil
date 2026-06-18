<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the certificate "type" concept (attendance slip vs participation
 * certificate). Issuance is now gated solely on whether an event/registration
 * has a certificate_template_id assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex('registrations_certificate_type_index');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn(['certificate_type', 'certificate_template_key']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['certificate_type', 'template_key']);
        });

        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->string('type')->nullable();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->string('certificate_type')->nullable();
            $table->string('template_key')->nullable();
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('certificate_type')->nullable();
            $table->string('certificate_template_key')->nullable();
            $table->index('certificate_type', 'registrations_certificate_type_index');
        });
    }
};
