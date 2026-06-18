<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON bag for admin-defined custom event attributes (entity = event in the
 * custom_fields table). Values are keyed by the field's `key`; definitions live
 * in custom_fields, so an attribute can be added/removed from the dashboard with
 * no migration. Mirrors participants.details / registrations.details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
