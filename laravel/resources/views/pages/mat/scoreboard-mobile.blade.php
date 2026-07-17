<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#111827">
    <title>Scorebord - Mat {{ $matNummer ?? $matId }}</title>
    <style @nonce>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            overflow: hidden;
            background: #111827;
            font-family: 'Arial Black', 'Helvetica Neue', sans-serif;
            -webkit-user-select: none; user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* ============================================================
           PORTRAIT-FIRST (default): wit boven / timer+osaekomi midden / blauw onder
           ============================================================ */
        .scoreboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
        }

        /* Header — poule info */
        .header-row {
            background: #111827;
            padding: max(1.4vh, env(safe-area-inset-top)) 12px 1vh;
            text-align: center;
            flex-shrink: 0;
        }
        .header-mat { color: #6B7280; font-size: clamp(11px, 1.8vh, 16px); font-weight: 600; }
        .header-poule { color: #FFFFFF; font-size: clamp(15px, 2.6vh, 26px); font-weight: 800; min-height: 1.2em; }

        /* Player blocks */
        .player {
            flex: 1;
            flex-basis: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 1.2vh 12px;
            position: relative;
        }
        .player-wit { background: #F3F4F6; }
        .player-blauw { background: #1E3A8A; }

        .player-head { text-align: center; width: 100%; }
        .naam {
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: clamp(22px, 5.5vh, 56px);
            line-height: 1.05;
        }
        .player-wit .naam { color: #1F2937; }
        .player-blauw .naam { color: #FFFFFF; }
        .club { font-size: clamp(12px, 2.4vh, 22px); font-weight: 500; }
        .player-wit .club { color: #6B7280; }
        .player-blauw .club { color: #93C5FD; }

        .player-body {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 3vw, 28px);
            width: 100%;
            margin-top: 1vh;
        }

        .scores-section { display: flex; flex-direction: row; align-items: center; gap: clamp(6px, 2vw, 18px); }
        .score-box { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .score-label { font-size: clamp(11px, 1.8vh, 18px); font-weight: 800; letter-spacing: 1px; }
        .player-wit .score-label { color: #6B7280; }
        .player-blauw .score-label { color: #93C5FD; }
        .score-value {
            font-size: clamp(30px, 7vh, 64px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            color: #EF4444;
            background: #111827;
            padding: 0.4vh 2.6vw;
            border-radius: 8px;
            min-width: clamp(42px, 11vw, 70px);
            text-align: center;
        }

        .shido-section { display: flex; align-items: center; gap: clamp(5px, 1.4vw, 12px); }
        .shido-card {
            width: clamp(18px, 5vw, 34px);
            height: clamp(26px, 6vh, 46px);
            border-radius: 4px;
            border: 2px dashed rgba(156,163,175,0.5);
            background: rgba(156,163,175,0.2);
        }
        .shido-card.active { background: #FACC15; border: 2px solid #EAB308; }

        /* Osaekomi dot per player (small, in player block) */
        .player-dot {
            width: clamp(14px, 3vh, 22px);
            height: clamp(14px, 3vh, 22px);
            border-radius: 50%;
            background: #374151;
            flex-shrink: 0;
        }
        .player-dot.active { background: #22C55E; box-shadow: 0 0 10px #22C55E; }

        /* Center bar — timer + osaekomi */
        .center-bar {
            background: #111827;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.4vh 12px;
            flex-shrink: 0;
            border-top: 2px solid rgba(255,255,255,0.06);
            border-bottom: 2px solid rgba(255,255,255,0.06);
        }
        .progress-bar {
            width: 70%;
            max-width: 480px;
            height: 5px;
            background: #374151;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .progress-fill { height: 100%; background: #22C55E; border-radius: 3px; transition: width 0.1s linear; }
        .progress-fill.warning { background: #EF4444; }
        .timer {
            font-size: clamp(56px, 13vh, 130px);
            font-weight: 900;
            color: #EF4444;
            font-family: 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .timer.warning { color: #EF4444; }
        .timer.golden-score { color: #EAB308; }
        .golden-score-badge {
            display: none;
            background: #EAB308; color: #000; font-weight: 900;
            font-size: clamp(12px, 2vh, 20px);
            padding: 0.4vh 3vw; border-radius: 20px; margin-top: 4px;
            animation: pulse 1.5s infinite;
        }
        .golden-score-badge.active { display: inline-block; }

        .osaekomi-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 3vw, 20px);
            margin-top: 0.6vh;
            min-height: 1.2em;
        }
        .osaekomi-dot {
            width: clamp(14px, 3vh, 24px);
            height: clamp(14px, 3vh, 24px);
            border-radius: 50%;
            background: #374151;
        }
        .osaekomi-dot.active { background: #22C55E; }
        .osaekomi-label { color: #9CA3AF; font-size: clamp(11px, 1.8vh, 16px); font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        .osaekomi-time {
            font-size: clamp(26px, 6vh, 56px);
            font-weight: 900;
            font-family: 'Courier New', monospace;
            color: #EF4444;
            font-variant-numeric: tabular-nums;
            min-width: 2ch;
            text-align: center;
        }
        .osaekomi-zone { font-size: clamp(12px, 2vh, 20px); font-weight: 800; color: #F97316; min-height: 1em; }
        .osaekomi-times-section { display: flex; flex-direction: row; gap: 5vw; margin-top: 0.4vh; }
        .osaekomi-times-col { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .osaekomi-time-entry { font-size: clamp(12px, 1.8vh, 18px); font-weight: 800; color: #EF4444; animation: blink 1s infinite; }

        /* ============================================================
           LANDSCAPE: rij-layout (wit links/rechts o.b.v. blauwRechts, timer midden)
           ============================================================ */
        @media (orientation: landscape) {
            .scoreboard { flex-direction: column; }
            .header-row { padding: 0.8vh 12px 0.4vh; }
            .header-poule { font-size: clamp(14px, 3vh, 28px); }
            .lscape-body {
                display: flex;
                flex-direction: row;
                flex: 1;
                min-height: 0;
            }
            .player {
                flex: 1;
                padding: 1vh 1.5vw;
            }
            .player .naam { font-size: clamp(20px, 7vh, 60px); }
            .player-body { flex-direction: column; gap: 1.5vh; }
            .center-bar {
                flex: 0.85;
                border-top: none; border-bottom: none;
                border-left: 2px solid rgba(255,255,255,0.06);
                border-right: 2px solid rgba(255,255,255,0.06);
            }
            .timer { font-size: clamp(60px, 20vh, 180px); }
            .osaekomi-time { font-size: clamp(30px, 9vh, 80px); }
        }
        /* Portrait: the .lscape-body wrapper is transparent (display: contents) so
           player/center stack vertically. Landscape: it becomes the flex row. */
        @media (orientation: portrait) {
            .lscape-body { display: contents; }
        }

        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.8; transform: scale(1.05); } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        @keyframes pulse-red { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        @keyframes awasete-blink { 0%, 100% { background: #DC2626; opacity: 1; } 50% { background: #7F1D1D; opacity: 0.82; } }

        /* Overlays — identical IDs/behaviour to the LCD view */
        .winner-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.95);
            z-index: 50; align-items: center; justify-content: center; flex-direction: column;
        }
        .winner-overlay.active { display: flex; }
        .winner-name { font-size: clamp(40px, 12vw, 90px); font-weight: 900; text-transform: uppercase; text-align: center; padding: 0 16px; }
        .winner-name.blauw { color: #3B82F6; }
        .winner-name.wit { color: #FFF; }
        .winner-title { font-size: clamp(24px, 7vw, 56px); color: #FACC15; font-weight: 900; margin-top: 8px; }
        .winner-type { font-size: clamp(14px, 4vw, 28px); color: #9CA3AF; font-weight: 600; margin-top: 4px; }

        .disconnect-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            z-index: 100; align-items: center; justify-content: center; flex-direction: column; gap: 16px;
        }
        .disconnect-overlay.active { display: flex; }
        .disconnect-overlay .dot { width: 20px; height: 20px; border-radius: 50%; background: #EF4444; box-shadow: 0 0 12px #EF4444; animation: pulse-red 1.5s ease-in-out infinite; }
        .disconnect-overlay .msg { color: #EF4444; font-size: clamp(18px,5vw,32px); font-weight: 800; }
        .disconnect-overlay .sub { color: #9CA3AF; font-size: clamp(12px,3.5vw,20px); }

        .awasete-warning {
            display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            align-items: center; justify-content: center; gap: 3vw;
            padding: 1.4vh 3vw; background: #DC2626; color: #fff; font-weight: 900;
            font-size: clamp(16px, 4.6vw, 40px); letter-spacing: 0.04em;
            box-shadow: 0 4px 24px rgba(0,0,0,0.55); animation: awasete-blink 0.6s steps(1) infinite;
        }
        .awasete-warning.active { display: flex; }
        .awasete-warning .awasete-time { font-variant-numeric: tabular-nums; min-width: 2ch; text-align: center; }

        .ippon-banner {
            display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 60; flex-direction: column; align-items: center; padding: 4vh 10vw;
            background: #DC2626; color: #fff; border: 0.6vh solid #fff; border-radius: 2vh;
            box-shadow: 0 8px 40px rgba(0,0,0,0.6); animation: awasete-blink 0.6s steps(1) infinite;
        }
        .ippon-banner.active { display: flex; }
        .ippon-banner .ippon-word { font-weight: 900; font-size: clamp(48px, 18vw, 160px); letter-spacing: 0.06em; line-height: 1; }
        .ippon-banner .ippon-name { font-weight: 700; font-size: clamp(16px, 4vw, 40px); margin-top: 1vh; text-align: center; }

        /* Per-device sound settings (localStorage) */
        .snd-gear { position: fixed; bottom: 10px; right: 10px; z-index: 60; width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.12); color: #fff; border: none; cursor: pointer; font-size: 18px; opacity: 0.5; }
        .snd-gear:hover { opacity: 1; }
        .snd-panel { display: none; position: fixed; bottom: 56px; right: 10px; z-index: 60; background: #1F2937; color: #fff; border: 1px solid #374151; border-radius: 10px; padding: 14px 16px; width: 240px; font-size: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
        .snd-panel.open { display: block; }
        .snd-panel h4 { margin: 0 0 10px; font-size: 14px; }
        .snd-row { display: flex; align-items: center; justify-content: space-between; margin: 8px 0; gap: 10px; }
        .snd-row label { flex: 1; }
        .snd-panel select, .snd-panel input[type=range] { flex: 1; min-width: 0; }
        .snd-test { width: 100%; margin-top: 10px; padding: 7px; border: none; border-radius: 6px; background: #DC2626; color: #fff; font-weight: 700; cursor: pointer; }
        .snd-hint { font-size: 11px; color: #9CA3AF; margin-top: 8px; line-height: 1.3; }

        .env-badge { position: fixed; top: 0; left: 0; z-index: 200; background: #DC2626; color: #fff; font-weight: 800; font-size: clamp(11px, 1.6vh, 16px); letter-spacing: 0.1em; padding: 4px 14px; border-bottom-right-radius: 8px; pointer-events: none; font-family: system-ui, sans-serif; }
    </style>
</head>
<body>
    @unless(app()->isProduction())
        <div class="env-badge">{{ strtoupper(app()->environment()) }}</div>
    @endunless

    @php
        $leftSide = $blauwRechts ? 'blauw' : 'wit';
        $rightSide = $blauwRechts ? 'wit' : 'blauw';
    @endphp

    <div id="app">
        <div class="scoreboard" id="scoreboard">
            {{-- Header — poule info --}}
            <div class="header-row">
                <div class="header-mat">Mat {{ $matNummer ?? '' }}</div>
                <div class="header-poule" id="header-poule"></div>
            </div>

            <div class="lscape-body">
                {{-- Left / top player --}}
                <div class="player player-{{ $leftSide }}">
                    <div class="player-head">
                        <div class="naam" id="{{ $leftSide }}-naam">{{ strtoupper($leftSide) }}</div>
                        <div class="club" id="{{ $leftSide }}-club"></div>
                    </div>
                    <div class="player-body">
                        <div class="player-dot" id="{{ $leftSide }}-osaekomi-dot"></div>
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
                </div>

                {{-- Center — timer + osaekomi --}}
                <div class="center-bar">
                    <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
                    <div class="timer" id="timer-display">{{ $initieleWedstrijdtijd }}</div>
                    <div class="golden-score-badge" id="gs-badge">GOLDEN SCORE</div>
                    <div class="osaekomi-row">
                        <span class="osaekomi-label">Osaekomi</span>
                        <span class="osaekomi-time" id="osaekomi-display">00</span>
                        <span class="osaekomi-zone" id="osaekomi-zone"></span>
                    </div>
                    <div class="osaekomi-times-section">
                        <div class="osaekomi-times-col" id="{{ $leftSide }}-osaekomi-times"></div>
                        <div class="osaekomi-times-col" id="{{ $rightSide }}-osaekomi-times"></div>
                    </div>
                </div>

                {{-- Right / bottom player --}}
                <div class="player player-{{ $rightSide }}">
                    <div class="player-head">
                        <div class="naam" id="{{ $rightSide }}-naam">{{ strtoupper($rightSide) }}</div>
                        <div class="club" id="{{ $rightSide }}-club"></div>
                    </div>
                    <div class="player-body">
                        <div class="player-dot" id="{{ $rightSide }}-osaekomi-dot"></div>
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
        </div>

        {{-- Winner overlay --}}
        <div class="winner-overlay" id="winner-overlay">
            <div class="winner-name" id="winner-name"></div>
            <div class="winner-title">WINNAAR</div>
            <div class="winner-type" id="winner-type"></div>
        </div>

        {{-- Awasete-ippon warning banner (2nd waza-ari) --}}
        <div class="awasete-warning" id="awasete-warning">
            <span>⚠ 2e WAZA-ARI — IPPON?</span>
            <span class="awasete-time" id="awasete-time">00</span>
            <span>⚠</span>
        </div>

        {{-- IPPON indicator --}}
        <div class="ippon-banner" id="ippon-banner">
            <span class="ippon-word">IPPON</span>
            <span class="ippon-name" id="ippon-name"></span>
        </div>

        {{-- Per-device sound settings --}}
        <button class="snd-gear" id="snd-gear" title="{{ __('Geluid') }}" data-action="toggle-snd-panel">🔊</button>
        <div class="snd-panel" id="snd-panel">
            <h4>{{ __('Waarschuwingsgeluid') }}</h4>
            <div class="snd-row"><label for="snd-enabled">{{ __('Geluid aan') }}</label><input type="checkbox" id="snd-enabled"></div>
            <div class="snd-row"><label for="snd-volume">{{ __('Volume') }}</label><input type="range" id="snd-volume" min="0" max="100"></div>
            <div class="snd-row"><label for="snd-type">{{ __('Signaal') }}</label>
                <select id="snd-type">
                    <option value="piep">{{ __('Piep') }}</option>
                    <option value="gong">{{ __('Gong') }}</option>
                    <option value="sirene">{{ __('Sirene') }}</option>
                </select>
            </div>
            <button class="snd-test" data-action="test-awasete">{{ __('Test geluid') }}</button>
            <div class="snd-hint">{{ __('Klik één keer op het scherm om het geluid te activeren.') }}</div>
        </div>

        {{-- Disconnect overlay --}}
        <div class="disconnect-overlay" id="disconnect-overlay">
            <div class="dot"></div>
            <div class="msg">{{ __('GEEN VERBINDING') }}</div>
            <div class="sub" id="disconnect-countdown"></div>
        </div>
    </div>

    <script @nonce>
    // Awasete-ippon warning sound — WebAudio (no assets), per-device settings in localStorage.
    (function() {
        var audioCtx = null;
        var LS = { enabled: 'awasete_sound_enabled', volume: 'awasete_sound_volume', type: 'awasete_sound_type' };
        var settings = {
            enabled: localStorage.getItem(LS.enabled) !== '0', // default on
            volume: parseInt(localStorage.getItem(LS.volume) || '70', 10),
            type: localStorage.getItem(LS.type) || 'piep',
        };

        function ensureCtx() {
            if (!audioCtx) {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) audioCtx = new AC();
            }
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            return audioCtx;
        }
        document.addEventListener('click', ensureCtx);

        function tone(freq, start, dur, type, vol) {
            var t = audioCtx.currentTime + start;
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, t);
            gain.gain.setValueAtTime(vol, t);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.connect(gain); gain.connect(audioCtx.destination);
            osc.start(t); osc.stop(t + dur);
        }
        function sweep(f1, f2, start, dur, vol) {
            var t = audioCtx.currentTime + start;
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(f1, t);
            osc.frequency.linearRampToValueAtTime(f2, t + dur);
            gain.gain.setValueAtTime(vol, t);
            osc.connect(gain); gain.connect(audioCtx.destination);
            osc.start(t); osc.stop(t + dur);
        }
        function play() {
            if (!settings.enabled) return;
            var ctx = ensureCtx();
            if (!ctx) return;
            var v = Math.max(0, Math.min(1, settings.volume / 100));
            if (v <= 0) return;
            if (settings.type === 'gong') {
                tone(180, 0, 1.2, 'sine', v);
            } else if (settings.type === 'sirene') {
                sweep(520, 1100, 0, 0.4, v * 0.5);
                sweep(1100, 520, 0.4, 0.4, v * 0.5);
            } else { // piep
                tone(1100, 0, 0.18, 'square', v * 0.6);
                tone(1100, 0.25, 0.18, 'square', v * 0.6);
            }
        }
        window.awaseteAudio = { play: play, ensureCtx: ensureCtx };

        var cb = document.getElementById('snd-enabled');
        var vol = document.getElementById('snd-volume');
        var typ = document.getElementById('snd-type');
        if (cb) { cb.checked = settings.enabled; cb.addEventListener('change', function() { settings.enabled = cb.checked; localStorage.setItem(LS.enabled, cb.checked ? '1' : '0'); }); }
        if (vol) { vol.value = settings.volume; vol.addEventListener('input', function() { settings.volume = parseInt(vol.value, 10); localStorage.setItem(LS.volume, vol.value); }); }
        if (typ) { typ.value = settings.type; typ.addEventListener('change', function() { settings.type = typ.value; localStorage.setItem(LS.type, typ.value); }); }

        window.toggleSndPanel = function() { ensureCtx(); var p = document.getElementById('snd-panel'); if (p) p.classList.toggle('open'); };
        window.testAwaseteSound = function() { ensureCtx(); play(); };
    })();
    </script>

    {{-- Wire data-action buttons (CSP: no inline on* handlers) --}}
    <script @nonce>
    document.addEventListener('click', function(e) {
        var t = e.target.closest('[data-action]');
        if (!t) return;
        var a = t.getAttribute('data-action');
        if (a === 'toggle-snd-panel' && window.toggleSndPanel) window.toggleSndPanel();
        if (a === 'test-awasete' && window.testAwaseteSound) window.testAwaseteSound();
    });
    </script>

    {{-- Shared live engine (Pusher/Reverb + handleEvent) — same as the LCD view --}}
    @include('pages.mat.partials._scoreboard-engine')
</body>
</html>
