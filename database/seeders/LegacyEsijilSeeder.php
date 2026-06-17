<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\CertificateType;
use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Enums\RegistrationSource;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyEsijilSeeder extends Seeder
{
    protected string $legacyConnection = 'legacy';

    protected array $branchIdsByName = [];

    /**
     * @var array<string, Participant>
     */
    protected array $participantsByNokp = [];

    protected array $eventIdsByLegacyId = [];

    protected array $eventDocumentDataById = [];

    /**
     * @var array<int, Registration>
     */
    protected array $registrationsByLegacyId = [];

    /**
     * @var array<string, Registration>
     */
    protected array $registrationsByEventAndParticipant = [];

    protected int $importedBranches = 0;

    protected int $importedEvents = 0;

    protected int $importedParticipants = 0;

    protected int $createdRegistrations = 0;

    protected int $createdCertificates = 0;

    protected int $skippedEvents = 0;

    protected int $skippedRegistrations = 0;

    protected int $mergedRegistrations = 0;

    protected int $updatedRegistrations = 0;

    protected int $processedLegacyRegistrations = 0;

    public function run(): void
    {
        $this->importBranches();
        $this->importEvents();
        $this->primeRegistrationImportCaches();
        $this->importRegistrations();
        $this->summarize();
    }

    protected function importBranches(): void
    {
        $this->legacyTable('ref_cawangans')
            ->orderBy('id')
            ->each(function (object $legacyBranch): void {
                $branch = Branch::query()->updateOrCreate(
                    ['legacy_id' => (int) $legacyBranch->id],
                    [
                        'name' => $this->cleanText($legacyBranch->nama),
                        'code' => null,
                        'is_active' => (bool) $legacyBranch->status,
                    ],
                );

                $this->branchIdsByName[$this->normalizeKey($branch->name)] = $branch->id;
                $this->importedBranches++;
            });

        Branch::query()
            ->get()
            ->each(function (Branch $branch): void {
                $this->branchIdsByName[$this->normalizeKey($branch->name)] = $branch->id;
            });
    }

    protected function importEvents(): void
    {
        $this->legacyTable('sijil')
            ->orderBy('id')
            ->each(function (object $legacyEvent): void {
                $title = $this->cleanText($legacyEvent->nama);
                $startsAt = $this->parseLegacyDate($legacyEvent->tarikh_mula);

                if ($title === null || $startsAt === null) {
                    $this->skippedEvents++;

                    return;
                }

                $endsAt = $this->parseLegacyDate($legacyEvent->tarikh_akhir) ?? $startsAt;

                $event = Event::query()->updateOrCreate(
                    ['legacy_id' => (int) $legacyEvent->id],
                    [
                        'title' => $title,
                        'description' => null,
                        'starts_at' => $startsAt->startOfDay(),
                        'ends_at' => $endsAt->endOfDay(),
                        'start_time_text' => $this->cleanText($legacyEvent->masa_mula),
                        'end_time_text' => $this->cleanText($legacyEvent->masa_akhir),
                        'venue' => $this->cleanText($legacyEvent->tempat),
                        'organizer_name' => $this->cleanText($legacyEvent->anjuran) ?? 'PUSPANITA Kebangsaan',
                        'registration_opens_at' => null,
                        'registration_closes_at' => null,
                        'status' => $endsAt->isPast() ? EventStatus::Completed : EventStatus::Published,
                        'certificate_type' => $certificateType = ((int) $legacyEvent->jenis === 1 ? CertificateType::AttendanceSlip : CertificateType::ParticipationCertificate),
                        'template_key' => $certificateType->legacyTemplateKey(),
                        'created_by' => null,
                    ],
                );

                $this->eventIdsByLegacyId[(int) $legacyEvent->id] = $event->id;
                $this->eventDocumentDataById[$event->id] = [
                    'certificate_type' => $event->certificate_type,
                    'template_key' => $event->template_key,
                ];
                $this->importedEvents++;
            });
    }

    protected function importRegistrations(): void
    {
        $this->legacyTable('peserta')
            ->orderBy('id')
            ->chunkById(500, function ($legacyRegistrations): void {
                $processedBeforeChunk = $this->processedLegacyRegistrations;

                foreach ($legacyRegistrations as $legacyRegistration) {
                    $this->processedLegacyRegistrations++;
                    $legacyEventId = $this->parseLegacyInteger($legacyRegistration->kursus_id);
                    $eventId = $legacyEventId === null ? null : ($this->eventIdsByLegacyId[$legacyEventId] ?? null);
                    $nokp = $this->normalizeNokp($legacyRegistration->nokp);

                    if ($eventId === null || $nokp === null) {
                        $this->skippedRegistrations++;

                        continue;
                    }

                    $participant = $this->upsertParticipant($legacyRegistration);
                    $eventDocumentData = $this->eventDocumentDataById[$eventId] ?? null;
                    $this->upsertRegistration($legacyRegistration, $eventId, $participant->id, $eventDocumentData, $legacyEventId);
                }

                if (
                    $this->command !== null
                    && intdiv($processedBeforeChunk, 2500) !== intdiv($this->processedLegacyRegistrations, 2500)
                ) {
                    $this->command->info("Legacy registrations processed: {$this->processedLegacyRegistrations}");
                }
            }, 'id');

        $this->createdCertificates = Registration::query()
            ->whereNotNull('certificate_type')
            ->count();
    }

    protected function primeRegistrationImportCaches(): void
    {
        Participant::query()
            ->get()
            ->each(function (Participant $participant): void {
                $this->participantsByNokp[$participant->nokp] = $participant;
            });

        Registration::query()
            ->get()
            ->each(function (Registration $registration): void {
                if ($registration->legacy_id !== null) {
                    $this->registrationsByLegacyId[(int) $registration->legacy_id] = $registration;
                }

                $this->registrationsByEventAndParticipant[
                    $this->registrationPairKey($registration->event_id, $registration->participant_id)
                ] = $registration;
            });
    }

    protected function legacyTable(string $table): Builder
    {
        return DB::connection($this->legacyConnection)->table($table);
    }

    /**
     * @param  array{certificate_type: CertificateType, template_key: string}|null  $eventDocumentData
     */
    protected function upsertRegistration(
        object $legacyRegistration,
        int $eventId,
        int $participantId,
        ?array $eventDocumentData,
        ?int $legacyEventId,
    ): Registration {
        $legacyRegistrationId = (int) $legacyRegistration->id;
        $pairKey = $this->registrationPairKey($eventId, $participantId);
        $registration = $this->registrationsByLegacyId[$legacyRegistrationId] ?? null;

        if ($registration !== null) {
            $registration->fill($this->registrationAttributesForLegacyIdMatch($registration, $legacyRegistration));
            $registration->forceFill($this->certificateAttributes($registration, $eventDocumentData, $legacyRegistrationId, $legacyEventId));

            if ($registration->isDirty()) {
                $registration->save();
            }

            $this->updatedRegistrations++;

            return $registration;
        }

        $registration = $this->registrationsByEventAndParticipant[$pairKey] ?? null;

        if ($registration !== null) {
            $registration->fill($this->registrationAttributesForMergedMatch($registration, $legacyRegistration));
            $registration->forceFill($this->certificateAttributes($registration, $eventDocumentData, $legacyRegistrationId, $legacyEventId));

            if ($registration->isDirty()) {
                $registration->save();
            }

            $this->registrationsByEventAndParticipant[$pairKey] = $registration;

            $this->mergedRegistrations++;

            return $registration;
        }

        $registration = new Registration;
        $registration->fill([
            'legacy_id' => $legacyRegistrationId,
            'event_id' => $eventId,
            'participant_id' => $participantId,
            'registered_at' => $this->parseLegacyDateTime($legacyRegistration->create_at) ?? now(),
            'attendance_status' => AttendanceStatus::Registered->value,
            'checked_in_at' => null,
            'completed_at' => null,
            'source' => RegistrationSource::LegacyImport->value,
            'remarks' => $this->buildRegistrationRemarks($legacyRegistration),
        ]);
        $registration->forceFill($this->certificateAttributes($registration, $eventDocumentData, $legacyRegistrationId, $legacyEventId));
        $registration->save();

        $this->registrationsByLegacyId[$legacyRegistrationId] = $registration;
        $this->registrationsByEventAndParticipant[$pairKey] = $registration;

        $this->createdRegistrations++;

        return $registration;
    }

    protected function upsertParticipant(object $legacyRegistration): Participant
    {
        $nokp = $this->normalizeNokp($legacyRegistration->nokp);
        $participant = $this->participantsByNokp[$nokp] ?? new Participant(['nokp' => $nokp]);
        $email = $this->normalizeEmail($legacyRegistration->emel, (int) $legacyRegistration->id, $participant->email);
        $branchId = $this->resolveBranchId($legacyRegistration->cawangan);
        $membershipNotes = $this->cleanText($legacyRegistration->lain_lain);

        $participant->fill([
            'full_name' => $this->cleanText($legacyRegistration->nama_penuh) ?? $participant->full_name ?? 'Legacy Participant',
            'email' => $email,
            'phone' => $this->cleanText($legacyRegistration->tel) ?? $participant->phone,
            'branch_id' => $branchId ?? $participant->branch_id,
            'membership_status' => $this->mapMembershipStatus($legacyRegistration->status_ahli),
            'membership_notes' => $membershipNotes ?? $participant->membership_notes,
        ]);

        if ($participant->isDirty()) {
            $participant->save();
        }

        $this->participantsByNokp[$nokp] = $participant;

        $this->importedParticipants++;

        return $participant;
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrationAttributesForLegacyIdMatch(Registration $registration, object $legacyRegistration): array
    {
        return [
            'registered_at' => $this->parseLegacyDateTime($legacyRegistration->create_at) ?? $registration->registered_at ?? now(),
            'attendance_status' => AttendanceStatus::Registered->value,
            'source' => RegistrationSource::LegacyImport->value,
            'remarks' => $this->mergeRemarks($registration->remarks, $this->buildRegistrationRemarks($legacyRegistration)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrationAttributesForMergedMatch(Registration $registration, object $legacyRegistration): array
    {
        return [
            'registered_at' => $this->earliestDateTime(
                $registration->registered_at,
                $this->parseLegacyDateTime($legacyRegistration->create_at),
            ) ?? $registration->registered_at ?? now(),
            'attendance_status' => AttendanceStatus::Registered->value,
            'source' => RegistrationSource::LegacyImport->value,
            'remarks' => $this->mergeRemarks($registration->remarks, $this->buildRegistrationRemarks($legacyRegistration)),
        ];
    }

    /**
     * @param  array{certificate_type: CertificateType, template_key: string}|null  $eventDocumentData
     * @return array<string, mixed>
     */
    protected function certificateAttributes(
        Registration $registration,
        ?array $eventDocumentData,
        int $legacyRegistrationId,
        ?int $legacyEventId,
    ): array {
        if ($eventDocumentData === null) {
            return [];
        }

        return [
            'certificate_type' => $eventDocumentData['certificate_type'],
            'certificate_template_key' => $eventDocumentData['template_key'],
            'cert_serial_number' => $registration->cert_serial_number,
            'certificate_issued_at' => null,
            'certificate_metadata' => [
                'source' => RegistrationSource::LegacyImport->value,
                'legacy_registration_id' => $legacyRegistrationId,
                'legacy_event_id' => $legacyEventId,
            ],
        ];
    }

    protected function registrationPairKey(int $eventId, int $participantId): string
    {
        return "{$eventId}:{$participantId}";
    }

    protected function resolveBranchId(?string $branchName): ?int
    {
        $cleanBranchName = $this->cleanText($branchName);

        if ($cleanBranchName === null) {
            return null;
        }

        $normalizedBranchName = $this->normalizeKey($cleanBranchName);

        if (isset($this->branchIdsByName[$normalizedBranchName])) {
            return $this->branchIdsByName[$normalizedBranchName];
        }

        $branch = Branch::query()->firstOrCreate(
            ['name' => $cleanBranchName],
            ['legacy_id' => null, 'code' => null, 'is_active' => true],
        );

        $this->branchIdsByName[$normalizedBranchName] = $branch->id;

        return $branch->id;
    }

    protected function mapMembershipStatus(?string $status): string
    {
        $normalizedStatus = $this->normalizeKey($status);

        return $normalizedStatus === 'ahli puspanita'
            ? MembershipStatus::Member->value
            : MembershipStatus::NonMember->value;
    }

    protected function buildRegistrationRemarks(object $legacyRegistration): ?string
    {
        $remarks = array_filter([
            $this->cleanText($legacyRegistration->status_ahli),
            $this->cleanText($legacyRegistration->lain_lain),
        ]);

        if ($remarks === []) {
            return null;
        }

        return implode(' | ', $remarks);
    }

    protected function mergeRemarks(?string $existingRemarks, ?string $incomingRemarks): ?string
    {
        $remarks = array_filter([
            $this->cleanText($existingRemarks),
            $this->cleanText($incomingRemarks),
        ]);

        if ($remarks === []) {
            return null;
        }

        return implode(' | ', array_values(array_unique($remarks)));
    }

    protected function earliestDateTime(mixed $existingDateTime, ?CarbonImmutable $incomingDateTime): ?CarbonImmutable
    {
        $existing = $existingDateTime instanceof CarbonImmutable
            ? $existingDateTime
            : ($existingDateTime !== null ? CarbonImmutable::parse((string) $existingDateTime) : null);

        if ($existing === null) {
            return $incomingDateTime;
        }

        if ($incomingDateTime === null) {
            return $existing;
        }

        return $existing->lessThanOrEqualTo($incomingDateTime) ? $existing : $incomingDateTime;
    }

    protected function parseLegacyDate(?string $value): ?CarbonImmutable
    {
        $cleanValue = $this->cleanText($value);

        if ($cleanValue === null || $cleanValue === '0') {
            return null;
        }

        try {
            return CarbonImmutable::parse($cleanValue);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseLegacyDateTime(?string $value): ?CarbonImmutable
    {
        $cleanValue = $this->cleanText($value);

        if ($cleanValue === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($cleanValue);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseLegacyInteger(?string $value): ?int
    {
        $cleanValue = $this->cleanText($value);

        if ($cleanValue === null) {
            return null;
        }

        return is_numeric($cleanValue) ? (int) $cleanValue : null;
    }

    protected function normalizeEmail(?string $email, int $legacyRegistrationId, ?string $existingEmail = null): string
    {
        $cleanEmail = Str::lower($this->cleanText($email) ?? '');

        if ($cleanEmail !== '') {
            return $cleanEmail;
        }

        if ($existingEmail !== null && $existingEmail !== '') {
            return $existingEmail;
        }

        return "legacy-registration-{$legacyRegistrationId}@placeholder.local";
    }

    protected function normalizeNokp(?string $nokp): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $nokp);

        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    protected function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleanValue = trim(preg_replace('/\s+/', ' ', $value));

        return $cleanValue === '' ? null : $cleanValue;
    }

    protected function normalizeKey(?string $value): string
    {
        return Str::lower($this->cleanText($value) ?? '');
    }

    protected function summarize(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info("Legacy branches processed: {$this->importedBranches}");
        $this->command->info("Legacy events imported: {$this->importedEvents}");
        $this->command->info("Legacy participants upserted: {$this->importedParticipants}");
        $this->command->info("Legacy registrations created: {$this->createdRegistrations}");
        $this->command->info("Legacy registrations updated by legacy id: {$this->updatedRegistrations}");
        $this->command->info("Legacy certificates prepared: {$this->createdCertificates}");
        $this->command->warn("Legacy registrations merged: {$this->mergedRegistrations}");
        $this->command->warn("Legacy events skipped: {$this->skippedEvents}");
        $this->command->warn("Legacy registrations skipped: {$this->skippedRegistrations}");
        $this->command->warn('Final registration count: '.Registration::query()->count());
        $this->command->warn('Final certificate-ready registration count: '.Registration::query()->whereNotNull('certificate_type')->count());
    }
}
