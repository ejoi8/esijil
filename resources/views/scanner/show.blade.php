<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title>Imbas · {{ $event->title }}</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f1115; color: #f3f4f6; }
        header { padding: 14px 16px; border-bottom: 1px solid #232733; }
        header .event { font-weight: 600; font-size: 15px; }
        header .station { font-size: 13px; color: #9aa0ab; margin-top: 2px; }
        .modes { display: flex; gap: 8px; margin-top: 12px; }
        .modes button { flex: 1; padding: 9px; border: 1px solid #2b3040; background: #1a1d24; color: #9aa0ab; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .modes button.on { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        #reader { width: 100%; max-width: 480px; margin: 0 auto; }
        .status { margin: 16px auto; max-width: 480px; padding: 16px; border-radius: 12px; text-align: center; font-size: 18px; font-weight: 600; background: #1a1d24; color: #cbd2dd; }
        .status.ok { background: #0f3d2e; color: #4ade80; }
        .status.warn { background: #3d340f; color: #fbbf24; }
        .status.err { background: #3d1515; color: #f87171; }
        .status.pending { color: #93c5fd; }
        .card { margin: 16px auto; max-width: 480px; background: #1a1d24; border: 1px solid #232733; border-radius: 14px; padding: 18px; }
        .card .name { font-size: 22px; font-weight: 700; }
        .card .badge { display: inline-block; margin-top: 8px; padding: 4px 11px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .badge.new { background: #0f2a4d; color: #93c5fd; }
        .badge.in { background: #3d340f; color: #fbbf24; }
        .badge.done { background: #0f3d2e; color: #4ade80; }
        .badge.bad { background: #3d1515; color: #f87171; }
        .fields { margin: 14px 0 4px; }
        .fields .row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-top: 1px solid #232733; font-size: 14px; }
        .fields .k { color: #9aa0ab; }
        .fields .v { color: #e5e7eb; font-weight: 600; text-align: right; }
        .acts { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
        .btn { padding: 14px; border: 0; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; }
        .btn-in { background: #16a34a; color: #fff; }
        .btn-next { background: #262b36; color: #cbd2dd; }
        .pin-input { width: 100%; margin-top: 12px; padding: 14px; border-radius: 12px; border: 1px solid #2b3040; background: #0f1115; color: #f3f4f6; font-size: 22px; text-align: center; letter-spacing: 6px; }
        .hint { text-align: center; color: #6b7280; font-size: 12px; padding: 0 16px 24px; }
    </style>
</head>
<body>
    <header>
        <div class="event">{{ $event->title }}</div>
        <div class="station">Stesen: {{ $station->label }}</div>
        <div class="modes">
            <button id="mode-review" type="button">Semak dahulu</button>
            <button id="mode-auto" type="button">Auto daftar masuk</button>
        </div>
    </header>

    <div id="gate" style="display:none">
        <div class="card" style="max-width:360px">
            <div class="name" style="font-size:18px">Masukkan PIN stesen</div>
            <div id="pin-err" class="badge bad" style="display:none; margin-bottom:4px">PIN salah — cuba lagi</div>
            <input id="pin-input" class="pin-input" inputmode="numeric" autocomplete="off" placeholder="••••">
            <div class="acts">
                <button id="pin-go" class="btn btn-in" type="button">Buka Pengimbas</button>
            </div>
        </div>
    </div>

    <div id="reader"></div>
    <div id="panel"></div>
    <p class="hint" id="hint"></p>

    <script>
        const SCAN_URL = @json(route('api.scan'));
        const VERIFY_URL = @json(route('api.scan.verify'));
        const STATION_TOKEN = @json($station->token);
        const PIN_REQUIRED = @json($pinRequired);
        const BYPASS = @json($bypass);
        const PIN_KEY = 'esijil_scan_pin_' + STATION_TOKEN.slice(0, 12);

        // Members scan with a signed bypass token (no PIN). Non-members reuse a PIN
        // entered earlier on this device, else null until the gate collects one.
        let pin = PIN_REQUIRED ? localStorage.getItem(PIN_KEY) : null;

        const panel = document.getElementById('panel');
        const hint = document.getElementById('hint');
        const reader = document.getElementById('reader');
        const gate = document.getElementById('gate');
        const pinInput = document.getElementById('pin-input');
        const pinErr = document.getElementById('pin-err');
        const btnReview = document.getElementById('mode-review');
        const btnAuto = document.getElementById('mode-auto');

        let mode = localStorage.getItem('esijil_scan_mode') === 'auto' ? 'auto' : 'review';
        let busy = false;     // a request is in flight
        let paused = false;   // a review card is showing — ignore new scans
        let gateOpen = false; // the PIN gate is showing — ignore new scans
        let scanner = null;

        function renderMode() {
            btnReview.classList.toggle('on', mode === 'review');
            btnAuto.classList.toggle('on', mode === 'auto');
            hint.textContent = mode === 'review'
                ? 'Imbas untuk lihat data — daftar masuk hanya direkod selepas anda sahkan.'
                : 'Imbas terus daftar masuk. Pastikan halaman ini sentiasa terbuka.';
        }
        function setMode(next) {
            mode = next;
            localStorage.setItem('esijil_scan_mode', mode);
            renderMode();
        }
        btnReview.addEventListener('click', () => setMode('review'));
        btnAuto.addEventListener('click', () => setMode('auto'));

        function el(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text; // textContent — never inject HTML
            return node;
        }
        function showStatus(message, tone) {
            panel.innerHTML = '';
            panel.appendChild(el('div', 'status' + (tone ? ' ' + tone : ''), message));
        }
        function fieldsBlock(fields) {
            const wrap = el('div', 'fields');
            (fields || []).forEach((f) => {
                const row = el('div', 'row');
                row.appendChild(el('span', 'k', f.label));
                row.appendChild(el('span', 'v', f.value));
                wrap.appendChild(row);
            });
            return wrap;
        }
        function timeText(iso) {
            try { return new Date(iso).toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' }); }
            catch (e) { return ''; }
        }

        // ---- PIN gate ----
        function showGate(error) {
            gateOpen = true;
            try { scanner && scanner.pause(true); } catch (e) {}
            reader.style.display = 'none';
            panel.innerHTML = '';
            hint.textContent = '';
            pinErr.style.display = error ? 'block' : 'none';
            pinInput.value = '';
            gate.style.display = 'block';
            pinInput.focus();
        }
        function hideGate() {
            gateOpen = false;
            gate.style.display = 'none';
            reader.style.display = '';
            renderMode();
        }
        const pinGo = document.getElementById('pin-go');
        async function submitPin() {
            const v = pinInput.value.trim();
            if (!v) return;
            pinGo.disabled = true;
            try {
                // Verify the PIN before the camera opens; a wrong PIN never starts it.
                const res = await fetch(VERIFY_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ station_token: STATION_TOKEN, pin: v }),
                });
                if (res.status === 429) {
                    pinErr.textContent = 'Terlalu banyak cubaan — tunggu sebentar';
                    pinErr.style.display = 'block';
                    return;
                }
                if (!res.ok && res.status !== 401 && res.status !== 403) {
                    pinErr.textContent = 'Ralat pelayan — cuba lagi';
                    pinErr.style.display = 'block';
                    return;
                }
                const data = await res.json();
                if (!data.ok) {
                    pinErr.textContent = 'PIN salah — cuba lagi';
                    pinErr.style.display = 'block';
                    pinInput.value = '';
                    pinInput.focus();
                    return;
                }
                pin = v;
                localStorage.setItem(PIN_KEY, pin);
                hideGate();
                startScanner();
            } catch (e) {
                pinErr.textContent = 'Ralat rangkaian — cuba lagi';
                pinErr.style.display = 'block';
            } finally {
                pinGo.disabled = false;
            }
        }
        pinGo.addEventListener('click', submitPin);
        pinInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') submitPin(); });

        function handlePinInvalid() {
            localStorage.removeItem(PIN_KEY);
            pin = null;
            paused = false;
            busy = false;
            pinErr.textContent = 'PIN stesen telah berubah — masukkan PIN baharu.';
            showGate(true);
        }

        // ---- scan API ----
        async function post(code, confirm) {
            const res = await fetch(SCAN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ station_token: STATION_TOKEN, code: code, pin: pin, bypass: BYPASS, confirm: confirm }),
            });
            // Distinguish throttling / server errors from the JSON {ok,status} the
            // app returns (401/403 still carry a status we want to read).
            if (res.status === 429) return { status: 'rate_limited' };
            if (!res.ok && res.status !== 401 && res.status !== 403) return { status: 'http_error' };
            return res.json();
        }

        function resume() {
            panel.innerHTML = '';
            paused = false;
            try { scanner && scanner.resume(); } catch (e) {}
        }

        function showCard(code, data) {
            panel.innerHTML = '';
            const card = el('div', 'card');
            card.appendChild(el('div', 'name', data.name || 'Tidak dikenali'));
            const acts = el('div', 'acts');

            if (data.status === 'found') {
                card.appendChild(el('div', 'badge new', 'Belum daftar masuk'));
                card.appendChild(fieldsBlock(data.fields));
                const checkin = el('button', 'btn btn-in', '✓ Daftar Masuk');
                checkin.addEventListener('click', () => confirmCheckin(code));
                acts.appendChild(checkin);
            } else if (data.status === 'already') {
                card.appendChild(el('div', 'badge in', 'Sudah daftar masuk · ' + timeText(data.checked_in_at)));
                card.appendChild(fieldsBlock(data.fields));
            } else {
                card.appendChild(el('div', 'badge bad', data.message || 'Tidak dikenali'));
            }

            const next = el('button', 'btn btn-next', 'Imbas Seterusnya');
            next.addEventListener('click', resume);
            acts.appendChild(next);
            card.appendChild(acts);
            panel.appendChild(card);
        }

        async function confirmCheckin(code) {
            if (busy) return;
            busy = true;
            showStatus('Mendaftar masuk…', 'pending');
            try {
                const data = await post(code, true);
                if (data.status === 'pin_invalid') { handlePinInvalid(); return; }
                if (data.status === 'rate_limited') { showStatus('Terlalu banyak imbasan — cuba lagi', 'warn'); setTimeout(resume, 1500); return; }
                if (data.status === 'http_error') { showStatus('Ralat pelayan — cuba lagi', 'err'); setTimeout(resume, 1500); return; }
                panel.innerHTML = '';
                const card = el('div', 'card');
                card.appendChild(el('div', 'name', data.name || ''));
                card.appendChild(el('div', 'badge done',
                    data.status === 'already' ? 'Sudah daftar masuk' : '✓ Daftar masuk berjaya'));
                const acts = el('div', 'acts');
                const next = el('button', 'btn btn-next', 'Imbas Seterusnya');
                next.addEventListener('click', resume);
                acts.appendChild(next);
                card.appendChild(acts);
                panel.appendChild(card);
            } catch (e) {
                showStatus('Ralat rangkaian — cuba lagi', 'err');
                setTimeout(resume, 1500);
            } finally {
                busy = false;
            }
        }

        async function onScan(code) {
            if (busy || paused || gateOpen) return;
            busy = true;

            if (mode === 'auto') {
                showStatus('Menyemak…', 'pending');
                try {
                    const data = await post(code, true);
                    if (data.status === 'pin_invalid') { handlePinInvalid(); return; }
                    if (data.status === 'rate_limited') { showStatus('Terlalu banyak imbasan — perlahankan sedikit', 'warn'); return; }
                    if (data.status === 'http_error') { showStatus('Ralat pelayan — cuba lagi', 'err'); return; }
                    if (data.status === 'present') {
                        showStatus('✓ ' + data.name + ' — daftar masuk', 'ok');
                    } else if (data.status === 'already') {
                        showStatus('• ' + data.name + ' — sudah daftar masuk', 'warn');
                    } else {
                        showStatus('✗ ' + (data.message || 'Tidak dikenali'), 'err');
                    }
                } catch (e) {
                    showStatus('Ralat rangkaian — cuba lagi', 'err');
                } finally {
                    setTimeout(() => { busy = false; if (panel.firstChild) panel.innerHTML = ''; }, 1800);
                }
                return;
            }

            // Review mode: pause, fetch the data, show the card; no write yet.
            paused = true;
            try { scanner && scanner.pause(true); } catch (e) {}
            showStatus('Menyemak…', 'pending');
            try {
                const data = await post(code, false);
                if (data.status === 'pin_invalid') { handlePinInvalid(); return; }
                if (data.status === 'rate_limited') { showStatus('Terlalu banyak imbasan — perlahankan sedikit', 'warn'); setTimeout(resume, 1500); return; }
                if (data.status === 'http_error') { showStatus('Ralat pelayan — cuba lagi', 'err'); setTimeout(resume, 1500); return; }
                showCard(code, data);
            } catch (e) {
                showStatus('Ralat rangkaian — cuba lagi', 'err');
                setTimeout(resume, 1500);
            } finally {
                busy = false;
            }
        }

        function startScanner() {
            if (scanner) { try { scanner.resume(); } catch (e) {} return; }
            scanner = new Html5Qrcode('reader');
            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 250 },
                (decodedText) => onScan(decodedText),
                () => {},
            ).catch(() => showStatus('Kamera tidak tersedia — benarkan akses kamera dan muat semula.', 'err'));
        }

        renderMode();
        if (PIN_REQUIRED && !pin) {
            showGate(false);
        } else {
            startScanner();
        }
    </script>
</body>
</html>
