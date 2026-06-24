<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scorebord - Mat {{ $matId }}</title>
    <style @nonce>
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
            position: relative;
            z-index: 2;
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
            margin-top: -12vh;
            position: relative;
            z-index: 1;
        }
        .side-col-wit { background: #F3F4F6; }
        .side-col-blauw { background: #1E3A8A; }

        .scores-section {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0.8vw;
            padding: 1vh 0.5vw;
        }
        .score-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 0 0.6vw;
            border-right: 1px solid rgba(156,163,175,0.3);
        }
        .score-box:last-child {
            border-right: none;
            padding-right: 0;
        }
        .score-label {
            font-size: clamp(12px, 2vh, 26px);
            font-weight: 800;
            text-align: center;
            letter-spacing: 1px;
        }
        .side-col-wit .score-label { color: #6B7280; }
        .side-col-blauw .score-label { color: #93C5FD; }
        .score-value {
            font-size: clamp(28px, 9vh, 110px);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            color: #EF4444;
            background: #111827;
            padding: 0.4vh 1vw;
            border-radius: 6px;
            min-width: clamp(36px, 6vw, 110px);
            text-align: center;
        }

        .shido-section {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: auto;
            margin-bottom: auto;
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
            background: rgba(0,0,0,0.95);
            z-index: 50;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .winner-overlay.active { display: flex; }

        .disconnect-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 100;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
        }
        .disconnect-overlay.active { display: flex; }
        .disconnect-overlay .dot {
            width: 20px; height: 20px;
            border-radius: 50%;
            background: #EF4444;
            box-shadow: 0 0 12px #EF4444;
            animation: pulse-red 1.5s ease-in-out infinite;
        }
        .disconnect-overlay .msg { color: #EF4444; font-size: clamp(18px,3vh,32px); font-weight: 800; }
        .disconnect-overlay .sub { color: #9CA3AF; font-size: clamp(12px,2vh,20px); }
        @keyframes pulse-red {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
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

        /* Spacer between names and timer */
        .name-timer-spacer { display: flex; flex-direction: row; height: 0.5vh; }

        /* Fullscreen overlay */
        .fullscreen-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100; cursor: pointer; align-items: center; justify-content: center; flex-direction: column; }
        .fullscreen-overlay-title { font-size: clamp(24px,5vh,48px); font-weight: 800; color: #fff; margin-bottom: 2vh; }
        .fullscreen-overlay-subtitle { font-size: clamp(16px,3vh,32px); color: #9CA3AF; }
        /* Awasete-ippon warning banner (2nd waza-ari) — referee alert */
        .awasete-warning {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 50;
            align-items: center;
            justify-content: center;
            gap: 2vw;
            padding: 1.2vh 2vw;
            background: #DC2626;
            color: #fff;
            font-weight: 900;
            font-size: clamp(20px, 4.6vh, 64px);
            letter-spacing: 0.04em;
            box-shadow: 0 4px 24px rgba(0,0,0,0.55);
            animation: awasete-blink 0.6s steps(1) infinite;
        }
        .awasete-warning.active { display: flex; }
        .awasete-warning .awasete-time { font-variant-numeric: tabular-nums; min-width: 2ch; text-align: center; }
        @keyframes awasete-blink {
            0%, 100% { background: #DC2626; opacity: 1; }
            50% { background: #7F1D1D; opacity: 0.82; }
        }
        /* IPPON indicator — osaekomi reached an ippon-deciding point (referee signs) */
        .ippon-banner {
            display: none;
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 60;
            flex-direction: column;
            align-items: center;
            padding: 3vh 7vw;
            background: #DC2626;
            color: #fff;
            border: 0.6vh solid #fff;
            border-radius: 2vh;
            box-shadow: 0 8px 40px rgba(0,0,0,0.6);
            animation: awasete-blink 0.6s steps(1) infinite;
        }
        .ippon-banner.active { display: flex; }
        .ippon-banner .ippon-word { font-weight: 900; font-size: clamp(48px, 16vh, 220px); letter-spacing: 0.06em; line-height: 1; }
        .ippon-banner .ippon-name { font-weight: 700; font-size: clamp(18px, 3.4vh, 48px); margin-top: 1vh; }
        /* Per-device sound settings (localStorage) */
        .snd-gear {
            position: fixed; bottom: 10px; right: 10px; z-index: 60;
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,0.12); color: #fff;
            border: none; cursor: pointer; font-size: 18px; opacity: 0.5;
        }
        .snd-gear:hover { opacity: 1; }
        .snd-panel {
            display: none; position: fixed; bottom: 52px; right: 10px; z-index: 60;
            background: #1F2937; color: #fff; border: 1px solid #374151;
            border-radius: 10px; padding: 14px 16px; width: 240px;
            font-size: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.6);
        }
        .snd-panel.open { display: block; }
        .snd-panel h4 { margin: 0 0 10px; font-size: 14px; }
        .snd-row { display: flex; align-items: center; justify-content: space-between; margin: 8px 0; gap: 10px; }
        .snd-row label { flex: 1; }
        .snd-panel select, .snd-panel input[type=range] { flex: 1; min-width: 0; }
        .snd-test { width: 100%; margin-top: 10px; padding: 7px; border: none; border-radius: 6px; background: #DC2626; color: #fff; font-weight: 700; cursor: pointer; }
        .snd-hint { font-size: 11px; color: #9CA3AF; margin-top: 8px; line-height: 1.3; }
        .env-badge {
            position: fixed; top: 0; left: 0; z-index: 200;
            background: #DC2626; color: #fff; font-weight: 800;
            font-size: clamp(12px, 1.6vh, 18px); letter-spacing: 0.1em;
            padding: 4px 14px; border-bottom-right-radius: 8px;
            pointer-events: none; font-family: system-ui, sans-serif;
        }
    </style>
</head>
<body>
    @unless(app()->isProduction())
        <div class="env-badge">{{ strtoupper(app()->environment()) }}</div>
    @endunless
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
            <div class="name-timer-spacer">
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

        {{-- Awasete-ippon warning banner (2nd waza-ari) — alerts the referee --}}
        <div class="awasete-warning" id="awasete-warning">
            <span>⚠ 2e WAZA-ARI — IPPON?</span>
            <span class="awasete-time" id="awasete-time">00</span>
            <span>⚠</span>
        </div>

        {{-- IPPON indicator — osaekomi reached an ippon-deciding point (app never auto-scores) --}}
        <div class="ippon-banner" id="ippon-banner">
            <span class="ippon-word">IPPON</span>
            <span class="ippon-name" id="ippon-name"></span>
        </div>

        {{-- Per-device sound settings for the awasete warning --}}
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

        {{-- Disconnect overlay — shown when WebSocket drops --}}
        <div class="disconnect-overlay" id="disconnect-overlay">
            <div class="dot"></div>
            <div class="msg">{{ __('GEEN VERBINDING') }}</div>
            <div class="sub" id="disconnect-countdown"></div>
        </div>
    </div>

    <script @nonce>
    function goFullscreen() {
        var el = document.documentElement;
        var rfs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (rfs) {
            rfs.call(el).then(function() {
                document.getElementById('fullscreen-overlay').style.display = 'none';
            }).catch(function() {
                // Fullscreen not supported — hide overlay anyway
                document.getElementById('fullscreen-overlay').style.display = 'none';
            });
        } else {
            document.getElementById('fullscreen-overlay').style.display = 'none';
        }
    }
    // Show overlay only if not already fullscreen
    if (!document.fullscreenElement && !window.navigator.standalone) {
        var overlay = document.getElementById('fullscreen-overlay');
        if (overlay) overlay.style.display = 'flex';
    }
    </script>
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
        // Autoplay policy: unlock the AudioContext on the first user gesture anywhere.
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

        // Wire the settings panel (elements are above this script in the DOM).
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
    @include('pages.mat.partials._scoreboard-engine')
</body>
</html>
