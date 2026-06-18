<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON bag for admin-defined custom participant fields (see the custom_fields
 * table and App\Fields\CustomFields). Values are keyed by the field's `key`; the
 * definitions live in custom_fields, so a field can be added/removed from the
 * dashboard with no migration. Core columns stay first-class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('membership_notes');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
