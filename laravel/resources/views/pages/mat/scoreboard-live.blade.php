<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scorebord - Mat {{ $matId }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; background: #000; font-family: 'Arial Black', 'Helvetica Neue', sans-serif; }

        .scoreboard {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            grid-template-rows: 1fr auto;
            height: 100vh;
            gap: 4px;
            padding: 4px;
        }

        /* Display view: WIT links, BLAUW rechts (gespiegeld t.o.v. bediening) */
        .panel {
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        .panel-wit {
            background: #fff;
            border: 4px solid #d1d5db;
            order: 1;
        }
        .panel-blauw {
            background: #1e40af;
            order: 3;
        }

        .panel-header {
            padding: 12px 20px;
            text-align: center;
        }
        .panel-wit .panel-header { background: #f3f4f6; }
        .panel-blauw .panel-header { background: #1e3a8a; }

        .naam {
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: clamp(20px, 4vw, 48px);
        }
        .panel-wit .naam { color: #1f2937; }
        .panel-blauw .naam { color: #fff; }

        .club {
            font-size: clamp(12px, 2vw, 22px);
            font-weight: 500;
        }
        .panel-wit .club { color: #6b7280; }
        .panel-blauw .club { color: #93c5fd; }

        .score-row {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 2vw, 24px);
            padding: 8px;
        }

        .score-box {
            text-align: center;
        }
        .score-label {
            font-size: clamp(10px, 1.5vw, 18px);
            font-weight: 700;
            letter-spacing: 2px;
        }
        .panel-wit .score-label { color: #6b7280; }
        .panel-blauw .score-label { color: #93c5fd; }

        .score-value {
            font-size: clamp(40px, 10vw, 140px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .panel-wit .score-value { color: #1f2937; }
        .panel-blauw .score-value { color: #fff; }

        .shido-row {
            display: flex;
            gap: clamp(4px, 0.5vw, 8px);
            padding: 8px 16px 12px;
        }
        .panel-wit .shido-row { justify-content: flex-end; }
        .panel-blauw .shido-row { justify-content: flex-start; }

        .shido-card {
            width: clamp(20px, 3vw, 40px);
            height: clamp(28px, 4vw, 56px);
            border-radius: 4px;
            border: 2px dashed rgba(156,163,175,0.5);
            background: rgba(156,163,175,0.2);
        }
        .shido-card.active {
            background: #facc15;
            border: 2px solid #eab308;
        }

        /* Osaekomi indicator */
        .osaekomi-indicator {
            display: none;
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: #f97316;
            color: #fff;
            border-radius: 50%;
            width: clamp(80px, 12vw, 160px);
            height: clamp(80px, 12vw, 160px);
            font-size: clamp(30px, 6vw, 72px);
            font-weight: 900;
            align-items: center;
            justify-content: center;
            animation: pulse 1s infinite;
        }
        .osaekomi-indicator.active { display: flex; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.05); }
        }

        /* Center column: timer */
        .center-col {
            order: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: clamp(180px, 25vw, 400px);
            padding: 8px;
        }

        .timer {
            font-size: clamp(48px, 10vw, 120px);
            font-weight: 900;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
            text-align: center;
            cursor: default;
            user-select: none;
        }
        .timer.warning { color: #ef4444; }
        .timer.golden-score { color: #eab308; }

        .progress-bar {
            width: 80%;
            height: 6px;
            background: #374151;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .progress-fill {
            height: 100%;
            background: #22c55e;
            border-radius: 3px;
            transition: width 0.1s linear;
        }
        .progress-fill.warning { background: #ef4444; }

        .golden-score-badge {
            display: none;
            background: #eab308;
            color: #000;
            font-weight: 900;
            font-size: clamp(14px, 2vw, 24px);
            padding: 4px 20px;
            border-radius: 20px;
            margin-top: 8px;
            animation: pulse 1.5s infinite;
        }
        .golden-score-badge.active { display: block; }

        /* Bottom bar: osaekomi */
        .bottom-bar {
            grid-column: 1 / -1;
            background: #1f2937;
            border-top: 1px solid #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            gap: clamp(16px, 3vw, 40px);
        }

        .osaekomi-label {
            color: #9ca3af;
            font-size: clamp(11px, 1.5vw, 18px);
            font-weight: 600;
        }
        .osaekomi-time {
            font-size: clamp(36px, 6vw, 72px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            color: #6b7280;
            font-variant-numeric: tabular-nums;
        }
        .osaekomi-time.active { color: #f97316; }

        .osaekomi-zone {
            font-size: clamp(10px, 1.2vw, 16px);
            font-weight: 800;
            color: #f97316;
        }

        /* Waiting state */
        .waiting {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #6b7280;
            font-size: clamp(16px, 3vw, 32px);
            font-weight: 600;
        }

        /* Winner overlay */
        .winner-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 50;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .winner-overlay.active { display: flex; }
        .winner-name {
            font-size: clamp(36px, 8vw, 80px);
            font-weight: 900;
            text-transform: uppercase;
        }
        .winner-name.blauw { color: #3b82f6; }
        .winner-name.wit { color: #fff; }
        .winner-title {
            font-size: clamp(24px, 5vw, 56px);
            color: #facc15;
            font-weight: 900;
            margin-top: 8px;
        }
        .winner-type {
            font-size: clamp(14px, 2.5vw, 28px);
            color: #9ca3af;
            font-weight: 600;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div id="app">
        {{-- Scoreboard — altijd zichtbaar --}}
        <div class="scoreboard" id="scoreboard">
            {{-- WIT panel (LEFT on display — gespiegeld) --}}
            <div class="panel panel-wit">
                <div class="panel-header">
                    <div class="naam" id="wit-naam">WIT</div>
                    <div class="club" id="wit-club"></div>
                </div>
                <div class="score-row">
                    <div class="score-box">
                        <div class="score-label">Y</div>
                        <div class="score-value" id="wit-yuko">0</div>
                    </div>
                    <div class="score-box">
                        <div class="score-label">W</div>
                        <div class="score-value" id="wit-wazaari">0</div>
                    </div>
                    <div class="score-box">
                        <div class="score-label">I</div>
                        <div class="score-value" id="wit-ippon">0</div>
                    </div>
                </div>
                <div class="shido-row">
                    <div class="shido-card" id="wit-shido-1"></div>
                    <div class="shido-card" id="wit-shido-2"></div>
                    <div class="shido-card" id="wit-shido-3"></div>
                </div>
                <div class="osaekomi-indicator" id="wit-osaekomi">
                    <span id="wit-osaekomi-time">0</span>
                </div>
            </div>

            {{-- Timer center --}}
            <div class="center-col">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="timer" id="timer-display">4:00</div>
                <div class="golden-score-badge" id="gs-badge">GOLDEN SCORE</div>
            </div>

            {{-- BLAUW panel (RIGHT on display — gespiegeld) --}}
            <div class="panel panel-blauw">
                <div class="panel-header">
                    <div class="naam" id="blauw-naam">BLAUW</div>
                    <div class="club" id="blauw-club"></div>
                </div>
                <div class="score-row">
                    <div class="score-box">
                        <div class="score-label">Y</div>
                        <div class="score-value" id="blauw-yuko">0</div>
                    </div>
                    <div class="score-box">
                        <div class="score-label">W</div>
                        <div class="score-value" id="blauw-wazaari">0</div>
                    </div>
                    <div class="score-box">
                        <div class="score-label">I</div>
                        <div class="score-value" id="blauw-ippon">0</div>
                    </div>
                </div>
                <div class="shido-row">
                    <div class="shido-card" id="blauw-shido-1"></div>
                    <div class="shido-card" id="blauw-shido-2"></div>
                    <div class="shido-card" id="blauw-shido-3"></div>
                </div>
                <div class="osaekomi-indicator" id="blauw-osaekomi">
                    <span id="blauw-osaekomi-time">0</span>
                </div>
            </div>

            {{-- Osaekomi bottom bar --}}
            <div class="bottom-bar">
                <span class="osaekomi-label">Osaekomi</span>
                <span class="osaekomi-time" id="osaekomi-display">00</span>
                <span class="osaekomi-zone" id="osaekomi-zone"></span>
            </div>
        </div>

        {{-- Winner overlay --}}
        <div class="winner-overlay" id="winner-overlay">
            <div class="winner-name" id="winner-name"></div>
            <div class="winner-title">WINNAAR</div>
            <div class="winner-type" id="winner-type"></div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    @php
        $reverbHost = parse_url(config('app.url'), PHP_URL_HOST);
        $reverbPort = config('reverb.apps.apps.0.options.port') ?? 443;
        $reverbKey = config('reverb.apps.apps.0.key') ?? env('REVERB_APP_KEY');
        $reverbScheme = config('reverb.apps.apps.0.options.scheme') ?? 'https';
    @endphp
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toernooiId = @js($toernooi->id);
        const matId = @js($matId);

        // State
        let matchDuration = 240;
        let timeRemaining = matchDuration;
        let isRunning = false;
        let isGoldenScore = false;
        let timerAnimFrame = null;
        let timerStartedAt = null;
        let timerRemainingAtStart = 0;

        let osaekomiActive = false;
        let osaekomiJudoka = null;
        let osaekomiStartedAt = null;
        let osaekomiAnimFrame = null;

        // DOM refs
        const els = {
            scoreboard: document.getElementById('scoreboard'),
            timer: document.getElementById('timer-display'),
            progress: document.getElementById('progress-fill'),
            gsBadge: document.getElementById('gs-badge'),
            osaekomi: document.getElementById('osaekomi-display'),
            osaekomiZone: document.getElementById('osaekomi-zone'),
            winnerOverlay: document.getElementById('winner-overlay'),
            winnerName: document.getElementById('winner-name'),
            winnerType: document.getElementById('winner-type'),
        };

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            const tenths = Math.floor((seconds % 1) * 10);
            if (seconds < 10) return `${mins}:${secs.toString().padStart(2, '0')}.${tenths}`;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function updateTimerDisplay() {
            els.timer.textContent = formatTime(timeRemaining);
            els.timer.className = 'timer' +
                (isGoldenScore ? ' golden-score' : '') +
                (!isGoldenScore && timeRemaining <= 30 ? ' warning' : '');

            const pct = isGoldenScore ? 0 : (timeRemaining / matchDuration) * 100;
            els.progress.style.width = pct + '%';
            els.progress.className = 'progress-fill' + (!isGoldenScore && timeRemaining <= 30 ? ' warning' : '');

            els.gsBadge.className = 'golden-score-badge' + (isGoldenScore ? ' active' : '');
        }

        function tickTimer() {
            if (!isRunning) return;
            const now = performance.now();
            const elapsed = (now - timerStartedAt) / 1000;

            if (isGoldenScore) {
                timeRemaining = timerRemainingAtStart + elapsed;
            } else {
                timeRemaining = Math.max(0, timerRemainingAtStart - elapsed);
            }

            updateTimerDisplay();
            timerAnimFrame = requestAnimationFrame(tickTimer);
        }

        function tickOsaekomi() {
            if (!osaekomiActive || !osaekomiStartedAt) return;
            const elapsed = Math.floor((performance.now() - osaekomiStartedAt) / 1000);

            els.osaekomi.textContent = elapsed.toString().padStart(2, '0');
            els.osaekomi.className = 'osaekomi-time active';

            // Zone labels
            let zone = '';
            if (elapsed >= 20) zone = 'IPPON';
            else if (elapsed >= 10) zone = 'WAZA-ARI';
            else if (elapsed >= 5) zone = 'YUKO';
            els.osaekomiZone.textContent = zone;

            // Osaekomi indicator on panel
            const panelEl = document.getElementById(osaekomiJudoka + '-osaekomi');
            const panelTimeEl = document.getElementById(osaekomiJudoka + '-osaekomi-time');
            if (panelEl) { panelEl.classList.add('active'); panelTimeEl.textContent = elapsed; }

            osaekomiAnimFrame = requestAnimationFrame(tickOsaekomi);
        }

        function updateScores(scores) {
            ['wit', 'blauw'].forEach(side => {
                const s = scores[side];
                document.getElementById(side + '-yuko').textContent = s.yuko;
                document.getElementById(side + '-wazaari').textContent = s.wazaari;
                document.getElementById(side + '-ippon').textContent = s.ippon ? '1' : '0';

                for (let i = 1; i <= 3; i++) {
                    const card = document.getElementById(side + '-shido-' + i);
                    card.className = 'shido-card' + (i <= s.shido ? ' active' : '');
                }
            });
        }

        function clearOsaekomiIndicators() {
            ['wit', 'blauw'].forEach(side => {
                const el = document.getElementById(side + '-osaekomi');
                if (el) el.classList.remove('active');
            });
            els.osaekomi.className = 'osaekomi-time';
            els.osaekomi.textContent = '00';
            els.osaekomiZone.textContent = '';
        }

        // Connect to Reverb via Pusher
        const pusher = new Pusher('{{ $reverbKey }}', {
            wsHost: '{{ $reverbHost }}',
            wsPort: {{ $reverbPort }},
            wssPort: {{ $reverbPort }},
            forceTLS: {{ $reverbScheme === 'https' ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            cluster: 'mt1'
        });

        const channelName = `scoreboard-display.${toernooiId}.${matId}`;
        const channel = pusher.subscribe(channelName);

        channel.bind('scoreboard.event', (payload) => {
            handleEvent(payload.data || payload);
        });

        pusher.connection.bind('connected', () => console.log('Reverb: verbonden'));
        pusher.connection.bind('error', (err) => console.error('Reverb: fout', err));

        function handleEvent(data) {
            switch (data.event) {
                case 'match.start':
                    els.winnerOverlay.classList.remove('active');

                    // Set names (display is mirrored: wit left, blauw right)
                    document.getElementById('wit-naam').textContent = data.judoka_wit?.naam || 'WIT';
                    document.getElementById('wit-club').textContent = data.judoka_wit?.club || '';
                    document.getElementById('blauw-naam').textContent = data.judoka_blauw?.naam || 'BLAUW';
                    document.getElementById('blauw-club').textContent = data.judoka_blauw?.club || '';

                    // Reset state
                    matchDuration = data.match_duration || 240;
                    timeRemaining = matchDuration;
                    isRunning = false;
                    isGoldenScore = false;
                    osaekomiActive = false;
                    clearOsaekomiIndicators();
                    updateScores({
                        wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                        blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                    });
                    updateTimerDisplay();
                    break;

                case 'timer.start':
                    isRunning = true;
                    isGoldenScore = data.golden_score || false;
                    timerRemainingAtStart = data.remaining;
                    timeRemaining = data.remaining;
                    timerStartedAt = performance.now();
                    tickTimer();
                    break;

                case 'timer.stop':
                    isRunning = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    timeRemaining = data.remaining;
                    updateTimerDisplay();
                    break;

                case 'timer.reset':
                    isRunning = false;
                    isGoldenScore = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    matchDuration = data.duration || 240;
                    timeRemaining = matchDuration;
                    osaekomiActive = false;
                    clearOsaekomiIndicators();
                    els.winnerOverlay.classList.remove('active');
                    updateTimerDisplay();
                    break;

                case 'score.update':
                    updateScores(data.scores);
                    break;

                case 'osaekomi.start':
                    osaekomiActive = true;
                    osaekomiJudoka = data.judoka;
                    osaekomiStartedAt = performance.now();
                    tickOsaekomi();
                    break;

                case 'osaekomi.stop':
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiIndicators();
                    break;

                case 'match.end':
                    isRunning = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiIndicators();

                    // Show winner overlay
                    const winnerSide = data.winner;
                    const nameEl = winnerSide === 'blauw'
                        ? document.getElementById('blauw-naam')
                        : document.getElementById('wit-naam');
                    els.winnerName.textContent = nameEl?.textContent || winnerSide.toUpperCase();
                    els.winnerName.className = 'winner-name ' + winnerSide;
                    els.winnerType.textContent = (data.uitslag_type || '').toUpperCase();
                    els.winnerOverlay.classList.add('active');
                    break;
            }
        }
    });
    </script>
</body>
</html>
