<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Contracts\View\View;

/**
 * The participant's own page (reached via their public_token link): shows the
 * check-in QR — which encodes the raw public_token the scanner posts to
 * /api/scan — and their per-event attendance status.
 */
class ParticipantStatusController extends Controller
{
    public function show(string $publicToken): View
    {
        $participant = Participant::query()
            ->where('public_token', $publicToken)
            ->with(['registrations' => fn ($query) => $query->with('event')->latest('registered_at')])
            ->firstOrFail();

        return view('participant.status', [
            'participant' => $participant,
        ]);
    }
}
