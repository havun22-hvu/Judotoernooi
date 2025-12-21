@extends('layouts.app')

@section('title', 'Mat Interface')

@section('content')
<div x-data="matInterface()" x-init="init()">
    <!-- Compacte header met huidige selectie -->
    <div class="flex items-center justify-between mb-1">
        <h1 class="text-lg font-bold text-gray-800">🥋 Mat Interface</h1>
        <div class="text-sm text-gray-600" x-show="blokId && matId">
            <span class="font-bold">Blok <span x-text="blokkenData.find(b => b.id == blokId)?.nummer"></span></span>
            &bull;
            <span class="font-bold">Mat <span x-text="mattenData.find(m => m.id == matId)?.nummer"></span></span>
            <a href="#blok-mat-keuze" class="ml-2 text-purple-600 hover:underline">(wijzig)</a>
        </div>
    </div>

    <template x-for="poule in poules" :key="poule.poule_id">
        <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
            <!-- Header -->
            <div :class="poule.type === 'eliminatie' ? 'bg-purple-700' : 'bg-green-700'" class="text-white px-3 py-1.5 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold">
                        <span x-text="(poule.type === 'eliminatie' ? 'Eliminatie' : 'Poule ' + poule.poule_nummer) + ' - ' + poule.leeftijdsklasse + ' ' + poule.gewichtsklasse + ' | Blok ' + poule.blok_nummer + ' - Mat ' + poule.mat_nummer"></span>
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Geen wedstrijden: toon waarschuwing -->
                    <div x-show="poule.wedstrijden.length === 0" class="bg-red-500 text-white px-3 py-1 rounded text-sm font-medium">
                        ⚠ Geen wedstrijden
                    </div>
                    <!-- Afgerond: toon klaar tijdstip -->
                    <div x-show="poule.spreker_klaar" class="bg-white px-3 py-1 rounded text-sm font-bold" :class="poule.type === 'eliminatie' ? 'text-purple-700' : 'text-green-700'">
                        ✓ Klaar om: <span x-text="poule.spreker_klaar_tijd"></span>
                    </div>
                    <!-- Nog niet klaar maar wel afgerond: toon knop -->
                    <button
                        x-show="isPouleAfgerond(poule) && !poule.spreker_klaar"
                        @click="markeerKlaar(poule)"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm font-bold"
                    >
                        ✓ Afronden
                    </button>
                </div>
            </div>

            <!-- ELIMINATIE WEERGAVE - Drag & Drop Bracket met A/B Tabs -->
            <template x-if="poule.type === 'eliminatie'">
                <div class="p-1" x-data="{ activeTab: 'A' }">
                    <!-- Tabs -->
                    <div class="flex mb-1 border-b border-gray-200">
                        <button @click="activeTab = 'A'"
                                :class="activeTab === 'A' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                            Groep A (Hoofdboom)
                        </button>
                        <template x-if="heeftHerkansing(poule)">
                            <button @click="activeTab = 'B'"
                                    :class="activeTab === 'B' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                                Groep B (Herkansing)
                            </button>
                        </template>
                    </div>

                    <!-- Groep A - Hoofdboom -->
                    <div x-show="activeTab === 'A'">
                        <div class="flex justify-end">
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800"
                                 ondragover="event.preventDefault(); this.classList.add('text-red-600','font-bold')"
                                 ondragleave="this.classList.remove('text-red-600','font-bold')"
                                 ondrop="this.classList.remove('text-red-600','font-bold'); window.verwijderJudoka(event)">
                                🗑️ verwijder
                            </div>
                        </div>
                        <div class="bracket-container overflow-x-auto pb-2" x-html="renderBracket(poule, 'A')"></div>
                    </div>

                    <!-- Groep B - Herkansing -->
                    <div x-show="activeTab === 'B'">
                        <div class="flex justify-between items-center">
                            <button @click="verwerkByes(poule)"
                                    class="text-xs bg-purple-100 hover:bg-purple-200 text-purple-700 px-2 py-1 rounded">
                                ⏩ Verwerk Byes
                            </button>
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800"
                                 ondragover="event.preventDefault(); this.classList.add('text-red-600','font-bold')"
                                 ondragleave="this.classList.remove('text-red-600','font-bold')"
                                 ondrop="this.classList.remove('text-red-600','font-bold'); window.verwijderJudoka(event)">
                                🗑️ verwijder
                            </div>
                        </div>
                        <div class="overflow-x-auto pb-2" x-html="renderBracket(poule, 'B')"></div>
                    </div>

                </div>
            </template>

            <!-- POULE WEERGAVE (Matrix Table) -->
            <template x-if="poule.type !== 'eliminatie'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-200 border-b-2 border-gray-400">
                                <th class="px-2 py-1 text-left font-bold text-gray-700 sticky left-0 bg-gray-200 min-w-[240px]">Naam</th>
                                <template x-for="(w, idx) in poule.wedstrijden" :key="'h-' + idx">
                                    <th class="px-0 py-1 text-center font-bold w-14 border-l border-gray-300 cursor-pointer select-none transition-colors"
                                        :class="getWedstrijdKleurClass(poule, w, idx)"
                                        @click="toggleVolgendeWedstrijd(poule, w)"
                                        :title="getWedstrijdTitel(poule, w, idx)"
                                        colspan="2">
                                        <div class="text-xs font-bold" x-text="(idx + 1)"></div>
                                    </th>
                                </template>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 border-l-2 border-blue-300 w-10 text-xs">WP</th>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 w-10 text-xs">JP</th>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-yellow-100 border-l-2 border-yellow-300 w-8 text-xs">#</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(judoka, jIdx) in poule.judokas" :key="judoka.id">
                                <tr class="border-b">
                                    <!-- Nr + Naam -->
                                    <td class="px-2 py-1 font-medium sticky left-0 bg-white border-r border-gray-200 text-sm">
                                        <span class="font-bold" x-text="(jIdx + 1) + '. ' + judoka.naam"></span>
                                        <span class="text-gray-500 text-xs" x-text="judoka.club ? ' (' + judoka.club + ')' : ''"></span>
                                    </td>

                                    <!-- Wedstrijd cellen -->
                                    <template x-for="(w, wIdx) in poule.wedstrijden" :key="'c-' + judoka.id + '-' + wIdx">
                                        <td class="px-0 py-0.5 text-center border-l"
                                            :class="speeltInWedstrijd(judoka.id, w) ? 'border-gray-200 bg-white' : 'bg-gray-600 border-gray-500'"
                                            colspan="2">
                                            <!-- Alleen inputs tonen als judoka in deze wedstrijd speelt -->
                                            <div x-show="speeltInWedstrijd(judoka.id, w)" class="flex justify-center gap-0">
                                                <!-- WP -->
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    maxlength="1"
                                                    class="w-5 text-center border border-gray-300 rounded-sm text-xs py-0.5 font-bold"
                                                    :class="getWpClass(getWP(w, judoka.id))"
                                                    :value="getWP(w, judoka.id)"
                                                    @input="updateWP(w, judoka.id, $event.target.value)"
                                                    @blur="saveScore(w, poule)"
                                                >
                                                <!-- JP met dropdown -->
                                                <select
                                                    class="w-7 text-center border border-gray-300 rounded-sm text-xs py-0.5 appearance-none bg-white"
                                                    :value="getJP(w, judoka.id)"
                                                    @change="updateJP(w, judoka.id, $event.target.value); saveScore(w, poule)"
                                                >
                                                    <option value=""></option>
                                                    <option value="0">0</option>
                                                    <option value="5">5</option>
                                                    <option value="7">7</option>
                                                    <option value="10">10</option>
                                                </select>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- Totaal WP -->
                                    <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 border-l-2 border-blue-300 text-blue-800 text-xs"
                                        x-text="getTotaalWP(poule, judoka.id)"></td>
                                    <!-- Totaal JP -->
                                    <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 text-blue-800 text-xs"
                                        x-text="getTotaalJP(poule, judoka.id)"></td>
                                    <!-- Plaats - alleen tonen na alle wedstrijden -->
                                    <td class="px-0.5 py-0.5 text-center font-bold border-l-2 border-yellow-300 text-xs"
                                        :class="isPouleAfgerond(poule) ? getPlaatsClass(getPlaats(poule, judoka.id)) : 'bg-yellow-50'"
                                        x-text="isPouleAfgerond(poule) ? getPlaats(poule, judoka.id) : '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>

    <div x-show="poules.length === 0 && blokId && matId" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen poules op deze mat in dit blok
    </div>

    <!-- Blok/Mat keuze onderaan -->
    <div id="blok-mat-keuze" class="bg-gray-100 rounded-lg p-4 mt-8 border border-gray-300">
        <h3 class="text-sm font-bold text-gray-600 mb-3">Blok & Mat selectie</h3>
        <div class="flex gap-4">
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">Blok</label>
                <select x-model="blokId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Selecteer...</option>
                    @foreach($blokken as $blok)
                    <option value="{{ $blok->id }}">Blok {{ $blok->nummer }}
                        @if($blok->weging_gesloten) (gesloten) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">Mat</label>
                <select x-model="matId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Selecteer...</option>
                    @foreach($matten as $mat)
                    <option value="{{ $mat->id }}">Mat {{ $mat->nummer }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<script>
// Global drop handler - plaats judoka in slot
// Als judoka vanuit een vorige wedstrijd komt, stuur bron_wedstrijd_id mee
// Check of positie correct is volgens schema, anders waarschuwing
window.dropJudoka = async function(event, targetWedstrijdId, positie) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));

    // Check of dit de juiste positie is volgens het schema
    if (data.volgendeWedstrijdId && data.winnaarNaarSlot) {
        // Dit is een doorschuif vanuit een vorige wedstrijd
        if (data.volgendeWedstrijdId == targetWedstrijdId && data.winnaarNaarSlot !== positie) {
            // Verkeerde positie! Toon waarschuwing
            const naam = data.judokaNaam || 'Deze judoka';
            const juistePositie = data.winnaarNaarSlot === 'wit' ? 'WIT' : 'BLAUW';
            const gekozenPositie = positie === 'wit' ? 'WIT' : 'BLAUW';

            const bevestig = confirm(
                `⚠️ Let op: verkeerde positie!\n\n` +
                `${naam} moet volgens het schema op ${juistePositie} staan.\n` +
                `U plaatst nu op ${gekozenPositie}.\n\n` +
                `Weet u het zeker?`
            );

            if (!bevestig) {
                return; // Annuleer de actie
            }
        }
    }

    try {
        const response = await fetch(`{{ route('toernooi.mat.plaats-judoka', $toernooi) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wedstrijd_id: targetWedstrijdId,
                judoka_id: data.judokaId,
                positie: positie,
                bron_wedstrijd_id: data.wedstrijdId || null  // Stuur bron wedstrijd mee voor uitslag registratie
            })
        });

        if (response.ok) {
            const result = await response.json();

            // Toon waarschuwingen aan admin (verkeerde plaatsing)
            if (result.waarschuwingen && result.waarschuwingen.length > 0) {
                alert('⚠️ WAARSCHUWING - Mogelijke fout:\n\n• ' + result.waarschuwingen.join('\n• '));
            }

            // Toon correcties aan admin als die er zijn
            if (result.correcties && result.correcties.length > 0) {
                alert('✅ Automatische correcties uitgevoerd:\n\n• ' + result.correcties.join('\n• '));
            }

            Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
        }
    } catch (err) {
        console.error('Drop error:', err);
    }
};

// Global drop handler - verwijder judoka uit slot
window.verwijderJudoka = async function(event) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));

    try {
        const response = await fetch(`{{ route('toernooi.mat.verwijder-judoka', $toernooi) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wedstrijd_id: data.wedstrijdId,
                judoka_id: data.judokaId
            })
        });

        if (response.ok) {
            Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
        }
    } catch (err) {
        console.error('Verwijder error:', err);
    }
};

