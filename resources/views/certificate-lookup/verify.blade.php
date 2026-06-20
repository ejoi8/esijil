<x-layouts.mono
    :title="'Pengesahan Sijil'"
    :description="'Pengesahan kesahihan sijil eSIJIL melalui nombor rujukan.'"
    :robots="'noindex,nofollow,noarchive'"
    :canonical="false"
>
    <div class="col">
        <p class="kicker">Pengesahan Sijil</p>

        @if ($registration)
            <h1>Sijil Sah</h1>
            <p class="lead">Sijil ini sah dan telah dikeluarkan melalui eSIJIL.</p>

            <div class="stack">
                <section class="card" aria-label="Butiran sijil">
                    <dl class="rows">
                        <div class="row">
                            <dt class="k">Nama</dt>
                            <dd class="v">{{ $registration->participant?->full_name ?? '-' }}</dd>
                        </div>
                        <div class="row">
                            <dt class="k">Program</dt>
                            <dd class="v">{{ $registration->event?->title ?? '-' }}</dd>
                        </div>
                        <div class="row">
                            <dt class="k">Tarikh</dt>
                            <dd class="v">{{ $registration->event?->starts_at?->format('d M Y') ?? '-' }}</dd>
                        </div>
                        <div class="row">
                            <dt class="k">No. Rujukan</dt>
                            <dd class="v">{{ $registration->cert_serial_number }}</dd>
                        </div>
                    </dl>
                </section>

                <a class="btn btn-line" href="{{ route('certificate-lookup.index') }}">Semak Sijil Lain</a>
            </div>
        @else
            <h1>Sijil Tidak Dijumpai</h1>
            <p class="lead">Tiada sijil sah dengan nombor rujukan <strong>{{ $serial }}</strong>.</p>

            <div class="stack">
                <a class="btn btn-line" href="{{ route('certificate-lookup.index') }}">Buat Semakan</a>
            </div>
        @endif
    </div>
</x-layouts.mono>
