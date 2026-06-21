@php
    use App\Enums\EventModule;
    use Illuminate\Support\Str;

    $registerOpen = $event->registration_open && $event->hasModule(EventModule::Registration);
    $canonical = route('events.landing', $event->slug);

    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->title,
        'description' => $event->description ?: null,
        'startDate' => $event->starts_at?->toIso8601String(),
        'endDate' => $event->ends_at?->toIso8601String(),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'url' => $canonical,
        'location' => $event->venue ? ['@type' => 'Place', 'name' => $event->venue] : null,
        'organizer' => $event->organizer_name ? ['@type' => 'Organization', 'name' => $event->organizer_name] : null,
    ], fn ($value) => $value !== null);

    $metaDescription = Str::limit(trim((string) ($event->description
        ?: 'Pendaftaran program '.$event->title.($event->venue ? ' di '.$event->venue : '').'.')), 160);
@endphp

<x-layouts.mono
    :title="$event->title"
    :description="$metaDescription"
    :canonical="$canonical"
>
    <div class="col">
        <p class="kicker">Pendaftaran Program</p>
        <h1>{{ $event->title }}</h1>
        @if ($event->description)
            <p class="lead">{{ $event->description }}</p>
        @endif

        <div class="stack">
            <section class="card" aria-label="Butiran program">
                <p class="card-title">Butiran Program</p>
                <dl class="rows">
                    <div class="row">
                        <dt class="k">Tarikh</dt>
                        <dd class="v">{{ $event->starts_at?->format('d M Y') ?: '—' }}</dd>
                    </div>
                    <div class="row">
                        <dt class="k">Masa</dt>
                        <dd class="v">{{ $event->start_time_text ?: $event->starts_at?->format('g:i A') ?: '—' }}@if ($event->end_time_text) – {{ $event->end_time_text }}@endif</dd>
                    </div>
                    <div class="row">
                        <dt class="k">Lokasi</dt>
                        <dd class="v">{{ $event->venue ?: 'Lokasi tidak dinyatakan' }}</dd>
                    </div>
                    <div class="row">
                        <dt class="k">Anjuran</dt>
                        <dd class="v">
                            @if ($event->organization)
                                <a href="{{ route('organizations.landing', $event->organization->slug) }}">{{ $event->organizer_name }}</a>
                            @else
                                {{ $event->organizer_name }}
                            @endif
                        </dd>
                    </div>
                </dl>

                <div style="margin-top:18px">
                    @if ($registerOpen)
                        <a class="btn btn-solid" href="{{ $event->publicRegistrationUrl() }}">Daftar Sekarang</a>
                    @else
                        <p class="notice">Pendaftaran ditutup buat masa ini.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <x-json-ld :data="$jsonLd" />
</x-layouts.mono>
