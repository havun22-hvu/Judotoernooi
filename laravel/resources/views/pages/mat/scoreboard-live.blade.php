<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scorebord - Mat {{ $matId }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; background: #111827; font-family: 'Arial Black', 'Helvetica Neue', sans-serif; }

        .scoreboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* 1. Names row */
        .names-row {
            display: flex;
            flex-direction: row;
        }
        .name-card {
            flex: 1;
            padding: 1.5vh 20px;
            text-align: center;
        }
        .name-card-wit { background: #F3F4F6; }
        .name-card-blauw { background: #1E3A8A; }
        .naam {
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: clamp(24px, 5vh, 64px);
        }
        .name-card-wit .naam { color: #1F2937; }
        .name-card-blauw .naam { color: #FFF; }
        .club {
            font-size: clamp(12px, 2.5vh, 28px);
            font-weight: 500;
        }
        .name-card-wit .club { color: #6B7280; }
        .name-card-blauw .club { color: #93C5FD; }

        /* 2. Spacer between names and timer */
        .spacer-row {
            display: flex;
            flex-direction: row;
            height: 8px;
        }

        /* 3. Timer row */
        .timer-row {
            display: flex;
            flex-direction: row;
        }
        .timer-side { flex: 1; }
        .timer-center {
            background: #111827;
            padding: 1vh 3vw;
            text-align: center;
        }
        .timer {
            font-size: clamp(48px, 14vh, 160px);
            font-weight: 900;
            color: #EF4444;
            font-family: 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .timer.warning { color: #EF4444; }
        .timer.golden-score { color: #EAB308; }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #374151;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        .progress-fill {
            height: 100%;
            background: #22C55E;
            border-radius: 2px;
            transition: width 0.1s linear;
        }
        .progress-fill.warning { background: #EF4444; }
        .golden-score-badge {
            display: none;
            background: #EAB308;
            color: #000;
            font-weight: 900;
            font-size: clamp(14px, 2.5vh, 28px);
            padding: 0.5vh 2vw;
            border-radius: 20px;
            margin-top: 4px;
            animation: pulse 1.5s infinite;
        }
        .golden-score-badge.active { display: inline-block; }

        /* 4. Scores row — Y/W/I vertical, red on dark */
        .scores-row {
            display: flex;
            flex-direction: row;
            flex: 3;
        }
        .score-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 2vh;
            gap: 1vh;
        }
        .score-col-wit { background: #F3F4F6; }
        .score-col-blauw { background: #1E3A8A; }
        .score-box {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 1vw;
        }
        .score-label {
            font-size: clamp(18px, 3vh, 36px);
            font-weight: 700;
            min-width: 2vw;
            text-align: right;
        }
        .score-col-wit .score-label { color: #6B7280; }
        .score-col-blauw .score-label { color: #93C5FD; }
        .score-value {
            font-size: clamp(40px, 10vh, 120px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            color: #EF4444;
            background: #1F2937;
            padding: 0.5vh 1.5vw;
            border-radius: 8px;
            min-width: clamp(60px, 8vw, 140px);
            text-align: center;
        }

        /* 5. Shido row */
        .shido-row {
            display: flex;
            flex-direction: row;
        }
        .shido-col {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1vh 0;
            gap: clamp(6px, 1vw, 16px);
        }
        .shido-col-wit { background: #F3F4F6; }
        .shido-col-blauw { background: #1E3A8A; }
        .shido-card {
            width: clamp(24px, 3vw, 50px);
            height: clamp(32px, 5vh, 70px);
            border-radius: 4px;
            border: 2px dashed rgba(156,163,175,0.5);
            background: rgba(156,163,175,0.2);
        }
        .shido-card.active {
            background: #FACC15;
            border: 2px solid #EAB308;
        }

        /* 6. Osaekomi row */
        .osaekomi-row {
            display: flex;
            flex-direction: row;
        }
        .osaekomi-btn-col {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5vh 0;
            position: relative;
        }
        .osaekomi-btn-col-wit { background: #F3F4F6; }
        .osaekomi-btn-col-blauw { background: #1E3A8A; }
        .osaekomi-timer-col {
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1vh 2vw;
        }
        .osaekomi-time {
            font-size: clamp(36px, 8vh, 80px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            color: #6B7280;
            font-variant-numeric: tabular-nums;
        }
        .osaekomi-time.active { color: #F97316; }
        .osaekomi-zone {
            font-size: clamp(14px, 2.5vh, 28px);
            font-weight: 800;
            color: #F97316;
            margin-left: 8px;
        }
        .osaekomi-indicator {
            display: none;
            position: absolute;
            background: #F97316;
            color: #FFF;
            border-radius: 50%;
            width: clamp(60px, 10vh, 120px);
            height: clamp(60px, 10vh, 120px);
            font-size: clamp(24px, 5vh, 52px);
            font-weight: 900;
            align-items: center;
            justify-content: center;
            animation: pulse 1s infinite;
        }
        .osaekomi-indicator.active { display: flex; }

        /* 7. Osaekomi times row */
        .osaekomi-times-row {
            display: flex;
            flex-direction: row;
            flex: 1;
            min-height: 40px;
        }
        .osaekomi-times-col {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 6px;
        }
        .osaekomi-times-col-wit { background: #F3F4F6; }
        .osaekomi-times-col-blauw { background: #1E3A8A; }

        /* 8. Footer */
        .footer-row {
            background: #111827;
            padding: 6px 16px;
            text-align: center;
        }
        .footer-text {
            color: #6B7280;
            font-size: 12px;
        }

        /* Half colors */
        .half-wit { background: #F3F4F6; flex: 1; }
        .half-blauw { background: #1E3A8A; flex: 1; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
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
        .winner-name.blauw { color: #3B82F6; }
        .winner-name.wit { color: #FFF; }
        .winner-title {
            font-size: clamp(24px, 5vw, 56px);
            color: #FACC15;
            font-weight: 900;
            margin-top: 8px;
        }
        .winner-type {
            font-size: clamp(14px, 2.5vw, 28px);
            color: #9CA3AF;
            font-weight: 600;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="scoreboard" id="scoreboard">
            {{-- 1. Names — wit links, blauw rechts (gespiegeld) --}}
            <div class="names-row">
                <div class="name-card name-card-wit">
                    <div class="naam" id="wit-naam">WIT</div>
                    <div class="club" id="wit-club"></div>
                </div>
                <div class="name-card name-card-blauw">
                    <div class="naam" id="blauw-naam">BLAUW</div>
                    <div class="club" id="blauw-club"></div>
                </div>
            </div>

            {{-- 2. Spacer --}}
            <div class="spacer-row">
                <div class="half-wit"></div>
                <div class="half-blauw"></div>
            </div>

            {{-- 3. Timer — donker midden, kleur zijkanten --}}
            <div class="timer-row">
                <div class="timer-side half-wit"></div>
                <div class="timer-center">
                    <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
                    <div class="timer" id="timer-display">4:00</div>
                    <div class="golden-score-badge" id="gs-badge">GOLDEN SCORE</div>
                </div>
                <div class="timer-side half-blauw"></div>
            </div>

            {{-- 4. Scores Y/W/I + Shido's --}}
            <div class="scores-row">
                <div class="score-col score-col-wit">
                    <div class="score-box"><span class="score-label">Y</span><span class="score-value" id="wit-yuko">0</span></div>
                    <div class="score-box"><span class="score-label">W</span><span class="score-value" id="wit-wazaari">0</span></div>
                    <div class="score-box"><span class="score-label">I</span><span class="score-value" id="wit-ippon">0</span></div>
                </div>
                <div class="score-col score-col-blauw">
                    <div class="score-box"><span class="score-label">Y</span><span class="score-value" id="blauw-yuko">0</span></div>
                    <div class="score-box"><span class="score-label">W</span><span class="score-value" id="blauw-wazaari">0</span></div>
                    <div class="score-box"><span class="score-label">I</span><span class="score-value" id="blauw-ippon">0</span></div>
                </div>
            </div>

            {{-- 5. Shido's --}}
            <div class="shido-row">
                <div class="shido-col shido-col-wit">
                    <div class="shido-card" id="wit-shido-1"></div>
                    <div class="shido-card" id="wit-shido-2"></div>
                    <div class="shido-card" id="wit-shido-3"></div>
                </div>
                <div class="shido-col shido-col-blauw">
                    <div class="shido-card" id="blauw-shido-1"></div>
                    <div class="shido-card" id="blauw-shido-2"></div>
                    <div class="shido-card" id="blauw-shido-3"></div>
                </div>
            </div>

            {{-- 6. Osaekomi — knoppen in kleur, timer donker midden --}}
            <div class="osaekomi-row">
                <div class="osaekomi-btn-col osaekomi-btn-col-wit">
                    <div class="osaekomi-indicator" id="wit-osaekomi"><span id="wit-osaekomi-time">0</span></div>
                </div>
                <div class="osaekomi-timer-col">
                    <span class="osaekomi-time" id="osaekomi-display">00</span>
                    <span class="osaekomi-zone" id="osaekomi-zone"></span>
                </div>
                <div class="osaekomi-btn-col osaekomi-btn-col-blauw">
                    <div class="osaekomi-indicator" id="blauw-osaekomi"><span id="blauw-osaekomi-time">0</span></div>
                </div>
            </div>

            {{-- 7. Osaekomi tijden --}}
            <div class="osaekomi-times-row">
                <div class="osaekomi-times-col osaekomi-times-col-wit"></div>
                <div class="osaekomi-times-col osaekomi-times-col-blauw"></div>
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
