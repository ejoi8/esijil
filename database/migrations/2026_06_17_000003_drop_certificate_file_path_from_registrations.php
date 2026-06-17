<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the unused registrations.certificate_file_path column. Certificates are
 * rendered on the fly and streamed; this column was never written (a vestige of
 * the old stored-file model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('certificate_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('certificate_file_path')->nullable()->after('cert_serial_number');
        });
    }
};
