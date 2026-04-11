<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JudoScoreBoard — Download</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .logo {
            font-size: 48px;
            margin-bottom: 8px;
        }

        h1 {
            font-size: 28px;
            font-weight: 900;
            color: #f8fafc;
            margin-bottom: 4px;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 15px;
            margin-bottom: 32px;
        }

        .version-badge {
            display: inline-block;
            background: #334155;
            color: #94a3b8;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .qr-container {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            display: inline-block;
            margin-bottom: 24px;
        }

        .qr-container img {
            width: 200px;
            height: 200px;
        }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 13px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            transition: background 0.2s;
            margin-bottom: 16px;
        }
        .download-btn:hover { background: #1d4ed8; }
        .download-btn.disabled {
            background: #475569;
            cursor: not-allowed;
            pointer-events: none;
        }

        .download-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .instructions {
            text-align: left;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #334155;
        }

        .instructions h3 {
            color: #f8fafc;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .instructions ol {
            color: #94a3b8;
            font-size: 14px;
            padding-left: 20px;
            line-height: 1.8;
        }

        .instructions li { margin-bottom: 4px; }

        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #334155;
        }

        .feature {
            text-align: left;
            padding: 12px;
            background: #0f172a;
            border-radius: 8px;
        }

        .feature-icon { font-size: 20px; margin-bottom: 4px; }
        .feature-title { color: #e2e8f0; font-size: 13px; font-weight: 700; }
        .feature-desc { color: #64748b; font-size: 11px; margin-top: 2px; }

        .footer {
            margin-top: 24px;
            color: #475569;
            font-size: 12px;
        }
        .footer a { color: #64748b; text-decoration: none; }
        .footer a:hover { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">🥋</div>
        <h1>JudoScoreBoard</h1>
        <p class="subtitle">Scorebord app voor judo toernooien</p>

        <div class="version-badge">
            Versie {{ config('scoreboard.version', '1.0.0') }}
        </div>

        @php
            $downloadUrl = config('scoreboard.download_url');
        @endphp

        {{-- QR Code (generated via Google Charts API) --}}
        @if($downloadUrl)
            <div class="qr-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($downloadUrl) }}" alt="QR Code">
            </div>
            <br>
            <a href="{{ $downloadUrl }}" class="download-btn">
                <svg viewBox="0 0 24 24"><path d="M5 20h14v-2H5v2zm7-18L5.33 9h3.67v4h4V9h3.67L12 2z" transform="rotate(180 12 12)"/></svg>
                Download APK
            </a>
        @else
            <div class="qr-container">
                <div class="qr-placeholder">APK nog niet beschikbaar</div>
            </div>
            <br>
            <span class="download-btn disabled">
                Build in voorbereiding...
            </span>
        @endif

        <div class="features">
            <div class="feature">
                <div class="feature-icon">⏱</div>
                <div class="feature-title">Timer</div>
                <div class="feature-desc">Countdown + Golden Score</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📊</div>
                <div class="feature-title">Scores</div>
                <div class="feature-desc">Yuko, Waza-ari, Ippon</div>
            </div>
            <div class="feature">
                <div class="feature-icon">🟡</div>
                <div class="feature-title">Shido</div>
                <div class="feature-desc">3 kaarten + Hansoku-make</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📡</div>
                <div class="feature-title">Live sync</div>
                <div class="feature-desc">Resultaat naar mat interface</div>
            </div>
        </div>

        <div class="instructions">
            <h3>Installatie</h3>
            <ol>
                <li>Download de APK op je Android tablet/telefoon</li>
                <li>Open het bestand en sta installatie toe</li>
                <li>Open de app en kies "Gekoppeld" of "Standalone"</li>
                <li>Voer de toegangscode in (bij gekoppeld)</li>
            </ol>
        </div>
    </div>

    <p class="footer">
        <a href="https://judotournament.org">JudoTournament.org</a> &mdash; Havun
    </p>
</body>
</html>
