<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Contracts\View\View;

/**
 * Public issuer / organizer profile aggregating an organization's opt-in public
 * events. Indexable only when the org has at least one listed + published event,
 * so organizations that never opted into public listing stay undiscoverable and
 * the page is never thin/empty.
 */
class OrganizationLandingController extends Controller
{
    public function show(Organization $organization): View
    {
        $events = $organization->events()
            ->publiclyListed()
            ->orderByDesc('starts_at')
            ->get();

        abort_if($events->isEmpty(), 404);

        return view('organization-landing', [
            'organization' => $organization,
            'events' => $events,
        ]);
    }
}
