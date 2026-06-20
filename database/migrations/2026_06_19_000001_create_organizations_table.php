<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant root. Every tenant-owned table carries organization_id and the
 * BelongsToOrganization trait; data isolation keys off this record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();   // tenant path segment + future subdomain
            $table->string('locale', 5)->default('en');
            $table->json('settings')->nullable();
            $table->string('status')->default('active');   // active | suspended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
