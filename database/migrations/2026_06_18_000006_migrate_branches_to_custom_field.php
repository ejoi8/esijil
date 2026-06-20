<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert the bespoke Branch resource into a participant dropdown custom field.
 *
 * Creates a "branch" select field (seeded from the canonical PUSPANITA branches)
 * and copies each participant's branch name into participants.details['branch'].
 * A follow-up migration then drops branch_id and the branches table.
 *
 * Raw queries throughout so it stays valid after the Branch model is removed.
 */
return new class extends Migration
{
    /** @var list<string> */
    protected array $canonicalBranches = [
        'PUSPANITA Kebangsaan',
        'Johor',
        'Kedah',
        'Kelantan',
        'Kuala Lumpur',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Perak',
        'Perlis',
        'Pulau Pinang',
        'Putrajaya',
        'Sabah',
        'Sarawak',
        'Selangor',
        'Terengganu',
    ];

    public function up(): void
    {
        $options = [];
        foreach ($this->canonicalBranches as $name) {
            $options[$name] = $name;
        }

        $exists = DB::table('custom_fields')
            ->where('entity', 'participant')
            ->whereNull('event_id')
            ->where('key', 'branch')
            ->exists();

        if (! $exists) {
            $now = now();

            DB::table('custom_fields')->insert([
                'entity' => 'participant',
                'event_id' => null,
                'key' => 'branch',
                'label' => 'Cawangan',
                'type' => 'select',
                'options' => json_encode($options),
                'required' => false,
                'scope' => 'both',
                'help_text' => null,
                'cert_var' => null,
                'sort' => 5,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Copy each participant's branch name into details['branch'].
        if (Schema::hasColumn('participants', 'branch_id') && Schema::hasTable('branches')) {
            $branchNames = DB::table('branches')->pluck('name', 'id');

            DB::table('participants')
                ->whereNotNull('branch_id')
                ->chunkById(500, function ($participants) use ($branchNames): void {
                    foreach ($participants as $participant) {
                        $name = $branchNames[$participant->branch_id] ?? null;

                        if ($name === null) {
                            continue;
                        }

                        $details = json_decode($participant->details ?? '', true);
                        $details = is_array($details) ? $details : [];
                        $details['branch'] = $name;

                        DB::table('participants')
                            ->where('id', $participant->id)
                            ->update(['details' => json_encode($details)]);
                    }
                });
        }
    }

    public function down(): void
    {
        DB::table('custom_fields')
            ->where('entity', 'participant')
            ->whereNull('event_id')
            ->where('key', 'branch')
            ->delete();

        // Strip the copied value back out of the details bag.
        if (Schema::hasColumn('participants', 'details')) {
            DB::table('participants')
                ->whereNotNull('details')
                ->chunkById(500, function ($participants): void {
                    foreach ($participants as $participant) {
                        $details = json_decode($participant->details ?? '', true);

                        if (! is_array($details) || ! array_key_exists('branch', $details)) {
                            continue;
                        }

                        unset($details['branch']);

                        DB::table('participants')
                            ->where('id', $participant->id)
                            ->update(['details' => $details === [] ? null : json_encode($details)]);
                    }
                });
        }
    }
};
