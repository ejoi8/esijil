<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Kod QR · {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; color: #111827; }
        .toolbar { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
        .toolbar button { padding: 8px 16px; border: 1px solid #111827; background: #111827; color: #fff; border-radius: 6px; cursor: pointer; }
        h1 { font-size: 16px; margin: 16px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 0 16px 24px; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; text-align: center; page-break-inside: avoid; }
        .card .name { font-weight: 600; font-size: 13px; }
        .card .meta { font-size: 11px; color: #6b7280; margin: 2px 0 8px; }
        .card img { width: 130px; height: 130px; }
        .empty { padding: 16px; color: #6b7280; }
        @media print {
            .toolbar { display: none; }
            h1 { margin: 0 0 8px; }
            .grid { gap: 8px; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <h1>{{ $event->title }} — Kod QR Kehadiran</h1>

    <div class="grid">
        @forelse ($event->registrations as $registration)
            @php($participant = $registration->participant)
            @if ($participant)
                <div class="card">
                    <div class="name">{{ $participant->full_name }}</div>
                    @if ($participant->external_id)
                        <div class="meta">{{ $participant->external_id }}</div>
                    @endif
                    <img src="{{ \App\Support\QrCode::dataUri($participant->public_token) }}" alt="Kod QR {{ $participant->full_name }}">
                </div>
            @endif
        @empty
            <p class="empty">Tiada peserta berdaftar.</p>
        @endforelse
    </div>
</body>
</html>
