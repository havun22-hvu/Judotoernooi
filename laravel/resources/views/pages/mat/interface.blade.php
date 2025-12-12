@extends('layouts.app')

@section('title', 'Mat Interface')

@section('content')
<div x-data="matInterface()" class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">🥋 Mat Interface</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
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
            <div class="bg-blue-800 text-white px-6 py-3 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold" x-text="poule.titel"></h2>
                    <div class="text-blue-200 text-sm">
                        <span x-text="poule.judokas.length + ' judoka\'s'"></span> •
                        <span x-text="poule.wedstrijden.length + ' wedstrijden'"></span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div x-show="isPouleAfgerond(poule) && !poule.spreker_klaar" class="bg-green-500 text-white px-3 py-1 rounded text-sm font-medium">
                        ✓ Afgerond
                    </div>
                    <button
                        x-show="isPouleAfgerond(poule) && !poule.spreker_klaar"
                        @click="markeerKlaar(poule)"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm font-bold"
                    >
                        📢 Klaar
                    </button>
                    <div x-show="poule.spreker_klaar" class="bg-purple-500 text-white px-3 py-1 rounded text-sm font-medium">
                        📢 Naar spreker
                    </div>
                </div>
            </div>

            <!-- Matrix Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="px-3 py-2 text-left font-medium text-gray-700 sticky left-0 bg-gray-100 min-w-[150px]">Naam</th>
                            <template x-for="(w, idx) in getUniqueWedstrijden(poule)" :key="'h-' + idx">
                                <th class="px-1 py-2 text-center font-medium text-gray-700 min-w-[80px]" colspan="2">
                                    <span x-text="'Wed ' + (idx + 1)"></span>
                                    <div class="text-xs text-gray-500 flex justify-center gap-1">
                                        <span>WP</span><span>JP</span>
                                    </div>
                                </th>
                            </template>
                            <th class="px-2 py-2 text-center font-medium text-gray-700 bg-blue-50" colspan="2">Totaal</th>
                            <th class="px-2 py-2 text-center font-medium text-gray-700 bg-purple-100">Plts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(judoka, jIdx) in poule.judokas" :key="judoka.id">
                            <tr class="border-b hover:bg-gray-50">
                                <!-- Judoka naam -->
                                <td class="px-3 py-2 font-medium sticky left-0 bg-white">
                                    <span x-text="judoka.naam"></span>
                                    <div class="text-xs text-gray-500" x-text="judoka.club || ''"></div>
                                </td>

                                <!-- Wedstrijd cellen -->
                                <template x-for="(w, wIdx) in getUniqueWedstrijden(poule)" :key="'c-' + judoka.id + '-' + wIdx">
                                    <template x-if="isJudokaInWedstrijd(judoka.id, w)">
                                        <!-- Judoka speelt in deze wedstrijd -->
                                        <td class="px-1 py-1 text-center border-l" colspan="2">
                                            <div class="flex justify-center gap-1">
                                                <!-- WP -->
                                                <select
                                                    class="w-12 text-center border rounded text-sm py-1"
                                                    :class="getWpClass(getWpForJudoka(judoka.id, w))"
                                                    x-model="w.wpScores[judoka.id]"
                                                    @change="updateWedstrijdScore(w, judoka.id, 'wp', $event.target.value)"
                                                >
                                                    <option value="">-</option>
                                                    <option value="0">0</option>
                                                    <option value="2">2</option>
                                                </select>
                                                <!-- JP -->
                                                <select
                                                    class="w-14 text-center border rounded text-sm py-1"
                                                    x-model="w.jpScores[judoka.id]"
                                                    @change="updateWedstrijdScore(w, judoka.id, 'jp', $event.target.value)"
                                                >
                                                    <option value="">-</option>
                                                    <option value="0">0</option>
                                                    <option value="5">5</option>
                                                    <option value="7">7</option>
                                                    <option value="10">10</option>
                                                </select>
                                            </div>
                                        </td>
                                    </template>
                                    <template x-if="!isJudokaInWedstrijd(judoka.id, w)">
                                        <!-- Judoka speelt niet in deze wedstrijd (grijs) -->
                                        <td class="px-1 py-1 text-center bg-gray-300 border-l" colspan="2"></td>
                                    </template>
                                </template>

                                <!-- Totaal WP -->
                                <td class="px-2 py-2 text-center font-bold bg-blue-50 border-l text-blue-700"
                                    x-text="getTotaalWP(poule, judoka.id)"></td>
                                <!-- Totaal JP -->
                                <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-700"
                                    x-text="getTotaalJP(poule, judoka.id)"></td>
                                <!-- Plaats -->
                                <td class="px-2 py-2 text-center font-bold bg-purple-100 text-purple-700"
                                    x-text="getPlaats(poule, judoka.id)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <div x-show="poules.length === 0 && blokId && matId" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen poules op deze mat in dit blok
    </div>
