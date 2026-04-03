@extends('layouts.app')

@section('title', __('Mobiel') . ' - ' . $toernooi->naam)

@section('content')
<div x-data="mobielApp()" class="max-w-lg mx-auto pb-24">

    {{-- Header --}}
    <div class="mb-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $toernooi->naam }}</h1>
                <p class="text-sm text-gray-500">{{ $toernooi->datum->format('d-m-Y') }}</p>
            </div>
            <a href="{{ route('toernooi.show', array_merge($toernooi->routeParams(), ['desktop' => 1])) }}"
               class="text-sm text-blue-600 hover:text-blue-800">
                {{ __('Desktop') }} &rarr;
            </a>
        </div>

        {{-- Quick stats --}}
        <div class="grid grid-cols-4 gap-2 mt-3">
            <div class="bg-blue-50 rounded-lg p-2 text-center">
                <div class="text-lg font-bold text-blue-600">{{ $statistieken['totaal_judokas'] }}</div>
                <div class="text-xs text-gray-500">{{ __("Judoka's") }}</div>
            </div>
            <div class="bg-green-50 rounded-lg p-2 text-center">
                <div class="text-lg font-bold text-green-600">{{ $statistieken['totaal_poules'] }}</div>
                <div class="text-xs text-gray-500">{{ __('Poules') }}</div>
            </div>
            <div class="bg-orange-50 rounded-lg p-2 text-center">
                <div class="text-lg font-bold text-orange-600">{{ $statistieken['totaal_wedstrijden'] }}</div>
                <div class="text-xs text-gray-500">{{ __('Wedstrijden') }}</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-2 text-center">
                <div class="text-lg font-bold text-purple-600">{{ $statistieken['aanwezig'] }}</div>
                <div class="text-xs text-gray-500">{{ __('Aanwezig') }}</div>
            </div>
        </div>
    </div>

    {{-- Tablet/PC hint --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm text-blue-700">
        <span class="font-medium">{{ __('Volledige voorbereiding?') }}</span>
        {{ __('Open de app op tablet of PC voor alle functies.') }}
    </div>

    {{-- Tab navigation --}}
    <div class="flex border-b border-gray-200 mb-4 overflow-x-auto">
        <button @click="activeTab = 'zoeken'" :class="activeTab === 'zoeken' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                class="flex-1 min-w-0 py-3 px-2 text-center text-sm font-medium border-b-2 whitespace-nowrap">
            {{ __('Zoeken') }}
        </button>
        <button @click="activeTab = 'toevoegen'" :class="activeTab === 'toevoegen' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                class="flex-1 min-w-0 py-3 px-2 text-center text-sm font-medium border-b-2 whitespace-nowrap">
            {{ __('Toevoegen') }}
        </button>
        <button @click="activeTab = 'matten'; refreshMatVoortgang()" :class="activeTab === 'matten' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                class="flex-1 min-w-0 py-3 px-2 text-center text-sm font-medium border-b-2 whitespace-nowrap">
            {{ __('Matten') }}
        </button>
        <button @click="activeTab = 'chat'" :class="activeTab === 'chat' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                class="flex-1 min-w-0 py-3 px-2 text-center text-sm font-medium border-b-2 whitespace-nowrap">
            {{ __('Chat') }}
        </button>
    </div>

    {{-- TAB 1: Judoka Zoeken --}}
    <div x-show="activeTab === 'zoeken'" x-cloak>
        <div class="relative mb-4">
            <input type="text" x-model="zoekterm" @input.debounce.300ms="zoekJudoka()"
                   placeholder="{{ __('Zoek op naam of club...') }}"
                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        {{-- Search results --}}
        <div x-show="zoekResultaten.length > 0" class="space-y-2">
            <template x-for="judoka in zoekResultaten" :key="judoka.id">
                <div class="bg-white border border-gray-200 rounded-lg p-3 cursor-pointer hover:bg-gray-50"
                     @click="selectJudoka(judoka)">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-gray-800" x-text="judoka.naam"></div>
                            <div class="text-sm text-gray-500" x-text="judoka.club || '-'"></div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="text-gray-600" x-text="judoka.leeftijdsklasse"></div>
                            <div class="font-medium" x-text="judoka.gewicht_gewogen ? judoka.gewicht_gewogen + ' kg' : 'Niet gewogen'"></div>
                        </div>
                    </div>
                    <div class="flex items-center mt-1 text-xs text-gray-400 space-x-2">
                        <span x-show="judoka.blok" x-text="'Blok ' + judoka.blok"></span>
                        <span x-show="judoka.band" x-text="judoka.band"></span>
                        <span :class="judoka.aanwezig ? 'text-green-600' : 'text-red-600'"
                              x-text="judoka.aanwezig ? '{{ __('Aanwezig') }}' : '{{ __('Afwezig') }}'"></span>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="zoekterm.length >= 2 && zoekResultaten.length === 0 && !zoekLoading" class="text-center text-gray-500 py-8">
            {{ __('Geen resultaten gevonden') }}
        </div>
        <div x-show="zoekLoading" class="text-center text-gray-400 py-8">
            {{ __('Zoeken...') }}
        </div>

        {{-- Selected judoka detail --}}
        <div x-show="geselecteerdeJudoka" x-cloak class="mt-4">
            <div class="bg-white border-2 border-blue-300 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2" x-text="geselecteerdeJudoka?.naam"></h3>

                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div><span class="text-gray-500">{{ __('Club') }}:</span> <span x-text="geselecteerdeJudoka?.club || '-'"></span></div>
                    <div><span class="text-gray-500">{{ __('Blok') }}:</span> <span x-text="geselecteerdeJudoka?.blok || '-'"></span></div>
                    <div><span class="text-gray-500">{{ __('Categorie') }}:</span> <span x-text="geselecteerdeJudoka?.leeftijdsklasse || '-'"></span></div>
                    <div><span class="text-gray-500">{{ __('Gewicht') }}:</span> <span x-text="geselecteerdeJudoka?.gewicht_gewogen ? geselecteerdeJudoka.gewicht_gewogen + ' kg' : '-'"></span></div>
                </div>

                {{-- Weight input --}}
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gewicht invullen/wijzigen') }}</label>
                    <div class="flex space-x-2">
                        <input type="number" x-model="nieuwGewicht" step="0.1" min="10" max="200"
                               placeholder="{{ __('Gewicht in kg') }}"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-base">
                        <button @click="updateGewicht()" :disabled="gewichtLoading"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium disabled:opacity-50">
                            {{ __('Opslaan') }}
                        </button>
                    </div>
                </div>

                {{-- Poule info + move --}}
                <div class="border-t pt-3">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">{{ __('Poule verplaatsen') }}</span>
                    </div>
                    <div class="flex space-x-2">
                        <select x-model="doelPouleId" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-base">
                            <option value="">{{ __('Kies poule...') }}</option>
                            <template x-for="poule in beschikbarePoules" :key="poule.id">
                                <option :value="poule.id" x-text="'Poule ' + poule.nummer + ' - ' + poule.leeftijdsklasse + (poule.gewichtsklasse ? ' (' + poule.gewichtsklasse + ')' : '')"></option>
                            </template>
                        </select>
                        <button @click="verplaatsJudoka()" :disabled="!doelPouleId || verplaatsLoading"
                                class="bg-orange-600 text-white px-4 py-2 rounded-lg font-medium disabled:opacity-50">
                            {{ __('Verplaats') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: Judoka Toevoegen --}}
    <div x-show="activeTab === 'toevoegen'" x-cloak>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="font-medium text-gray-800 mb-3">{{ __('Nieuwe judoka toevoegen aan poule') }}</h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('Naam') }} *</label>
                    <input type="text" x-model="nieuweJudoka.naam" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('Geboortejaar') }}</label>
                        <input type="number" x-model="nieuweJudoka.geboortejaar" min="1990" max="{{ date('Y') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('Gewicht (kg)') }}</label>
                        <input type="number" x-model="nieuweJudoka.gewicht" step="0.1" min="10" max="200"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('Band') }}</label>
                        <select x-model="nieuweJudoka.band" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                            <option value="">-</option>
                            <option value="wit">{{ __('Wit') }}</option>
                            <option value="geel">{{ __('Geel') }}</option>
                            <option value="oranje">{{ __('Oranje') }}</option>
                            <option value="groen">{{ __('Groen') }}</option>
                            <option value="blauw">{{ __('Blauw') }}</option>
                            <option value="bruin">{{ __('Bruin') }}</option>
                            <option value="zwart">{{ __('Zwart') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('Club') }}</label>
                        <select x-model="nieuweJudoka.club_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                            <option value="">-</option>
                            @foreach($clubs as $club)
                                <option value="{{ $club->id }}">{{ $club->naam }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('Poule') }} *</label>
                    <select x-model="nieuweJudoka.poule_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base">
                        <option value="">{{ __('Kies poule...') }}</option>
                        <template x-for="poule in beschikbarePoules" :key="poule.id">
                            <option :value="poule.id" x-text="'Poule ' + poule.nummer + ' - ' + poule.leeftijdsklasse + (poule.gewichtsklasse ? ' (' + poule.gewichtsklasse + ')' : '')"></option>
                        </template>
                    </select>
                </div>
                <button @click="voegJudokaToe()" :disabled="!nieuweJudoka.naam || !nieuweJudoka.poule_id || toevoegenLoading"
                        class="w-full bg-green-600 text-white py-3 rounded-lg font-medium text-base disabled:opacity-50">
                    <span x-show="!toevoegenLoading">{{ __('Judoka toevoegen') }}</span>
                    <span x-show="toevoegenLoading">{{ __('Bezig...') }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- TAB 3: Mat Voortgang --}}
    <div x-show="activeTab === 'matten'" x-cloak>
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-medium text-gray-800">{{ __('Voortgang per mat') }}</h3>
            <button @click="refreshMatVoortgang()" class="text-sm text-blue-600">{{ __('Ververs') }}</button>
        </div>

        <div class="space-y-3">
            <template x-for="mat in matVoortgang" :key="mat.id">
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    {{-- Mat header --}}
                    <div class="p-3 flex justify-between items-center cursor-pointer" @click="mat._open = !mat._open">
                        <div>
                            <span class="font-medium text-gray-800" x-text="mat.naam"></span>
                            <span class="text-sm text-gray-500 ml-2" x-text="mat.gespeeld + '/' + mat.totaal_wedstrijden + ' wedstrijden'"></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-bold" :class="mat.resterend === 0 ? 'text-green-600' : 'text-orange-600'"
                                  x-text="mat.resterend + ' over'"></span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="mat._open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    <div class="px-3 pb-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all"
                                 :class="mat.resterend === 0 ? 'bg-green-500' : 'bg-blue-500'"
                                 :style="'width: ' + (mat.totaal_wedstrijden > 0 ? Math.round(mat.gespeeld / mat.totaal_wedstrijden * 100) : 0) + '%'">
                            </div>
                        </div>
                    </div>

                    {{-- Poule details (expandable) --}}
                    <div x-show="mat._open" x-collapse class="border-t bg-gray-50">
                        <template x-for="poule in mat.poules" :key="poule.id">
                            <div class="px-3 py-2 flex justify-between items-center border-b border-gray-100 last:border-0 text-sm">
                                <div>
                                    <span class="text-gray-700" x-text="'P' + poule.nummer"></span>
                                    <span class="text-gray-500 ml-1" x-text="poule.leeftijdsklasse"></span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="text-gray-600" x-text="poule.gespeeld + '/' + poule.totaal"></span>
                                    <span class="font-medium min-w-[3rem] text-right"
                                          :class="poule.resterend === 0 ? 'text-green-600' : 'text-orange-600'"
                                          x-text="poule.resterend + ' over'"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="mat.poules.length === 0" class="px-3 py-2 text-sm text-gray-400">
                            {{ __('Geen poules op deze mat') }}
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="matVoortgang.length === 0" class="text-center text-gray-500 py-8">
            {{ __('Geen matten geconfigureerd') }}
        </div>
    </div>

    {{-- TAB 4: Chat --}}
    <div x-show="activeTab === 'chat'" x-cloak>
        @include('partials.chat-widget', [
            'chatType' => 'hoofdjury',
            'toernooiId' => $toernooi->id,
            'chatApiBase' => route('toernooi.chat.index', $toernooi->routeParams()),
        ])
    </div>

    {{-- Toast notification --}}
    <div x-show="toast.show" x-transition:enter="transition ease-out duration-300"
         x-transition:leave="transition ease-in duration-200"
         class="fixed bottom-4 left-4 right-4 max-w-lg mx-auto z-50 text-white px-4 py-3 rounded-lg shadow-lg"
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'">
        <span x-text="toast.message"></span>
    </div>
