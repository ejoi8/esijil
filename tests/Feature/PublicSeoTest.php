<?php

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves robots.txt with disallows and a sitemap directive', function () {
    $res = $this->get('/robots.txt')->assertOk();

    expect($res->headers->get('Content-Type'))->toContain('text/plain');
    $res->assertSee('Disallow: /auth', false)
        ->assertSee('Disallow: /scan', false)
        ->assertSee('Disallow: /dev', false)
        ->assertSee('Sitemap:', false);
});

it('lists only opt-in published events in the sitemap', function () {
    $listed = Event::factory()->create(['status' => EventStatus::Published, 'listed' => true]);
    $unlisted = Event::factory()->create(['status' => EventStatus::Published, 'listed' => false]);
    $draft = Event::factory()->create(['status' => EventStatus::Draft, 'listed' => true]);

    $res = $this->get('/sitemap.xml')->assertOk();

    expect($res->headers->get('Content-Type'))->toContain('application/xml');
    $res->assertSee($listed->slug, false)
        ->assertDontSee($unlisted->slug, false)
        ->assertDontSee($draft->slug, false);
});

it('renders a public landing page (with Event JSON-LD) for an opt-in published event', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'listed' => true,
        'registration_open' => true,
    ]);

    $this->get(route('events.landing', $event->slug))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('application/ld+json', false)
        ->assertSee('schema.org', false)
        ->assertSee('index,follow', false)
        ->assertSee('Daftar Sekarang');
});

it('404s the landing page for unlisted or draft events', function () {
    $unlisted = Event::factory()->create(['status' => EventStatus::Published, 'listed' => false]);
    $draft = Event::factory()->create(['status' => EventStatus::Draft, 'listed' => true]);

    $this->get(route('events.landing', $unlisted->slug))->assertNotFound();
    $this->get(route('events.landing', $draft->slug))->assertNotFound();
});
