<?php

namespace App\Http\Controllers;

use App\Models\ScannerStation;
use Illuminate\Contracts\View\View;

/**
 * The operator's scanning screen, opened via the station's token URL (no
 * account). The page POSTs scanned codes to the stateless /api/scan endpoint.
 */
class ScannerController extends Controller
{
    public function show(string $stationToken): View
    {
        $station = ScannerStation::query()
            ->where('token', $stationToken)
            ->where('active', true)
            ->with('event')
            ->firstOrFail();

        abort_if($station->event === null, 404);
        abort_if($station->expires_at !== null && $station->expires_at->isPast(), 404);

        return view('scanner.show', [
            'station' => $station,
            'event' => $station->event,
        ]);
    }
}
