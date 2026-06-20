@props([
    'title' => config('app.name'),
    'description' => 'Pendaftaran program PUSPANITA melalui pautan jemputan yang dikongsi kepada peserta.',
    'canonical' => null,
    'robots' => 'index,follow',
    'ogType' => 'website',
    'ogImage' => '/images/og/esijil-share.svg',
    'ogImageAlt' => 'eSIJIL PUSPANITA',
])

@php
    $siteName = config('app.name');
    $metaTitle = $title === $siteName ? $siteName : "{$title} | {$siteName}";
    $metaDescription = trim((string) $description);
    $metaRobots = trim((string) $robots);
    $metaCanonical = $canonical === false ? null : ($canonical ?: url()->current());
    $metaUrl = $metaCanonical ?: request()->fullUrl();
    $metaOgImage = str_starts_with((string) $ogImage, 'http') ? (string) $ogImage : url((string) $ogImage);
    $metaLocale = str_replace('_', '-', app()->getLocale());
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

        <meta property="og:locale" content="{{ $metaLocale }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $metaUrl }}">
        <meta property="og:image" content="{{ $metaOgImage }}">
        <meta property="og:image:alt" content="{{ $ogImageAlt }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaOgImage }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <style>
            @include('partials.mono-tokens')

            /* page chrome */
            .page{min-height:100vh;display:flex;flex-direction:column}
            main{flex:1;padding:64px 0 72px}
            .col{max-width:640px;margin:0 auto}
            h1{font-size:clamp(30px,5vw,44px);line-height:1.05;letter-spacing:-.03em;font-weight:700;margin:10px 0 0}
            .lead{color:var(--muted);font-size:17px;line-height:1.5;margin:12px 0 0;max-width:34rem}
            .stack{margin-top:28px}
            .stack>*+*{margin-top:18px}
            .card-title{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0 0 4px}

            /* form */
            .field{margin-top:18px}
            .field:first-child{margin-top:0}
            .label{display:block;font-size:13px;font-weight:600;color:var(--muted);margin-bottom:7px}
            .input,.select{width:100%;font:inherit;font-size:15px;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:10px;padding:11px 14px;transition:border-color .15s ease}
            .input::placeholder{color:var(--faint)}
            .input:hover,.select:hover{border-color:#cfcfcf}
            .input:focus,.select:focus{outline:none;border-color:var(--ink)}
            .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
            @media (max-width:560px){.grid2{grid-template-columns:1fr}}
            .selectwrap{position:relative}
            .selectwrap svg{position:absolute;right:14px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--faint)}
            .select{appearance:none;-webkit-appearance:none;padding-right:38px}

            /* flat status notices */
            .notice{font-size:14px;padding:12px 14px;border-radius:10px;border:1px solid var(--line);color:var(--muted)}
            .notice+.notice{margin-top:12px}
            .notice.is-success{border-color:var(--success);color:var(--success)}
            .notice.is-danger{border-color:var(--danger);color:var(--danger)}
            .err{color:var(--danger);font-size:13px;margin:7px 0 0}

            .formfoot{margin-top:24px;padding-top:18px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:14px;align-items:flex-start}
            @media (min-width:560px){.formfoot{flex-direction:row;align-items:center;justify-content:space-between}}
            .hint{color:var(--muted);font-size:14px;margin:0}

            .site-foot .wrap{padding-top:24px;padding-bottom:24px;text-align:center}
        </style>
    </head>
    <body>
        <a class="skip" href="#main">Langkau ke kandungan</a>
        <div class="page">
            <header class="site-head">
                <div class="wrap">
                    <a class="brand" href="{{ route('home') }}">
                        <x-brand-mark />
                        eSIJIL
                    </a>
                    <a class="linkarrow" href="{{ route('certificate-lookup.index') }}">
                        Semakan Sijil
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </header>

            <main id="main">
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
