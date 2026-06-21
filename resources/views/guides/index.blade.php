<x-layouts.mono
    :title="'Panduan'"
    :description="'Panduan ringkas mengurus pendaftaran acara, kehadiran QR dan sijil digital untuk persatuan, sekolah dan penganjur.'"
    :canonical="route('guides.index')"
>
    <div class="col">
        <p class="kicker">Panduan</p>
        <h1>Panduan &amp; sumber</h1>
        <p class="lead">Tip ringkas untuk mengurus pendaftaran, kehadiran dan sijil program anda.</p>

        <div class="stack">
            @foreach ($guides as $slug => $guide)
                <section class="card">
                    <h2 style="font-size:18px;letter-spacing:-.02em;margin:0 0 6px">
                        <a href="{{ route('guides.show', $slug) }}">{{ $guide['title'] }}</a>
                    </h2>
                    <p class="hint" style="margin:0">{{ $guide['description'] }}</p>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.mono>
