<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON bag for admin-defined custom registration fields (see the custom_fields
 * table). Values are keyed by the field's `key`; the definitions live in
 * custom_fields, so a field can be added/removed from the dashboard with no
 * migration. Mirrors participants.details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