</div>

<script>
function mobielApp() {
    return {
        activeTab: 'zoeken',

        // Search
        zoekterm: '',
        zoekResultaten: [],
        zoekLoading: false,
        geselecteerdeJudoka: null,
        nieuwGewicht: '',
        gewichtLoading: false,
        doelPouleId: '',
        verplaatsLoading: false,

        // Add judoka
        nieuweJudoka: { naam: '', geboortejaar: '', gewicht: '', band: '', club_id: '', poule_id: '' },
        toevoegenLoading: false,

        // Mat progress
        matVoortgang: @json($matVoortgang).map(m => ({...m, _open: false})),

        // Available poules (loaded once)
        beschikbarePoules: [],

        // Toast
        toast: { show: false, message: '', type: 'success' },

        init() {
            this.laadPoules();
        },

        async laadPoules() {
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.poules-api", $toernooi->routeParams()) }}');
                this.beschikbarePoules = await response.json();
            } catch (e) {
                console.error('Failed to load poules', e);
            }
        },

        async zoekJudoka() {
            if (this.zoekterm.length < 2) {
                this.zoekResultaten = [];
                return;
            }
            this.zoekLoading = true;
            try {
                const response = await fetch('{{ route("toernooi.judoka.zoek", $toernooi->routeParams()) }}?q=' + encodeURIComponent(this.zoekterm));
                this.zoekResultaten = await response.json();
            } catch (e) {
                console.error('Search failed', e);
            }
            this.zoekLoading = false;
        },

        selectJudoka(judoka) {
            this.geselecteerdeJudoka = judoka;
            this.nieuwGewicht = judoka.gewicht_gewogen || '';
            this.doelPouleId = '';
        },

        async updateGewicht() {
            if (!this.geselecteerdeJudoka || !this.nieuwGewicht) return;
            this.gewichtLoading = true;
            try {
                const response = await fetch('{{ route("toernooi.weging.registreer", array_merge($toernooi->routeParams(), ["judoka" => "__ID__"])) }}'.replace('__ID__', this.geselecteerdeJudoka.id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ gewicht: parseFloat(this.nieuwGewicht) }),
                });
                const data = await response.json();
                if (response.ok) {
                    this.geselecteerdeJudoka.gewicht_gewogen = parseFloat(this.nieuwGewicht);
                    this.showToast('Gewicht opgeslagen', 'success');
                } else {
                    this.showToast(data.message || 'Fout bij opslaan', 'error');
                }
            } catch (e) {
                this.showToast('Netwerkfout', 'error');
            }
            this.gewichtLoading = false;
        },

        async verplaatsJudoka() {
            if (!this.geselecteerdeJudoka || !this.doelPouleId) return;
            this.verplaatsLoading = true;
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.verplaats-judoka", $toernooi->routeParams()) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        judoka_id: this.geselecteerdeJudoka.id,
                        poule_id: parseInt(this.doelPouleId),
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast(this.geselecteerdeJudoka.naam + ' verplaatst', 'success');
                    this.geselecteerdeJudoka = null;
                    this.doelPouleId = '';
                    // Re-search to update results
                    if (this.zoekterm.length >= 2) this.zoekJudoka();
                } else {
                    this.showToast(data.error || data.message || 'Verplaatsen mislukt', 'error');
                }
            } catch (e) {
                this.showToast('Netwerkfout', 'error');
            }
            this.verplaatsLoading = false;
        },

        async voegJudokaToe() {
            if (!this.nieuweJudoka.naam || !this.nieuweJudoka.poule_id) return;
            this.toevoegenLoading = true;
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.nieuwe-judoka", $toernooi->routeParams()) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        naam: this.nieuweJudoka.naam,
                        geboortejaar: this.nieuweJudoka.geboortejaar ? parseInt(this.nieuweJudoka.geboortejaar) : null,
                        gewicht: this.nieuweJudoka.gewicht ? parseFloat(this.nieuweJudoka.gewicht) : null,
                        band: this.nieuweJudoka.band || null,
                        club_id: this.nieuweJudoka.club_id ? parseInt(this.nieuweJudoka.club_id) : null,
                        poule_id: parseInt(this.nieuweJudoka.poule_id),
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast(data.message || 'Judoka toegevoegd', 'success');
                    this.nieuweJudoka = { naam: '', geboortejaar: '', gewicht: '', band: '', club_id: '', poule_id: '' };
                } else {
                    this.showToast(data.message || 'Toevoegen mislukt', 'error');
                }
            } catch (e) {
                this.showToast('Netwerkfout', 'error');
            }
            this.toevoegenLoading = false;
        },

        async refreshMatVoortgang() {
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.mat-voortgang", $toernooi->routeParams()) }}');
                const data = await response.json();
                // Preserve _open state
                this.matVoortgang = data.map(m => {
                    const existing = this.matVoortgang.find(em => em.id === m.id);
                    return {...m, _open: existing?._open ?? false};
                });
            } catch (e) {
                console.error('Failed to refresh mat progress', e);
            }
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3000);
        },
    };
}
</script>
@endsection
