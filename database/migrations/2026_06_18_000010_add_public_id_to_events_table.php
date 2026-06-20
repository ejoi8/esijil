<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give events an opaque public identifier for their signed registration URL, so
 * the URL carries a random token (/events/{public_id}/register) instead of the
 * sequential primary key. Only the public registration route binds to it; the
 * admin/Filament routes keep using the numeric id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('public_id', 32)->nullable()->after('id');
        });

        DB::table('events')
            ->select('id')
            ->whereNull('public_id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    DB::table('events')->where('id', $event->id)->update([
                        'public_id' => Str::random(22),
                    ]);
                }
            });

        Schema::table('events', function (Blueprint $table): void {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
