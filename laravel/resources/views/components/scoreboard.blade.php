{{--
    Judo Scoreboard Component

    Usage:
    - Standalone: <x-scoreboard />
    - With wedstrijd: <x-scoreboard :wedstrijd="$wedstrijd" />
    - TV Display: <x-scoreboard display-mode />
    - Compact: <x-scoreboard compact />

    Props:
    - wedstrijd: Wedstrijd model (optional)
    - displayMode: boolean - fullscreen TV display
    - compact: boolean - smaller version for mat interface
--}}

@props([
    'wedstrijd' => null,
    'displayMode' => false,
    'compact' => false,
    'matchDuration' => 240, // 4 minutes default (in seconds)
])

@php
    $witNaam = $wedstrijd?->judokaWit?->naam ?? 'WIT';
    $blauwNaam = $wedstrijd?->judokaBlauw?->naam ?? 'BLAUW';
    $witClub = $wedstrijd?->judokaWit?->club?->naam ?? '';
    $blauwClub = $wedstrijd?->judokaBlauw?->club?->naam ?? '';
@endphp

<div
    x-data="scoreboard({
        witNaam: @js($witNaam),
        blauwNaam: @js($blauwNaam),
        witClub: @js($witClub),
        blauwClub: @js($blauwClub),
        wedstrijdId: @js($wedstrijd?->id),
        matchDuration: {{ $matchDuration }},
        displayMode: {{ $displayMode ? 'true' : 'false' }},
        compact: {{ $compact ? 'true' : 'false' }}
    })"
    x-init="init()"
    :class="{
        'fixed inset-0 z-50 bg-black': displayMode,
        'w-full': !displayMode
    }"
    class="scoreboard select-none"
    @keydown.space.prevent="toggleTimer()"
    @keydown.escape="if(displayMode) exitFullscreen()"
    @keydown.r="resetTimer()"
    tabindex="0"
