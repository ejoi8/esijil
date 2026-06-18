<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-field limits for file-upload custom fields: a max size (KB) and a list of
 * allowed file extensions. Both nullable (no limit when unset).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->unsignedInteger('max_file_kb')->nullable()->after('options');
            $table->json('accepted_file_types')->nullable()->after('max_file_kb');
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropColumn(['max_file_kb', 'accepted_file_types']);
        });
    }
};
