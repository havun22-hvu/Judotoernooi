@php
    $pwaApp = 'spreker';
    // API URLs - different for device-bound vs admin access
    if (isset($toegang)) {
        $routeParams = [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'toegang' => $toegang->id,
        ];
        $terugUrl = route('spreker.terug', $routeParams);
        $notitiesGetUrl = route('spreker.notities.get', $routeParams);
        $notitiesSaveUrl = route('spreker.notities.save', $routeParams);
        $afgeroepenUrl = route('spreker.afgeroepen', $routeParams);
        $standingsUrl = route('spreker.standings', $routeParams);
    } else {
        $terugUrl = route('toernooi.spreker.terug', $toernooi->routeParams());
        $notitiesGetUrl = route('toernooi.spreker.notities.get', $toernooi->routeParams());
        $notitiesSaveUrl = route('toernooi.spreker.notities.save', $toernooi->routeParams());
        $afgeroepenUrl = route('toernooi.spreker.afgeroepen', $toernooi->routeParams());
        $standingsUrl = route('toernooi.spreker.standings', $toernooi->routeParams());
    }
@endphp
<div x-data="sprekerInterface()" x-cloak>
    <!-- Feedback bar (groene balk) -->
    <div
        x-show="showFeedbackBar"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg font-medium flex items-center gap-2"
    >
        <span>✓</span>
        <span x-text="feedbackMessage"></span>
    </div>

    <!-- Tab Navigation - Sticky -->
    <div class="flex border-b border-gray-200 mb-4 bg-white rounded-t-lg shadow-sm sticky top-[60px] z-40">
        <button
            @click="activeTab = 'uitslagen'"
            :class="activeTab === 'uitslagen' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors"
        >
            <span class="text-lg">🏆</span> Uitslagen
            <span x-show="klarePouleCount > 0" class="ml-1 bg-green-500 text-white text-xs px-2 py-0.5 rounded-full" x-text="klarePouleCount"></span>
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

    <!-- TAB 1: UITSLAGEN -->
    <div x-show="activeTab === 'uitslagen'">
        <div class="flex justify-end items-center mb-4 gap-2">
            <button
                @click="toonGeschiedenis = !toonGeschiedenis"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2"
            >
                <span>📋</span> Vorige
            </button>
            <button
                @click="refreshUitslagen()"
                :disabled="isRefreshing"
                class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 disabled:opacity-50"
            >
                <span :class="isRefreshing ? 'animate-spin' : ''">🔄</span>
                <span x-text="isRefreshing ? 'Laden...' : 'Vernieuwen'"></span>
            </button>
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
                        <button
                            @click="toonPouleDetail(item.id)"
                            class="flex justify-between items-center bg-white px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors w-full text-left"
                        >
                            <span>
                                <span :class="item.type === 'eliminatie' ? 'text-purple-600' : 'text-green-600'" class="font-medium" x-text="item.naam"></span>
                            </span>
                            <span class="text-gray-400 flex items-center gap-2">
                                <span x-text="item.tijd"></span>
                                <span class="text-blue-500">👁️</span>
                            </span>
                        </button>
                    </template>
                </div>
            </template>
        </div>


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
                        <div class="font-bold text-lg flex items-center gap-2">
                            @if($poule->is_eliminatie)
                                Eliminatie - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                            @else
                                Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                            @endif
                            @if(!empty($poule->has_barrage))
                                <span class="bg-yellow-500 text-yellow-900 text-xs px-2 py-0.5 rounded-full font-bold animate-pulse">⚔️ BARRAGE</span>
                            @endif
                        </div>
                        <div class="{{ $poule->is_eliminatie ? 'text-purple-200' : 'text-green-200' }} text-sm">
                            Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }} | Klaar: {{ $poule->spreker_klaar->format('H:i') }}
                            @if(!empty($poule->has_barrage))
                                <span class="text-yellow-300">• Incl. barrage punten</span>
                            @endif
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
                            @php
                                $pouleActiveJudokas = $poule->judokas->filter(fn($j) => $j->gewicht_gewogen !== null && $j->aanwezigheid !== 'afwezig');
                            @endphp
                            <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer" @click="togglePouleDetail({{ $poule->id }})">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-gray-800">
                                            <span x-text="openPoules.includes({{ $poule->id }}) ? '▼' : '▶'" class="mr-1"></span>
                                            Poule {{ $poule->nummer }} - {{ $poule->getDisplayTitel() }}
                                        </div>
                                        <div class="text-sm text-gray-500 ml-4">
                                            {{ $pouleActiveJudokas->count() }} judoka's
                                        </div>
                                    </div>
                                </div>
                                <!-- Judoka namen (inklapbaar) - alleen aanwezige judoka's -->
                                <div x-show="openPoules.includes({{ $poule->id }})" class="mt-2 pl-4 border-l-2 border-blue-200">
                                    <div class="grid grid-cols-1 gap-1 text-sm">
                                        @foreach($pouleActiveJudokas as $judoka)
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

    <!-- TAB 3: NOTITIES (Spiekbriefje) - Schermvullend -->
    <div x-show="activeTab === 'notities'" x-data="{ fontSize: 18 }" class="flex flex-col">
        <!-- Textarea - schermvullend tot aan toolbar (60px toolbar + 120px header/tabs) -->
        <textarea
            x-model="notities"
            @input.debounce.2000ms="autoSaveNotities()"
            :style="'font-size: ' + fontSize + 'px; line-height: 1.5; height: calc(100vh - 180px);'"
            class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none break-words mb-16"
            style="word-wrap: break-word; overflow-wrap: break-word;"
            :class="{ 'border-yellow-400': hasUnsavedChanges }"
            placeholder="Typ hier je notities..."
        ></textarea>

        <!-- Fixed werkbalk onderaan scherm -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-gray-300 shadow-lg px-4 py-3 z-50">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Zoom controls -->
                <div class="flex items-center gap-2 border-r pr-3 mr-1">
                    <button
                        @click="fontSize = Math.max(14, fontSize - 2)"
                        class="w-12 h-12 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-lg text-2xl font-bold"
                        title="Kleiner"
                    >−</button>
                    <span class="w-10 text-center text-base font-medium text-gray-600" x-text="fontSize"></span>
                    <button
                        @click="fontSize = Math.min(48, fontSize + 2)"
                        class="w-12 h-12 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-lg text-2xl font-bold"
                        title="Groter"
                    >+</button>
                </div>

                <!-- Opslaan -->
                <button
                    @click="saveNotities()"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg text-xl font-medium"
                >
                    💾
                </button>

                <!-- Template dropdown -->
                <select
                    x-model="selectedTemplate"
                    @change="laadTemplate()"
                    class="px-3 py-3 border-2 border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 flex-1 min-w-[120px] max-w-[200px]"
                >
                    <option value="">📋 Template...</option>
                    <template x-for="(template, index) in templates" :key="index">
                        <option :value="index" x-text="template.naam"></option>
                    </template>
                </select>

                <!-- Templates beheren (inclusief opslaan als template) -->
                <button
                    @click="showTemplateModal = true"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-lg text-xl"
                    title="Templates beheren"
                >
                    ⚙️
                </button>

                <!-- Wis -->
                <button
                    @click="clearNotities()"
                    class="text-red-600 hover:text-red-800 px-4 py-3 text-xl"
                    title="Notities wissen"
                >
                    🗑️
                </button>

                <!-- Status indicators (rechts) -->
                <div class="ml-auto flex items-center gap-2 text-base">
                    <span x-show="autoSaving" x-cloak class="text-gray-400">⏳</span>
                    <span x-show="hasUnsavedChanges && !autoSaving" x-cloak class="text-yellow-600 text-xl">●</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Opslaan als template -->
    <div x-show="showSaveAsModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50" @click.self="showSaveAsModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center rounded-t-lg">
                <span class="font-bold">📋 Opslaan als template</span>
                <button @click="showSaveAsModal = false" class="text-white hover:text-gray-200 text-xl">&times;</button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Template naam:</label>
                    <input
                        type="text"
                        x-model="saveAsNaam"
                        placeholder="Bijv. 'Welkomstwoord aangepast'"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="doSaveAsTemplate()"
                    >
                </div>

                <!-- Bestaande templates om te overschrijven -->
                <template x-if="templates.length > 0">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Of overschrijf bestaande:</label>
                        <div class="space-y-1 max-h-32 overflow-y-auto">
                            <template x-for="(template, index) in templates" :key="index">
                                <button
                                    @click="overschrijfTemplate(index)"
                                    class="w-full text-left px-3 py-2 text-sm bg-gray-50 hover:bg-yellow-50 rounded border hover:border-yellow-400 transition-colors"
                                >
                                    <span class="font-medium" x-text="template.naam"></span>
                                    <span class="text-gray-400 ml-1">→ overschrijven</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end gap-2">
                    <button
                        @click="showSaveAsModal = false"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm"
                    >
                        Annuleren
                    </button>
                    <button
                        @click="doSaveAsTemplate()"
                        :disabled="!saveAsNaam.trim()"
                        class="bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white px-4 py-2 rounded-lg text-sm font-medium"
                    >
                        Opslaan als nieuw
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Template beheer -->
    <div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50" @click.self="showTemplateModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden">
            <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center">
                <span class="font-bold">📋 Templates beheren</span>
                <button @click="showTemplateModal = false" class="text-white hover:text-gray-200 text-xl">&times;</button>
            </div>
            <div class="p-4 overflow-y-auto max-h-[70vh]">
                <!-- Huidige notities opslaan als template -->
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <label class="block text-sm font-medium text-green-800 mb-2">💾 Huidige notities opslaan als template:</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            x-model="nieuweTemplateNaam"
                            placeholder="Naam voor template..."
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            @keyup.enter="saveAsTemplate()"
                        >
                        <button
                            @click="saveAsTemplate()"
                            :disabled="!nieuweTemplateNaam.trim() || !notities.trim()"
                            class="bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white px-4 py-2 rounded-lg text-sm font-medium"
                        >
                            Opslaan
                        </button>
                    </div>
                </div>

                <!-- Bestaande templates -->
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Opgeslagen templates:</h3>
                    <template x-if="templates.length === 0">
                        <p class="text-gray-500 text-sm italic">Nog geen templates opgeslagen</p>
                    </template>
                    <template x-for="(template, index) in templates" :key="index">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                            <div class="flex-1 min-w-0">
                                <span class="font-medium text-gray-800" x-text="template.naam"></span>
                                <p class="text-xs text-gray-500 truncate" x-text="template.tekst.substring(0, 50) + '...'"></p>
                            </div>
                            <div class="flex gap-2 ml-2">
                                <button
                                    @click="selectedTemplate = index; laadTemplate(); showTemplateModal = false"
                                    class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1"
                                    title="Laden"
                                >
                                    📥
                                </button>
                                <button
                                    @click="deleteTemplate(index)"
                                    class="text-red-600 hover:text-red-800 text-sm px-2 py-1"
                                    title="Verwijderen"
                                >
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Standaard voorbeeldtekst toevoegen -->
                <div class="mt-4 pt-4 border-t">
                    <button
                        @click="addDefaultTemplate()"
                        class="w-full bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium"
                    >
                        ➕ Voeg standaard voorbeeldtekst toe
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Poule uitslagen bekijken -->
    <div x-show="showPouleModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50" @click.self="showPouleModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[80vh] overflow-hidden">
            <!-- Header -->
            <div :class="selectedPouleData?.poule?.is_eliminatie ? 'bg-purple-700' : 'bg-green-700'" class="text-white px-4 py-3 flex justify-between items-center">
                <div x-show="!loadingPoule && selectedPouleData">
                    <div class="font-bold" x-text="selectedPouleData?.poule?.is_eliminatie
                        ? 'Eliminatie - ' + selectedPouleData?.poule?.leeftijdsklasse + ' ' + selectedPouleData?.poule?.gewichtsklasse
                        : 'Poule ' + selectedPouleData?.poule?.nummer + ' - ' + selectedPouleData?.poule?.leeftijdsklasse + ' ' + selectedPouleData?.poule?.gewichtsklasse"></div>
                </div>
                <div x-show="loadingPoule" class="font-bold">Laden...</div>
                <button @click="showPouleModal = false" class="text-white hover:text-gray-200 text-xl">&times;</button>
            </div>

            <!-- Content -->
            <div class="p-4 overflow-y-auto max-h-[60vh]">
                <div x-show="loadingPoule" class="text-center py-8">
                    <div class="animate-spin text-4xl">🔄</div>
                    <p class="text-gray-500 mt-2">Uitslagen laden...</p>
                </div>

                <!-- ELIMINATIE: Medaille winnaars -->
                <template x-if="!loadingPoule && selectedPouleData?.poule?.is_eliminatie">
                    <div class="grid gap-3">
                        <template x-for="(standing, index) in selectedPouleData?.standings || []" :key="index">
                            <div class="flex items-center gap-3 p-3 rounded-lg"
                                :class="{
                                    'bg-gradient-to-r from-yellow-100 to-yellow-200 border-2 border-yellow-400': standing.plaats === 1,
                                    'bg-gradient-to-r from-gray-100 to-gray-200 border-2 border-gray-400': standing.plaats === 2,
                                    'bg-gradient-to-r from-orange-100 to-orange-200 border-2 border-orange-400': standing.plaats === 3
                                }">
                                <div class="text-3xl" x-text="standing.plaats === 1 ? '🥇' : (standing.plaats === 2 ? '🥈' : '🥉')"></div>
                                <div>
                                    <div class="font-bold text-lg" x-text="standing.naam"></div>
                                    <div class="text-sm text-gray-600" x-text="standing.club"></div>
                                </div>
                                <div class="ml-auto text-2xl font-bold"
                                    :class="{
                                        'text-yellow-700': standing.plaats === 1,
                                        'text-gray-700': standing.plaats === 2,
                                        'text-orange-700': standing.plaats === 3
                                    }"
                                    x-text="standing.plaats + 'e'"></div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- POULE: Resultaten tabel -->
                <template x-if="!loadingPoule && selectedPouleData && !selectedPouleData?.poule?.is_eliminatie">
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
                            <template x-for="(standing, index) in selectedPouleData?.standings || []" :key="index">
                                <tr class="border-b last:border-0">
                                    <td class="px-3 py-2">
                                        <span class="font-bold" x-text="standing.naam"></span>
                                        <span class="text-gray-500 text-xs" x-text="'(' + standing.club + ')'"></span>
                                    </td>
                                    <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-800" x-text="standing.wp"></td>
                                    <td class="px-2 py-2 text-center bg-blue-50 text-blue-800" x-text="standing.jp"></td>
                                    <td class="px-2 py-2 text-center font-bold text-lg"
                                        :class="{
                                            'bg-yellow-400 text-yellow-900': index === 0,
                                            'bg-gray-300 text-gray-800': index === 1,
                                            'bg-orange-300 text-orange-900': index === 2,
                                            'bg-yellow-50': index > 2
                                        }"
                                        x-text="index + 1"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
