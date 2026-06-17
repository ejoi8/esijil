<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Semak &amp; muat turun sijil program PUSPANITA</title>
    <meta name="description" content="{{ config('app.name') }} ialah sistem sijil digital PUSPANITA. Daftar program melalui pautan jemputan, hadir, kemudian semak dan muat turun sijil anda menggunakan No. KP.">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="canonical" href="{{ route('home') }}">

    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name') }} — Semak &amp; muat turun sijil program PUSPANITA">
    <meta property="og:description" content="Sistem sijil digital PUSPANITA. Semak dan muat turun sijil program anda menggunakan No. KP.">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ url('/images/og/esijil-share.svg') }}">
    <meta property="og:image:alt" content="eSIJIL PUSPANITA">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name') }} — Semak &amp; muat turun sijil program PUSPANITA">
    <meta name="twitter:description" content="Sistem sijil digital PUSPANITA. Semak dan muat turun sijil program anda menggunakan No. KP.">
    <meta name="twitter:image" content="{{ url('/images/og/esijil-share.svg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Minimal Mono — monochrome, hairline borders, no shadows/gradients/accent colours. --}}
    <style>
        :root{
            --ink:#0a0a0a; --muted:#6b7280; --faint:#9ca3af; --line:#e7e7e7; --alt:#fafafa;
            --maxw:1040px;
        }
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
        body{margin:0;background:#fff;color:var(--ink);
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
            font-size:16px;line-height:1.5;letter-spacing:-.011em;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
        a{color:inherit;text-decoration:none}
        .wrap{width:100%;max-width:var(--maxw);margin:0 auto;padding:0 24px}
        section{scroll-margin-top:72px}
        :focus-visible{outline:2px solid var(--ink);outline-offset:2px;border-radius:6px}

        /* header / sticky nav */
        .site-head{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.82);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
        .site-head .wrap{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .brand{display:inline-flex;align-items:center;gap:9px;font-weight:700;font-size:17px;color:var(--ink);letter-spacing:-.02em}
        .brand .mark{width:26px;height:26px;border-radius:7px;background:var(--ink);display:inline-flex;align-items:center;justify-content:center;flex:none}
        .nav{display:flex;align-items:center;gap:22px}
        .nav-links{display:flex;align-items:center;gap:22px}
        .nav-links a{color:var(--muted);font-size:14px;font-weight:500;transition:color .15s ease}
        .nav-links a:hover{color:var(--ink)}
        .nav-cta{display:flex;align-items:center;gap:16px}
        .lnk-login{color:var(--muted);font-size:14px;font-weight:600;transition:color .15s ease}
        .lnk-login:hover{color:var(--ink)}
        @media (max-width:720px){.nav-links{display:none}}

        /* buttons */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 18px;border-radius:10px;font-size:14px;font-weight:600;border:1px solid var(--ink);cursor:pointer;transition:opacity .15s ease,border-color .15s ease,background .15s ease,color .15s ease}
        .btn-solid{background:var(--ink);color:#fff}
        .btn-solid:hover{opacity:.88}
        .btn-line{background:#fff;color:var(--ink);border-color:var(--line)}
        .btn-line:hover{border-color:var(--ink)}
        .btn-white{background:#fff;color:var(--ink);border-color:#fff}
        .btn-white:hover{opacity:.88}

        .linkarrow{display:inline-flex;align-items:center;gap:6px;color:var(--faint);font-size:14px;font-weight:600;transition:color .15s ease}
        .linkarrow:hover{color:var(--ink)}
        .linkarrow svg{transition:transform .15s ease}
        .linkarrow:hover svg{transform:translateX(3px)}
        .linkarrow.on-dark{color:rgba(255,255,255,.7)}
        .linkarrow.on-dark:hover{color:#fff}

        .kicker{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0}

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

        /* grids */
        .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:40px}
        @media (max-width:880px){.grid3{grid-template-columns:repeat(2,1fr)}}
        @media (max-width:560px){.grid3{grid-template-columns:1fr}}

        /* feature cards */
        .card{border:1px solid var(--line);border-radius:14px;padding:26px;background:#fff;transition:border-color .15s ease}
        .card:hover{border-color:var(--ink)}
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
        .rows{margin:0}
        .row{display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px dashed var(--line)}
        .row:first-of-type{border-top:0}
        .row .k{color:var(--muted);font-size:13px;font-weight:600}
        .row .v{color:var(--ink);font-weight:600;text-align:right}
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
        .site-foot{border-top:1px solid var(--line)}
        .site-foot .wrap{padding:36px 24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px}
        .foot-links{display:flex;align-items:center;gap:22px}
        .foot-links a{color:var(--muted);font-size:14px;font-weight:500;transition:color .15s ease}
        .foot-links a:hover{color:var(--ink)}
        .site-foot .copy{color:var(--faint);font-size:13px;letter-spacing:.04em;margin:0}
        .em{color:var(--faint)}
    </style>
</head>
<body>
    {{-- ===== Sticky nav ===== --}}
    <header class="site-head">
        <div class="wrap">
            <a class="brand" href="#atas" aria-label="{{ config('app.name') }}">
                <span class="mark" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 3v5h5"/>
                    </svg>
                </span>
                eSIJIL
            </a>
            <nav class="nav" aria-label="Navigasi utama">
                <div class="nav-links">
                    <a href="#ciri">Ciri</a>
                    <a href="#cara">Cara guna</a>
                    <a href="#sijil">Contoh sijil</a>
                    <a href="#soalan">Soalan lazim</a>
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
                    <p class="kicker">Sijil digital PUSPANITA</p>
                    <h1>Sijil program anda, sedia untuk dimuat turun.</h1>
                    <p class="lead">Semak dan dapatkan sijil rasmi anda dalam format PDF menggunakan No. KP — tanpa borang, tanpa menunggu.</p>
                    <div class="hero-actions">
                        <a class="btn btn-solid" href="{{ route('certificate-lookup.index') }}">Semak Sijil</a>
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
                    <h2>Satu tempat untuk semua sijil program anda</h2>
                </div>
                <div class="grid3">
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>
                        <h3>Semak dengan No. KP</h3>
                        <p>Masukkan nombor kad pengenalan anda dan sistem akan paparkan setiap sijil yang layak untuk anda.</p>
                    </div>
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                        </div>
                        <h3>Muat turun PDF</h3>
                        <p>Sijil dijana sebagai fail PDF yang kemas — sedia untuk disimpan, dicetak atau dikongsi.</p>
                    </div>
                    <div class="card">
                        <div class="ico" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.5 8.5 8 11 4.5-2.5 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                        <h3>Sah &amp; rasmi</h3>
                        <p>Setiap sijil membawa nombor sijil unik dan rekod program rasmi PUSPANITA.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== How it works ===== --}}
        <section id="cara" class="sec">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Cara ia berfungsi</p>
                    <h2>Dari jemputan ke sijil dalam empat langkah</h2>
                    <p class="lead">Pendaftaran program adalah melalui jemputan sahaja — anda tidak perlu mendaftar akaun.</p>
                </div>
                <div class="steps">
                    <div class="step">
                        <p class="num">01</p>
                        <h3>Terima pautan jemputan</h3>
                        <p>Penganjur menghantar pautan jemputan bertandatangan khusus untuk program anda.</p>
                    </div>
                    <div class="step">
                        <p class="num">02</p>
                        <h3>Daftar program</h3>
                        <p>Buka pautan, sahkan butiran anda dan daftar — hanya mengambil masa seminit.</p>
                    </div>
                    <div class="step">
                        <p class="num">03</p>
                        <h3>Hadir program</h3>
                        <p>Sertai program seperti dijadualkan. Kehadiran anda direkodkan oleh penganjur.</p>
                    </div>
                    <div class="step">
                        <p class="num">04</p>
                        <h3>Semak &amp; muat turun sijil</h3>
                        <p>Selepas program, semak dengan No. KP dan muat turun sijil PDF anda.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Doc preview ===== --}}
        <section id="sijil" class="sec sec-alt">
            <div class="wrap">
                <div class="sec-head center">
                    <p class="kicker">Contoh rekod sijil</p>
                    <h2>Inilah yang anda lihat semasa semakan</h2>
                </div>
                <div class="preview">
                    <div class="preview-card" role="img" aria-label="Contoh rekod sijil untuk Nor Aisyah binti Ramli: No. KP, Program, Tarikh dan Nombor Sijil.">
                        <div class="preview-head">
                            <span class="brand-mini">
                                <span class="mini-mark" aria-hidden="true">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                                </span>
                                Sijil PUSPANITA
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
                            <div class="row"><span class="k">No. KP</span><span class="v">8********-**-****</span></div>
                            <div class="row"><span class="k">Program</span><span class="v">Bengkel Kepimpinan PUSPANITA 2026</span></div>
                            <div class="row"><span class="k">Tarikh</span><span class="v">14 Mac 2026</span></div>
                            <div class="row big"><span class="k">Nombor Sijil</span><span class="v">PSP-2026-04821</span></div>
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
                        <summary>Bagaimana saya menyemak sijil saya?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Klik butang Semak Sijil dan masukkan No. KP anda. Sistem akan memaparkan setiap sijil program yang layak untuk anda muat turun.</p>
                    </details>
                    <details>
                        <summary>Adakah saya perlu mendaftar akaun?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Tidak. Pendaftaran program adalah melalui pautan jemputan sahaja, dan semakan sijil hanya memerlukan No. KP anda.</p>
                    </details>
                    <details>
                        <summary>Saya tidak menerima pautan jemputan. Apa patut saya buat?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Pautan jemputan dihantar oleh penganjur program. Sila hubungi penganjur PUSPANITA program anda untuk mendapatkan semula pautan tersebut.</p>
                    </details>
                    <details>
                        <summary>Bilakah sijil saya tersedia?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Sijil tersedia untuk dimuat turun selepas kehadiran anda disahkan oleh penganjur program.</p>
                    </details>
                    <details>
                        <summary>Saya kakitangan PUSPANITA — di mana saya log masuk?<span class="toggle" aria-hidden="true"></span></summary>
                        <p class="answer">Gunakan pautan Log Masuk di bahagian atas untuk mengakses panel pentadbir bagi mengurus program dan sijil.</p>
                    </details>
                </div>
            </div>
        </section>

        {{-- ===== Inverted CTA band ===== --}}
        <section class="sec">
            <div class="wrap">
                <div class="cta">
                    <h2>Sijil anda menanti.</h2>
                    <p>Semak dengan No. KP anda dan muat turun sijil program dalam beberapa saat.</p>
                    <div class="acts">
                        <a class="btn btn-white" href="{{ route('certificate-lookup.index') }}">Semak Sijil</a>
                        <a class="linkarrow on-dark" href="#cara">
                            Lihat cara ia berfungsi
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
                <span class="mark" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 3v5h5"/>
                    </svg>
                </span>
                eSIJIL
            </a>
            <div class="foot-links">
                <a href="{{ route('certificate-lookup.index') }}">Semak Sijil</a>
                <a href="{{ url('/auth') }}">Log Masuk</a>
            </div>
            <p class="copy">ICT PUSPANITA &copy; 2026 <span class="em" aria-hidden="true">—</span> <span aria-hidden="true">🇲🇾</span></p>
        </div>
    </footer>
</body>
</html>
