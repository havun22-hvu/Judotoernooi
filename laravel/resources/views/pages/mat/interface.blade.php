@extends('layouts.app')

@section('title', 'Mat Interface')

@section('content')
<div x-data="matInterface()" x-init="init()" class="max-w-7xl mx-auto">
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
            <div class="bg-green-700 text-white px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold">
                        <span x-text="'Poule ' + poule.poule_nummer + ' - ' + poule.leeftijdsklasse + ' ' + poule.gewichtsklasse + ' | Blok ' + poule.blok_nummer + ' - Mat ' + poule.mat_nummer"></span>
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Geen wedstrijden: toon waarschuwing -->
                    <div x-show="poule.wedstrijden.length === 0" class="bg-red-500 text-white px-3 py-1 rounded text-sm font-medium">
                        ⚠ Geen wedstrijden
                    </div>
                    <!-- Afgerond: toon klaar tijdstip -->
                    <div x-show="poule.spreker_klaar" class="bg-white text-green-700 px-3 py-1 rounded text-sm font-bold">
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

            <!-- Matrix Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-200 border-b-2 border-gray-400">
                            <th class="px-2 py-2 text-center font-bold text-gray-700 w-10">Nr</th>
                            <th class="px-3 py-2 text-left font-bold text-gray-700 sticky left-0 bg-gray-200 min-w-[220px]">Naam</th>
                            <template x-for="(w, idx) in poule.wedstrijden" :key="'h-' + idx">
                                <th class="px-0 py-2 text-center font-bold text-gray-700 min-w-[70px] border-l border-gray-300" colspan="2">
                                    <div x-text="'Wed ' + (idx + 1)"></div>
                                    <div class="text-xs text-gray-500 font-normal flex justify-center">
                                        <span class="w-8">WP</span><span class="w-8">JP</span>
                                    </div>
                                </th>
                            </template>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 bg-blue-100 border-l-2 border-blue-300 w-12">WP</th>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 bg-blue-100 w-12">JP</th>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 bg-yellow-100 border-l-2 border-yellow-300 w-12">Plts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(judoka, jIdx) in poule.judokas" :key="judoka.id">
                            <tr class="border-b">
                                <!-- Nummer -->
                                <td class="px-2 py-2 text-center font-bold text-gray-600" x-text="jIdx + 1"></td>
                                <!-- Judoka naam -->
                                <td class="px-3 py-2 font-medium sticky left-0 bg-white border-r border-gray-200">
                                    <span class="font-bold" x-text="judoka.naam"></span>
                                    <span class="text-gray-500 font-normal text-sm" x-text="judoka.club ? ' (' + judoka.club + ')' : ''"></span>
                                </td>

                                <!-- Wedstrijd cellen -->
                                <template x-for="(w, wIdx) in poule.wedstrijden" :key="'c-' + judoka.id + '-' + wIdx">
                                    <td class="px-0 py-1 text-center border-l"
                                        :class="speeltInWedstrijd(judoka.id, w) ? 'border-gray-200 bg-white' : 'bg-gray-600 border-gray-500'"
                                        colspan="2">
                                        <!-- Alleen inputs tonen als judoka in deze wedstrijd speelt -->
                                        <div x-show="speeltInWedstrijd(judoka.id, w)" class="flex justify-center gap-0.5">
                                            <!-- WP: editable input, geen pijltjes -->
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="1"
                                                class="w-8 text-center border border-gray-300 rounded text-sm py-1 font-bold"
                                                :class="getWpClass(w.wpScores[judoka.id])"
                                                :value="w.wpScores[judoka.id] ?? ''"
                                                @input="updateWP(w, judoka.id, $event.target.value)"
                                                @blur="saveScore(w)"
                                            >
                                            <!-- JP: editable + dropdown suggesties, geen pijltjes -->
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                list="jp-options"
                                                maxlength="2"
                                                class="w-8 text-center border border-gray-300 rounded text-sm py-1"
                                                :value="w.jpScores[judoka.id] ?? ''"
                                                @input="updateJP(w, judoka.id, $event.target.value)"
                                                @blur="saveScore(w)"
                                            >
                                        </div>
                                    </td>
                                </template>

                                <!-- Totaal WP -->
                                <td class="px-2 py-2 text-center font-bold bg-blue-50 border-l-2 border-blue-300 text-blue-800"
                                    x-text="getTotaalWP(poule, judoka.id)"></td>
                                <!-- Totaal JP -->
                                <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-800"
                                    x-text="getTotaalJP(poule, judoka.id)"></td>
                                <!-- Plaats -->
                                <td class="px-2 py-2 text-center font-bold border-l-2 border-yellow-300"
                                    :class="getPlaatsClass(getPlaats(poule, judoka.id))"
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

    <!-- Datalist voor JP dropdown suggesties -->
    <datalist id="jp-options">
        <option value="0">
        <option value="5">
        <option value="7">
        <option value="10">
    </datalist>
</div>

<script>
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

                    // Initialize from saved scores
                    if (w.is_gespeeld || w.score_wit || w.score_blauw) {
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
            return wedstrijd.wit.id == judokaId || wedstrijd.blauw.id == judokaId;
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
            const wp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id === judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            wedstrijd.wpScores[judokaId] = wp;

            // Auto-set opponent WP
            if (wp === 2) {
                wedstrijd.wpScores[opponentId] = 0;
            } else if (wp === 0) {
                wedstrijd.wpScores[opponentId] = 2;
            }
        },

        updateJP(wedstrijd, judokaId, value) {
            const jp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id === judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            wedstrijd.jpScores[judokaId] = jp;

            // Logica: als JP > 0, dan WP automatisch 2
            if (jp > 0) {
                wedstrijd.wpScores[judokaId] = 2;
                wedstrijd.wpScores[opponentId] = 0;
            }
        },

        async saveScore(wedstrijd) {
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

            wedstrijd.is_gespeeld = !!(winnaarId || (wedstrijd.jpScores[wedstrijd.wit.id] !== undefined && wedstrijd.jpScores[wedstrijd.blauw.id] !== undefined));
            wedstrijd.winnaar_id = winnaarId;
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
        }
    }
}
</script>
@endsection
