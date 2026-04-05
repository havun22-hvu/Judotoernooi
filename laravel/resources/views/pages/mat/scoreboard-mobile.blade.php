<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Scorebord - Mat {{ $matNummer ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; background: #111827; font-family: 'Arial Black', 'Helvetica Neue', sans-serif; -webkit-user-select: none; user-select: none; }

        .scoreboard { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

        /* Header */
        .header {
            background: #111827;
            padding: 6px 12px;
            text-align: center;
            flex-shrink: 0;
        }
        .header-mat { color: #6B7280; font-size: 12px; font-weight: 600; }
        .header-poule { color: #FFF; font-size: 16px; font-weight: 800; }

        /* Judoka card */
        .judoka-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 8px 12px;
            min-height: 0;
        }
        .judoka-wit { background: #F3F4F6; }
        .judoka-blauw { background: #1E3A8A; }

        .judoka-naam {
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: clamp(18px, 5vw, 36px);
        }
        .judoka-wit .judoka-naam { color: #1F2937; }
        .judoka-blauw .judoka-naam { color: #FFF; }

        .judoka-club { font-size: clamp(10px, 2.5vw, 16px); font-weight: 500; }
        .judoka-wit .judoka-club { color: #6B7280; }
        .judoka-blauw .judoka-club { color: #93C5FD; }

        /* Scores row */
        .scores-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
        }
        .score-item {
            display: flex;
            flex: 1;
            align-items: center;
            gap: 6px;
            background: rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 6px 8px;
        }
        .judoka-blauw .score-item { background: rgba(0,0,0,0.25); }
        .score-lbl {
            font-size: clamp(14px, 4vw, 22px);
            font-weight: 800;
            min-width: 20px;
            text-align: center;
        }
        .judoka-wit .score-lbl { color: #6B7280; }
        .judoka-blauw .score-lbl { color: #93C5FD; }

        .score-val {
            font-size: clamp(28px, 8vw, 52px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            color: #EF4444;
            background: #111827;
            padding: 4px 12px;
            border-radius: 6px;
            min-width: 48px;
            text-align: center;
            line-height: 1.1;
        }

        /* Shido cards */
        .shido-row {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }
        .shido {
            width: 24px;
            height: 34px;
            border-radius: 3px;
            border: 1.5px dashed rgba(156,163,175,0.5);
            background: rgba(156,163,175,0.2);
        }
        .shido.active { background: #FACC15; border: 1.5px solid #EAB308; }

        /* Timer center */
        .timer-section {
            background: #111827;
            text-align: center;
            padding: 8px 12px;
            flex-shrink: 0;
        }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #374151;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        .progress-fill { height: 100%; background: #22C55E; border-radius: 2px; transition: width 0.1s linear; }
        .progress-fill.warning { background: #EF4444; }

        .timer {
            font-size: clamp(40px, 14vw, 80px);
            font-weight: 900;
            color: #EF4444;
            font-family: 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .timer.warning { color: #EF4444; }
        .timer.golden-score { color: #EAB308; }

        .gs-badge {
            display: none;
            background: #EAB308;
            color: #000;
            font-weight: 900;
            font-size: 12px;
            padding: 2px 12px;
            border-radius: 12px;
            margin-top: 2px;
            animation: pulse 1.5s infinite;
        }
        .gs-badge.active { display: inline-block; }

        /* Osaekomi */
        .osaekomi-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 4px;
        }
        .osae-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #374151;
        }
        .osae-dot.active { background: #22C55E; }
        .osae-time {
            font-size: clamp(24px, 8vw, 48px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            color: #374151;
            font-variant-numeric: tabular-nums;
        }
        .osae-time.active { color: #EF4444; }
        .osae-zone {
            font-size: 12px;
            font-weight: 800;
            color: #F97316;
            text-align: center;
        }

        /* Osaekomi times */
        .osae-times {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 2px;
        }
        .osae-times-col { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .osae-time-entry { font-size: 12px; font-weight: 800; color: #EF4444; animation: blink 1s infinite; }

        /* Winner overlay */
        .winner-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 50;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .winner-overlay.active { display: flex; }
        .winner-name { font-size: clamp(28px, 8vw, 60px); font-weight: 900; text-transform: uppercase; }
        .winner-name.blauw { color: #3B82F6; }
        .winner-name.wit { color: #FFF; }
        .winner-title { font-size: clamp(20px, 5vw, 40px); color: #FACC15; font-weight: 900; margin-top: 4px; }
        .winner-type { font-size: clamp(12px, 3vw, 20px); color: #9CA3AF; font-weight: 600; margin-top: 2px; }

        /* Standby message */
        .standby {
            display: flex;
            flex: 1;
            align-items: center;
            justify-content: center;
            color: #4B5563;
            font-size: 16px;
            font-weight: 600;
        }
        .standby.hidden { display: none; }

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        /* ===== LANDSCAPE: 3-column layout like LCD ===== */
        @media (orientation: landscape) {
            .scoreboard { flex-direction: column; }
            .header { padding: 4px 12px; }
            .header-poule { font-size: 14px; }
            .header-mat { font-size: 10px; }

            .match-content { display: flex; flex-direction: row; flex: 1; min-height: 0; }

            .judoka-card {
                flex: 1;
                padding: 6px 10px;
            }
            .judoka-naam { font-size: clamp(14px, 3vh, 28px); }
            .judoka-club { font-size: clamp(9px, 1.5vh, 14px); }

            .score-val { font-size: clamp(20px, 5vh, 40px); padding: 2px 8px; min-width: 36px; }
            .score-lbl { font-size: clamp(10px, 2vh, 16px); }
            .score-item { padding: 4px 6px; gap: 4px; }

            .timer-section {
                flex: 0.6;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 4px 8px;
            }
            .timer { font-size: clamp(28px, 10vh, 60px); }

            .osae-time { font-size: clamp(16px, 5vh, 32px); }
            .osae-dot { width: 12px; height: 12px; }
        }
    </style>
</head>
<body>
    <div class="scoreboard" id="scoreboard">
        {{-- Header --}}
        <div class="header">
            <div class="header-mat">Mat {{ $matNummer ?? '' }}</div>
            <div class="header-poule" id="header-poule"></div>
        </div>

        {{-- Standby message --}}
        <div class="standby" id="standby">
            Wacht op wedstrijd...
        </div>

        {{-- Match content (portrait: stacked, landscape: row) --}}
        <div class="match-content" id="match-content" style="display:none; flex-direction:column; flex:1; min-height:0;">
            {{-- Wit judoka --}}
            <div class="judoka-card judoka-wit">
                <div class="judoka-naam" id="wit-naam">WIT</div>
                <div class="judoka-club" id="wit-club"></div>
                <div class="scores-row">
                    <div class="score-item"><span class="score-lbl">Y</span><span class="score-val" id="wit-yuko">0</span></div>
                    <div class="score-item"><span class="score-lbl">W</span><span class="score-val" id="wit-wazaari">0</span></div>
                    <div class="score-item"><span class="score-lbl">I</span><span class="score-val" id="wit-ippon">0</span></div>
                </div>
                <div class="shido-row">
                    <div class="shido" id="wit-shido-1"></div>
                    <div class="shido" id="wit-shido-2"></div>
                    <div class="shido" id="wit-shido-3"></div>
                </div>
            </div>

            {{-- Timer --}}
            <div class="timer-section">
                <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
                <div class="timer" id="timer-display">{{ floor(($toernooi->getMatchDuration()) / 60) }}:00</div>
                <div class="gs-badge" id="gs-badge">GOLDEN SCORE</div>
                <div class="osaekomi-section">
                    <div class="osae-dot" id="wit-osaekomi-dot"></div>
                    <div>
                        <div class="osae-time" id="osaekomi-display">00</div>
                        <div class="osae-zone" id="osaekomi-zone"></div>
                    </div>
                    <div class="osae-dot" id="blauw-osaekomi-dot"></div>
                </div>
                <div class="osae-times">
                    <div class="osae-times-col" id="wit-osaekomi-times"></div>
                    <div class="osae-times-col" id="blauw-osaekomi-times"></div>
                </div>
            </div>

            {{-- Blauw judoka --}}
            <div class="judoka-card judoka-blauw">
                <div class="judoka-naam" id="blauw-naam">BLAUW</div>
                <div class="judoka-club" id="blauw-club"></div>
                <div class="scores-row">
                    <div class="score-item"><span class="score-lbl">Y</span><span class="score-val" id="blauw-yuko">0</span></div>
                    <div class="score-item"><span class="score-lbl">W</span><span class="score-val" id="blauw-wazaari">0</span></div>
                    <div class="score-item"><span class="score-lbl">I</span><span class="score-val" id="blauw-ippon">0</span></div>
                </div>
                <div class="shido-row">
                    <div class="shido" id="blauw-shido-1"></div>
                    <div class="shido" id="blauw-shido-2"></div>
                    <div class="shido" id="blauw-shido-3"></div>
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

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    @php
        $appUrl = config('app.url', 'https://localhost');
        $reverbHost = parse_url($appUrl, PHP_URL_HOST);
        $reverbPort = parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80;
        $reverbKey = config('broadcasting.connections.reverb.key');
        $reverbScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
    @endphp
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toernooiId = @js($toernooi->id);
        const matId = @js($matId);
        const initialMatch = @js($currentMatch ?? null);

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
            standby: document.getElementById('standby'),
            matchContent: document.getElementById('match-content'),
        };

        function showMatch() {
            els.standby.style.display = 'none';
            els.matchContent.style.display = 'flex';
        }

        function showStandby() {
            els.standby.style.display = 'flex';
            els.matchContent.style.display = 'none';
        }

        if (initialMatch) {
            showMatch();
            els.headerPoule.textContent = initialMatch.poule_naam || '';
            document.getElementById('wit-naam').textContent = initialMatch.judoka_wit?.naam || 'WIT';
            document.getElementById('wit-club').textContent = initialMatch.judoka_wit?.club || '';
            document.getElementById('blauw-naam').textContent = initialMatch.judoka_blauw?.naam || 'BLAUW';
            document.getElementById('blauw-club').textContent = initialMatch.judoka_blauw?.club || '';
            matchDuration = initialMatch.match_duration || matchDuration;
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
            els.timer.className = 'timer' + (isGoldenScore ? ' golden-score' : '') + (!isGoldenScore && timeRemaining <= 30 ? ' warning' : '');
            const pct = isGoldenScore ? 0 : (timeRemaining / matchDuration) * 100;
            els.progress.style.width = pct + '%';
            els.progress.className = 'progress-fill' + (!isGoldenScore && timeRemaining <= 30 ? ' warning' : '');
            els.gsBadge.className = 'gs-badge' + (isGoldenScore ? ' active' : '');
        }

        function tickTimer() {
            if (!isRunning) return;
            const elapsed = (performance.now() - timerStartedAt) / 1000;
            timeRemaining = isGoldenScore ? timerRemainingAtStart + elapsed : Math.max(0, timerRemainingAtStart - elapsed);
            updateTimerDisplay();
            timerAnimFrame = requestAnimationFrame(tickTimer);
        }

        function tickOsaekomi() {
            if (!osaekomiActive || !osaekomiStartedAt) return;
            const elapsed = Math.floor((performance.now() - osaekomiStartedAt) / 1000);
            els.osaekomi.textContent = elapsed.toString().padStart(2, '0');
            els.osaekomi.className = 'osae-time active';
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
                    document.getElementById(side + '-shido-' + i).className = 'shido' + (i <= s.shido ? ' active' : '');
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
                const unique = [...new Set(osaekomiTimes[side])];
                unique.forEach(time => {
                    const zone = getOsaekomiZone(time);
                    const el = document.createElement('div');
                    el.className = 'osae-time-entry';
                    el.textContent = time + 's' + (zone ? ' → ' + zone : '');
                    col.appendChild(el);
                });
            });
        }

        function clearOsaekomiState() {
            els.witDot.classList.remove('active');
            els.blauwDot.classList.remove('active');
            els.osaekomi.className = 'osae-time';
            els.osaekomi.textContent = '00';
            els.osaekomiZone.textContent = '';
        }

        // Reverb/Pusher
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
        channel.bind('scoreboard.event', (payload) => handleEvent(payload.data || payload));

        function handleEvent(data) {
            switch (data.event) {
                case 'match.start':
                    showMatch();
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
                    updateScores({ wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 }, blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 } });
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
                    if (data.osaekomi_times) { osaekomiTimes = data.osaekomi_times; renderOsaekomiTimes(); }
                    break;

                case 'osaekomi.start':
                    clearOsaekomiState();
                    osaekomiActive = true;
                    osaekomiJudoka = data.judoka;
                    osaekomiStartedAt = performance.now();
                    if (data.judoka === 'wit') els.witDot.classList.add('active');
                    if (data.judoka === 'blauw') els.blauwDot.classList.add('active');
                    if (data.osaekomi_times) { osaekomiTimes = data.osaekomi_times; renderOsaekomiTimes(); }
                    tickOsaekomi();
                    break;

                case 'osaekomi.stop':
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiState();
                    if (data.osaekomi_times) { osaekomiTimes = data.osaekomi_times; renderOsaekomiTimes(); }
                    break;

                case 'match.end':
                    isRunning = false;
                    if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                    osaekomiActive = false;
                    if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                    clearOsaekomiState();
                    const winnerSide = data.winner;
                    const nameEl = document.getElementById(winnerSide + '-naam');
                    els.winnerName.textContent = nameEl?.textContent || winnerSide.toUpperCase();
                    els.winnerName.className = 'winner-name ' + winnerSide;
                    els.winnerType.textContent = (data.uitslag_type || '').toUpperCase();
                    els.winnerOverlay.classList.add('active');
                    break;

                case 'match.assign':
                    showMatch();
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
                    updateScores({ wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 }, blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 } });
                    updateTimerDisplay();
                    break;

                case 'match.unassign':
                    showStandby();
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
                    updateScores({ wit: { yuko: 0, wazaari: 0, ippon: false, shido: 0 }, blauw: { yuko: 0, wazaari: 0, ippon: false, shido: 0 } });
                    updateTimerDisplay();
                    els.winnerOverlay.classList.remove('active');
                    break;
            }
        }

        updateTimerDisplay();
    });
    </script>
</body>
</html>
