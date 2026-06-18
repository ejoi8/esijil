<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the time-based registration window (registration_opens_at /
 * registration_closes_at) with a single manual on/off switch — the sole
 * "accepting submissions" gate. (The signed registration link itself no longer
 * expires; see Event::publicRegistrationUrl().)
 *
 * Backfill keeps currently-open published events open so nothing breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('registration_open')->default(false)->after('organizer_name');
        });

        DB::table('events')
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now());
            })
            ->update(['registration_open' => true]);

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['registration_opens_at', 'registration_closes_at']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dateTime('registration_opens_at')->nullable()->after('organizer_name');
            $table->dateTime('registration_closes_at')->nullable()->after('registration_opens_at');
            $table->dropColumn('registration_open');
        });
    }
};
