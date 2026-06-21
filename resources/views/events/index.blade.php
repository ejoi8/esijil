@php
    $isSearch = $q !== '';
    $canonical = $isSearch ? false : ($events->currentPage() > 1 ? $events->url($events->currentPage()) : route('events.index'));
    $itemList = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => collect($events->items())->map(fn ($event, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => route('events.landing', $event->slug),
            'name' => $event->title,
        ])->all(),
    ];
@endphp

<x-layouts.mono
    :title="'Senarai Acara'"
    :description="'Senarai acara dan program yang dibuka untuk pendaftaran awam — cari dan daftar acara anda.'"
    :canonical="$canonical"
    :robots="$isSearch ? 'noindex,follow' : 'index,follow'"
>
    <style>
        .ev-pill{display:inline-flex;align-items:center;font-size:12px;font-weight:600;border-radius:999px;padding:4px 10px;border:1px solid var(--line);color:var(--muted)}
        .ev-pill.ok{border-color:var(--success);color:var(--success)}
        .ev-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .pager{display:flex;justify-content:space-between;gap:12px;margin-top:22px}
    </style>

    <div class="col">
        <p class="kicker">Acara</p>
        <h1>Senarai acara</h1>
        <p class="lead">Terokai acara dan program yang dibuka untuk pendaftaran awam.</p>

        <form action="{{ route('events.index') }}" method="GET" style="margin-top:18px">
            <div class="field">
                <label class="label" for="q">Cari acara</label>
                <input id="q" name="q" type="search" class="input" value="{{ $q }}" placeholder="Nama acara…" autocomplete="off">
            </div>
        </form>

        <div class="stack">
            @forelse ($events as $event)
                <section class="card" aria-label="Acara">
                    <h2 style="font-size:18px;letter-spacing:-.02em;margin:0 0 6px">
                        <a href="{{ route('events.landing', $event->slug) }}">{{ $event->title }}</a>
                    </h2>
                    <p class="hint" style="margin:0">
                        {{ $event->starts_at?->format('d M Y') ?: 'Tarikh belum ditetapkan' }}@if ($event->venue) · {{ $event->venue }}@endif@if ($event->organization) · <a href="{{ route('organizations.landing', $event->organization->slug) }}">{{ $event->organizer_name }}</a>@endif
                    </p>
                    <div class="ev-meta">
                        @if ($event->registration_open)
                            <span class="ev-pill ok">Pendaftaran dibuka</span>
                        @else
                            <span class="ev-pill">Pendaftaran ditutup</span>
                        @endif
                        @if ($event->capacity !== null)
                            @php $left = max(0, $event->capacity - $event->registrations_count); @endphp
                            <span class="ev-pill {{ $left > 0 ? 'ok' : '' }}">{{ $left > 0 ? 'Baki '.$left.' tempat' : 'Penuh' }}</span>
                        @endif
                    </div>
                </section>
            @empty
                <section class="card">
                    <p class="hint" style="margin:0">{{ $q !== '' ? 'Tiada acara ditemui untuk carian ini.' : 'Tiada acara umum buat masa ini.' }}</p>
                </section>
            @endforelse
        </div>

        @if ($events->hasPages())
            <div class="pager">
                @if ($events->previousPageUrl())
                    <a class="btn btn-line" href="{{ $events->previousPageUrl() }}">← Sebelumnya</a>
                @else
                    <span></span>
                @endif
                @if ($events->nextPageUrl())
                    <a class="btn btn-line" href="{{ $events->nextPageUrl() }}">Seterusnya →</a>
                @endif
            </div>
        @endif
    </div>

    <x-json-ld :data="$itemList" />
</x-layouts.mono>
