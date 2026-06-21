<?php

namespace App\Filament\Imports;

use App\Enums\RegistrationSource;
use App\Models\Participant;
use App\Models\Registration;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

/**
 * Imports a participant roster from CSV (Filament's chunked-queue importer:
 * column mapping + per-row validation + a failed-rows report for free). The
 * target organization — and optionally an event to enrol into — are passed as
 * options by the ImportAction, since the queued job has no Filament tenant.
 */
class ParticipantImporter extends Importer
{
    protected static ?string $model = Participant::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('full_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('external_id')
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $organizationId = (int) ($this->options['organization_id'] ?? 0);

        $query = Participant::query()->where('organization_id', $organizationId);

        // Upsert: an imported external_id wins (e.g. staff card), else email.
        $existing = null;

        if (filled($this->data['external_id'] ?? null)) {
            $existing = (clone $query)->where('external_id', $this->data['external_id'])->first();
        }

        if ($existing === null && filled($this->data['email'] ?? null)) {
            $existing = (clone $query)->where('email', $this->data['email'])->first();
        }

        return $existing ?? Participant::make(['organization_id' => $organizationId]);
    }

    protected function afterSave(): void
    {
        $eventId = $this->options['event_id'] ?? null;

        if (blank($eventId)) {
            return;
        }

        // Enrol the participant into the target event (idempotent on re-import).
        Registration::firstOrCreate(
            [
                'event_id' => $eventId,
                'participant_id' => $this->record->getKey(),
            ],
            [
                'organization_id' => $this->options['organization_id'] ?? null,
                'source' => RegistrationSource::Import->value,
                'registered_at' => now(),
            ],
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows).' '.str('participant')->plural($import->successful_rows).' imported.';

        if (($failed = $import->getFailedRowsCount()) > 0) {
            $body .= ' '.number_format($failed).' '.str('row')->plural($failed).' failed.';
        }

        return $body;
    }
}
