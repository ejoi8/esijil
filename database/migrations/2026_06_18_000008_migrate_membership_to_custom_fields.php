<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move membership_status + membership_notes off the participants table and into
 * admin-managed participant custom fields. Per the architecture decision, the
 * existing column values are NOT backfilled — only the field definitions are
 * created, so the forms keep asking for them and new data flows into details.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $fields = [
            [
                'entity' => 'participant',
                'event_id' => null,
                'key' => 'membership_status',
                'label' => 'Status Ahli',
                'type' => 'select',
                'options' => json_encode(['member' => 'Ahli', 'non_member' => 'Bukan Ahli']),
                'required' => true,
                'scope' => 'both',
                'help_text' => null,
                'cert_var' => null,
                'sort' => 10,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'entity' => 'participant',
                'event_id' => null,
                'key' => 'membership_notes',
                'label' => 'Nota Keahlian',
                'type' => 'textarea',
                'options' => null,
                'required' => false,
                'scope' => 'admin',
                'help_text' => null,
                'cert_var' => null,
                'sort' => 20,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($fields as $field) {
            $exists = DB::table('custom_fields')
                ->where('entity', 'participant')
                ->whereNull('event_id')
                ->where('key', $field['key'])
                ->exists();

            if (! $exists) {
                DB::table('custom_fields')->insert($field);
            }
        }

        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn(['membership_status', 'membership_notes']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->string('membership_status')->default('non_member')->after('phone');
            $table->text('membership_notes')->nullable()->after('membership_status');
        });

        DB::table('custom_fields')
            ->where('entity', 'participant')
            ->whereNull('event_id')
            ->whereIn('key', ['membership_status', 'membership_notes'])
            ->delete();
    }
};
