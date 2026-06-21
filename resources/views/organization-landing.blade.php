@php
    use Illuminate\Support\Str;

    $canonical = route('organizations.landing', $organization);

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $organization->name,
        'url' => $canonical,
    ];

    $metaDescription = Str::limit('Program & acara anjuran '.$organization->name.' yang dibuka untuk pendaftaran awam.', 160);
@endphp

<x-layouts.mono
    :title="$organization->name"
    :description="$metaDescription"
    :canonical="$canonical"
>
    <div class="col">
        <p class="kicker">Penganjur</p>
        <h1>{{ $organization->name }}</h1>
        <p class="lead">Program &amp; acara anjuran {{ $organization->name }} yang dibuka untuk pendaftaran.</p>

        <div class="stack">
            <section class="card" aria-label="Senarai acara">
                <p class="card-title">Acara</p>
                <ul style="list-style:none;margin:0;padding:0">
                    @foreach ($events as $event)
                        <li style="padding:12px 0;border-top:1px solid var(--line)">
                            <a href="{{ route('events.landing', $event->slug) }}">{{ $event->title }}</a>
                            <div class="hint">{{ $event->starts_at?->format('d M Y') ?: '' }}@if ($event->venue) · {{ $event->venue }}@endif</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

    <x-json-ld :data="$jsonLd" />
</x-layouts.mono>
