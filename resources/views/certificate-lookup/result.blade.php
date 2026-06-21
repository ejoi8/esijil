<x-layouts.mono
    :title="'Keputusan Semakan Sijil'"
    :description="'Halaman keputusan semakan sijil untuk peserta yang telah membuat carian menggunakan emel.'"
    :robots="'noindex,nofollow,noarchive'"
    :canonical="false"
>
    <style>
        .certlist{margin:0}
        .certrow{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 0;border-top:1px solid var(--line)}
        .certrow:first-of-type{border-top:0;padding-top:0}
        .certrow .meta{min-width:0}
        .certrow .t{font-weight:600;color:var(--ink);margin:0;overflow-wrap:anywhere}
        .certrow .d{color:var(--muted);font-size:14px;margin:4px 0 0}
        .certrow .none{color:var(--faint);font-size:13px;font-weight:600;flex:none}
        .empty{color:var(--muted);font-size:15px;margin:0}
    </style>

    <div class="col">
        <p class="kicker">Keputusan Semakan</p>
        <h1>{{ $participant->full_name }}</h1>
        <p class="lead">{{ $participant->email }} &middot; {{ $registrations->count() }} rekod sijil</p>

        <div class="stack">
            <section class="card" aria-label="Senarai sijil">
                <div class="certlist">
                    @forelse ($registrations as $registration)
                        <div class="certrow">
                            <div class="meta">
                                <p class="t">{{ $registration->event->title }}</p>
                                <p class="d">{{ $registration->event->starts_at?->format('d M Y') }}</p>
                            </div>
                            @if ($registration->certificate_template_id !== null)
                                <a class="btn btn-solid" href="{{ route('certificate-lookup.download', $registration) }}">Muat Turun</a>
                            @else
                                <span class="none">Tiada sijil</span>
                            @endif
                        </div>
                    @empty
                        <p class="empty">Tiada rekod sijil dijumpai untuk peserta ini.</p>
                    @endforelse
                </div>
            </section>

            <a class="btn btn-line" href="{{ route('certificate-lookup.index') }}">Buat Semakan Baru</a>
        </div>
    </div>
</x-layouts.mono>
