@extends('layouts.app')

@section('title', 'Mat Interface')

@section('content')
<div x-data="matInterface()" x-init="init()">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">🥋 Mat Interface</h1>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Blok</label>
                <select x-model="blokId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2">
                    <option value="">Selecteer blok...</option>
                    @foreach($blokken as $blok)
                    <option value="{{ $blok->id }}">Blok {{ $blok->nummer }}
                        @if($blok->weging_gesloten) (gesloten) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Mat</label>
                <select x-model="matId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2">
                    <option value="">Selecteer mat...</option>
                    @foreach($matten as $mat)
                    <option value="{{ $mat->id }}">Mat {{ $mat->nummer }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <template x-for="poule in poules" :key="poule.poule_id">
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <!-- Header -->
            <div :class="poule.type === 'eliminatie' ? 'bg-purple-700' : 'bg-green-700'" class="text-white px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold">
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

            <!-- ELIMINATIE WEERGAVE - Drag & Drop Bracket -->
            <template x-if="poule.type === 'eliminatie'">
                <div class="p-4">
                    <!-- Groep A - Hoofdboom -->
                    <div class="mb-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-sm font-bold text-purple-800">Groep A</h3>
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
                    <template x-if="heeftHerkansing(poule)">
                        <div class="mb-6 pt-4 border-t">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-sm font-bold text-purple-800">Groep B (Herkansing)</h3>
                                <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800"
                                     ondragover="event.preventDefault(); this.classList.add('text-red-600','font-bold')"
                                     ondragleave="this.classList.remove('text-red-600','font-bold')"
                                     ondrop="this.classList.remove('text-red-600','font-bold'); window.verwijderJudoka(event)">
                                    🗑️ verwijder
                                </div>
                            </div>
                            <div class="overflow-x-auto pb-2" x-html="renderBracket(poule, 'B')"></div>
                        </div>
                    </template>

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
</div>

<script>
// Global drop handler - plaats judoka in slot
window.dropJudoka = async function(event, targetWedstrijdId, positie) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));

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
                positie: positie
            })
        });

        if (response.ok) {
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
    const voorgeselecteerdBlok = blokNummer ? blokkenData.find(b => b.nummer == blokNummer) : null;

    return {
        blokId: voorgeselecteerdBlok ? String(voorgeselecteerdBlok.id) : '',
        matId: '',
        poules: [],

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

        // Get bracket als array van rondes met wedstrijden
        getEliminatieBracket(poule, groep) {
            const wedstrijden = poule.wedstrijden.filter(w => w.groep === groep && w.ronde !== 'brons');
            if (wedstrijden.length === 0) return [];

            // Groepeer op ronde
            const rondesMap = {};
            wedstrijden.forEach(w => {
                if (!rondesMap[w.ronde]) {
                    rondesMap[w.ronde] = [];
                }
                rondesMap[w.ronde].push(w);
            });

            // Sorteer rondes
            const rondes = Object.entries(rondesMap)
                .sort((a, b) => {
                    // Voorronde altijd eerst (A en B)
                    if (a[0] === 'voorronde' || a[0] === 'b_voorronde') return -1;
                    if (b[0] === 'voorronde' || b[0] === 'b_voorronde') return 1;
                    // B-ronde op nummer (b_ronde_1, b_ronde_2...)
                    if (a[0].startsWith('b_ronde_') && b[0].startsWith('b_ronde_')) {
                        const numA = parseInt(a[0].replace('b_ronde_', ''));
                        const numB = parseInt(b[0].replace('b_ronde_', ''));
                        return numA - numB;
                    }
                    // Normale rondes: meeste wedstrijden eerst
                    return b[1].length - a[1].length;
                })
                .map(([ronde, weds]) => {
                    weds.sort((a, b) => (a.bracket_positie || 0) - (b.bracket_positie || 0));

                    const aantalWeds = weds.length;
                    let naam = ronde;

                    // A-bracket ronde namen
                    if (ronde === 'voorronde') naam = 'Voorronde';
                    else if (aantalWeds === 1 && ronde === 'finale') naam = 'Finale';
                    else if (aantalWeds === 2 && ronde === 'halve_finale') naam = '1/2';
                    else if (aantalWeds <= 4 && ronde === 'kwartfinale') naam = '1/4';
                    else if (aantalWeds <= 8) naam = '1/8';
                    else if (aantalWeds <= 16) naam = '1/16';
                    // B-poule ronde namen
                    else if (ronde === 'b_voorronde') naam = 'Voorronde';
                    else if (ronde.startsWith('b_ronde_')) {
                        const nr = ronde.replace('b_ronde_', '');
                        naam = `R${nr}`;
                    }

                    return { naam, ronde, wedstrijden: weds };
                });

            return rondes;
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

            const h = 24; // slot height
            let html = '';

            // Kleuren op basis van groep
            const headerColor = 'text-purple-600';
            const ringColor = 'ring-purple-400';
            const winIcon = groep === 'A' ? '🏆' : '→ Brons';

            // Header met ronde namen (zelfde structuur als body)
            html += '<div class="flex mb-2">';
            rondes.forEach((ronde, rondeIdx) => {
                html += `<div class="w-28 flex-shrink-0 text-center text-xs font-bold ${headerColor}">${ronde.naam}</div>`;
                // Connector ruimte (zelfde als in body: w-3)
                if (rondeIdx < rondes.length - 1) {
                    html += '<div class="w-3 flex-shrink-0"></div>';
                }
            });
            // Winnaar kolom header
            html += `<div class="w-28 flex-shrink-0 text-center text-xs font-bold text-yellow-600">${winIcon}</div>`;
            html += '</div>';

            html += '<div class="flex">';

            rondes.forEach((ronde, rondeIdx) => {
                // Standaard bracket symmetrie: elke ronde halveert
                const factor = Math.pow(2, rondeIdx) - 1;
                const gap = factor * h;
                const marginTop = factor * (h / 2);

                html += `<div class="flex flex-col flex-shrink-0 w-28" style="gap: ${gap}px; margin-top: ${marginTop}px;">`;

                ronde.wedstrijden.forEach((wed, wedIdx) => {
                    const isLastRound = rondeIdx === rondes.length - 1;

                    // Wit slot met bracket lijn rechts
                    html += `<div class="relative">`;
                    html += `<div class="w-28 h-6 bg-white border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
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
                    // Bracket lijn rechts (bovenste helft)
                    if (!isLastRound) {
                        html += `<div class="absolute right-0 top-0 w-3 h-full border-t border-r border-gray-400"></div>`;
                    }
                    html += '</div>';

                    // Blauw slot met bracket lijn rechts
                    html += `<div class="relative">`;
                    html += `<div class="w-28 h-6 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
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
                    // Bracket lijn rechts (onderste helft)
                    if (!isLastRound) {
                        html += `<div class="absolute right-0 top-0 w-3 h-full border-b border-r border-gray-400"></div>`;
                    }
                    html += '</div>';
                });

                html += '</div>';

                // Ruimte voor connector naar volgende ronde
                if (rondeIdx < rondes.length - 1) {
                    html += '<div class="w-3 flex-shrink-0"></div>';
                }
            });

            // Winnaar slot(s)
            const laatsteRondeWedstrijden = rondes[rondes.length - 1]?.wedstrijden || [];
            const factorWinnaar = Math.pow(2, rondes.length) - 1;
            const marginTopWinnaar = factorWinnaar * (h / 2);

            if (groep === 'A') {
                // Groep A: 1 winnaar (finale winnaar)
                const finale = laatsteRondeWedstrijden[0];
                const winnaar = finale?.is_gespeeld ? (finale.winnaar_id === finale.wit?.id ? finale.wit : finale.blauw) : null;
                html += `<div class="flex flex-col flex-shrink-0" style="margin-top: ${marginTopWinnaar}px;">`;
                html += `<div class="w-28 h-6 bg-yellow-100 border border-yellow-400 rounded flex items-center px-2 text-xs font-bold truncate">`;
                html += winnaar ? winnaar.naam : '';
                html += '</div></div>';
            } else {
                // Groep B: 2 winnaars (halve finale winnaars gaan naar brons)
                const gapWinnaar = (Math.pow(2, rondes.length - 1) - 1) * h;
                const marginTopHF = (Math.pow(2, rondes.length - 1) - 1) * (h / 2);
                html += `<div class="flex flex-col flex-shrink-0" style="margin-top: ${marginTopHF}px; gap: ${gapWinnaar}px;">`;
                laatsteRondeWedstrijden.forEach(wed => {
                    const winnaar = wed.is_gespeeld ? (wed.winnaar_id === wed.wit?.id ? wed.wit : wed.blauw) : null;
                    html += `<div class="w-28 h-6 bg-amber-100 border border-amber-400 rounded flex items-center px-2 text-xs truncate">`;
                    html += winnaar ? `${winnaar.naam} →🥉` : '→ 🥉';
                    html += '</div>';
                });
                html += '</div>';
            }

            html += '</div>'; // sluit bracket body

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
