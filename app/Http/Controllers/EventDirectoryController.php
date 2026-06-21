<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public, indexable directory of every opt-in ("listed") published event across
 * all organizations — the cross-tenant browse / discovery + SEO surface.
 * Searchable by title and paginated; unlisted and draft events never appear.
 */
class EventDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $events = Event::query()
            ->publiclyListed()
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'))
            ->with('organization:id,slug,name')
            ->withCount('registrations')
            ->orderByDesc('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('events.index', ['events' => $events, 'q' => $q]);
    }
}
