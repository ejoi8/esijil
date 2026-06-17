@props([
    'title' => config('app.name'),
    'description' => 'Pendaftaran program PUSPANITA melalui pautan jemputan yang dikongsi kepada peserta.',
    'canonical' => null,
    'robots' => 'index,follow',
])

@php
    $siteName = config('app.name');
    $metaTitle = $title === $siteName ? $siteName : "{$title} | {$siteName}";
    $metaDescription = trim((string) $description);
    $metaRobots = trim((string) $robots);
    $metaCanonical = $canonical === false ? null : ($canonical ?: url()->current());
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="robots" content="{{ $metaRobots }}">
        <meta name="theme-color" content="#0a0a0a">
        @if ($metaCanonical)
            <link rel="canonical" href="{{ $metaCanonical }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        {{-- Minimal Mono — monochrome, hairline borders, no shadows/gradients. --}}
        <style>
            :root{
                --ink:#0a0a0a; --muted:#6b7280; --faint:#9ca3af; --line:#e7e7e7; --alt:#fafafa;
                --success:#16a34a; --danger:#dc2626; --maxw:1040px;
            }
            *{box-sizing:border-box}
            html{-webkit-text-size-adjust:100%}
            body{margin:0;background:#fff;color:var(--ink);
                font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                line-height:1.5;letter-spacing:-.011em;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
            a{color:inherit;text-decoration:none}
            .wrap{width:100%;max-width:var(--maxw);margin:0 auto;padding:0 24px}
            .page{min-height:100vh;display:flex;flex-direction:column}

            /* header */
            .site-head{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.82);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
            .site-head .wrap{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px}
            .brand{display:inline-flex;align-items:center;gap:9px;font-weight:700;font-size:17px;color:var(--ink)}
            .brand .mark{width:26px;height:26px;border-radius:7px;background:var(--ink);display:inline-flex;align-items:center;justify-content:center;flex:none}
            .linkarrow{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:14px;font-weight:600;transition:color .15s ease}
            .linkarrow:hover{color:var(--ink)}
            .linkarrow svg{transition:transform .15s ease}
            .linkarrow:hover svg{transform:translateX(3px)}

            /* main + section head */
            main{flex:1;padding:64px 0 72px}
            .col{max-width:640px;margin:0 auto}
            .kicker{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0}
            h1{font-size:clamp(30px,5vw,44px);line-height:1.05;letter-spacing:-.03em;font-weight:700;margin:10px 0 0}
            .lead{color:var(--muted);font-size:17px;line-height:1.5;margin:12px 0 0;max-width:34rem}

            /* cards */
            .card{border:1px solid var(--line);border-radius:14px;padding:24px;background:#fff;transition:border-color .15s ease}
            .stack{margin-top:28px}
            .stack>*+*{margin-top:18px}
            .card-title{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0 0 4px}

            /* data-preview rows */
            .rows{margin:0}
            .row{display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:13px 0;border-top:1px dashed var(--line)}
            .row:first-child{border-top:0;padding-top:0}
            .row .k{color:var(--muted);font-size:13px;font-weight:600}
            .row .v{color:var(--ink);font-weight:600;text-align:right}

            /* form */
            .field{margin-top:18px}
            .field:first-child{margin-top:0}
            .label{display:block;font-size:13px;font-weight:600;color:var(--muted);margin-bottom:7px}
            .input,.select{width:100%;font:inherit;font-size:15px;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:10px;padding:11px 14px;transition:border-color .15s ease}
            .input::placeholder{color:var(--faint)}
            .input:hover,.select:hover{border-color:#cfcfcf}
            .input:focus,.select:focus{outline:none;border-color:var(--ink)}
            .input:focus-visible,.select:focus-visible,.btn:focus-visible,.linkarrow:focus-visible{outline:2px solid var(--ink);outline-offset:2px;border-radius:6px}
            .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
            @media (max-width:560px){.grid2{grid-template-columns:1fr}}
            .selectwrap{position:relative}
            .selectwrap svg{position:absolute;right:14px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--faint)}
            .select{appearance:none;-webkit-appearance:none;padding-right:38px}

            /* buttons */
            .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;border:1px solid var(--ink);cursor:pointer;transition:opacity .15s ease,border-color .15s ease,background .15s ease}
            .btn-solid{background:var(--ink);color:#fff}
            .btn-solid:hover{opacity:.88}
            .btn-line{background:#fff;color:var(--ink);border-color:var(--line)}
            .btn-line:hover{border-color:var(--ink)}
            .btn:disabled{cursor:not-allowed;background:#fff;color:var(--faint);border-color:var(--line);opacity:1}

            /* flat status notices */
            .notice{font-size:14px;padding:12px 14px;border-radius:10px;border:1px solid var(--line);color:var(--muted)}
            .notice+.notice{margin-top:12px}
            .notice.is-success{border-color:var(--success);color:var(--success)}
            .notice.is-danger{border-color:var(--danger);color:var(--danger)}
            .err{color:var(--danger);font-size:13px;margin:7px 0 0}

            .formfoot{margin-top:24px;padding-top:18px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:14px;align-items:flex-start}
            @media (min-width:560px){.formfoot{flex-direction:row;align-items:center;justify-content:space-between}}
            .hint{color:var(--muted);font-size:14px;margin:0}

            /* footer */
            .site-foot{border-top:1px solid var(--line)}
            .site-foot .wrap{padding-top:24px;padding-bottom:24px;text-align:center}
            .site-foot p{margin:0;color:var(--faint);font-size:13px;letter-spacing:.04em}
        </style>
    </head>
    <body>
        <div class="page">
            <header class="site-head">
                <div class="wrap">
                    <a class="brand" href="{{ route('home') }}">
                        <span class="mark" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                                <path d="M14 3v5h5"/>
                            </svg>
                        </span>
                        eSIJIL
                    </a>
                    <a class="linkarrow" href="{{ route('certificate-lookup.index') }}">
                        Semakan Sijil
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </header>

            <main>
                <div class="wrap">
                    {{ $slot }}
                </div>
            </main>

            <footer class="site-foot">
                <div class="wrap">
                    <p>ICT PUSPANITA &copy; 2026</p>
                </div>
            </footer>
        </div>
    </body>
</html>
