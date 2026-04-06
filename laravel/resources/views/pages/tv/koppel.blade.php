<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JudoToernooi — TV Koppelen</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; background: #111827; font-family: 'Arial', 'Helvetica Neue', sans-serif; color: #fff; }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            padding: 2rem;
        }

        .logo {
            font-size: clamp(24px, 4vh, 48px);
            font-weight: 800;
            color: #3B82F6;
            margin-bottom: 2vh;
        }

        .instruction {
            font-size: clamp(16px, 2.5vh, 28px);
            color: #9CA3AF;
            margin-bottom: 4vh;
        }

        .code-box {
            background: #1F2937;
            border: 3px solid #374151;
            border-radius: 16px;
            padding: 4vh 6vw;
            margin-bottom: 3vh;
        }

        .code {
            font-size: clamp(72px, 18vh, 200px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3em;
            color: #F9FAFB;
            font-variant-numeric: tabular-nums;
        }

        .status {
            font-size: clamp(14px, 2vh, 24px);
            color: #6B7280;
            margin-bottom: 2vh;
        }

        .status.waiting { color: #6B7280; }
        .status.linking { color: #FACC15; }
        .status.linked { color: #22C55E; }
        .status.expired { color: #EF4444; }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #374151;
            border-top-color: #3B82F6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        .hint {
            font-size: clamp(12px, 1.5vh, 18px);
            color: #4B5563;
            max-width: 600px;
        }

        .countdown {
            font-size: clamp(12px, 1.5vh, 18px);
            color: #4B5563;
            margin-top: 2vh;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container" id="app">
        <div class="logo">JudoToernooi</div>

        <div id="state-waiting">
            <p class="instruction">Voer deze code in bij de toernooi-instellingen</p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>
            <p class="status waiting"><span class="spinner"></span> Wachten op koppeling...</p>
            <p class="hint">Ga op de laptop naar Instellingen → Organisatie → Device Toegangen → klik "Koppel TV" bij de juiste mat</p>
            <p class="countdown" id="countdown"></p>
            <p class="hint" id="debug" style="margin-top: 2vh; color: #4B5563;"></p>
        </div>

        <div id="state-linked" style="display: none;">
            <p class="status linked" style="font-size: clamp(24px, 4vh, 48px); font-weight: bold;">TV Gekoppeld!</p>
            <p class="instruction" style="margin-top: 2vh;">Scorebord wordt geladen...</p>
        </div>

        <div id="state-expired" style="display: none;">
            <p class="status expired" style="font-size: clamp(20px, 3vh, 36px);">Code verlopen</p>
            <p class="instruction" style="margin-top: 2vh;">
                <a href="{{ route('tv.koppel') }}" style="color: #3B82F6; text-decoration: underline;">Klik hier voor een nieuwe code</a>
            </p>
        </div>
    </div>

    <script>
    (function() {
        const koppelingId = {{ $koppelingId }};
        const pollUrl = '{{ route('tv.poll', ['koppeling' => $koppelingId]) }}';
        const expiresAt = new Date(Date.now() + 10 * 60 * 1000);
        let polling = true;

        function updateCountdown() {
            const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const el = document.getElementById('countdown');
            if (el) el.textContent = 'Code verloopt over ' + mins + ':' + secs.toString().padStart(2, '0');
            if (remaining <= 0 && polling) {
                showState('expired');
                polling = false;
            }
        }

        function showState(state) {
            document.getElementById('state-waiting').style.display = state === 'waiting' ? '' : 'none';
            document.getElementById('state-linked').style.display = state === 'linked' ? '' : 'none';
            document.getElementById('state-expired').style.display = state === 'expired' ? '' : 'none';
        }

        async function poll() {
            if (!polling) return;
            const dbg = document.getElementById('debug');
            try {
                dbg.textContent = 'Polling: ' + pollUrl;
                const resp = await fetch(pollUrl);
                const data = await resp.json();
                dbg.textContent = 'Response: ' + JSON.stringify(data);

                if (data.status === 'linked' && data.redirect) {
                    polling = false;
                    showState('linked');
                    setTimeout(() => window.location.href = data.redirect, 1500);
                    return;
                }

                if (data.status === 'expired') {
                    polling = false;
                    showState('expired');
                    return;
                }
            } catch (e) {
                dbg.textContent = 'Error: ' + e.message;
            }

            setTimeout(poll, 3000);
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
        poll();
    })();
    </script>
</body>
</html>
