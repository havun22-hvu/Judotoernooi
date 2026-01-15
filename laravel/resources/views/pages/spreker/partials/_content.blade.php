@php $pwaApp = 'spreker'; @endphp
<div x-data="sprekerInterface()" x-cloak>
    <!-- Tab Navigation -->
    <div class="flex border-b border-gray-200 mb-4 bg-white rounded-t-lg shadow-sm">
        <button
            @click="activeTab = 'uitslagen'"
            :class="activeTab === 'uitslagen' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors"
        >
            <span class="text-lg">🏆</span> Uitslagen
            @if($klarePoules->isNotEmpty())
            <span class="ml-1 bg-green-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $klarePoules->count() }}</span>
            @endif
        </button>
        <button
            @click="activeTab = 'oproepen'"
            :class="activeTab === 'oproepen' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors"
        >
            <span class="text-lg">📣</span> Oproepen
        </button>
        <button
            @click="activeTab = 'notities'"
            :class="activeTab === 'notities' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors"
        >
            <span class="text-lg">📝</span> Notities
        </button>
    </div>

    <!-- Melding: auto-refresh staat uit -->
    <div x-show="activeTab !== 'uitslagen'" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mb-4 flex items-center gap-2 text-amber-800 text-sm">
        <span>⚠️</span>
        <span>Auto-refresh staat uit. Ga naar <button @click="activeTab = 'uitslagen'" class="underline font-medium">Uitslagen</button> voor automatische updates.</span>
    </div>

    <!-- TAB 1: UITSLAGEN -->
    <div x-show="activeTab === 'uitslagen'">
        <div class="flex justify-end items-center mb-4 gap-2">
            <button
                @click="toonGeschiedenis = !toonGeschiedenis"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2"
            >
                <span>📋</span> Vorige
            </button>
            <div class="text-xs text-gray-500">
                Auto-refresh 10s
            </div>
        </div>

        <!-- Geschiedenis van afgeroepen poules (opgeslagen in localStorage) -->
        <div x-show="toonGeschiedenis" x-cloak class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-center mb-3">
                <span class="text-gray-700 font-bold">📋 Eerder afgeroepen (vandaag)</span>
                <button @click="toonGeschiedenis = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <template x-if="geschiedenis.length === 0">
                <p class="text-gray-500 text-sm">Nog geen prijsuitreikingen vandaag</p>
            </template>
            <template x-if="geschiedenis.length > 0">
                <div class="grid gap-2 max-h-64 overflow-y-auto">
                    <template x-for="item in geschiedenis" :key="item.id + '-' + item.tijd">
                        <div class="flex justify-between items-center bg-white px-3 py-2 rounded border text-sm">
                            <span>
                                <span :class="item.type === 'eliminatie' ? 'text-purple-600' : 'text-green-600'" class="font-medium" x-text="item.naam"></span>
                            </span>
                            <span class="text-gray-400" x-text="item.tijd"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- TERUG SECTIE: Recent afgeroepen poules -->
        @if($afgeroepen->isNotEmpty())
        <div class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-orange-700 font-bold">⚠️ AL AFGEROEPEN - per ongeluk? Klik om terug te zetten:</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($afgeroepen as $poule)
                <button
                    onclick="zetTerug({{ $poule->id }}, this)"
                    class="bg-orange-100 hover:bg-green-200 text-orange-800 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors border border-orange-300"
                >
                    <span>↩️</span>
                    <span class="line-through opacity-70">
                        @if($poule->type === 'eliminatie')
                            Elim. {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                        @else
                            Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                        @endif
                    </span>
                    <span class="text-orange-500 text-xs">({{ $poule->afgeroepen_at->format('H:i') }})</span>
                </button>
                @endforeach
            </div>
        </div>
        @endif

        @if($klarePoules->isEmpty())
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <div class="text-6xl mb-4">🎙️</div>
            <h2 class="text-2xl font-bold text-gray-600 mb-2">Wachten op uitslagen...</h2>
            <p class="text-gray-500">Afgeronde poules verschijnen hier automatisch</p>
        </div>
        @else
        <div class="space-y-6">
            @foreach($klarePoules as $poule)
            <div class="bg-white rounded-lg shadow overflow-hidden" id="poule-{{ $poule->id }}">
                <!-- Header -->
                <div class="{{ $poule->is_eliminatie ? 'bg-purple-700' : 'bg-green-700' }} text-white px-4 py-3 flex justify-between items-center">
                    <div>
                        <div class="font-bold text-lg">
                            @if($poule->is_eliminatie)
                                Eliminatie - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                            @else
                                Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                            @endif
                        </div>
                        <div class="{{ $poule->is_eliminatie ? 'text-purple-200' : 'text-green-200' }} text-sm">
                            Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }} | Klaar: {{ $poule->spreker_klaar->format('H:i') }}
                        </div>
                    </div>
                    @php
                        $pouleNaam = $poule->is_eliminatie
                            ? "Elim. {$poule->nummer} - {$poule->leeftijdsklasse} {$poule->gewichtsklasse}"
                            : "Poule {$poule->nummer} - {$poule->leeftijdsklasse} {$poule->gewichtsklasse}";
                        $pouleType = $poule->is_eliminatie ? 'eliminatie' : 'poule';
                    @endphp
                    <button
                        @click="markeerAfgeroepen({{ $poule->id }}, '{{ addslashes($pouleNaam) }}', '{{ $pouleType }}')"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-bold flex items-center gap-2"
                    >
                        ✓ Afgerond
                    </button>
                </div>

                @if($poule->is_eliminatie)
                <!-- ELIMINATIE: Medaille winnaars -->
                <div class="p-4">
                    <div class="grid gap-3">
                        @foreach($poule->standings as $standing)
                        @php $plaats = $standing['plaats']; @endphp
                        <div class="flex items-center gap-3 p-3 rounded-lg
                            @if($plaats === 1) bg-gradient-to-r from-yellow-100 to-yellow-200 border-2 border-yellow-400
                            @elseif($plaats === 2) bg-gradient-to-r from-gray-100 to-gray-200 border-2 border-gray-400
                            @else bg-gradient-to-r from-orange-100 to-orange-200 border-2 border-orange-400
                            @endif">
                            <div class="text-3xl">
                                @if($plaats === 1) 🥇
                                @elseif($plaats === 2) 🥈
                                @else 🥉
                                @endif
                            </div>
                            <div>
                                <div class="font-bold text-lg">{{ $standing['judoka']->naam }}</div>
                                <div class="text-sm text-gray-600">{{ $standing['judoka']->club?->naam ?? '-' }}</div>
                            </div>
                            <div class="ml-auto text-2xl font-bold
                                @if($plaats === 1) text-yellow-700
                                @elseif($plaats === 2) text-gray-700
                                @else text-orange-700
                                @endif">
                                {{ $plaats }}e
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <!-- POULE: Resultaten tabel -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-200 border-b-2 border-gray-400">
                                <th class="px-3 py-2 text-left font-bold text-gray-700">Naam</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">WP</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">JP</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">#</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($poule->standings as $index => $standing)
                            @php $plaats = $index + 1; @endphp
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2">
                                    <span class="font-bold">{{ $standing['judoka']->naam }}</span>
                                    <span class="text-gray-500 text-xs">({{ $standing['judoka']->club?->naam ?? '-' }})</span>
                                </td>
                                <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-800">{{ $standing['wp'] }}</td>
                                <td class="px-2 py-2 text-center bg-blue-50 text-blue-800">{{ $standing['jp'] }}</td>
                                <td class="px-2 py-2 text-center font-bold text-lg
                                    @if($plaats === 1) bg-yellow-400 text-yellow-900
                                    @elseif($plaats === 2) bg-gray-300 text-gray-800
                                    @elseif($plaats === 3) bg-orange-300 text-orange-900
                                    @else bg-yellow-50
                                    @endif">
                                    {{ $plaats }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- TAB 2: OPROEPEN (Poules per blok per mat) -->
    <div x-show="activeTab === 'oproepen'">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <p class="text-blue-800 text-sm">
                <span class="font-bold">📣 Oproep hulp:</span> Gebruik deze lijst om judoka's naar de juiste mat te roepen.
            </p>
        </div>

        @if(isset($poulesPerBlok) && $poulesPerBlok->isNotEmpty())
            <!-- Blok selector -->
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($poulesPerBlok as $blokNr => $data)
                <button
                    @click="selectedBlok = {{ $blokNr }}"
                    :class="selectedBlok === {{ $blokNr }} ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-4 py-2 rounded-lg font-medium transition-colors"
                >
                    Blok {{ $blokNr }}
                </button>
                @endforeach
            </div>

            <!-- Poules per mat voor geselecteerd blok -->
            @foreach($poulesPerBlok as $blokNr => $data)
            <div x-show="selectedBlok === {{ $blokNr }}" class="space-y-4">
                @if($data['matten']->isEmpty())
                <div class="bg-gray-100 rounded-lg p-8 text-center">
                    <p class="text-gray-500">Geen poules toegewezen aan matten in dit blok</p>
                </div>
                @else
                    @foreach($data['matten'] as $matData)
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-gray-800 text-white px-4 py-2 font-bold">
                            Mat {{ $matData['mat']->nummer ?? '?' }}
                            <span class="text-gray-400 font-normal ml-2">({{ $matData['poules']->count() }} poules)</span>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @foreach($matData['poules'] as $poule)
                            <div class="px-4 py-3 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-bold text-gray-800">
                                            {{ $poule->titel ?: "Poule {$poule->nummer}" }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $poule->judokas->count() }} judoka's
                                        </div>
                                    </div>
                                    <button
                                        @click="togglePouleDetail({{ $poule->id }})"
                                        class="text-blue-600 hover:text-blue-800 text-sm"
                                    >
                                        <span x-text="openPoules.includes({{ $poule->id }}) ? '▼ Verberg' : '▶ Toon namen'"></span>
                                    </button>
                                </div>
                                <!-- Judoka namen (inklapbaar) -->
                                <div x-show="openPoules.includes({{ $poule->id }})" class="mt-2 pl-4 border-l-2 border-blue-200">
                                    <div class="grid grid-cols-1 gap-1 text-sm">
                                        @foreach($poule->judokas as $judoka)
                                        <div class="flex justify-between">
                                            <span class="font-medium">{{ $judoka->naam }}</span>
                                            <span class="text-gray-500">{{ $judoka->club?->naam ?? '-' }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
            @endforeach
        @else
        <div class="bg-gray-100 rounded-lg p-8 text-center">
            <p class="text-gray-500">Geen blokken/poules beschikbaar</p>
        </div>
        @endif
    </div>

    <!-- TAB 3: NOTITIES (Spiekbriefje) -->
    <div x-show="activeTab === 'notities'">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
            <p class="text-yellow-800 text-sm">
                <span class="font-bold">📝 Spiekbriefje:</span> Maak notities voor het welkomstwoord en andere aandachtspunten.
                Notities worden lokaal opgeslagen op dit apparaat.
            </p>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Welkomstwoord / Aandachtspunten</label>
                <textarea
                    x-model="notities"
                    @input="saveNotities()"
                    rows="12"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Typ hier je notities of gebruik de knop hieronder voor een voorbeeldtekst..."
                ></textarea>
            </div>
            <div class="flex justify-between items-center text-sm text-gray-500">
                <span x-show="notitiesSaved" class="text-green-600">✓ Opgeslagen</span>
                <button
                    @click="clearNotities()"
                    class="text-red-600 hover:text-red-800"
                >
                    Wis notities
                </button>
            </div>
        </div>

        <!-- Voorbeeldtekst laden -->
        <div class="mt-4">
            <button
                @click="laadVoorbeeldtekst()"
                class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium"
            >
                📋 Laad voorbeeldtekst
            </button>
        </div>
    </div>
</div>

<script>
// Terug functie - zet afgeroepen poule terug naar klaar
async function zetTerug(pouleId, button) {
    try {
        button.disabled = true;
        button.innerHTML = '⏳ Bezig...';

        const response = await fetch('{{ route('toernooi.spreker.terug', $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ poule_id: pouleId })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Fout: ' + (data.message || 'Onbekende fout'));
            button.disabled = false;
            button.innerHTML = '↩️ Terug';
        }
    } catch (err) {
        alert('Fout: ' + err.message);
        button.disabled = false;
    }
}

