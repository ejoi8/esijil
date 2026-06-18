<x-layouts.mono
    :title="'Pas Kehadiran'"
    :description="'Pas kehadiran peserta — kod QR untuk daftar masuk program.'"
    :robots="'noindex,nofollow,noarchive'"
    :canonical="false"
>
    <div class="col">
        <p class="kicker">Pas Kehadiran</p>
        <h1>{{ $participant->full_name }}</h1>

        <div class="stack">
            <section class="card" aria-label="Kod QR Kehadiran">
                <p class="card-title">Kod QR Kehadiran</p>
                <p class="hint">Tunjukkan kod ini di kaunter untuk daftar masuk.</p>
                <div style="display:flex;justify-content:center;padding:18px">
                    <img
                        src="{{ \App\Support\QrCode::dataUri($participant->public_token) }}"
                        alt="Kod QR Kehadiran"
                        style="width:220px;height:220px"
                    >
                </div>
            </section>

            <section class="card" aria-label="Program">
                <p class="card-title">Program Anda</p>
                <dl class="rows">
                    @forelse ($participant->registrations as $registration)
                        <div class="row">
                            <dt class="k">{{ $registration->event?->title ?? '-' }}</dt>
                            <dd class="v">{{ $registration->checked_in_at ? 'Telah daftar masuk' : 'Belum daftar masuk' }}</dd>
                        </div>
                    @empty
                        <div class="row">
                            <dd class="v">Tiada program berdaftar.</dd>
                        </div>
                    @endforelse
                </dl>
            </section>
        </div>
    </div>
</x-layouts.mono>
