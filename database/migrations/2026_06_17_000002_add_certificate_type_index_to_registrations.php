<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index registrations.certificate_type — filtered by the issuedCertificates()
 * relations on Event/Participant/CertificateTemplate and by template backfills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->index('certificate_type', 'registrations_certificate_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex('registrations_certificate_type_index');
        });
    }
};
