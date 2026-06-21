<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event seat capacity (stock control). Null = unlimited. The public
 * registration form consumes a seat per active registration and blocks new
 * sign-ups once the cap is reached; admin and import paths are not limited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable()->after('registration_open');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('capacity');
        });
    }
};