>
    {{-- Toolbar (hidden in display mode when timer running) --}}
    <div
        x-show="!displayMode || !isRunning"
        x-transition
        class="bg-gray-800 text-white px-4 py-2 flex items-center justify-between"
        :class="{ 'absolute top-0 left-0 right-0 z-10': displayMode }"
    >
        <div class="flex items-center gap-4">
            <span class="font-bold">🥋 Scorebord</span>
            <template x-if="wedstrijdId">
                <span class="text-gray-400 text-sm">Wedstrijd #<span x-text="wedstrijdId"></span></span>
            </template>
        </div>
        <div class="flex items-center gap-2">
            {{-- Time settings --}}
            <select
                x-model="matchDuration"
                @change="resetTimer()"
                class="bg-gray-700 text-white rounded px-2 py-1 text-sm"
                :disabled="isRunning"
            >
                <option value="120">2:00</option>
                <option value="180">3:00</option>
                <option value="240">4:00</option>
                <option value="300">5:00</option>
            </select>

            {{-- Fullscreen toggle --}}
            <button
                @click="toggleFullscreen()"
                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded text-sm"
                title="Volledig scherm (F11)"
            >
                <span x-show="!displayMode">⛶ Volledig scherm</span>
                <span x-show="displayMode">✕ Sluiten</span>
            </button>
        </div>
    </div>

    {{-- Main scoreboard --}}
    <div
        class="grid grid-cols-2 gap-1 p-2"
        :class="{
            'h-screen pt-14': displayMode,
            'min-h-[400px]': !compact && !displayMode,
            'min-h-[250px]': compact
        }"
        :style="displayMode ? 'height: calc(100vh - 56px)' : ''"
    >
        {{-- BLAUW (LEFT - IJF standard) --}}
        <div
            class="bg-blue-600 rounded-lg flex flex-col relative overflow-hidden"
            @click="if(!isRunning) selectJudoka('blauw')"
        >
            {{-- Naam --}}
            <div class="text-white text-center py-2 px-4 bg-blue-700">
                <div
                    class="font-bold uppercase truncate"
                    :class="displayMode ? 'text-4xl' : (compact ? 'text-lg' : 'text-2xl')"
                    x-text="blauwNaam"
                ></div>
                <div
                    x-show="blauwClub"
                    class="text-blue-200 truncate"
                    :class="displayMode ? 'text-xl' : 'text-sm'"
                    x-text="blauwClub"
                ></div>
            </div>

            {{-- Score --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    {{-- Ippon/Waza-ari score --}}
                    <div
                        class="font-mono font-black text-white cursor-pointer hover:opacity-80 transition-opacity"
                        :class="displayMode ? 'text-[200px] leading-none' : (compact ? 'text-6xl' : 'text-9xl')"
                        @click.stop="cycleScore('blauw')"
                        x-text="scores.blauw.display"
                    ></div>

                    {{-- Waza-ari indicator --}}
                    <div
                        class="flex justify-center gap-2 mt-2"
                        :class="displayMode ? 'gap-4 mt-4' : ''"
                    >
                        <template x-for="i in scores.blauw.wazaari" :key="'bw-' + i">
                            <div
                                class="bg-white rounded"
                                :class="displayMode ? 'w-8 h-8' : 'w-4 h-4'"
                            ></div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Shido's (yellow cards) --}}
            <div
                class="absolute bottom-4 left-4 flex gap-2"
                :class="displayMode ? 'gap-3' : ''"
            >
                <template x-for="i in 3" :key="'bs-' + i">
                    <div
                        class="rounded cursor-pointer transition-all"
                        :class="[
                            displayMode ? 'w-12 h-16' : (compact ? 'w-6 h-8' : 'w-8 h-12'),
                            i <= scores.blauw.shido ? 'bg-yellow-400 shadow-lg' : 'bg-gray-400/30 border-2 border-dashed border-gray-400'
                        ]"
                        @click.stop="toggleShido('blauw', i)"
                        :title="'Shido ' + i"
                    ></div>
                </template>
            </div>

            {{-- Osaekomi indicator --}}
            <div
                x-show="osaekomi.active && osaekomi.judoka === 'blauw'"
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold animate-pulse"
                :class="displayMode ? 'w-32 h-32 text-5xl' : 'w-20 h-20 text-3xl'"
            >
                <span x-text="osaekomi.time"></span>
            </div>
        </div>

        {{-- WIT (RIGHT - IJF standard) --}}
        <div
            class="bg-white border-4 border-gray-300 rounded-lg flex flex-col relative overflow-hidden"
            @click="if(!isRunning) selectJudoka('wit')"
        >
            {{-- Naam --}}
            <div class="text-gray-800 text-center py-2 px-4 bg-gray-100">
                <div
                    class="font-bold uppercase truncate"
                    :class="displayMode ? 'text-4xl' : (compact ? 'text-lg' : 'text-2xl')"
                    x-text="witNaam"
                ></div>
                <div
                    x-show="witClub"
                    class="text-gray-500 truncate"
                    :class="displayMode ? 'text-xl' : 'text-sm'"
                    x-text="witClub"
                ></div>
            </div>

            {{-- Score --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    {{-- Ippon/Waza-ari score --}}
                    <div
                        class="font-mono font-black text-gray-800 cursor-pointer hover:opacity-80 transition-opacity"
                        :class="displayMode ? 'text-[200px] leading-none' : (compact ? 'text-6xl' : 'text-9xl')"
                        @click.stop="cycleScore('wit')"
                        x-text="scores.wit.display"
                    ></div>

                    {{-- Waza-ari indicator --}}
                    <div
                        class="flex justify-center gap-2 mt-2"
                        :class="displayMode ? 'gap-4 mt-4' : ''"
                    >
                        <template x-for="i in scores.wit.wazaari" :key="'ww-' + i">
                            <div
                                class="bg-blue-600 rounded"
                                :class="displayMode ? 'w-8 h-8' : 'w-4 h-4'"
                            ></div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Shido's (yellow cards) --}}
            <div
                class="absolute bottom-4 right-4 flex gap-2"
                :class="displayMode ? 'gap-3' : ''"
            >
                <template x-for="i in 3" :key="'ws-' + i">
                    <div
                        class="rounded cursor-pointer transition-all"
                        :class="[
                            displayMode ? 'w-12 h-16' : (compact ? 'w-6 h-8' : 'w-8 h-12'),
                            i <= scores.wit.shido ? 'bg-yellow-400 shadow-lg' : 'bg-gray-400/30 border-2 border-dashed border-gray-400'
                        ]"
                        @click.stop="toggleShido('wit', i)"
                        :title="'Shido ' + i"
                    ></div>
                </template>
            </div>

            {{-- Osaekomi indicator --}}
            <div
                x-show="osaekomi.active && osaekomi.judoka === 'wit'"
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold animate-pulse"
                :class="displayMode ? 'w-32 h-32 text-5xl' : 'w-20 h-20 text-3xl'"
            >
                <span x-text="osaekomi.time"></span>
            </div>
        </div>
    </div>

    {{-- Timer bar --}}
    <div
        class="bg-gray-900 py-4"
        :class="{ 'fixed bottom-0 left-0 right-0': displayMode }"
    >
        {{-- Progress bar --}}
        <div class="h-2 bg-gray-700 mx-4 rounded-full overflow-hidden mb-3">
            <div
                class="h-full transition-all duration-100"
                :class="timeRemaining <= 30 ? 'bg-red-500' : 'bg-green-500'"
                :style="'width: ' + (timeRemaining / matchDuration * 100) + '%'"
            ></div>
        </div>

        <div class="flex items-center justify-center gap-8">
            {{-- Main timer display --}}
            <div
                class="font-mono font-bold text-center cursor-pointer"
                :class="[
                    displayMode ? 'text-8xl' : (compact ? 'text-4xl' : 'text-6xl'),
                    timeRemaining <= 30 ? 'text-red-500' : 'text-white'
                ]"
                @click="toggleTimer()"
            >
                <span x-text="formatTime(timeRemaining)"></span>
            </div>
        </div>

        {{-- Control buttons --}}
        <div class="flex justify-center gap-4 mt-4">
            <button
                @click="toggleTimer()"
                class="px-6 py-3 rounded-lg font-bold text-lg transition-colors"
                :class="isRunning ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'"
            >
                <span x-show="!isRunning">▶ START</span>
                <span x-show="isRunning">⏸ STOP</span>
            </button>

            <button
                @click="resetTimer()"
                class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-bold text-lg"
                :disabled="isRunning"
                :class="{ 'opacity-50 cursor-not-allowed': isRunning }"
            >
                ↺ RESET
            </button>

            <button
                @click="toggleOsaekomi()"
                class="px-6 py-3 rounded-lg font-bold text-lg transition-colors"
                :class="osaekomi.active ? 'bg-orange-600 hover:bg-orange-700 text-white animate-pulse' : 'bg-orange-500 hover:bg-orange-600 text-white'"
            >
                <span x-show="!osaekomi.active">⏱ OSAEKOMI</span>
                <span x-show="osaekomi.active">TOKETA (<span x-text="osaekomi.time"></span>s)</span>
            </button>
        </div>

        {{-- Golden Score indicator --}}
        <div
            x-show="isGoldenScore"
            class="text-center mt-3"
        >
            <span class="bg-yellow-500 text-black px-4 py-1 rounded-full font-bold animate-pulse">
                ⚡ GOLDEN SCORE
            </span>
        </div>
    </div>

    {{-- Winner overlay --}}
    <div
        x-show="winner"
        x-transition
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
        @click="winner = null"
    >
        <div class="text-center">
            <div
                class="text-6xl font-black mb-4"
                :class="winner === 'blauw' ? 'text-blue-500' : 'text-white'"
                x-text="winner === 'blauw' ? blauwNaam : witNaam"
            ></div>
            <div class="text-4xl text-yellow-400 font-bold">🏆 WINNAAR 🏆</div>
            <div class="text-gray-400 mt-4">Klik om te sluiten</div>
        </div>
    </div>
</div>

@pushOnce('scripts')
<script>
// Web Audio context for alarm sounds
let audioContext = null;

function createAlarmSound(frequency = 880, duration = 0.5, type = 'square') {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }

    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);

    oscillator.type = type;
    oscillator.frequency.value = frequency;

    gainNode.gain.setValueAtTime(0.5, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);

    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + duration);
}

