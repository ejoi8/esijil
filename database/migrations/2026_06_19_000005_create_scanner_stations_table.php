<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C — per-event scanner stations (token/PIN auth, no accounts) and the
 * station that recorded each check-in. Attendance itself stays on the
 * registration (checked_in_at / attendance_status), which is naturally idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->index();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->char('token', 40)->unique();   // embedded in the scanner URL
            $table->string('pin')->nullable();
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('registrations', function (Blueprint $table): void {
            // Plain column (no FK) to avoid an SQLite table rebuild that would
            // drop the deleted_at-aware unique indexes on registrations.
            $table->foreignId('checked_in_station_id')->nullable()->after('checked_in_at');
            $table->index('checked_in_station_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex('registrations_checked_in_station_id_index');
            $table->dropColumn('checked_in_station_id');
        });

        Schema::dropIfExists('scanner_stations');
    }
};
