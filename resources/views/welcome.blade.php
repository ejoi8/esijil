<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Sistem pendaftaran acara, kehadiran QR &amp; sijil digital</title>
    <meta name="description" content="{{ config('app.name') }} ialah platform acara hujung-ke-hujung: cipta pautan pendaftaran boleh kongsi, rekod kehadiran dengan imbasan QR, dan terbitkan sijil digital yang boleh disahkan — semuanya dalam satu tempat.">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="canonical" href="{{ route('home') }}">

    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name') }} — Pendaftaran acara, kehadiran QR &amp; sijil digital">
    <meta property="og:description" content="Cipta pautan pendaftaran, rekod kehadiran dengan QR, dan terbitkan sijil digital boleh sahih — dalam satu platform.">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ url('/images/og/esijil-share.svg') }}">
    <meta property="og:image:alt" content="{{ config('app.name') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name') }} — Pendaftaran acara, kehadiran QR &amp; sijil digital">
    <meta name="twitter:description" content="Cipta pautan pendaftaran, rekod kehadiran dengan QR, dan terbitkan sijil digital boleh sahih — dalam satu platform.">
    <meta name="twitter:image" content="{{ url('/images/og/esijil-share.svg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        @include('partials.mono-tokens')

        html{scroll-behavior:smooth}
        section{scroll-margin-top:72px}

        /* nav */
        .nav{display:flex;align-items:center;gap:22px}
        .nav-links{display:flex;align-items:center;gap:22px}
        .nav-links a{color:var(--muted);font-size:14px;font-weight:500;transition:color .15s ease}
        .nav-links a:hover{color:var(--ink)}
        .nav-cta{display:flex;align-items:center;gap:16px}
        .lnk-login{color:var(--muted);font-size:14px;font-weight:600;transition:color .15s ease}
        .lnk-login:hover{color:var(--ink)}
        @media (max-width:720px){.nav-links{display:none}}

        .linkarrow.on-dark{color:rgba(255,255,255,.7)}
        .linkarrow.on-dark:hover{color:#fff}

        /* section rhythm + bands */
        .sec{padding:88px 0}
        .sec-alt{background:var(--alt);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
        .inner{max-width:640px;margin:0 auto}
        .center{text-align:center}
        .sec-head{max-width:560px;margin:0 auto}
        .sec-head.center{text-align:center}
        h2{font-size:clamp(26px,4vw,36px);line-height:1.08;letter-spacing:-.03em;font-weight:700;margin:10px 0 0}
        .lead{color:var(--muted);font-size:17px;line-height:1.5;margin:12px 0 0}

        /* hero */
        .hero{padding:84px 0 80px}
        .hero h1{font-size:clamp(36px,6.4vw,60px);line-height:1.03;letter-spacing:-.035em;font-weight:700;margin:14px 0 0;max-width:14ch}
        .hero .lead{max-width:46ch}
        .hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:18px;margin-top:30px}

        /* grids + feature cards */
        .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:40px}
        @media (max-width:880px){.grid3{grid-template-columns:repeat(2,1fr)}}
        @media (max-width:560px){.grid3{grid-template-columns:1fr}}
        .card .ico{color:var(--ink);margin-bottom:14px}
        .card h3{font-size:17px;font-weight:600;letter-spacing:-.02em;margin:0 0 6px}
        .card p{color:var(--muted);font-size:15px;margin:0}

        /* steps */
        .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:40px}
        @media (max-width:880px){.steps{grid-template-columns:repeat(2,1fr)}}
        @media (max-width:560px){.steps{grid-template-columns:1fr}}
        .step{border:1px solid var(--line);border-radius:14px;padding:24px;background:#fff;transition:border-color .15s ease}
        .step:hover{border-color:var(--ink)}
        .step .num{font-size:13px;font-weight:700;letter-spacing:.04em;color:var(--faint);margin:0 0 12px}
        .step h3{font-size:16px;font-weight:600;letter-spacing:-.02em;margin:0 0 6px}
        .step p{color:var(--muted);font-size:14px;margin:0}

        /* doc preview */
        .preview{max-width:520px;margin:40px auto 0}
        .preview-card{border:1px solid var(--line);border-radius:16px;padding:28px;background:#fff}
        .preview-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:16px;border-bottom:1px solid var(--line)}
        .brand-mini{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;letter-spacing:.04em;color:var(--ink)}
        .brand-mini .mini-mark{width:20px;height:20px;border-radius:6px;background:var(--ink);display:inline-flex;align-items:center;justify-content:center;flex:none}
        .pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--ink);border:1px solid var(--line);border-radius:999px;padding:5px 11px}
        .recipient{padding:18px 0 14px;border-bottom:1px dashed var(--line)}
        .recipient .name{font-size:24px;font-weight:700;letter-spacing:-.02em;color:var(--ink);margin:0}
        .recipient .sub{font-size:13px;font-weight:600;color:var(--muted);margin:6px 0 0}
        .row.big .v{font-size:22px;letter-spacing:-.02em}

        /* FAQ */
        .faq{max-width:680px;margin:36px auto 0;border-top:1px solid var(--line)}
        .faq details{border-bottom:1px solid var(--line)}
        .faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 0;font-size:16px;font-weight:600;letter-spacing:-.015em}
        .faq summary::-webkit-details-marker{display:none}
        .faq summary:focus-visible{outline:2px solid var(--ink);outline-offset:4px;border-radius:4px}
        .toggle{position:relative;width:16px;height:16px;flex:none}
        .toggle::before,.toggle::after{content:"";position:absolute;left:50%;top:50%;background:var(--ink);border-radius:2px;transition:transform .2s ease}
        .toggle::before{width:1.5px;height:16px;transform:translate(-50%,-50%)}
        .toggle::after{width:16px;height:1.5px;transform:translate(-50%,-50%)}
        .faq details[open] .toggle::before{transform:translate(-50%,-50%) rotate(45deg)}
        .faq details[open] .toggle::after{transform:translate(-50%,-50%) rotate(45deg)}
        .faq .answer{color:var(--muted);font-size:15px;padding:0 0 22px;max-width:60ch}

        /* CTA band (single inverted moment) */
        .cta{background:var(--ink);color:#fff;border-radius:22px;padding:64px 32px;text-align:center}
        .cta h2{color:#fff;margin:0}
        .cta p{color:rgba(255,255,255,.66);font-size:17px;margin:14px auto 0;max-width:42ch}
        .cta .acts{margin-top:30px;display:flex;flex-wrap:wrap;gap:16px;justify-content:center}

        /* footer */
        .site-foot .wrap{padding:36px 24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px}
        .foot-links{display:flex;align-items:center;gap:22px}
        .foot-links a{color:var(--muted);font-size:14px;font-weight:500;transition:color .15s ease}
        .foot-links a:hover{color:var(--ink)}
        .em{color:var(--faint)}
    </style>
</head>
<body>
    {{-- ===== Sticky nav ===== --}}
    <header class="site-head">
        <div class="wrap">
            <a class="brand" href="#atas" aria-label="{{ config('app.name') }}">
                <x-brand-mark />
                {{ config('app.name') }}
            </a>
            <nav class="nav" aria-label="Navigasi utama">
                <div class="nav-links">
                    <a href="#ciri">Ciri</a>
                    <a href="#cara">Cara guna</a>
                    <a href="#sijil">Contoh sijil</a>
                    <a href="#soalan">Soalan lazim</a>
                    <a href="{{ route('events.index') }}">Acara</a>
                    <a href="{{ route('guides.index') }}">Panduan</a>
                </div>
                <div class="nav-cta">
                    <a class="lnk-login" href="{{ url('/auth') }}">Log Masuk</a>
                    <a class="btn btn-solid" href="{{ route('certificate-lookup.index') }}">Semak Sijil</a>
                </div>
            </nav>
        </div>
    </header>

    <main id="atas">
        {{-- ===== Hero ===== --}}
        <section class="hero">
            <div class="wrap">
                <div class="inner">
                    <p class="kicker">Platform acara hujung-ke-hujung</p>
                    <h1>Daftar. Hadir. Sijil. Dalam satu platform.</h1>
                    <p class="lead">{{ config('app.name') }} membantu persatuan, agensi, sekolah dan penganjur menguruskan acara dari pendaftaran dalam talian, kehadiran berasaskan QR, hinggalah sijil digital yang boleh disahkan.</p>
                    <div class="hero-actions">
                        <a class="btn btn-solid" href="{{ url('/auth') }}">Log Masuk</a>
                        <a class="linkarrow" href="#cara">
                            Lihat cara ia berfungsi
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Feature cards ===== --}}
        <section id="ciri" class="sec sec-alt">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Apa yang anda boleh buat</p>
                    <h2>Satu platform untuk seluruh kitaran acara</h2>
                </div>
                <div class="grid3">
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
                        </div>
                        <h3>Pendaftaran dalam talian</h3>
                        <p>Kongsi pautan pendaftaran bertandatangan, kumpul medan tersuai dan hadkan bilangan tempat secara automatik.</p>
                    </div>
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 21v.01M21 17v.01M17 21v.01"/></svg>
                        </div>
                        <h3>Kehadiran QR</h3>
                        <p>Imbas kod QR peserta untuk daftar masuk pantas di pintu — dengan mod semak data dahulu atau mod laju.</p>
                    </div>
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.5 8.5 8 11 4.5-2.5 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                        <h3>Sijil digital boleh sahih</h3>
                        <p>Jana sijil PDF dengan nombor unik, dan benarkan penerima menyemak kesahihannya secara dalam talian.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== How it works ===== --}}
        <section id="cara" class="sec">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Cara ia berfungsi</p>
                    <h2>Dari pendaftaran ke sijil dalam empat langkah</h2>
                    <p class="lead">Sediakan acara sekali, dan biarkan setiap peringkat mengalir dengan kemas.</p>
                </div>
                <div class="steps">
                    <div class="step">
                        <p class="num">01</p>
                        <h3>Cipta acara</h3>
                        <p>Tetapkan butiran acara, medan pendaftaran dan had tempat dalam panel pentadbir.</p>
                    </div>
                    <div class="step">
                        <p class="num">02</p>
                        <h3>Kongsi pautan</h3>
                        <p>Edarkan pautan pendaftaran bertandatangan kepada peserta — tiada akaun diperlukan untuk mendaftar.</p>
                    </div>
                    <div class="step">
                        <p class="num">03</p>
                        <h3>Imbas kehadiran</h3>
                        <p>Daftar masuk peserta di pintu dengan mengimbas kod QR mereka melalui telefon.</p>
                    </div>
                    <div class="step">
                        <p class="num">04</p>
                        <h3>Terbitkan sijil</h3>
                        <p>Keluarkan sijil digital; peserta menyemak dan memuat turunnya menggunakan emel.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Doc preview ===== --}}
        <section id="sijil" class="sec sec-alt">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Contoh rekod sijil</p>
                    <h2>Inilah yang peserta lihat semasa semakan</h2>
                </div>
                <div class="preview">
                    <div class="preview-card" role="img" aria-label="Contoh rekod sijil untuk Nor Aisyah binti Ramli: Emel, Program, Tarikh dan Nombor Sijil.">
                        <div class="preview-head">
                            <span class="brand-mini">
                                <x-brand-mark variant="mini" />
                                Sijil Digital
                            </span>
                            <span class="pill">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                Disahkan
                            </span>
                        </div>
                        <div class="recipient">
                            <p class="name">Nor Aisyah binti Ramli</p>
                            <p class="sub">Penerima sijil</p>
                        </div>
                        <div class="rows">
                            <div class="row"><span class="k">Emel</span><span class="v">n****@email.com</span></div>
                            <div class="row"><span class="k">Program</span><span class="v">Seminar Kepimpinan 2026</span></div>
                            <div class="row"><span class="k">Tarikh</span><span class="v">14 Mac 2026</span></div>
                            <div class="row big"><span class="k">Nombor Sijil</span><span class="v">SJL-2026-04821</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== FAQ ===== --}}
        <section id="soalan" class="sec">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Soalan lazim</p>
                    <h2>Perkara yang biasa ditanya</h2>
                </div>
                <div class="faq">
                    <details>
                        <summary>Apakah {{ config('app.name') }}?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">{{ config('app.name') }} ialah platform untuk menguruskan acara hujung-ke-hujung — pendaftaran dalam talian, kehadiran berasaskan QR, dan penerbitan sijil digital yang boleh disahkan.</p>
                    </details>
                    <details>
                        <summary>Siapa yang sesuai menggunakannya?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Persatuan dan NGO, agensi kerajaan, sekolah dan IPT, penyedia latihan/CPD, serta mana-mana penganjur acara yang memerlukan pendaftaran, kehadiran dan sijil dalam satu tempat.</p>
                    </details>
                    <details>
                        <summary>Adakah peserta perlu mendaftar akaun?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Tidak. Peserta mendaftar melalui pautan jemputan yang dikongsi penganjur, dan menyemak sijil hanya dengan emel.</p>
                    </details>
                    <details>
                        <summary>Bagaimana kehadiran direkodkan?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Petugas mengimbas kod QR peserta di pintu menggunakan telefon. Mod semak membenarkan pengesahan data dahulu sebelum daftar masuk direkodkan.</p>
                    </details>
                    <details>
                        <summary>Bagaimana peserta menyemak sijil mereka?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Klik Semak Sijil dan masukkan emel. Sistem akan memaparkan setiap sijil yang layak untuk dimuat turun.</p>
                    </details>
                </div>
            </div>
        </section>

        {{-- ===== Inverted CTA band ===== --}}
        <section class="sec">
            <div class="wrap">
                <div class="cta">
                    <h2>Uruskan acara anda dengan lebih kemas.</h2>
                    <p>Satu platform untuk pendaftaran, kehadiran dan sijil — sedia untuk acara anda yang seterusnya.</p>
                    <div class="acts">
                        <a class="btn btn-white" href="{{ url('/auth') }}">Log Masuk</a>
                        <a class="linkarrow on-dark" href="{{ route('certificate-lookup.index') }}">
                            Semak sijil anda
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- ===== Footer ===== --}}
    <footer class="site-foot">
        <div class="wrap">
            <a class="brand" href="#atas" aria-label="{{ config('app.name') }}">
                <x-brand-mark />
                {{ config('app.name') }}
            </a>
            <div class="foot-links">
                <a href="{{ route('events.index') }}">Acara</a>
                <a href="{{ route('guides.index') }}">Panduan</a>
                <a href="{{ route('certificate-lookup.index') }}">Semak Sijil</a>
                <a href="{{ url('/auth') }}">Log Masuk</a>
            </div>
            <p class="copy">{{ config('app.name') }} &copy; {{ date('Y') }} <span class="em" aria-hidden="true">—</span> <span aria-hidden="true">🇲🇾</span></p>
        </div>
    </footer>
</body>
</html>
