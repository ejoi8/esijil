<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Contracts\View\View;

/**
 * Public, indexable landing page for an event the organizer has opted to list
 * (status = Published AND listed = true). A read-only marketing/SEO surface —
 * the registration submission itself stays behind the signed route, and events
 * are not discoverable unless explicitly listed (privacy-preserving default).
 */
class EventLandingController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless(
            EventStatus::fromMixed($event->status) === EventStatus::Published && $event->listed,
            404,
        );

        return view('event-landing', ['event' => $event]);
    }
}
