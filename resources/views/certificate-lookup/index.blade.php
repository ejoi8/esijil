<x-layouts.mono
    :title="'Semakan Sijil'"
    :description="'Semak sijil program PUSPANITA menggunakan emel dan muat turun sijil digital yang tersedia secara terus.'"
    :canonical="route('certificate-lookup.index')"
>
    <div class="col">
        <p class="kicker">Semakan Sijil</p>
        <h1>Semakan dan Muat Turun Sijil</h1>
        <p class="lead">Masukkan emel anda untuk semak dan muat turun sijil program PUSPANITA yang tersedia.</p>

        <div class="stack">
            <section class="card" aria-label="Carian sijil">
                <form action="{{ route('certificate-lookup.search') }}" method="POST">
                    @csrf

                    <div class="field">
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

                    <div class="formfoot">
                        <p class="hint">Semakan dihadkan untuk mengelakkan penggunaan berulang secara berlebihan.</p>
                        <button type="submit" class="btn btn-solid">Semak Sijil</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-layouts.mono>
