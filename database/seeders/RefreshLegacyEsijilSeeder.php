<?php

namespace Database\Seeders;

use App\Enums\RegistrationSource;
use App\Models\Branch;
use App\Models\Registration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RefreshLegacyEsijilSeeder extends Seeder
{
    protected string $legacyConnection = 'legacy';

    /**
     * DESTRUCTIVE: hard-deletes imported events/registrations and re-mirrors the
     * legacy tables. `db:seed` already confirms before running in production;
     * everything below runs in a single transaction so a mid-run failure rolls
     * the purge back instead of leaving a half-truncated database.
     */
    public function run(): void
    {
        $this->command?->warn('RefreshLegacyEsijilSeeder will hard-delete imported legacy data and re-import it.');

        $participantIdsToDelete = DB::transaction(function (): Collection {
            $ids = $this->participantIdsLinkedOnlyToLegacyImport();

            $this->purgeImportedApplicationData($ids);
            $this->replaceMirroredLegacyTables();

            $this->call([
                LegacyEsijilSeeder::class,
                NormalizeLegacyBranchesSeeder::class,
            ]);

            $this->deleteStaleLegacyBranchesWithoutParticipants();

            return $ids;
        });

        $this->summarize($participantIdsToDelete);
    }

    protected function participantIdsLinkedOnlyToLegacyImport(): Collection
    {
        $participantIds = Registration::query()
            ->where('source', RegistrationSource::LegacyImport->value)
            ->distinct()
            ->pluck('participant_id');

        if ($participantIds->isEmpty()) {
            return collect();
        }

        $participantIdsWithNonLegacyRegistrations = Registration::query()
            ->whereIn('participant_id', $participantIds)
            ->where('source', '<>', RegistrationSource::LegacyImport->value)
            ->distinct()
            ->pluck('participant_id');

        return $participantIds
            ->diff($participantIdsWithNonLegacyRegistrations)
            ->values();
    }

    protected function purgeImportedApplicationData(Collection $participantIdsToDelete): void
    {
        DB::table('registrations')
            ->where('source', RegistrationSource::LegacyImport->value)
            ->delete();

        DB::table('events')
            ->whereNotNull('legacy_id')
            ->delete();

        if ($participantIdsToDelete->isNotEmpty()) {
            DB::table('participants')
                ->whereIn('id', $participantIdsToDelete->all())
                ->delete();
        }
    }

    protected function replaceMirroredLegacyTables(): void
    {
        $this->replaceMirroredLegacyTable('peserta');
        $this->replaceMirroredLegacyTable('sijil');
        $this->replaceMirroredLegacyTable('ref_cawangans');
    }

    protected function replaceMirroredLegacyTable(string $table): void
    {
        // delete() (not truncate()) so the refresh stays inside the transaction;
        // TRUNCATE implicitly commits on MySQL and cannot be rolled back.
        DB::table($table)->delete();

        $this->legacyTable($table)
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows) use ($table): void {
                DB::table($table)->insert(
                    $rows
                        ->map(fn (object $row): array => (array) $row)
                        ->all(),
                );
            }, 'id');
    }

    protected function deleteStaleLegacyBranchesWithoutParticipants(): void
    {
        $activeLegacyBranchIds = $this->legacyTable('ref_cawangans')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        Branch::withTrashed()
            ->whereNotNull('legacy_id')
            ->whereNotIn('legacy_id', $activeLegacyBranchIds)
            ->get()
            ->filter(fn (Branch $branch): bool => ! $branch->participants()->exists())
            ->each(fn (Branch $branch): ?bool => $branch->forceDelete());
    }

    protected function legacyTable(string $table): Builder
    {
        return DB::connection($this->legacyConnection)->table($table);
    }

    protected function summarize(Collection $participantIdsToDelete): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info('Legacy import was refreshed from scratch.');
        $this->command->info('Legacy-only participants replaced: '.$participantIdsToDelete->count());
        $this->command->info('Raw legacy peserta rows mirrored: '.DB::table('peserta')->count());
        $this->command->info('Raw legacy sijil rows mirrored: '.DB::table('sijil')->count());
        $this->command->info('Raw legacy branches mirrored: '.DB::table('ref_cawangans')->count());
        $this->command->warn('Final event count: '.DB::table('events')->count());
        $this->command->warn('Final registration count: '.DB::table('registrations')->count());
        $this->command->warn('Final certificate count: '.DB::table('certificates')->count());
    }
}
