<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\Response;

/**
 * Crawl directives + a sitemap of the publicly indexable surfaces (home, the
 * certificate-lookup hub, and opt-in public event landing pages). Served via
 * routes rather than static files so the absolute URLs follow APP_URL across a
 * rebrand/domain change. The noindexed verify/status/success pages are excluded.
 */
class SitemapController extends Controller
{
    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /auth',
            'Disallow: /scan',
            'Disallow: /dev',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('certificate-lookup.index'), 'lastmod' => null],
        ];

        Event::query()
            ->where('status', EventStatus::Published->value)
            ->where('listed', true)
            ->whereNotNull('slug')
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Event $event) use (&$urls): void {
                $urls[] = [
                    'loc' => route('events.landing', $event->slug),
                    'lastmod' => $event->updated_at?->toAtomString(),
                ];
            });

        // Issuer profiles for organizations with at least one public event.
        Organization::query()
            ->whereHas('events', fn ($query) => $query
                ->where('status', EventStatus::Published->value)
                ->where('listed', true))
            ->orderBy('name')
            ->get(['slug'])
            ->each(function (Organization $organization) use (&$urls): void {
                $urls[] = ['loc' => route('organizations.landing', $organization->slug), 'lastmod' => null];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'
                .($url['lastmod'] ? '<lastmod>'.e($url['lastmod']).'</lastmod>' : '')
                .'</url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
