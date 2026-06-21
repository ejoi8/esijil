<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\CustomFieldEntity;
use App\Enums\ScanMatchMode;
use App\Fields\CustomFields;
use App\Http\Requests\ScanRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScannerStation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a scanned code to a participant and (optionally) records the check-in.
 *
 * Two modes via the `confirm` flag:
 *   - confirm=false  identify only — returns who it is, their status, and their
 *                    registration details WITHOUT writing anything, so an
 *                    operator can verify the data before committing.
 *   - confirm=true   records the check-in (default; idempotent — a second scan
 *                    returns "already" with no double effect).
 *
 * When a station has a PIN it must accompany every scan. `verifyPin()` lets the
 * scanner page check the PIN up front (before opening the camera); the scan
 * endpoint re-checks it so the gate can't be bypassed by calling the API.
 */
class ScanController extends Controller
{
    public function __invoke(ScanRequest $request): JsonResponse
    {
        $station = $this->resolveStation((string) $request->string('station_token'));

        if ($station === null) {
            return response()->json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Stesen pengimbas tidak sah atau telah tamat tempoh.',
            ], 401);
        }

        if (! $this->pinAuthorized($station, $request)) {
            return response()->json([
                'ok' => false,
                'status' => 'pin_invalid',
                'message' => 'PIN stesen diperlukan atau salah.',
            ], 403);
        }

        $event = $station->event;
        $code = (string) $request->string('code');
        $confirm = $request->boolean('confirm', true);

        $participant = Participant::query()
            ->where('organization_id', $event->organization_id)
            ->when(
                $event->scan_match_mode === ScanMatchMode::ExternalId,
                fn (Builder $query): Builder => $query->where('external_id', $code),
                fn (Builder $query): Builder => $query->where('public_token', $code),
            )
            ->first();

        $registration = $participant === null ? null : Registration::query()
            ->where('event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($participant === null || $registration === null) {
            return response()->json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Kod tidak ditemui untuk program ini.',
            ]);
        }

        // Identify-only reads expose participant data without recording anything,
        // so log them to keep the disclosure auditable.
        if (! $confirm) {
            Log::info('scan.identify', [
                'station_id' => $station->id,
                'event_id' => $event->id,
                'registration_id' => $registration->id,
                'mode' => $event->scan_match_mode->value,
            ]);
        }

        // Already checked in — no action either way. Include the details when only
        // identifying so the operator can still review them.
        if ($registration->checked_in_at !== null) {
            $payload = [
                'ok' => true,
                'status' => 'already',
                'name' => $participant->full_name,
                'checked_in_at' => $registration->checked_in_at->toIso8601String(),
            ];

            if (! $confirm) {
                $payload['fields'] = $this->fieldsFor($event, $registration);
            }

            return response()->json($payload);
        }

        // Identify only — return the data without recording anything.
        if (! $confirm) {
            return response()->json([
                'ok' => true,
                'status' => 'found',
                'name' => $participant->full_name,
                'registered_at' => $registration->registered_at?->toIso8601String(),
                'fields' => $this->fieldsFor($event, $registration),
            ]);
        }

        $registration->forceFill([
            'checked_in_at' => now(),
            'attendance_status' => AttendanceStatus::Attended->value,
            'checked_in_station_id' => $station->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'status' => 'present',
            'name' => $participant->full_name,
            'checked_in_at' => $registration->checked_in_at->toIso8601String(),
        ]);
    }

    /**
     * Up-front PIN check for the scanner gate: {ok:true} when the station has no
     * PIN or the supplied PIN matches, so the page only opens the camera on a
     * correct PIN. Shares the per-station throttle to bound brute-forcing.
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $request->validate([
            'station_token' => ['required', 'string'],
            'pin' => ['nullable', 'string', 'max:20'],
            'bypass' => ['nullable', 'string'],
        ]);

        $station = $this->resolveStation((string) $request->string('station_token'));

        if ($station === null) {
            return response()->json(['ok' => false], 401);
        }

        $ok = $this->pinAuthorized($station, $request);

        return response()->json(['ok' => $ok], $ok ? 200 : 403);
    }

    /** Resolve an active, non-expired station with an event, or null. */
    private function resolveStation(string $token): ?ScannerStation
    {
        $station = ScannerStation::query()
            ->where('token', $token)
            ->where('active', true)
            ->with('event')
            ->first();

        if ($station === null || $station->event === null
            || ($station->expires_at !== null && $station->expires_at->isPast())) {
            return null;
        }

        return $station;
    }

    /**
     * A station with no PIN is open; otherwise the request must carry the correct
     * PIN (checked against the stored hash) or a valid member bypass token issued
     * by the scanner page to a logged-in organization member.
     */
    private function pinAuthorized(ScannerStation $station, Request|ScanRequest $request): bool
    {
        if (! filled($station->pin)) {
            return true;
        }

        $pin = (string) $request->string('pin');
        if ($pin !== '' && Hash::check($pin, (string) $station->pin)) {
            return true;
        }

        $bypass = (string) $request->string('bypass');
        if ($bypass !== '') {
            try {
                return hash_equals('scan-bypass:'.$station->id, (string) decrypt($bypass));
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * The event's registration custom fields as label/value pairs (only those
     * that have a value), so the operator can verify the data before checking in.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function fieldsFor(Event $event, Registration $registration): array
    {
        return CustomFields::definitions(CustomFieldEntity::Registration, $event)
            ->map(fn ($field): array => [
                'label' => (string) $field->label,
                'value' => CustomFields::display($field, data_get($registration->details, $field->key)),
            ])
            ->filter(fn (array $field): bool => $field['value'] !== '')
            ->values()
            ->all();
    }
}