function matInterface() {
    const urlParams = new URLSearchParams(window.location.search);
    const blokNummer = urlParams.get('blok');
    const blokkenData = @json($blokken->map(fn($b) => ['id' => $b->id, 'nummer' => $b->nummer]));
    const mattenData = @json($matten->map(fn($m) => ['id' => $m->id, 'nummer' => $m->nummer]));
    const voorgeselecteerdBlok = blokNummer ? blokkenData.find(b => b.nummer == blokNummer) : null;

    return {
        blokId: voorgeselecteerdBlok ? String(voorgeselecteerdBlok.id) : '',
        matId: '',
        poules: [],
        blokkenData,
        mattenData,

        init() {
            if (this.blokId && @json($matten->count()) > 0) {
                this.matId = '{{ $matten->first()?->id }}';
                this.laadWedstrijden();
            }
        },

        async laadWedstrijden() {
            if (!this.blokId || !this.matId) {
                this.poules = [];
                return;
            }

            const response = await fetch(`{{ route('toernooi.mat.wedstrijden', $toernooi) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    blok_id: this.blokId,
                    mat_id: this.matId
                })
            });

            const data = await response.json();

            // Initialize scores from existing data
            this.poules = data.map(poule => {
                poule.wedstrijden = poule.wedstrijden.map(w => {
                    w.wpScores = {};
                    w.jpScores = {};

                    // Initialize from saved scores (only for poule matches with both judokas)
                    if (w.wit && w.blauw && (w.is_gespeeld || w.score_wit || w.score_blauw)) {
                        // Parse WP from winner
                        if (w.winnaar_id === w.wit.id) {
                            w.wpScores[w.wit.id] = 2;
                            w.wpScores[w.blauw.id] = 0;
                        } else if (w.winnaar_id === w.blauw.id) {
                            w.wpScores[w.wit.id] = 0;
                            w.wpScores[w.blauw.id] = 2;
                        }

                        // Parse JP from scores
                        if (w.score_wit) w.jpScores[w.wit.id] = parseInt(w.score_wit) || 0;
                        if (w.score_blauw) w.jpScores[w.blauw.id] = parseInt(w.score_blauw) || 0;
                    }

                    return w;
                });
                return poule;
            });
        },

        // Check of judoka in deze wedstrijd speelt (== voor type-onafhankelijk)
        speeltInWedstrijd(judokaId, wedstrijd) {
            return (wedstrijd.wit && wedstrijd.wit.id == judokaId) || (wedstrijd.blauw && wedstrijd.blauw.id == judokaId);
        },

        // Getters voor reactieve waarden
        getWP(wedstrijd, judokaId) {
            const val = wedstrijd.wpScores?.[judokaId];
            return val !== undefined ? val : '';
        },

        getJP(wedstrijd, judokaId) {
            const val = wedstrijd.jpScores?.[judokaId];
            return val !== undefined ? val : '';
        },

        getWpClass(wp) {
            wp = parseInt(wp);
            if (wp === 2) return 'bg-green-200 text-green-800';
            if (wp === 0) return 'bg-red-200 text-red-800';
            return 'bg-white';
        },

        getPlaatsClass(plaats) {
            if (plaats === 1) return 'bg-yellow-400 text-yellow-900';  // Goud
            if (plaats === 2) return 'bg-gray-300 text-gray-800';       // Zilver
            if (plaats === 3) return 'bg-orange-300 text-orange-900';   // Brons
            return 'bg-yellow-50';
        },

        updateWP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;
            const wp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id == judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            // Reassign objects voor Alpine reactivity
            wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: wp };

            // Auto-set opponent WP
            if (wp === 2) {
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [opponentId]: 0 };
            } else if (wp === 0) {
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [opponentId]: 2 };
            }
        },

        updateJP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;
            const jp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id == judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            // Reassign objects voor Alpine reactivity
            wedstrijd.jpScores = { ...wedstrijd.jpScores, [judokaId]: jp };

            // Logica: als JP > 0, dan WP=2 voor winnaar, WP=0 en JP=0 voor verliezer
            if (jp > 0) {
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: 2, [opponentId]: 0 };
                wedstrijd.jpScores = { ...wedstrijd.jpScores, [opponentId]: 0 };
            }
        },

        async saveScore(wedstrijd, poule) {
            // Skip if no judokas (eliminatie TBD)
            if (!wedstrijd.wit || !wedstrijd.blauw) return;

            // Determine winner based on WP
            let winnaarId = null;
            if (wedstrijd.wpScores[wedstrijd.wit.id] === 2) {
                winnaarId = wedstrijd.wit.id;
            } else if (wedstrijd.wpScores[wedstrijd.blauw.id] === 2) {
                winnaarId = wedstrijd.blauw.id;
            }

            // Save to backend
            await fetch(`{{ route('toernooi.mat.uitslag', $toernooi) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    wedstrijd_id: wedstrijd.id,
                    winnaar_id: winnaarId,
                    score_wit: String(wedstrijd.jpScores[wedstrijd.wit.id] || ''),
                    score_blauw: String(wedstrijd.jpScores[wedstrijd.blauw.id] || ''),
                    uitslag_type: 'punten'
                })
            });

            const wasGespeeld = wedstrijd.is_gespeeld;
            wedstrijd.is_gespeeld = !!(winnaarId || (wedstrijd.jpScores[wedstrijd.wit.id] !== undefined && wedstrijd.jpScores[wedstrijd.blauw.id] !== undefined));
            wedstrijd.winnaar_id = winnaarId;

            // Clear manual override when match is completed (auto-advance)
            if (!wasGespeeld && wedstrijd.is_gespeeld && poule && poule.huidige_wedstrijd_id) {
                poule.huidige_wedstrijd_id = null;
                // Notify backend
                fetch(`{{ route('toernooi.mat.huidige-wedstrijd', $toernooi) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id,
                        wedstrijd_id: null
                    })
                });
            }
        },

        getTotaalWP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.speeltInWedstrijd(judokaId, w)) {
                    totaal += parseInt(w.wpScores?.[judokaId]) || 0;
                }
            });
            return totaal;
        },

        getTotaalJP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.speeltInWedstrijd(judokaId, w)) {
                    totaal += parseInt(w.jpScores?.[judokaId]) || 0;
                }
            });
            return totaal;
        },

        getPlaats(poule, judokaId) {
            const standings = poule.judokas.map(j => ({
                id: j.id,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

            const wedstrijden = poule.wedstrijden;
            standings.sort((a, b) => {
                if (b.wp !== a.wp) return b.wp - a.wp;
                if (b.jp !== a.jp) return b.jp - a.jp;
                // Head-to-head
                for (const w of wedstrijden) {
                    const isMatch = (w.wit.id === a.id && w.blauw.id === b.id)
                                 || (w.wit.id === b.id && w.blauw.id === a.id);
                    if (isMatch && w.winnaar_id) {
                        return w.winnaar_id === a.id ? -1 : 1;
                    }
                }
                return 0;
            });

            return standings.findIndex(s => s.id === judokaId) + 1;
        },

        isPouleAfgerond(poule) {
            return poule.wedstrijden.length > 0 && poule.wedstrijden.every(w => w.is_gespeeld);
        },

        async markeerKlaar(poule) {
            try {
                const response = await fetch(`{{ route('toernooi.mat.poule-klaar', $toernooi) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id
                    })
                });

                const data = await response.json();
                if (data.success) {
                    poule.spreker_klaar = true;
                    // Get current time
                    const now = new Date();
                    poule.spreker_klaar_tijd = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                }
            } catch (err) {
                alert('Fout bij markeren: ' + err.message);
            }
        },

        async verwerkByes(poule) {
            try {
                const response = await fetch(`{{ route('toernooi.mat.verwerk-byes', $toernooi) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id
                    })
                });

                const data = await response.json();
                if (data.success) {
                    if (data.verwerkt && data.verwerkt.length > 0) {
                        alert('✅ Byes verwerkt:\n\n• ' + data.verwerkt.join('\n• '));
                    } else {
                        alert('ℹ️ Geen byes gevonden om te verwerken');
                    }
                    this.laadWedstrijden();
                }
            } catch (err) {
                alert('Fout bij verwerken byes: ' + err.message);
            }
        },

        // Bepaal huidige en volgende wedstrijd
        getHuidigeEnVolgende(poule) {
            const wedstrijden = poule.wedstrijden;
            if (!wedstrijden || wedstrijden.length === 0) return { huidige: null, volgende: null };

            // Automatisch: zoek laatste gespeelde wedstrijd
            let laatsteGespeeldIdx = -1;
            for (let i = wedstrijden.length - 1; i >= 0; i--) {
                if (wedstrijden[i].is_gespeeld) {
                    laatsteGespeeldIdx = i;
                    break;
                }
            }

            // Huidige (groen) = altijd automatisch: eerste niet gespeelde na laatst gespeelde
            const huidigeIdx = laatsteGespeeldIdx + 1;
            const huidige = huidigeIdx < wedstrijden.length ? wedstrijden[huidigeIdx] : null;

            // Volgende (geel) = handmatig geselecteerd OF automatisch de tweede niet gespeelde
            let volgende = null;
            if (poule.huidige_wedstrijd_id) {
                // Handmatige selectie voor volgende wedstrijd
                volgende = wedstrijden.find(w => w.id === poule.huidige_wedstrijd_id && !w.is_gespeeld);
            }
            if (!volgende) {
                // Automatisch: tweede niet gespeelde
                const volgendeIdx = laatsteGespeeldIdx + 2;
                volgende = volgendeIdx < wedstrijden.length ? wedstrijden[volgendeIdx] : null;
            }

            return { huidige, volgende };
        },

        // CSS class voor wedstrijd header
        getWedstrijdKleurClass(poule, wedstrijd, idx) {
            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);

            if (wedstrijd.is_gespeeld) {
                return 'bg-gray-300 text-gray-600'; // Gespeeld
            }
            if (huidige && wedstrijd.id === huidige.id) {
                return 'bg-green-500 text-white'; // Huidige (groen)
            }
            if (volgende && wedstrijd.id === volgende.id) {
                return 'bg-yellow-400 text-yellow-900'; // Volgende (geel)
            }
            return 'bg-gray-200 text-gray-700'; // Nog niet aan de beurt
        },

        // Tooltip voor wedstrijd header
        getWedstrijdTitel(poule, wedstrijd, idx) {
            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);

            if (wedstrijd.is_gespeeld) return 'Gespeeld';
            if (huidige && wedstrijd.id === huidige.id) return 'Nu aan de beurt';
            if (volgende && wedstrijd.id === volgende.id) {
                return poule.huidige_wedstrijd_id === wedstrijd.id
                    ? 'Handmatig geselecteerd - klik om te deselecteren'
                    : 'Volgende - klik om te wijzigen';
            }
            return 'Klik om als volgende te selecteren';
        },

        // Toggle volgende wedstrijd selectie
        async toggleVolgendeWedstrijd(poule, wedstrijd) {
            // Niet toestaan voor gespeelde wedstrijden
            if (wedstrijd.is_gespeeld) return;

            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);
            let nieuweId = null;

            // Klik op GROENE (huidige) wedstrijd = geen actie (groen beweegt alleen door score)
            if (huidige && wedstrijd.id === huidige.id) {
                return; // Groen is automatisch, geen actie
            }
            // Klik op GELE (volgende) wedstrijd = deselecteren als handmatig geselecteerd
            else if (volgende && wedstrijd.id === volgende.id && poule.huidige_wedstrijd_id === wedstrijd.id) {
                nieuweId = null; // Terug naar automatisch
            }
            // Klik op andere wedstrijd = selecteer als volgende
            else {
                nieuweId = wedstrijd.id;
            }

            try {
                const response = await fetch(`{{ route('toernooi.mat.huidige-wedstrijd', $toernooi) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id,
                        wedstrijd_id: nieuweId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    poule.huidige_wedstrijd_id = nieuweId;
                }
            } catch (err) {
                console.error('Fout bij selecteren wedstrijd:', err);
            }
        },

        // === ELIMINATIE FUNCTIES ===

        // Ronde volgorde voor sorteren (finale = laatste)
        rondeVolgorde: {
            'zestiende_finale': 1,
            'achtste_finale': 2,
            'kwartfinale': 3,
            'halve_finale': 4,
            'finale': 5,
            'herkansing_r1': 1,
            'herkansing_r2': 2,
            'herkansing_r3': 3,
            'herkansing_r4': 4,
        },

        // Bepaal ronde naam op basis van aantal slots
        getRondeNaamVoorSlots(aantalSlots, rondeKey) {
            // Gebaseerd op aantal deelnemers in de ronde
            if (aantalSlots >= 32) return '1/16';
            if (aantalSlots >= 16) return '1/8';
            if (aantalSlots >= 8) return '1/4';
            if (aantalSlots >= 4) return '1/2';
            if (aantalSlots >= 2) return 'Finale';
            // Fallback: gebruik key maar maak het leesbaar
            return rondeKey.replace('_', ' ').replace('ronde ', 'R');
        },

        // Ronde volgorde voor sorteren (kleinste eerst = meeste wedstrijden)
        rondeVolgordeLookup: {
            'voorronde': 0, 'b_voorronde': 0,
            'zestiende_finale': 1, 'b_zestiende_finale': 1,
            'achtste_finale': 2, 'b_achtste_finale': 2,
            'kwartfinale': 3, 'b_kwartfinale': 3,
            'halve_finale': 4, 'b_halve_finale': 4,
            'finale': 5, 'b_brons': 5,
        },

        // Get bracket als array van rondes met wedstrijden
        getEliminatieBracket(poule, groep) {
            const wedstrijden = poule.wedstrijden.filter(w => w.groep === groep && w.ronde !== 'b_brons');
            if (wedstrijden.length === 0) return [];

            // Groepeer op ronde
            const rondesMap = {};
            wedstrijden.forEach(w => {
                if (!rondesMap[w.ronde]) {
                    rondesMap[w.ronde] = [];
                }
                rondesMap[w.ronde].push(w);
            });

            // Sorteer rondes op volgorde (voorronde eerst, finale/halve laatst)
            const rondes = Object.entries(rondesMap)
                .sort((a, b) => {
                    const volgordeA = this.rondeVolgordeLookup[a[0]] ?? 99;
                    const volgordeB = this.rondeVolgordeLookup[b[0]] ?? 99;
                    return volgordeA - volgordeB;
                })
                .map(([ronde, weds]) => {
                    weds.sort((a, b) => (a.bracket_positie || 0) - (b.bracket_positie || 0));

                    // Bepaal leesbare naam
                    let naam = this.getRondeDisplayNaam(ronde, weds.length);

                    return { naam, ronde, wedstrijden: weds };
                });

            return rondes;
        },

        // Geef leesbare naam voor ronde
        getRondeDisplayNaam(ronde, aantalWeds) {
            const namen = {
                'voorronde': 'Voorronde',
                'zestiende_finale': '1/16',
                'achtste_finale': '1/8',
                'kwartfinale': '1/4',
                'halve_finale': '1/2',
                'finale': 'Finale',
                'b_voorronde': 'Voorronde',
                'b_zestiende_finale': '1/16',
                'b_achtste_finale': '1/8',
                'b_kwartfinale': '1/4',
                'b_halve_finale': '1/2',
                'b_brons': 'Brons',
            };
            return namen[ronde] || ronde.replace('b_', '').replace('_', ' ');
        },

        // Check of poule herkansing (groep B) heeft
        heeftHerkansing(poule) {
            return poule.wedstrijden.some(w => w.groep === 'B');
        },

        // Check of poule bronswedstrijden heeft
        heeftBronsWedstrijden(poule) {
            return poule.wedstrijden.some(w => w.ronde === 'brons');
        },

        // Render herkansing eenvoudig (geen bracket symmetrie)
        renderHerkansingSimple(rondes) {
            const ringColor = 'ring-purple-400';
            let html = '';

            // Header
            html += '<div class="flex gap-2 mb-2">';
            rondes.forEach(ronde => {
                html += `<div class="w-28 text-center text-xs font-bold text-purple-600">${ronde.naam}</div>`;
            });
            html += '</div>';

            // Body - elke ronde als kolom
            html += '<div class="flex gap-2 items-start">';

            rondes.forEach(ronde => {
                html += '<div class="flex flex-col w-28">';

                ronde.wedstrijden.forEach(wed => {
                    // Wit slot
                    html += `<div class="w-28 h-6 bg-white border border-gray-300 rounded-l flex items-center text-xs"
                                  ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                  ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                  ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'wit')">`;
                    if (wed.wit) {
                        html += `<div class="w-full h-full px-2 flex items-center cursor-move truncate" draggable="true"
                                      ondragstart="event.dataTransfer.setData('text/plain', JSON.stringify({judokaId:${wed.wit.id}, wedstrijdId:${wed.id}}))">
                                    ${wed.wit.naam}
                                 </div>`;
                    }
                    html += '</div>';

                    // Blauw slot
                    html += `<div class="w-28 h-6 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs mb-2"
                                  ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                  ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                  ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'blauw')">`;
                    if (wed.blauw) {
                        html += `<div class="w-full h-full px-2 flex items-center cursor-move truncate" draggable="true"
                                      ondragstart="event.dataTransfer.setData('text/plain', JSON.stringify({judokaId:${wed.blauw.id}, wedstrijdId:${wed.id}}))">
                                    ${wed.blauw.naam}
                                 </div>`;
                    }
                    html += '</div>';
                });

                html += '</div>';
            });

            html += '</div>';

            return html;
        },

        // Render bronswedstrijden
        renderBronsWedstrijden(poule) {
            const bronsWeds = poule.wedstrijden.filter(w => w.ronde === 'brons');
            if (bronsWeds.length === 0) return '';

            const ringColor = 'ring-amber-400';
            let html = '';

            bronsWeds.forEach((wed, idx) => {
                html += `<div class="bg-amber-50 border border-amber-200 rounded-lg p-3">`;
                html += `<div class="text-xs font-bold text-amber-700 mb-2">Brons ${idx + 1}</div>`;

                // Wit slot (halve finale verliezer)
                html += `<div class="mb-2">`;
                html += `<div class="text-xs text-gray-500 mb-1">Halve finale verliezer:</div>`;
                html += `<div class="w-full h-8 bg-white border border-gray-300 rounded flex items-center px-2 text-sm drop-slot"
                              ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                              ondragleave="this.classList.remove('ring-2','${ringColor}')"
                              ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'wit')">`;
                if (wed.wit) {
                    html += `<div class="w-full h-full flex items-center cursor-move truncate" draggable="true"
                                  ondragstart="event.dataTransfer.setData('text/plain', JSON.stringify({judokaId:${wed.wit.id}, wedstrijdId:${wed.id}}))">
                                ${wed.wit.naam}
                             </div>`;
                } else {
                    html += `<span class="text-gray-400 italic">Wacht op halve finale...</span>`;
                }
                html += `</div></div>`;

                // Blauw slot (herkansing winnaar)
                html += `<div class="mb-2">`;
                html += `<div class="text-xs text-gray-500 mb-1">Herkansing winnaar:</div>`;
                html += `<div class="w-full h-8 bg-blue-50 border border-gray-300 rounded flex items-center px-2 text-sm drop-slot"
                              ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                              ondragleave="this.classList.remove('ring-2','${ringColor}')"
                              ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'blauw')">`;
                if (wed.blauw) {
                    html += `<div class="w-full h-full flex items-center cursor-move truncate" draggable="true"
                                  ondragstart="event.dataTransfer.setData('text/plain', JSON.stringify({judokaId:${wed.blauw.id}, wedstrijdId:${wed.id}}))">
                                ${wed.blauw.naam}
                             </div>`;
                } else {
                    html += `<span class="text-gray-400 italic">Wacht op herkansing...</span>`;
                }
                html += `</div></div>`;

                // Winnaar (3e plaats)
                html += `<div class="mt-3 pt-2 border-t border-amber-200">`;
                html += `<div class="text-xs text-amber-700 font-bold mb-1">🥉 3e plaats:</div>`;
                html += `<div class="w-full h-8 bg-amber-100 border border-amber-300 rounded flex items-center px-2 text-sm font-bold">`;
                if (wed.is_gespeeld && wed.winnaar_id) {
                    const winnaar = wed.winnaar_id === wed.wit?.id ? wed.wit : wed.blauw;
                    html += winnaar ? winnaar.naam : '';
                }
                html += `</div></div>`;

                html += `</div>`;
            });

            return html;
        },

        // Render bracket als HTML met draggable chips
        renderBracket(poule, groep) {
            const rondes = this.getEliminatieBracket(poule, groep);
            if (rondes.length === 0) return '<div class="text-gray-500">Geen wedstrijden</div>';

            const h = 28; // slot height
            let html = '';

            // Kleuren op basis van groep
            const headerColor = 'text-purple-600';
            const ringColor = 'ring-purple-400';
            const winIcon = groep === 'A' ? '🏆' : '→ Brons';

            // Bepaal of er een voorronde is
            const voorrondeIdx = rondes.findIndex(r => r.ronde === 'voorronde' || r.ronde === 'b_voorronde');
            const heeftVoorrondeInBracket = voorrondeIdx >= 0;

            // Header met ronde namen
            html += '<div class="flex mb-1">';
            rondes.forEach((ronde, rondeIdx) => {
                const isVoorrondeHeader = ronde.ronde === 'voorronde' || ronde.ronde === 'b_voorronde';
                const headerWidth = isVoorrondeHeader ? 'w-36' : 'w-32';
                const aantalWeds = ronde.wedstrijden.length;
                const headerTekst = isVoorrondeHeader ? `${ronde.naam} (${aantalWeds})` : ronde.naam;
                html += `<div class="${headerWidth} flex-shrink-0 text-center text-xs font-bold ${headerColor}">${headerTekst}</div>`;
                if (rondeIdx < rondes.length - 1) {
                    html += '<div class="w-2 flex-shrink-0"></div>';
                }
            });
            html += `<div class="w-32 flex-shrink-0 text-center text-xs font-bold text-yellow-600">${winIcon}</div>`;
            html += '</div>';

            // Simpele berekening met absolute positioning
            const potjeHeight = 2 * h; // 56px (wit + blauw)
            const potjeGap = 8; // marge tussen potjes

            // Bereken posities voor elke ronde
            // Ronde 0: potje i op positie i * (potjeHeight + potjeGap)
            // Ronde 1: potje i gecentreerd tussen potje 2i en 2i+1 van ronde 0
            // etc.
            const berekenPotjeTop = (rondeIdx, potjeIdx) => {
                if (rondeIdx === 0) {
                    return potjeIdx * (potjeHeight + potjeGap);
                }
                // Gecentreerd tussen 2 potjes van vorige ronde
                const prevPotje1 = potjeIdx * 2;
                const prevPotje2 = potjeIdx * 2 + 1;
                const top1 = berekenPotjeTop(rondeIdx - 1, prevPotje1);
                const top2 = berekenPotjeTop(rondeIdx - 1, prevPotje2);
                // Center tussen de 2 potjes
                const center1 = top1 + potjeHeight / 2;
                const center2 = top2 + potjeHeight / 2;
                const center = (center1 + center2) / 2;
                return center - potjeHeight / 2;
            };

            // Bepaal totale hoogte (gebaseerd op eerste ronde)
            const eersteRonde = rondes[0];
            const heeftVoorronde = eersteRonde.ronde === 'voorronde' || eersteRonde.ronde === 'b_voorronde';
            const aantalEersteRonde = heeftVoorronde
                ? (rondes[1]?.wedstrijden.length || 8) * 2  // 16 voor voorronde
                : eersteRonde.wedstrijden.length;
            const totaleHoogte = aantalEersteRonde * (potjeHeight + potjeGap);

            html += `<div class="flex" style="height: ${totaleHoogte}px;">`;

            rondes.forEach((ronde, rondeIdx) => {
                const isVoorronde = ronde.ronde === 'voorronde' || ronde.ronde === 'b_voorronde';

                if (isVoorronde) {
                    // VOORRONDE = 1/16 finale: 16 potjes met absolute positioning
                    const eersteRonde = rondes[1]; // 1/8 finale
                    const aantal1_8 = eersteRonde ? eersteRonde.wedstrijden.length : 8;
                    const aantalVoorondePotjes = aantal1_8 * 2; // 16 potjes voor 8 1/8 matches

                    // Tel echte voorronde wedstrijden voor nummering
                    let wedstrijdTeller = 0;

                    html += `<div class="relative flex-shrink-0 w-36">`; // iets breder voor nummer

                    // Map voorronde wedstrijden op bracket_positie
                    const voorrondeMap = {};
                    ronde.wedstrijden.forEach(w => {
                        voorrondeMap[w.bracket_positie] = w;
                    });

                    // Render 16 potjes met absolute positioning
                    const winnaarIcon = '<span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>';

                    for (let potjeNr = 1; potjeNr <= aantalVoorondePotjes; potjeNr++) {
                        const wed = voorrondeMap[potjeNr];
                        const topPos = berekenPotjeTop(0, potjeNr - 1); // 0-indexed

                        // Check winnaar status (niet bij bye)
                        const isBye = wed?.uitslag_type === 'bye';
                        const isWitWinnaar = wed?.is_gespeeld && wed?.winnaar_id === wed?.wit?.id && !isBye;
                        const isBlauwWinnaar = wed?.is_gespeeld && wed?.winnaar_id === wed?.blauw?.id && !isBye;

                        // Wedstrijdnummer alleen als er een wedstrijd is
                        if (wed) wedstrijdTeller++;
                        const wedNr = wed ? wedstrijdTeller : '';

                        // Drag data met volgende wedstrijd info voor validatie
                        const witDragData = wed ? JSON.stringify({
                            judokaId: wed.wit?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.wit?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot
                        }).replace(/"/g, '&quot;') : '';

                        const blauwDragData = wed ? JSON.stringify({
                            judokaId: wed.blauw?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.blauw?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot
                        }).replace(/"/g, '&quot;') : '';

                        // Potje container met absolute positie - flex voor nummer + slots
                        html += `<div class="absolute w-36 flex items-center" style="top: ${topPos}px;">`;
                        // Nummer links van potje (verticaal gecentreerd)
                        html += `<div class="w-4 text-xs text-gray-500 font-medium text-right pr-1">${wedNr}</div>`;
                        // Slots container
                        html += `<div class="flex-1">`;

                        // Wit slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-white border border-gray-300 rounded-l flex items-center text-xs border-r-0">`;
                        if (wed && wed.wit) {
                            html += `<div class="w-full h-full px-1 flex items-center cursor-move" draggable="true"
                                       ondragstart="event.dataTransfer.setData('text/plain', '${witDragData}')">
                                     <span class="truncate">${wed.wit.naam}</span>${isWitWinnaar ? winnaarIcon : ''}
                                  </div>`;
                        }
                        html += '</div>';
                        html += `<div class="absolute right-0 top-0 w-4 h-full border-t border-r border-gray-400"></div>`;
                        html += '</div>';

                        // Blauw slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs border-r-0">`;
                        if (wed && wed.blauw) {
                            html += `<div class="w-full h-full px-1 flex items-center cursor-move" draggable="true"
                                       ondragstart="event.dataTransfer.setData('text/plain', '${blauwDragData}')">
                                     <span class="truncate">${wed.blauw.naam}</span>${isBlauwWinnaar ? winnaarIcon : ''}
                                  </div>`;
                        }
                        html += '</div>';
                        html += `<div class="absolute right-0 top-0 w-4 h-full border-b border-r border-gray-400"></div>`;
                        html += '</div>';

                        html += '</div>'; // einde slots container (flex-1)
                        html += '</div>'; // einde potje container (absolute)
                    }

                    html += '</div>';
                } else {
                    // Normale ronde rendering met absolute positioning
                    html += `<div class="relative flex-shrink-0 w-32">`;

                    // Sorteer wedstrijden op bracket_positie voor correcte volgorde
                    const sortedWeds = [...ronde.wedstrijden].sort((a, b) => a.bracket_positie - b.bracket_positie);
                    sortedWeds.forEach((wed, wedIdx) => {
                        const isLastRound = rondeIdx === rondes.length - 1;
                        const topPos = berekenPotjeTop(rondeIdx, wedIdx);

                        // Potje container met absolute positie
                        html += `<div class="absolute w-32" style="top: ${topPos}px;">`;

                        // Helper: groen cirkeltje voor winnaar (niet bij bye)
                        const winnaarIcon = '<span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>';
                        const isBye = wed.uitslag_type === 'bye';
                        const isWitWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.wit?.id && !isBye;
                        const isBlauwWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.blauw?.id && !isBye;

                        // Drag data met volgende wedstrijd info voor validatie
                        const witDragData = JSON.stringify({
                            judokaId: wed.wit?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.wit?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot
                        }).replace(/"/g, '&quot;');

                        const blauwDragData = JSON.stringify({
                            judokaId: wed.blauw?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.blauw?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot
                        }).replace(/"/g, '&quot;');

                        // Wit slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-white border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
                                      ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                      ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                      ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'wit')">`;
                        if (wed.wit) {
                            html += `<div class="w-full h-full px-1 flex items-center cursor-move" draggable="true"
                                          ondragstart="event.dataTransfer.setData('text/plain', '${witDragData}')">
                                        <span class="truncate">${wed.wit.naam}</span>${isWitWinnaar ? winnaarIcon : ''}
                                     </div>`;
                        }
                        html += '</div>';
                        if (!isLastRound) {
                            html += `<div class="absolute right-0 top-0 w-4 h-full border-t border-r border-gray-400"></div>`;
                        }
                        html += '</div>';

                        // Blauw slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
                                      ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                      ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                      ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'blauw')">`;
                        if (wed.blauw) {
                            html += `<div class="w-full h-full px-1 flex items-center cursor-move" draggable="true"
                                          ondragstart="event.dataTransfer.setData('text/plain', '${blauwDragData}')">
                                        <span class="truncate">${wed.blauw.naam}</span>${isBlauwWinnaar ? winnaarIcon : ''}
                                     </div>`;
                        }
                        html += '</div>';
                        if (!isLastRound) {
                            html += `<div class="absolute right-0 top-0 w-4 h-full border-b border-r border-gray-400"></div>`;
                        }
                        html += '</div>';

                        html += '</div>'; // einde potje
                    });

                    html += '</div>';
                }

                // Ruimte voor connector
                if (rondeIdx < rondes.length - 1) {
                    html += '<div class="w-2 flex-shrink-0"></div>';
                }
            });

            // Winnaar slot(s) met absolute positioning
            const laatsteRondeWedstrijden = rondes[rondes.length - 1]?.wedstrijden || [];
            const laatsteRondeIdx = rondes.length - 1;

            if (groep === 'A') {
                const finale = laatsteRondeWedstrijden[0];
                const winnaar = finale?.is_gespeeld ? (finale.winnaar_id === finale.wit?.id ? finale.wit : finale.blauw) : null;
                const verliezer = finale?.is_gespeeld ? (finale.winnaar_id === finale.wit?.id ? finale.blauw : finale.wit) : null;
                // Winnaar (goud) en verliezer (zilver) naast finale
                const winnaarTop = berekenPotjeTop(laatsteRondeIdx, 0) + h / 2 - 16; // Iets hoger voor goud
                html += `<div class="relative flex-shrink-0 w-32">`;
                // Goud (1e plaats)
                html += `<div class="absolute w-32" style="top: ${winnaarTop}px;">`;
                html += `<div class="w-32 h-7 bg-yellow-100 border border-yellow-400 rounded flex items-center px-1 text-xs font-bold truncate">`;
                html += winnaar ? `🥇 ${winnaar.naam}` : '🥇';
                html += '</div></div>';
                // Zilver (2e plaats)
                html += `<div class="absolute w-32" style="top: ${winnaarTop + 30}px;">`;
                html += `<div class="w-32 h-7 bg-gray-200 border border-gray-400 rounded flex items-center px-1 text-xs truncate">`;
                html += verliezer ? `🥈 ${verliezer.naam}` : '🥈';
                html += '</div></div>';
                html += '</div>';
            } else {
                // B groep: 2 bronzen winnaars (1 per halve finale)
                html += `<div class="relative flex-shrink-0 w-32">`;
                laatsteRondeWedstrijden.forEach((wed, wedIdx) => {
                    const winnaar = wed.is_gespeeld ? (wed.winnaar_id === wed.wit?.id ? wed.wit : wed.blauw) : null;
                    const winnaarTop = berekenPotjeTop(laatsteRondeIdx, wedIdx) + h / 2;
                    html += `<div class="absolute w-32" style="top: ${winnaarTop}px;">`;
                    html += `<div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate">`;
                    html += winnaar ? `${winnaar.naam} →🥉` : '→ 🥉';
                    html += '</div></div>';
                });
                html += '</div>';
            }

            html += '</div>';

            return html;
        },

        // Get finale winnaar
        getFinaleWinnaar(poule, groep) {
            const finale = poule.wedstrijden.find(w => w.groep === groep && w.ronde === 'finale');
            if (!finale || !finale.is_gespeeld || !finale.winnaar_id) return null;
            return finale.winnaar_id === finale.wit?.id ? finale.wit : finale.blauw;
        },

        // Get brons winnaars (2 stuks bij double elimination)
        getBronsWinnaars(poule) {
            const bronsWedstrijden = poule.wedstrijden.filter(w => w.ronde === 'brons');
            return bronsWedstrijden
                .filter(w => w.is_gespeeld && w.winnaar_id)
                .map(w => w.winnaar_id === w.wit?.id ? w.wit : w.blauw)
                .filter(j => j);
        },

        // Drag & drop handler
        async dropJudoka(event, poule, targetSlot) {
            // Get dragged judoka data from event
            const dragData = event.dataTransfer?.getData('text/plain');
            if (!dragData) return;

            try {
                const { judokaId, fromWedstrijdId, fromPositie } = JSON.parse(dragData);

                // Find target wedstrijd
                const targetWedstrijd = poule.wedstrijden.find(w => w.id === targetSlot.wedstrijdId);
                if (!targetWedstrijd) return;

                // Registreer als winnaar als we van vorige ronde naar volgende slepen
                const fromWedstrijd = poule.wedstrijden.find(w => w.id === fromWedstrijdId);
                if (fromWedstrijd && fromWedstrijd.volgende_wedstrijd_id === targetSlot.wedstrijdId) {
                    // Dit is een winnaar die doorschuift
                    const response = await fetch(`{{ route('toernooi.mat.uitslag', $toernooi) }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            wedstrijd_id: fromWedstrijdId,
                            winnaar_id: judokaId,
                            uitslag_type: 'eliminatie'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.laadWedstrijden();
                    }
                }
            } catch (err) {
                console.error('Drop error:', err);
            }
        }
    }
}
</script>
@endsection
