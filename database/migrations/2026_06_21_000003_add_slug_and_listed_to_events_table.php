<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public, indexable event landing pages (/e/{slug}). `slug` is a readable,
 * unique URL key; `listed` is an opt-in (default false) so events stay unlisted
 * — reachable only via their shared signed link — unless an organizer chooses
 * to make them discoverable in search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('public_id');
            $table->boolean('listed')->default(false)->after('registration_open');
        });

        // Backfill a unique slug for existing rows (md5(id) suffix guarantees uniqueness).
        foreach (DB::table('events')->select('id', 'title')->get() as $event) {
            $base = Str::slug((string) $event->title) ?: 'acara';
            DB::table('events')->where('id', $event->id)->update([
                'slug' => $base.'-'.substr(md5((string) $event->id), 0, 8),
            ]);
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'listed']);
        });
    }
};
