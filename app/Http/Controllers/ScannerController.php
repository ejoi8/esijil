<?php

namespace App\Http\Controllers;

use App\Models\ScannerStation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The operator's scanning screen, opened via the station's token URL.
 *
 * Access: when a station has a PIN it gates the scanner. A logged-in member of
 * the event's organization bypasses the prompt — the page supplies the PIN to
 * the scan API on their behalf — so admins skip it while shared links still
 * require it. /api/scan independently enforces the PIN, so the gate can't be
 * bypassed by calling the API directly.
 */
class ScannerController extends Controller
{
    public function show(Request $request, string $stationToken): View
    {
        $station = ScannerStation::query()
            ->where('token', $stationToken)
            ->where('active', true)
            ->with('event')
            ->firstOrFail();

        abort_if($station->event === null, 404);
        abort_if($station->expires_at !== null && $station->expires_at->isPast(), 404);

        $user = $request->user();
        $isMember = $user !== null
            && $user->organizations()->whereKey($station->event->organization_id)->exists();

        return view('scanner.show', [
            'station' => $station,
            'event' => $station->event,
            // Non-members must enter the PIN (when the station has one); members
            // get it embedded so they skip the prompt.
            'pinRequired' => filled($station->pin) && ! $isMember,
            'embeddedPin' => $isMember ? (string) $station->pin : null,
        ]);
    }
}
