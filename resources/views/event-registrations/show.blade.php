<x-layouts.mono
    :title="$event->title"
    :description="trim(collect([
        $event->description,
        $event->venue ? 'Lokasi: '.$event->venue : null,
        $event->starts_at?->format('d M Y') ? 'Tarikh: '.$event->starts_at?->format('d M Y') : null,
    ])->filter()->implode(' | ')) ?: 'Pendaftaran program PUSPANITA melalui pautan jemputan yang dikongsi kepada peserta.'"
    :robots="'noindex,nofollow,noarchive'"
    :canonical="false"
>
    <div class="col">
        <p class="kicker">Pendaftaran Program</p>
        <h1>{{ $event->title }}</h1>
        <p class="lead">
            {{ $event->description ?: 'Lengkapkan maklumat di bawah untuk mendaftar program ini.' }}
        </p>

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
                        <dd class="v">
                            {{ $event->start_time_text ?: $event->starts_at?->format('g:i A') ?: '—' }}@if ($event->end_time_text) – {{ $event->end_time_text }}@endif
                        </dd>
                    </div>
                    <div class="row">
                        <dt class="k">Lokasi</dt>
                        <dd class="v">{{ $event->venue ?: 'Lokasi tidak dinyatakan' }}</dd>
                    </div>
                    <div class="row">
                        <dt class="k">Anjuran</dt>
                        <dd class="v">{{ $event->organizer_name }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card" aria-label="Maklumat peserta">
                <p class="card-title">Maklumat Peserta</p>

                @if (session('registration_created'))
                    <div class="notice is-success">Pendaftaran berjaya diterima.</div>
                @endif

                @if (session('registration_exists'))
                    <div class="notice">Rekod pendaftaran untuk No. KP ini telah wujud.</div>
                @endif

                @if ($errors->has('event'))
                    <div class="notice is-danger">{{ $errors->first('event') }}</div>
                @endif

                @if (! $registrationIsOpen)
                    <div class="notice">Pendaftaran belum dibuka atau telah ditutup.</div>
                @endif

                <form action="{{ request()->fullUrl() }}" method="POST" data-registration-form style="margin-top:18px">
                    @csrf

                    <div class="field">
                        <label class="label" for="full_name">Nama Penuh</label>
                        <input
                            id="full_name"
                            name="full_name"
                            type="text"
                            class="input"
                            value="{{ old('full_name') }}"
                            placeholder="Nama seperti dalam kad pengenalan"
                            required
                        >
                        @error('full_name')<p class="err">{{ $message }}</p>@enderror
                    </div>

                    <div class="field grid2">
                        <div>
                            <label class="label" for="nokp">No. KP</label>
                            <input
                                id="nokp"
                                name="nokp"
                                type="text"
                                inputmode="numeric"
                                class="input"
                                value="{{ old('nokp') }}"
                                placeholder="Contoh: 900101015555"
                                required
                            >
                            @error('nokp')<p class="err">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="label" for="email">Emel</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                class="input"
                                value="{{ old('email') }}"
                                placeholder="nama@email.com"
                                required
                            >
                            @error('email')<p class="err">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="field grid2">
                        <div>
                            <label class="label" for="phone">Telefon</label>
                            <input
                                id="phone"
                                name="phone"
                                type="text"
                                inputmode="tel"
                                class="input"
                                value="{{ old('phone') }}"
                                placeholder="Nombor telefon"
                            >
                            @error('phone')<p class="err">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="label" for="membership_status">Status Ahli</label>
                            <div class="selectwrap">
                                <select id="membership_status" name="membership_status" class="select" required>
                                    <option value="member" @selected(old('membership_status') === 'member')>Ahli</option>
                                    <option value="non_member" @selected(old('membership_status', 'non_member') === 'non_member')>Bukan Ahli</option>
                                </select>
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            @error('membership_status')<p class="err">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="formfoot">
                        <p class="hint">Tekan hantar sekali sahaja. Jika rekod telah wujud, mesej status akan dipaparkan.</p>
                        <button type="submit" class="btn btn-solid" @disabled(! $registrationIsOpen) data-submit-button>
                            <span data-submit-label>Hantar Pendaftaran</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-registration-form]');
            const submitButton = form?.querySelector('[data-submit-button]');
            const submitLabel = submitButton?.querySelector('[data-submit-label]');

            if (! form || ! submitButton || ! submitLabel) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (submitButton.disabled) {
                    event.preventDefault();

                    return;
                }

                submitButton.disabled = true;
                submitButton.setAttribute('aria-disabled', 'true');
                submitLabel.textContent = 'Sedang Dihantar...';
            });
        });
    </script>
</x-layouts.mono>
