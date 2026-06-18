<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the Branch entity: branch now lives as a participant custom field (see
 * the previous migration, which already backfilled participants.details['branch']).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }

    public function down(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('participants', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('phone')
                ->constrained()
                ->nullOnDelete();
        });
    }
};
