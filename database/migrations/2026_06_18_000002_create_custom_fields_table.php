<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed custom field definitions. Each row describes one extra field
 * (its label, type, options, validation, where it shows) for a target entity
 * (participant or registration). The values themselves live in that entity's
 * `details` JSON column, keyed by `key` — so a field can be added or removed
 * from the dashboard without a migration or a config file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('entity');                   // CustomFieldEntity: participant|registration
            $table->string('key');                      // machine slug, unique per entity
            $table->string('label');
            $table->string('type')->default('text');    // CustomFieldType
            $table->json('options')->nullable();        // choices for select-type fields
            $table->boolean('required')->default(false);
            $table->string('scope')->default('admin');  // CustomFieldScope: admin|public|both
            $table->text('help_text')->nullable();
            $table->string('cert_var')->nullable();     // certificate template variable mapping
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['entity', 'key']);
            $table->index(['entity', 'active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
