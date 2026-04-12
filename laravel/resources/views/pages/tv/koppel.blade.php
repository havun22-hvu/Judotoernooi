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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">JudoToernooi</div>

        <div id="state-waiting">
            <p class="instruction">Voer deze code in bij de toernooi-instellingen</p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>
            <p class="status"><span class="spinner"></span> Wachten op koppeling...</p>
            <p class="hint">Ga op de laptop naar Instellingen → Organisatie → Device Toegangen → klik "Koppel TV" bij de juiste mat</p>
            <p class="countdown" id="countdown"></p>
        </div>

        <div id="state-linked" style="display: none;">
            <p class="status" style="font-size: clamp(24px, 4vh, 48px); font-weight: bold; color: #22C55E;">TV Gekoppeld!</p>
            <p class="instruction" style="margin-top: 2vh;">Scorebord wordt geladen...</p>
        </div>

        <div id="state-expired" style="display: none;">
            <p class="status" style="font-size: clamp(20px, 3vh, 36px); color: #EF4444;">Code verlopen</p>
            <p class="instruction" style="margin-top: 2vh;">
                <a href="{{ route('tv.koppel') }}" style="color: #3B82F6; text-decoration: underline;">Klik hier voor een nieuwe code</a>
            </p>
        </div>
    </div>

    @php
        $appUrl = config('app.url');
        $reverbHost = parse_url($appUrl, PHP_URL_HOST);
        $reverbPort = parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80;
        $reverbKey = config('broadcasting.connections.reverb.key');
        $reverbScheme = parse_url($appUrl, PHP_URL_SCHEME);
    @endphp
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script @nonce>
    (function() {
        const code = '{{ $code }}';
        const expiresAt = new Date(Date.now() + 10 * 60 * 1000);

        function showState(state) {
            document.getElementById('state-waiting').style.display = state === 'waiting' ? '' : 'none';
            document.getElementById('state-linked').style.display = state === 'linked' ? '' : 'none';
            document.getElementById('state-expired').style.display = state === 'expired' ? '' : 'none';
        }

        function updateCountdown() {
            const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const el = document.getElementById('countdown');
            if (el) el.textContent = 'Code verloopt over ' + mins + ':' + secs.toString().padStart(2, '0');
            if (remaining <= 0) {
                showState('expired');
            }
        }

        // Connect to Reverb
        const pusher = new Pusher('{{ $reverbKey }}', {
            wsHost: '{{ $reverbHost }}',
            wsPort: {{ $reverbPort }},
            wssPort: {{ $reverbPort }},
            forceTLS: {{ $reverbScheme === 'https' ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            cluster: 'mt1'
        });

        const channel = pusher.subscribe('tv-koppeling.' + code);

        channel.bind('tv.linked', function(data) {
            if (data.redirect) {
                showState('linked');
                setTimeout(() => window.location.href = data.redirect, 1500);
            }
        });

        pusher.connection.bind('error', function(err) {
            console.error('[TV] Reverb error:', err);
        });

        setInterval(updateCountdown, 1000);
        updateCountdown();
    })();
    </script>
</body>
</html>
