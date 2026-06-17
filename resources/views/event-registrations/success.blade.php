<x-layouts.mono
    :title="'Pendaftaran Diterima'"
    :description="'Halaman pengesahan selepas pendaftaran program berjaya diterima melalui pautan jemputan.'"
    :robots="'noindex,nofollow,noarchive'"
    :canonical="false"
>
    <div class="col">
        <p class="kicker">Status Pendaftaran</p>
        <h1>Pendaftaran berjaya diterima.</h1>
        <p class="lead">
            Sijil tidak dijana semasa pendaftaran supaya proses kekal laju ketika trafik tinggi. Jana sijil apabila anda perlukannya.
        </p>

        <div class="stack">
            <section class="card" aria-label="Butiran pendaftaran">
                <p class="card-title">Butiran Pendaftaran</p>
                <dl class="rows">
                    <div class="row">
                        <dt class="k">Nama</dt>
                        <dd class="v">{{ $registration->participant->full_name }}</dd>
                    </div>
                    <div class="row">
                        <dt class="k">No. KP</dt>
                        <dd class="v">{{ $registration->participant->nokp }}</dd>
                    </div>
                    <div class="row">
                        <dt class="k">Program</dt>
                        <dd class="v">{{ $registration->event->title }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card" aria-label="Sijil">
                <p class="card-title">Sijil</p>
                <p class="hint">PDF dijana di server setiap kali anda memuat turun sijil ini.</p>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
                    @if ($registration->certificate_type !== null)
                        <a class="btn btn-solid" href="{{ route('events.register.certificate', $registration) }}">
                            Muat Turun Sijil
                        </a>
                    @endif
                    <a class="btn btn-line" href="{{ route('certificate-lookup.index') }}">
                        Semakan Sijil
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-layouts.mono>
