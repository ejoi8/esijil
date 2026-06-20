<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMatchMode;
use App\Http\Requests\ScanRequest;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScannerStation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Records a check-in scan. Stateless and lean (no session/Filament boot): the
 * station token authorizes, and the check-in is naturally idempotent because
 * checked_in_at is set once — a replayed/duplicate scan returns "already" with
 * no double effect.
 */
class ScanController extends Controller
{
    public function __invoke(ScanRequest $request): JsonResponse
    {
        $station = ScannerStation::query()
            ->where('token', (string) $request->string('station_token'))
            ->where('active', true)
            ->with('event')
            ->first();

        if ($station === null || $station->event === null
            || ($station->expires_at !== null && $station->expires_at->isPast())) {
            return response()->json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Invalid or expired scanner station.',
            ], 401);
        }

        $event = $station->event;
        $code = (string) $request->string('code');

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

        if ($registration === null) {
            return response()->json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Code not found for this event.',
            ]);
        }

        if ($registration->checked_in_at !== null) {
            return response()->json([
                'ok' => true,
                'status' => 'already',
                'name' => $participant->full_name,
                'checked_in_at' => $registration->checked_in_at->toIso8601String(),
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
}