// Terug functie - zet afgeroepen poule terug naar klaar
async function zetTerug(pouleId, button) {
    try {
        button.disabled = true;
        button.innerHTML = '⏳ Bezig...';

        const response = await fetch('{{ $terugUrl }}', {
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
    const vandaag = new Date().toDateString();

    return {
        activeTab: 'uitslagen',
        toonGeschiedenis: false,
        geschiedenis: [],
        selectedBlok: {{ isset($poulesPerBlok) && $poulesPerBlok->isNotEmpty() ? $poulesPerBlok->keys()->first() : 1 }},
        openPoules: [],
        notities: '',
        isRefreshing: false,
        showPouleModal: false,
        selectedPouleData: null,
        loadingPoule: false,
        klarePouleCount: {{ $klarePoules->count() }},
        // Templates
        templates: [],
        selectedTemplate: '',
        showTemplateModal: false,
        nieuweTemplateNaam: '',
        // Auto-save
        hasUnsavedChanges: false,
        autoSaving: false,
        lastSavedNotities: '',
        // Save as modal
        showSaveAsModal: false,
        saveAsNaam: '',
        // Feedback bar
        showFeedbackBar: false,
        feedbackMessage: '',

        async init() {
            // Laad templates uit localStorage
            this.loadTemplates();
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

            // Laad notities van server
            await this.loadNotities();
        },

        async loadNotities() {
            try {
                const response = await fetch('{{ $notitiesGetUrl }}');
                const data = await response.json();
                if (data.success && data.notities) {
                    this.notities = data.notities;
                    this.lastSavedNotities = data.notities;
                    this.hasUnsavedChanges = false;
                }
            } catch (err) {
                console.error('Fout bij laden notities:', err);
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
                const response = await fetch('{{ $afgeroepenUrl }}', {
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

                    // Update badge count
                    this.klarePouleCount = Math.max(0, this.klarePouleCount - 1);

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

        async saveNotities() {
            try {
                const response = await fetch('{{ $notitiesSaveUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ notities: this.notities })
                });
                const data = await response.json();
                if (data.success) {
                    this.lastSavedNotities = this.notities;
                    this.hasUnsavedChanges = false;
                    this.showFeedback('Notities opgeslagen!');
                }
            } catch (err) {
                console.error('Fout bij opslaan notities:', err);
                alert('Fout bij opslaan');
            }
        },

        async clearNotities() {
            if (confirm('Weet je zeker dat je alle notities wilt wissen?')) {
                this.notities = '';
                await this.saveNotities();
            }
        },

        refreshUitslagen() {
            this.isRefreshing = true;
            location.reload();
        },

        async toonPouleDetail(pouleId) {
            this.loadingPoule = true;
            this.showPouleModal = true;

            try {
                const response = await fetch('{{ $standingsUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ poule_id: pouleId })
                });

                const data = await response.json();
                if (data.success) {
                    this.selectedPouleData = data;
                } else {
                    alert('Fout: ' + (data.message || 'Onbekende fout'));
                    this.showPouleModal = false;
                }
            } catch (err) {
                alert('Fout: ' + err.message);
                this.showPouleModal = false;
            } finally {
                this.loadingPoule = false;
            }
        },

        // Feedback helper (groene balk ipv lelijke alert)
        showFeedback(message) {
            this.feedbackMessage = message;
            this.showFeedbackBar = true;
            setTimeout(() => this.showFeedbackBar = false, 3000);
        },

        // Template functies
        loadTemplates() {
            const TEMPLATE_KEY = 'spreker_templates_{{ $toernooi->organisator_id }}';
            try {
                const stored = localStorage.getItem(TEMPLATE_KEY);
                if (stored) {
                    this.templates = JSON.parse(stored);
                }
            } catch (e) {
                this.templates = [];
            }
        },

        saveTemplates() {
            const TEMPLATE_KEY = 'spreker_templates_{{ $toernooi->organisator_id }}';
            localStorage.setItem(TEMPLATE_KEY, JSON.stringify(this.templates));
        },

        laadTemplate() {
            if (this.selectedTemplate === '' || this.selectedTemplate === null) return;
            const template = this.templates[this.selectedTemplate];
            if (!template) return;

            if (this.notities && !confirm(`Template "${template.naam}" laden?\n\nDit vervangt je huidige notities.`)) {
                this.selectedTemplate = '';
                return;
            }
            this.notities = template.tekst;
            this.saveNotities();
            this.selectedTemplate = '';
        },

        saveAsTemplate() {
            const naam = this.nieuweTemplateNaam.trim();
            if (!naam || !this.notities.trim()) return;

            // Check of naam al bestaat
            const exists = this.templates.findIndex(t => t.naam.toLowerCase() === naam.toLowerCase());
            if (exists >= 0) {
                if (!confirm(`Template "${naam}" bestaat al. Overschrijven?`)) return;
                this.templates[exists].tekst = this.notities;
            } else {
                this.templates.push({ naam, tekst: this.notities });
            }

            this.saveTemplates();
            this.nieuweTemplateNaam = '';
            this.showFeedback(`Template "${naam}" opgeslagen!`);
        },

        deleteTemplate(index) {
            const template = this.templates[index];
            if (!confirm(`Template "${template.naam}" verwijderen?`)) return;
            this.templates.splice(index, 1);
            this.saveTemplates();
        },

        addDefaultTemplate() {
            const defaultTemplates = [
                {
                    naam: 'Welkomstwoord',
                    tekst: `WELKOMSTWOORD
- Welkom bij het judotoernooi!
- Namens de organisatie wensen wij iedereen een sportieve dag
- Dank aan alle vrijwilligers en scheidsrechters

HUISREGELS
- Roken en vapen is verboden in het hele gebouw
- Alleen judoka's en coaches op de wedstrijdvloer

PRAKTISCH
- Kantine open tot 17:00
- Toiletten bij de ingang en achter de kantine
- EHBO-post naast de jury tafel
- Gevonden voorwerpen bij de inschrijftafel`
                },
                {
                    naam: 'Prijsuitreiking',
                    tekst: `PRIJSUITREIKING
- Direct na de laatste wedstrijd per categorie
- Judoka's verzamelen bij de podiummat
- Ouders welkom om foto's te maken
- Winnaars worden per poule opgeroepen

MEDAILLES
- Goud: 1e plaats
- Zilver: 2e plaats
- Brons: 3e plaats(en)`
                },
                {
                    naam: 'Afsluitend woord',
                    tekst: `AFSLUITING
- Dank aan alle deelnemers voor het sportieve gedrag
- Dank aan de scheidsrechters en vrijwilligers
- Tot volgend jaar!

OPRUIMEN
- Graag afval in de prullenbakken
- Niet vergeten: judopakken, bidons, tassen
- Gevonden voorwerpen bij de organisatie`
                }
            ];

            let added = 0;
            defaultTemplates.forEach(dt => {
                if (!this.templates.find(t => t.naam === dt.naam)) {
                    this.templates.push(dt);
                    added++;
                }
            });

            this.saveTemplates();
            if (added > 0) {
                this.showFeedback(`${added} standaard template(s) toegevoegd!`);
            } else {
                this.showFeedback('Alle standaard templates bestaan al.');
            }
        },

        // Auto-save functie (wordt aangeroepen na 2 sec inactiviteit)
        async autoSaveNotities() {
            // Check of er wijzigingen zijn
            if (this.notities === this.lastSavedNotities) {
                this.hasUnsavedChanges = false;
                return;
            }

            this.hasUnsavedChanges = true;
            this.autoSaving = true;

            try {
                const response = await fetch('{{ $notitiesSaveUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ notities: this.notities })
                });
                const data = await response.json();
                if (data.success) {
                    this.lastSavedNotities = this.notities;
                    this.hasUnsavedChanges = false;
                }
            } catch (err) {
                console.error('Auto-save fout:', err);
            } finally {
                this.autoSaving = false;
            }
        },

        // Opslaan als nieuwe template
        doSaveAsTemplate() {
            const naam = this.saveAsNaam.trim();
            if (!naam || !this.notities.trim()) return;

            // Check of naam al bestaat
            const exists = this.templates.findIndex(t => t.naam.toLowerCase() === naam.toLowerCase());
            if (exists >= 0) {
                if (!confirm(`Template "${naam}" bestaat al. Overschrijven?`)) return;
                this.templates[exists].tekst = this.notities;
            } else {
                this.templates.push({ naam, tekst: this.notities });
            }

            this.saveTemplates();
            this.saveAsNaam = '';
            this.showSaveAsModal = false;
            this.showFeedback(`Template "${naam}" opgeslagen!`);
        },

        // Overschrijf bestaande template
        overschrijfTemplate(index) {
            const template = this.templates[index];
            if (!confirm(`Template "${template.naam}" overschrijven met huidige tekst?`)) return;

            this.templates[index].tekst = this.notities;
            this.saveTemplates();
            this.showSaveAsModal = false;
            this.showFeedback(`Template "${template.naam}" bijgewerkt!`);
        }
    }
}

</script>
