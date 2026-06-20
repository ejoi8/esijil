<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A printable sheet of participant check-in QR codes for an event (print to PDF
 * in the browser). Admin-gated and scoped to the event's organization.
 */
class EventQrSheetController extends Controller
{
    public function __invoke(Request $request, Event $event): View
    {
        $user = $request->user();

        abort_unless($user?->can('registration.view') ?? false, 403);
        abort_unless($user->organizations()->whereKey($event->organization_id)->exists(), 403);

        $event->load(['registrations' => fn ($query) => $query->with('participant')->orderBy('registered_at')]);

        return view('qr-sheet', ['event' => $event]);
    }
}