function playMatchEndSound() {
    // Triple beep for match end
    createAlarmSound(880, 0.3);
    setTimeout(() => createAlarmSound(880, 0.3), 400);
    setTimeout(() => createAlarmSound(1100, 0.5), 800);
}

function playOsaekomiSound() {
    // Single low beep for osaekomi milestones
    createAlarmSound(440, 0.2);
}

function playIpponSound() {
    // Victory fanfare
    createAlarmSound(523, 0.15); // C
    setTimeout(() => createAlarmSound(659, 0.15), 150); // E
    setTimeout(() => createAlarmSound(784, 0.15), 300); // G
    setTimeout(() => createAlarmSound(1047, 0.4), 450); // High C
}

document.addEventListener('alpine:init', () => {
    Alpine.data('scoreboard', (config) => ({
        // Config
        witNaam: config.witNaam,
        blauwNaam: config.blauwNaam,
        witClub: config.witClub,
        blauwClub: config.blauwClub,
        wedstrijdId: config.wedstrijdId,
        matchDuration: config.matchDuration,
        displayMode: config.displayMode,
        compact: config.compact,

        // Timer state
        timeRemaining: config.matchDuration,
        isRunning: false,
        isGoldenScore: false,
        lastTimestamp: null,
        animationFrameId: null,

        // Scores
        scores: {
            blauw: { display: '0', wazaari: 0, shido: 0, ippon: false },
            wit: { display: '0', wazaari: 0, shido: 0, ippon: false }
        },

        // Osaekomi
        osaekomi: {
            active: false,
            judoka: null,
            time: 0,
            startTime: null
        },

        // Winner
        winner: null,

        init() {
            // Focus for keyboard controls
            this.$el.focus();

            // Initialize audio context on first interaction
            this.$el.addEventListener('click', () => {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
            }, { once: true });
        },

        // Timer functions using requestAnimationFrame for precision
        toggleTimer() {
            if (this.isRunning) {
                this.stopTimer();
            } else {
                this.startTimer();
            }
        },

        startTimer() {
            if (this.timeRemaining <= 0 && !this.isGoldenScore) return;

            this.isRunning = true;
            this.lastTimestamp = performance.now();
            this.tick();
        },

        stopTimer() {
            this.isRunning = false;
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }

            // Also stop osaekomi
            if (this.osaekomi.active) {
                this.stopOsaekomi();
            }
        },

        tick() {
            if (!this.isRunning) return;

            const now = performance.now();
            const delta = (now - this.lastTimestamp) / 1000; // Convert to seconds
            this.lastTimestamp = now;

            // Update main timer (only count down if not in golden score)
            if (!this.isGoldenScore) {
                this.timeRemaining = Math.max(0, this.timeRemaining - delta);

                if (this.timeRemaining <= 0) {
                    this.onTimeUp();
                    return;
                }
            } else {
                // Golden score: count up
                this.timeRemaining += delta;
            }

            // Update osaekomi
            if (this.osaekomi.active) {
                this.osaekomi.time = Math.floor((now - this.osaekomi.startTime) / 1000);
                this.checkOsaekomiScore();
            }

            this.animationFrameId = requestAnimationFrame(() => this.tick());
        },

        resetTimer() {
            this.stopTimer();
            this.timeRemaining = this.matchDuration;
            this.isGoldenScore = false;
            this.scores = {
                blauw: { display: '0', wazaari: 0, shido: 0, ippon: false },
                wit: { display: '0', wazaari: 0, shido: 0, ippon: false }
            };
            this.osaekomi = { active: false, judoka: null, time: 0, startTime: null };
            this.winner = null;
        },

        onTimeUp() {
            this.stopTimer();
            playMatchEndSound();

            // Check for winner or golden score
            const blauwScore = this.calculateTotalScore('blauw');
            const witScore = this.calculateTotalScore('wit');

            if (blauwScore > witScore) {
                this.declareWinner('blauw');
            } else if (witScore > blauwScore) {
                this.declareWinner('wit');
            } else {
                // Tie - go to golden score
                this.isGoldenScore = true;
                this.timeRemaining = 0;
            }
        },

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            const tenths = Math.floor((seconds % 1) * 10);

            if (seconds < 10) {
                return `${mins}:${secs.toString().padStart(2, '0')}.${tenths}`;
            }
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        // Score functions
        cycleScore(judoka) {
            const score = this.scores[judoka];

            if (score.ippon) {
                // Reset to 0
                score.display = '0';
                score.wazaari = 0;
                score.ippon = false;
            } else if (score.wazaari >= 1) {
                // Waza-ari awasete ippon
                score.display = 'IPPON';
                score.wazaari = 2;
                score.ippon = true;
                this.declareWinner(judoka);
            } else {
                // Add waza-ari
                score.wazaari++;
                score.display = 'W';
            }
        },

        toggleShido(judoka, level) {
            const score = this.scores[judoka];

            if (score.shido >= level) {
                // Remove shido
                score.shido = level - 1;
            } else {
                // Add shido
                score.shido = level;

                // 3 shido = hansoku make = ippon for opponent
                if (score.shido >= 3) {
                    const opponent = judoka === 'blauw' ? 'wit' : 'blauw';
                    this.scores[opponent].display = 'IPPON';
                    this.scores[opponent].ippon = true;
                    this.declareWinner(opponent);
                }
            }
        },

        // Osaekomi functions
        toggleOsaekomi() {
            if (this.osaekomi.active) {
                this.stopOsaekomi();
            } else {
                this.startOsaekomi();
            }
        },

        startOsaekomi() {
            // Default to last selected or blauw
            const judoka = this.lastSelectedJudoka || 'blauw';
            this.osaekomi = {
                active: true,
                judoka: judoka,
                time: 0,
                startTime: performance.now()
            };

            if (!this.isRunning) {
                this.startTimer();
            }
        },

        stopOsaekomi() {
            this.osaekomi.active = false;
        },

        checkOsaekomiScore() {
            const time = this.osaekomi.time;
            const judoka = this.osaekomi.judoka;

            // Osaekomi scoring (IJF rules 2024)
            // 10 seconds = waza-ari
            // 20 seconds = ippon

            if (time >= 20) {
                playIpponSound();
                this.scores[judoka].display = 'IPPON';
                this.scores[judoka].ippon = true;
                this.stopOsaekomi();
                this.declareWinner(judoka);
            } else if (time === 10) {
                playOsaekomiSound();
                if (this.scores[judoka].wazaari === 0) {
                    this.scores[judoka].wazaari = 1;
                    this.scores[judoka].display = 'W';
                } else {
                    // Already has waza-ari = ippon
                    this.scores[judoka].display = 'IPPON';
                    this.scores[judoka].wazaari = 2;
                    this.scores[judoka].ippon = true;
                    this.stopOsaekomi();
                    this.declareWinner(judoka);
                }
            }
        },

        selectJudoka(judoka) {
            this.lastSelectedJudoka = judoka;
            if (this.osaekomi.active) {
                this.osaekomi.judoka = judoka;
                this.osaekomi.startTime = performance.now();
                this.osaekomi.time = 0;
            }
        },

        // Winner
        declareWinner(judoka) {
            this.stopTimer();
            playIpponSound();
            this.winner = judoka;
        },

        calculateTotalScore(judoka) {
            const score = this.scores[judoka];
            if (score.ippon) return 100;

            let total = score.wazaari * 10;

            // Opponent shido gives points
            const opponent = judoka === 'blauw' ? 'wit' : 'blauw';
            total += this.scores[opponent].shido;

            return total;
        },

        // Fullscreen
        toggleFullscreen() {
            if (this.displayMode) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        },

        enterFullscreen() {
            this.displayMode = true;
            if (this.$el.requestFullscreen) {
                this.$el.requestFullscreen();
            }
        },

        exitFullscreen() {
            this.displayMode = false;
            if (document.exitFullscreen && document.fullscreenElement) {
                document.exitFullscreen();
            }
        }
    }));
});
</script>
@endPushOnce
