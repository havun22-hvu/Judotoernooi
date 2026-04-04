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

        /* 0. Header — poule info */
        .header-row {
            background: #111827;
            padding: 1vh 16px 0.5vh;
            text-align: center;
        }
        .header-poule {
            color: #FFFFFF;
            font-size: clamp(16px, 3vh, 36px);
            font-weight: 800;
        }
        .header-mat {
            color: #6B7280;
            font-size: clamp(10px, 1.5vh, 18px);
            font-weight: 600;
        }

        /* 1. Names row */
        .names-row {
            display: flex;
            flex-direction: row;
        }
        .name-card {
            flex: 1;
            flex-basis: 0;
            padding: 2vh 8px 1.5vh;
            text-align: center;
            border-bottom: 3px solid rgba(0,0,0,0.3);
        }
        .name-card-wit { background: #F3F4F6; border-bottom-color: #D1D5DB; }
        .name-card-blauw { background: #1E3A8A; border-bottom-color: rgba(255,255,255,0.2); }
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

        /* 2. Timer row — donker midden, kleur zijkanten */
        .timer-row {
            display: flex;
            flex-direction: row;
        }
        .timer-side { flex: 1; flex-basis: 0; }
        .timer-center {
            background: #111827;
            padding: 2.5vh 5vw;
            text-align: center;
        }
        .timer {
            font-size: clamp(72px, 22vh, 240px);
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

        /* 3. Main content — 3-kolom layout */
        .main-3col {
            display: flex;
            flex-direction: row;
            flex: 1;
        }

        /* Side columns (wit/blauw) — scores + shido's */
        .side-col {
            flex: 1;
            flex-basis: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            margin-top: -4vh;
        }
        .side-col-wit { background: #F3F4F6; }
        .side-col-blauw { background: #1E3A8A; }

        .scores-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 0;
            gap: 0;
        }
        .score-box {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 1vw;
            border-bottom: 1px solid rgba(156,163,175,0.3);
            padding-bottom: 2px;
            margin-bottom: 2px;
        }
        .score-box:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .score-label {
            font-size: clamp(16px, 3vh, 36px);
            font-weight: 800;
            min-width: 2vw;
            text-align: right;
            letter-spacing: 1px;
        }
        .side-col-wit .score-label { color: #6B7280; }
        .side-col-blauw .score-label { color: #93C5FD; }
        .score-value {
            font-size: clamp(40px, 12vh, 140px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            color: #EF4444;
            background: #111827;
            padding: 0.5vh 1.5vw;
            border-radius: 6px;
            min-width: clamp(48px, 8vw, 140px);
            text-align: center;
        }

        .shido-section {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3vh 0 0;
            gap: clamp(6px, 1vw, 16px);
        }
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

        /* Middle column — osaekomi (doorlopende banen, donker vak in midden) */
        .middle-col {
            flex: 0.5;
            flex-basis: 0;
            display: flex;
            flex-direction: column;
        }
        /* Top/bottom banen in middle column */
        .middle-banen {
            display: flex;
            flex-direction: row;
        }
        .middle-banen-top { flex: 0.8; }
        .middle-banen-bottom { flex: 1; }
        .middle-half-wit { background: #F3F4F6; flex: 1; }
        .middle-half-blauw { background: #1E3A8A; flex: 1; }

        /* Osaekomi dark box in middle */
        .osaekomi-box {
            background: #111827;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 1.5vh 2vw;
        }
        .osaekomi-label {
            color: #FFFFFF;
            font-size: clamp(14px, 3vh, 32px);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .osaekomi-dots-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 2vw;
            margin-top: 0.5vh;
        }
        .osaekomi-dot {
            width: clamp(20px, 3vh, 40px);
            height: clamp(20px, 3vh, 40px);
            border-radius: 50%;
            background: #374151;
        }
        .osaekomi-dot.active {
            background: #22C55E;
        }
        .osaekomi-time {
            font-size: clamp(36px, 8vh, 80px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            color: #EF4444;
            font-variant-numeric: tabular-nums;
        }
        .osaekomi-time.active { color: #EF4444; }
        .osaekomi-zone {
            font-size: clamp(14px, 2.5vh, 28px);
            font-weight: 800;
            color: #F97316;
        }

        /* Osaekomi times in dark box */
        .osaekomi-times-section {
            display: flex;
            flex-direction: row;
            gap: 3vw;
            margin-top: 1vh;
        }
        .osaekomi-times-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .osaekomi-time-entry {
            font-size: clamp(16px, 3vh, 32px);
            font-weight: 800;
            color: #EF4444;
            animation: blink 1s infinite;
        }

        /* Bottom half of middle = split wit/blauw banen */

        /* Half colors */
        .half-wit { background: #F3F4F6; flex: 1; flex-basis: 0; }
        .half-blauw { background: #1E3A8A; flex: 1; flex-basis: 0; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
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
            {{-- 0. Header — poule info --}}
            <div class="header-row">
                <div class="header-mat">Mat {{ $matNummer ?? '' }}</div>
                <div class="header-poule" id="header-poule"></div>
            </div>

            {{-- 1. Names — links/rechts op basis van blauwRechts instelling --}}
            <div class="names-row">
                <div class="name-card name-card-{{ $blauwRechts ? 'blauw' : 'wit' }}">
                    <div class="naam" id="{{ $blauwRechts ? 'blauw' : 'wit' }}-naam">{{ $blauwRechts ? 'BLAUW' : 'WIT' }}</div>
                    <div class="club" id="{{ $blauwRechts ? 'blauw' : 'wit' }}-club"></div>
                </div>
                <div class="name-card name-card-{{ $blauwRechts ? 'wit' : 'blauw' }}">
                    <div class="naam" id="{{ $blauwRechts ? 'wit' : 'blauw' }}-naam">{{ $blauwRechts ? 'WIT' : 'BLAUW' }}</div>
                    <div class="club" id="{{ $blauwRechts ? 'wit' : 'blauw' }}-club"></div>
                </div>
            </div>

            {{-- Spacer tussen namen en timer --}}
            <div style="display:flex;flex-direction:row;height:0.5vh">
                <div class="half-{{ $blauwRechts ? 'blauw' : 'wit' }}"></div>
                <div class="half-{{ $blauwRechts ? 'wit' : 'blauw' }}"></div>
            </div>

            {{-- 2. Timer — donker midden, kleur zijkanten --}}
            <div class="timer-row">
                <div class="timer-side half-{{ $blauwRechts ? 'blauw' : 'wit' }}"></div>
                <div class="timer-center">
                    <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
                    <div class="timer" id="timer-display">{{ floor(($toernooi->getMatchDuration()) / 60) }}:00</div>
                    <div class="golden-score-badge" id="gs-badge">GOLDEN SCORE</div>
                </div>
                <div class="timer-side half-{{ $blauwRechts ? 'wit' : 'blauw' }}"></div>
            </div>

            @php
                $leftSide = $blauwRechts ? 'blauw' : 'wit';
                $rightSide = $blauwRechts ? 'wit' : 'blauw';
            @endphp

            {{-- 3. Main content — 3 kolommen --}}
            <div class="main-3col">
                {{-- Linker kolom --}}
                <div class="side-col side-col-{{ $leftSide }}">
                    <div class="scores-section">
                        <div class="score-box"><span class="score-label">Y</span><span class="score-value" id="{{ $leftSide }}-yuko">0</span></div>
                        <div class="score-box"><span class="score-label">W</span><span class="score-value" id="{{ $leftSide }}-wazaari">0</span></div>
                        <div class="score-box"><span class="score-label">I</span><span class="score-value" id="{{ $leftSide }}-ippon">0</span></div>
                    </div>
                    <div class="shido-section">
                        <div class="shido-card" id="{{ $leftSide }}-shido-1"></div>
                        <div class="shido-card" id="{{ $leftSide }}-shido-2"></div>
                        <div class="shido-card" id="{{ $leftSide }}-shido-3"></div>
                    </div>
                </div>

                {{-- Midden kolom --}}
                <div class="middle-col">
                    <div class="middle-banen middle-banen-top">
                        <div class="middle-half-{{ $leftSide }}"></div>
                        <div class="middle-half-{{ $rightSide }}"></div>
                    </div>
                    <div class="osaekomi-box">
                        <div class="osaekomi-label">Osaekomi</div>
                        <div class="osaekomi-dots-row">
                            <div class="osaekomi-dot" id="{{ $leftSide }}-osaekomi-dot"></div>
                            <span class="osaekomi-time" id="osaekomi-display">00</span>
                            <div class="osaekomi-dot" id="{{ $rightSide }}-osaekomi-dot"></div>
                        </div>
                        <span class="osaekomi-zone" id="osaekomi-zone"></span>
                        <div class="osaekomi-times-section">
                            <div class="osaekomi-times-col" id="{{ $leftSide }}-osaekomi-times"></div>
                            <div class="osaekomi-times-col" id="{{ $rightSide }}-osaekomi-times"></div>
                        </div>
                    </div>
                    <div class="middle-banen middle-banen-bottom">
                        <div class="middle-half-{{ $leftSide }}"></div>
                        <div class="middle-half-{{ $rightSide }}"></div>
                    </div>
                </div>

                {{-- Rechter kolom --}}
                <div class="side-col side-col-{{ $rightSide }}">
                    <div class="scores-section">
                        <div class="score-box"><span class="score-label">Y</span><span class="score-value" id="{{ $rightSide }}-yuko">0</span></div>
                        <div class="score-box"><span class="score-label">W</span><span class="score-value" id="{{ $rightSide }}-wazaari">0</span></div>
                        <div class="score-box"><span class="score-label">I</span><span class="score-value" id="{{ $rightSide }}-ippon">0</span></div>
                    </div>
                    <div class="shido-section">
                        <div class="shido-card" id="{{ $rightSide }}-shido-1"></div>
                        <div class="shido-card" id="{{ $rightSide }}-shido-2"></div>
                        <div class="shido-card" id="{{ $rightSide }}-shido-3"></div>
                    </div>
                </div>
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
        $reverbHost = env('VITE_REVERB_HOST', parse_url(config('app.url'), PHP_URL_HOST));
        $reverbPort = (int) env('VITE_REVERB_PORT', 443);
        $reverbKey = config('broadcasting.connections.reverb.key') ?? env('REVERB_APP_KEY');
        $reverbScheme = env('VITE_REVERB_SCHEME', 'https');
    @endphp
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toernooiId = @js($toernooi->id);
        const matId = @js($matId);
        const initialMatch = @js($currentMatch ?? null);

        // State
        let matchDuration = @js($toernooi->getMatchDuration());
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
        let osaekomiTimes = { wit: [], blauw: [] };

        // DOM refs
        const els = {
            timer: document.getElementById('timer-display'),
            progress: document.getElementById('progress-fill'),
            gsBadge: document.getElementById('gs-badge'),
            osaekomi: document.getElementById('osaekomi-display'),
            osaekomiZone: document.getElementById('osaekomi-zone'),
            witDot: document.getElementById('wit-osaekomi-dot'),
            blauwDot: document.getElementById('blauw-osaekomi-dot'),
            winnerOverlay: document.getElementById('winner-overlay'),
            winnerName: document.getElementById('winner-name'),
            winnerType: document.getElementById('winner-type'),
            headerPoule: document.getElementById('header-poule'),
        };

        // Load initial match data
        if (initialMatch) {
            els.headerPoule.textContent = [initialMatch.poule_naam, initialMatch.ronde ? `Ronde ${initialMatch.ronde}` : ''].filter(Boolean).join(' · ');
            document.getElementById('wit-naam').textContent = initialMatch.judoka_wit?.naam || 'WIT';
            document.getElementById('wit-club').textContent = initialMatch.judoka_wit?.club || '';
            document.getElementById('blauw-naam').textContent = initialMatch.judoka_blauw?.naam || 'BLAUW';
            document.getElementById('blauw-club').textContent = initialMatch.judoka_blauw?.club || '';
            matchDuration = initialMatch.match_duration || @js($toernooi->getMatchDuration());
            timeRemaining = matchDuration;
        }

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

        function getOsaekomiZone(seconds) {
            if (seconds >= 20) return 'IPPON';
            if (seconds >= 10) return 'WAZA-ARI';
            if (seconds >= 5) return 'YUKO';
            return '';
        }

        function renderOsaekomiTimes() {
            ['wit', 'blauw'].forEach(side => {
                const col = document.getElementById(side + '-osaekomi-times');
                col.innerHTML = '';
                osaekomiTimes[side].forEach(time => {
                    const zone = getOsaekomiZone(time);
                    const el = document.createElement('div');
                    el.className = 'osaekomi-time-entry';
                    el.textContent = time + 's' + (zone ? ' → ' + zone : '');
                    col.appendChild(el);
                });
            });
        }

        function clearOsaekomiState() {
            els.witDot.classList.remove('active');
            els.blauwDot.classList.remove('active');
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
            console.log('[LCD] Event ontvangen:', JSON.stringify(payload).substring(0, 200));
            handleEvent(payload.data || payload);
        });

        pusher.connection.bind('connected', () => console.log('[LCD] Reverb verbonden op kanaal:', channelName));
        pusher.connection.bind('error', (err) => console.error('[LCD] Reverb fout:', err));

        function handleEvent(data) {
            switch (data.event) {
                case 'match.start':
                    els.winnerOverlay.classList.remove('active');
                    els.headerPoule.textContent = [data.poule_naam, data.ronde ? `Ronde ${data.ronde}` : ''].filter(Boolean).join(' · ');

                    document.getElementById('wit-naam').textContent = data.judoka_wit?.naam || 'WIT';
                    document.getElementById('wit-club').textContent = data.judoka_wit?.club || '';
                    document.getElementById('blauw-naam').textContent = data.judoka_blauw?.naam || 'BLAUW';
                    document.getElementById('blauw-club').textContent = data.judoka_blauw?.club || '';

                    matchDuration = data.match_duration || @js($toernooi->getMatchDuration());
                    timeRemaining = matchDuration;
                    isRunning = false;
                    isGoldenScore = false;
                    osaekomiActive = false;
                    osaekomiTimes = { wit: [], blauw: [] };
                    clearOsaekomiState();
                    renderOsaekomiTimes();
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
                    matchDuration = data.duration || @js($toernooi->getMatchDuration());
                    timeRemaining = matchDuration;
                    osaekomiActive = false;
                    clearOsaekomiState();
                    els.winnerOverlay.classList.remove('active');
                    updateTimerDisplay();
                    break;

                case 'score.update':
                    updateScores(data.scores);
                    if (data.osaekomi_times) {
                        osaekomiTimes = data.osaekomi_times;
                        renderOsaekomiTimes();
                    }
                    break;

                case 'osaekomi.start':
                    clearOsaekomiState();
                    osaekomiActive = true;
                    osaekomiJudoka = data.judoka;
                    osaekomiStartedAt = performance.now();

                    // Green dot on active side
                    if (data.judoka === 'wit') els.witDot.classList.add('active');
                    if (data.judoka === 'blauw') els.blauwDot.classList.add('active');

                    if (data.osaekomi_times) {
                        osaekomiTimes = data.osaekomi_times;
                        renderOsaekomiTimes();
                    }
                    tickOsaekomi();
                    break;

                case 'osaekomi.stop':
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiState();
                    if (data.osaekomi_times) {
                        osaekomiTimes = data.osaekomi_times;
                        renderOsaekomiTimes();
                    }
                    break;

                case 'match.end':
                    isRunning = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiState();

                    const winnerSide = data.winner;
                    const nameEl = winnerSide === 'blauw'
                        ? document.getElementById('blauw-naam')
                        : document.getElementById('wit-naam');
                    els.winnerName.textContent = nameEl?.textContent || winnerSide.toUpperCase();
                    els.winnerName.className = 'winner-name ' + winnerSide;
                    els.winnerType.textContent = (data.uitslag_type || '').toUpperCase();
                    els.winnerOverlay.classList.add('active');
                    break;

                case 'match.assign':
                    // Match assigned to mat (groen gezet) — prepare next match behind overlay
                    // Do NOT remove winner overlay here — it stays until match.start
                    els.headerPoule.textContent = [data.poule_naam, data.ronde ? `Ronde ${data.ronde}` : ''].filter(Boolean).join(' · ');
                    document.getElementById('wit-naam').textContent = data.judoka_wit?.naam || 'WIT';
                    document.getElementById('wit-club').textContent = data.judoka_wit?.club || '';
                    document.getElementById('blauw-naam').textContent = data.judoka_blauw?.naam || 'BLAUW';
                    document.getElementById('blauw-club').textContent = data.judoka_blauw?.club || '';
                    matchDuration = data.match_duration || @js($toernooi->getMatchDuration());
                    timeRemaining = matchDuration;
                    isRunning = false;
                    isGoldenScore = false;
                    osaekomiActive = false;
                    osaekomiTimes = { wit: [], blauw: [] };
                    clearOsaekomiState();
                    renderOsaekomiTimes();
                    updateScores({
                        wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                        blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                    });
                    updateTimerDisplay();
                    break;

                case 'match.unassign':
                    // Match removed from mat — reset to standby
                    els.headerPoule.textContent = '';
                    isRunning = false;
                    isGoldenScore = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    osaekomiTimes = { wit: [], blauw: [] };
                    clearOsaekomiState();
                    renderOsaekomiTimes();
                    document.getElementById('wit-naam').textContent = 'WIT';
                    document.getElementById('wit-club').textContent = '';
                    document.getElementById('blauw-naam').textContent = 'BLAUW';
                    document.getElementById('blauw-club').textContent = '';
                    matchDuration = @js($toernooi->getMatchDuration());
                    timeRemaining = matchDuration;
                    updateScores({
                        wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                        blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 },
                    });
                    updateTimerDisplay();
                    els.winnerOverlay.classList.remove('active');
                    break;
            }
        }
    });
    </script>
</body>
</html>