</div>

<script>
function matInterface() {
    return {
        blokId: '',
        matId: '',
        poules: [],

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

                    // Initialize from saved scores
                    if (w.is_gespeeld) {
                        // Parse WP from winner
                        if (w.winnaar_id === w.wit.id) {
                            w.wpScores[w.wit.id] = '2';
                            w.wpScores[w.blauw.id] = '0';
                        } else if (w.winnaar_id === w.blauw.id) {
                            w.wpScores[w.wit.id] = '0';
                            w.wpScores[w.blauw.id] = '2';
                        }

                        // Parse JP from scores
                        w.jpScores[w.wit.id] = w.score_wit || '';
                        w.jpScores[w.blauw.id] = w.score_blauw || '';
                    }

                    return w;
                });
                return poule;
            });
        },

        getUniqueWedstrijden(poule) {
            return poule.wedstrijden;
        },

        isJudokaInWedstrijd(judokaId, wedstrijd) {
            return wedstrijd.wit.id === judokaId || wedstrijd.blauw.id === judokaId;
        },

        getWpForJudoka(judokaId, wedstrijd) {
            return wedstrijd.wpScores?.[judokaId] || '';
        },

        getWpClass(wp) {
            if (wp === '2') return 'bg-green-100 text-green-800';
            if (wp === '0') return 'bg-red-100 text-red-800';
            return '';
        },

        async updateWedstrijdScore(wedstrijd, judokaId, type, value) {
            // Determine opponent
            const opponentId = wedstrijd.wit.id === judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            if (type === 'wp') {
                wedstrijd.wpScores[judokaId] = value;
                // Auto-set opponent WP
                if (value === '2') {
                    wedstrijd.wpScores[opponentId] = '0';
                } else if (value === '0') {
                    wedstrijd.wpScores[opponentId] = '2';
                }
            } else {
                wedstrijd.jpScores[judokaId] = value;
            }

            // Determine winner based on WP
            let winnaarId = null;
            if (wedstrijd.wpScores[wedstrijd.wit.id] === '2') {
                winnaarId = wedstrijd.wit.id;
            } else if (wedstrijd.wpScores[wedstrijd.blauw.id] === '2') {
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
                    score_wit: wedstrijd.jpScores[wedstrijd.wit.id] || '',
                    score_blauw: wedstrijd.jpScores[wedstrijd.blauw.id] || '',
                    uitslag_type: 'punten'
                })
            });

            wedstrijd.is_gespeeld = !!(winnaarId || (wedstrijd.jpScores[wedstrijd.wit.id] && wedstrijd.jpScores[wedstrijd.blauw.id]));
            wedstrijd.winnaar_id = winnaarId;
        },

        getTotaalWP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.isJudokaInWedstrijd(judokaId, w)) {
                    const wp = parseInt(w.wpScores?.[judokaId]) || 0;
                    totaal += wp;
                }
            });
            return totaal;
        },

        getTotaalJP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.isJudokaInWedstrijd(judokaId, w)) {
                    const jp = parseInt(w.jpScores?.[judokaId]) || 0;
                    totaal += jp;
                }
            });
            return totaal;
        },

        getPlaats(poule, judokaId) {
            // Calculate standings based on WP, then JP, then head-to-head
            const standings = poule.judokas.map(j => ({
                id: j.id,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

            // Sort by WP desc, then JP desc, then head-to-head
            const wedstrijden = poule.wedstrijden;
            standings.sort((a, b) => {
                if (b.wp !== a.wp) return b.wp - a.wp;
                if (b.jp !== a.jp) return b.jp - a.jp;
                // Head-to-head: find their match and check winner
                for (const w of wedstrijden) {
                    const isMatch = (w.wit.id === a.id && w.blauw.id === b.id)
                                 || (w.wit.id === b.id && w.blauw.id === a.id);
                    if (isMatch && w.winnaar_id) {
                        return w.winnaar_id === a.id ? -1 : 1;
                    }
                }
                return 0;
            });

            const index = standings.findIndex(s => s.id === judokaId);
            return index + 1;
        },

        isPouleAfgerond(poule) {
            return poule.wedstrijden.every(w => w.is_gespeeld);
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
                }
            } catch (err) {
                alert('Fout bij markeren: ' + err.message);
            }
        }
    }
}
</script>
@endsection
