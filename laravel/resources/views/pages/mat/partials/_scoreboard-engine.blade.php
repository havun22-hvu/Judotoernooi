{{--
    Shared scoreboard engine — Pusher/Reverb live relay + handleEvent state machine.

    Used by BOTH the landscape LCD view (scoreboard-live) and the public mobile
    view (scoreboard-mobile). It operates purely on element IDs, so any host view
    that renders the same ID set (header-poule, wit/blauw-naam/club, timer-display,
    progress-fill, gs-badge, *-yuko/wazaari/ippon, *-shido-1..3, osaekomi-display,
    *-osaekomi-dot, osaekomi-zone, *-osaekomi-times, awasete-warning/awasete-time,
    ippon-banner/ippon-name, winner-overlay/winner-name/winner-type,
    disconnect-overlay/disconnect-countdown) gets identical live behaviour.

    Inherits these blade vars from the include scope:
      $toernooi, $matId, $currentMatch (optional)
    Reverb connection params are derived locally below from config('app.url').

    Plain vanilla JS, no Alpine. Every <script> carries @nonce for the strict CSP.
--}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js" integrity="sha384-gA0TPBlnosOv77mNKhqDqUd7BMOqU7f5VlaEGFdyCus4A5l7JHELZ4K5dQMBSL1j" crossorigin="anonymous" @nonce></script>
@php
    // Use config() not env() — env() returns null after config:cache
    $appUrl = config('app.url', 'https://localhost');
    $reverbHost = parse_url($appUrl, PHP_URL_HOST);
    $reverbPort = parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80;
    $reverbKey = config('broadcasting.connections.reverb.key');
    $reverbScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
@endphp
<script @nonce>
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
        awaseteWarning: document.getElementById('awasete-warning'),
        awaseteTime: document.getElementById('awasete-time'),
        ipponBanner: document.getElementById('ippon-banner'),
        ipponName: document.getElementById('ippon-name'),
    };

    // Compact round label: elimination rounds → "1/16 finale" etc. (saves space).
    // Mirrors the JudoScoreBoard app util. Separator may be space or underscore.
    function formatRonde(ronde) {
        if (!ronde) return '';
        var r = String(ronde).trim();
        var map = [
            [/\bzestiende[\s_]*finale\b/i, '1/16 finale'],
            [/\bachtste[\s_]*finale\b/i, '1/8 finale'],
            [/\bkwart[\s_]*finale\b/i, '1/4 finale'],
            [/\bhalve[\s_]*finale\b/i, '1/2 finale'],
        ];
        for (var i = 0; i < map.length; i++) {
            if (map[i][0].test(r)) return map[i][1];
        }
        return r.replace(/_/g, ' ');
    }

    function showAwaseteWarning() {
        els.awaseteWarning.classList.add('active');
        if (window.awaseteAudio) window.awaseteAudio.play();
    }
    function hideAwaseteWarning() {
        els.awaseteWarning.classList.remove('active');
    }
    function showIppon(side) {
        var nameEl = side === 'blauw'
            ? document.getElementById('blauw-naam')
            : document.getElementById('wit-naam');
        els.ipponName.textContent = nameEl ? nameEl.textContent : (side ? side.toUpperCase() : '');
        els.ipponBanner.classList.add('active');
        if (window.awaseteAudio) window.awaseteAudio.play();
    }
    function hideIppon() {
        els.ipponBanner.classList.remove('active');
    }

    // Load initial match data
    if (initialMatch) {
        els.headerPoule.textContent = [initialMatch.poule_naam, formatRonde(initialMatch.ronde)].filter(Boolean).join(' · ');
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

        if (els.awaseteWarning.classList.contains('active')) {
            els.awaseteTime.textContent = elapsed.toString().padStart(2, '0');
        }

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
            const unique = [...new Set(osaekomiTimes[side])];
            unique.forEach(time => {
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
        hideAwaseteWarning();
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

    const disconnectOverlay = document.getElementById('disconnect-overlay');
    const disconnectCountdown = document.getElementById('disconnect-countdown');
    let disconnectTimer = null;
    let reloadCountdown = null;
    const RELOAD_AFTER_MS = 60000;

    function showDisconnect() {
        disconnectOverlay.classList.add('active');
        let remaining = Math.round(RELOAD_AFTER_MS / 1000);
        disconnectCountdown.textContent = `Herladen in ${remaining}s`;
        reloadCountdown = setInterval(() => {
            remaining--;
            disconnectCountdown.textContent = `Herladen in ${remaining}s`;
            if (remaining <= 0) {
                clearInterval(reloadCountdown);
                window.location.reload();
            }
        }, 1000);
    }

    function hideDisconnect() {
        disconnectOverlay.classList.remove('active');
        clearTimeout(disconnectTimer);
        clearInterval(reloadCountdown);
        disconnectCountdown.textContent = '';
    }

    pusher.connection.bind('connected', () => {
        clearTimeout(disconnectTimer);
        hideDisconnect();
    });

    pusher.connection.bind('disconnected', () => {
        disconnectTimer = setTimeout(showDisconnect, 5000);
    });

    pusher.connection.bind('unavailable', () => {
        disconnectTimer = setTimeout(showDisconnect, 5000);
    });

    pusher.connection.bind('failed', () => {
        showDisconnect();
    });

    pusher.connection.bind('error', (err) => {
        console.error('[scoreboard] Reverb fout:', err);
    });

    function loadMatch(data, removeWinnerOverlay) {
        if (removeWinnerOverlay) els.winnerOverlay.classList.remove('active');
        els.headerPoule.textContent = [data.poule_naam, formatRonde(data.ronde)].filter(Boolean).join(' · ');

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
    }

    function handleEvent(data) {
        switch (data.event) {
            case 'match.start':
                loadMatch(data, true);
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
                hideIppon();
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
                hideIppon();
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

            case 'osaekomi.warning':
                // 2nd waza-ari during a hold — alert the referee (banner + single sound).
                if (data.active) {
                    els.awaseteTime.textContent = els.osaekomi.textContent;
                    showAwaseteWarning();
                } else {
                    hideAwaseteWarning();
                }
                break;

            case 'osaekomi.ippon':
                // Osaekomi reached an ippon-deciding point — show a clear IPPON for the
                // referee. Persistent: NOT cleared by osaekomi.stop (I-threshold stops the
                // hold). The app sends active:false on award / release / reset.
                if (data.active) {
                    showIppon(data.judoka);
                } else {
                    hideIppon();
                }
                break;

            case 'match.end':
                isRunning = false;
                if (timerAnimFrame) cancelAnimationFrame(timerAnimFrame);
                osaekomiActive = false;
                if (osaekomiAnimFrame) cancelAnimationFrame(osaekomiAnimFrame);
                clearOsaekomiState();
                hideAwaseteWarning();
                hideIppon();

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
                loadMatch(data, false);
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
