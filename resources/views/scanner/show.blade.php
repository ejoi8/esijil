<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title>Scan · {{ $event->title }}</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f1115; color: #f3f4f6; }
        header { padding: 14px 16px; border-bottom: 1px solid #232733; }
        header .event { font-weight: 600; font-size: 15px; }
        header .station { font-size: 13px; color: #9aa0ab; margin-top: 2px; }
        #reader { width: 100%; max-width: 480px; margin: 0 auto; }
        .status { margin: 16px auto; max-width: 480px; padding: 16px; border-radius: 12px; text-align: center; font-size: 18px; font-weight: 600; background: #1a1d24; color: #cbd2dd; }
        .status.ok { background: #0f3d2e; color: #4ade80; }
        .status.warn { background: #3d340f; color: #fbbf24; }
        .status.err { background: #3d1515; color: #f87171; }
        .status.pending { color: #93c5fd; }
        .hint { text-align: center; color: #6b7280; font-size: 12px; padding: 0 16px 24px; }
    </style>
</head>
<body>
    <header>
        <div class="event">{{ $event->title }}</div>
        <div class="station">Station: {{ $station->label }}</div>
    </header>

    <div id="reader"></div>
    <div id="status" class="status">Point the camera at a participant QR code.</div>
    <p class="hint">Scans are recorded instantly. Keep this page open.</p>

    <script>
        const SCAN_URL = @json(route('api.scan'));
        const STATION_TOKEN = @json($station->token);
        const statusEl = document.getElementById('status');
        let busy = false;

        function setStatus(message, tone) {
            statusEl.textContent = message;
            statusEl.className = 'status' + (tone ? ' ' + tone : '');
        }

        async function submit(code) {
            if (busy) {
                return;
            }
            busy = true;
            setStatus('Checking…', 'pending');

            try {
                const response = await fetch(SCAN_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        station_token: STATION_TOKEN,
                        code: code,
                        client_scan_id: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()),
                    }),
                });
                const data = await response.json();

                if (data.status === 'present') {
                    setStatus('✓ ' + data.name + ' — checked in', 'ok');
                } else if (data.status === 'already') {
                    setStatus('• ' + data.name + ' — already checked in', 'warn');
                } else {
                    setStatus('✗ ' + (data.message || 'Not recognised'), 'err');
                }
            } catch (error) {
                setStatus('Network error — try again', 'err');
            } finally {
                // Brief cooldown so one QR isn't submitted repeatedly.
                setTimeout(() => {
                    busy = false;
                    setStatus('Point the camera at a participant QR code.');
                }, 2000);
            }
        }

        const scanner = new Html5Qrcode('reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 250 },
            (decodedText) => submit(decodedText),
            () => {},
        ).catch(() => setStatus('Camera unavailable — allow camera access and reload.', 'err'));
    </script>
</body>
</html>