function sprekerInterface() {
    const STORAGE_KEY = 'spreker_geschiedenis_{{ $toernooi->id }}';
    const NOTITIES_KEY = 'spreker_notities_{{ $toernooi->id }}';
    const vandaag = new Date().toDateString();

    return {
        activeTab: 'uitslagen',
        toonGeschiedenis: false,
        geschiedenis: [],
        selectedBlok: {{ isset($poulesPerBlok) && $poulesPerBlok->isNotEmpty() ? $poulesPerBlok->keys()->first() : 1 }},
        openPoules: [],
        notities: '',
        notitiesSaved: false,

        init() {
            // Laad geschiedenis uit localStorage
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                if (data.datum === vandaag) {
                    this.geschiedenis = data.items || [];
                } else {
                    this.geschiedenis = [];
                }
            }

            // Voeg bestaande afgeroepen poules uit database toe
            @php
                $dbAfgeroepenData = $toernooi->poules()
                    ->whereNotNull('afgeroepen_at')
                    ->whereDate('afgeroepen_at', today())
                    ->get()
                    ->map(function($p) {
                        return [
                            'id' => $p->id,
                            'naam' => $p->type === 'eliminatie'
                                ? "Elim. {$p->nummer} - {$p->leeftijdsklasse} {$p->gewichtsklasse}"
                                : "Poule {$p->nummer} - {$p->leeftijdsklasse} {$p->gewichtsklasse}",
                            'type' => $p->type === 'eliminatie' ? 'eliminatie' : 'poule',
                            'tijd' => $p->afgeroepen_at->format('H:i')
                        ];
                    });
            @endphp
            const dbAfgeroepen = @json($dbAfgeroepenData);

            dbAfgeroepen.forEach(item => {
                if (!this.geschiedenis.find(g => g.id === item.id && g.tijd === item.tijd)) {
                    this.geschiedenis.push(item);
                }
            });

            this.geschiedenis.sort((a, b) => b.tijd.localeCompare(a.tijd));
            this.saveGeschiedenis();

            // Laad notities
            const storedNotities = localStorage.getItem(NOTITIES_KEY);
            if (storedNotities) {
                this.notities = storedNotities;
            }
        },

        saveGeschiedenis() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                datum: vandaag,
                items: this.geschiedenis
            }));
        },

        addToGeschiedenis(pouleId, naam, type, tijd) {
            this.geschiedenis.unshift({ id: pouleId, naam, type, tijd });
            this.saveGeschiedenis();
        },

        async markeerAfgeroepen(pouleId, pouleNaam, pouleType) {
            try {
                const response = await fetch('{{ route('toernooi.spreker.afgeroepen', $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ poule_id: pouleId })
                });

                const data = await response.json();
                if (data.success) {
                    const tijd = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
                    this.addToGeschiedenis(pouleId, pouleNaam, pouleType, tijd);

                    const element = document.getElementById('poule-' + pouleId);
                    if (element) {
                        element.style.transition = 'opacity 0.3s, transform 0.3s';
                        element.style.opacity = '0';
                        element.style.transform = 'translateX(100px)';
                        setTimeout(() => element.remove(), 300);
                    }
                }
            } catch (err) {
                alert('Fout: ' + err.message);
            }
        },

        togglePouleDetail(pouleId) {
            if (this.openPoules.includes(pouleId)) {
                this.openPoules = this.openPoules.filter(id => id !== pouleId);
            } else {
                this.openPoules.push(pouleId);
            }
        },

        saveNotities() {
            localStorage.setItem(NOTITIES_KEY, this.notities);
            this.notitiesSaved = true;
            setTimeout(() => this.notitiesSaved = false, 2000);
        },

        clearNotities() {
            if (confirm('Weet je zeker dat je alle notities wilt wissen?')) {
                this.notities = '';
                localStorage.removeItem(NOTITIES_KEY);
            }
        },

        laadVoorbeeldtekst() {
            const voorbeeldtekst = `WELKOMSTWOORD
- Welkom bij het 7e WestFries Open Judotoernooi!
- Namens Judoschool Cees Veen wensen wij iedereen een sportieve dag
- Dank aan alle vrijwilligers en scheidsrechters

HUISREGELS
- Roken en vapen is verboden in het hele gebouw
- Honden alleen buiten (behalve hulphonden)
- Alleen judoka's en coaches op de wedstrijdvloer
- Schoenen uit op de mat!

PRAKTISCH
- Kantine open tot 17:00
- Toiletten bij de ingang en achter de kantine
- EHBO-post naast de jury tafel
- Gevonden voorwerpen bij de inschrijftafel

PRIJSUITREIKING
- Direct na de laatste wedstrijd per categorie
- Judoka's verzamelen bij de podiummat
- Ouders welkom om foto's te maken`;

            if (this.notities && !confirm('Dit vervangt je huidige notities. Doorgaan?')) {
                return;
            }
            this.notities = voorbeeldtekst;
            this.saveNotities();
        }
    }
}

// Auto-refresh elke 10 seconden (ALLEEN als uitslagen tab actief is)
setInterval(function() {
    // Check via data attribute welke tab actief is
    const activeTabBtn = document.querySelector('[x-data] button.border-blue-500');
    const isUitslagenActive = activeTabBtn && activeTabBtn.textContent.includes('Uitslagen');

    // Alleen refreshen als we op de uitslagen tab zitten
    if (isUitslagenActive) {
        location.reload();
    }
}, 10000);
</script>
