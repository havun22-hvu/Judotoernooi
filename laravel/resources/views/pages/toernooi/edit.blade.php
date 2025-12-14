@extends('layouts.app')

@section('title', 'Instellingen')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ activeTab: 'toernooi' }">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-800">Instellingen</h1>
            <span id="save-status" class="text-sm text-gray-400 hidden"></span>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('toernooi.pagina-builder.index', $toernooi) }}" target="_blank" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Pagina Builder
            </a>
            <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
                &larr; Terug naar Dashboard
            </a>
        </div>
    </div>

    <!-- TABS -->
    <div class="flex border-b mb-6">
        <button type="button"
                @click="activeTab = 'toernooi'"
                :class="activeTab === 'toernooi' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-6 py-3 font-medium border-b-2 -mb-px transition-colors">
            Toernooi
        </button>
        <button type="button"
                @click="activeTab = 'organisatie'"
                :class="activeTab === 'organisatie' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-6 py-3 font-medium border-b-2 -mb-px transition-colors">
            Organisatie
        </button>
    </div>

    <!-- TAB: TOERNOOI -->
    <div x-show="activeTab === 'toernooi'" x-cloak>
    <form action="{{ route('toernooi.update', $toernooi) }}" method="POST" id="toernooi-form">
        @csrf
        @method('PUT')

        <!-- ALGEMEEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Algemeen</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="naam" class="block text-gray-700 font-medium mb-1">Naam Toernooi *</label>
                    <input type="text" name="naam" id="naam" value="{{ old('naam', $toernooi->naam) }}"
                           class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror" required>
                    @error('naam')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="organisatie" class="block text-gray-700 font-medium mb-1">Organisatie</label>
                    <input type="text" name="organisatie" id="organisatie" value="{{ old('organisatie', $toernooi->organisatie) }}"
                           placeholder="Naam van de organiserende club" class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="datum" class="block text-gray-700 font-medium mb-1">Datum Toernooi *</label>
                    <input type="date" name="datum" id="datum" value="{{ old('datum', $toernooi->datum->format('Y-m-d')) }}"
                           class="w-full border rounded px-3 py-2 @error('datum') border-red-500 @enderror" required>
                    @error('datum')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="locatie" class="block text-gray-700 font-medium mb-1">Locatie</label>
                    <input type="text" name="locatie" id="locatie" value="{{ old('locatie', $toernooi->locatie) }}"
                           placeholder="Adres of naam sporthal" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        <!-- INSCHRIJVING -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Inschrijving</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="inschrijving_deadline" class="block text-gray-700 font-medium mb-1">Inschrijving Deadline</label>
                    <input type="date" name="inschrijving_deadline" id="inschrijving_deadline"
                           value="{{ old('inschrijving_deadline', $toernooi->inschrijving_deadline?->format('Y-m-d')) }}"
                           class="w-full border rounded px-3 py-2">
                    <p class="text-gray-500 text-sm mt-1">Tot wanneer kunnen clubs judoka's opgeven?</p>
                </div>

                <div>
                    <label for="max_judokas" class="block text-gray-700 font-medium mb-1">Maximum Aantal Deelnemers</label>
                    <input type="number" name="max_judokas" id="max_judokas"
                           value="{{ old('max_judokas', $toernooi->max_judokas) }}"
                           placeholder="Leeg = onbeperkt" class="w-full border rounded px-3 py-2" min="1">
                    <p class="text-gray-500 text-sm mt-1">Coaches krijgen waarschuwing bij 80%</p>
                </div>
            </div>
        </div>

        <!-- MATTEN & BLOKKEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Matten & Tijdsblokken</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="aantal_matten" class="block text-gray-700 font-medium mb-1">Aantal Matten</label>
                    <input type="number" name="aantal_matten" id="aantal_matten"
                           value="{{ old('aantal_matten', $toernooi->aantal_matten) }}"
                           class="w-full border rounded px-3 py-2" min="1" max="20">
                    <p class="text-gray-500 text-sm mt-1">Hoeveel wedstrijdmatten zijn beschikbaar?</p>
                </div>

                <div>
                    <label for="aantal_blokken" class="block text-gray-700 font-medium mb-1">Aantal Tijdsblokken</label>
                    <input type="number" name="aantal_blokken" id="aantal_blokken"
                           value="{{ old('aantal_blokken', $toernooi->aantal_blokken) }}"
                           class="w-full border rounded px-3 py-2" min="1" max="12">
                    <p class="text-gray-500 text-sm mt-1">In hoeveel tijdsblokken wordt het toernooi verdeeld?</p>
                </div>
            </div>

        </div>

        <!-- POULE INSTELLINGEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Poule Instellingen</h2>

            <div>
                <label class="block text-gray-700 font-medium mb-2">Voorkeursvolgorde Poule Grootte</label>
                <p class="text-gray-500 text-sm mb-3">Sleep om de volgorde aan te passen. Eerste = meest gewenst, laagste = minimum, hoogste = maximum.</p>

                @php
                    $voorkeur = old('poule_grootte_voorkeur', $toernooi->poule_grootte_voorkeur) ?? [5, 4, 6, 3];
                    if (is_string($voorkeur)) {
                        $voorkeur = json_decode($voorkeur, true) ?? [5, 4, 6, 3];
                    }
                @endphp

                <div id="voorkeur-container" class="flex flex-wrap gap-2 mb-3">
                    @foreach($voorkeur as $index => $grootte)
                    <div class="voorkeur-item flex items-center bg-blue-100 border-2 border-blue-300 rounded-lg px-4 py-2 cursor-move" draggable="true" data-grootte="{{ $grootte }}">
                        <span class="font-bold text-blue-800 text-lg mr-2">{{ $grootte }}</span>
                        <span class="text-blue-600 text-sm">judoka's</span>
                        <button type="button" class="ml-3 text-gray-400 hover:text-red-500 remove-voorkeur" title="Verwijder">&times;</button>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <select id="add-voorkeur-select" class="border rounded px-3 py-2 text-sm">
                        @for($i = 2; $i <= 8; $i++)
                        <option value="{{ $i }}">{{ $i }} judoka's</option>
                        @endfor
                    </select>
                    <button type="button" id="add-voorkeur-btn" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                        + Toevoegen
                    </button>
                </div>

                <input type="hidden" name="poule_grootte_voorkeur" id="poule_grootte_voorkeur_input" value="{{ json_encode($voorkeur) }}">

                <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                    <strong>Voorbeeld:</strong> Bij [5, 4, 6, 3] krijg je liever 3 poules van 4 dan 2 poules van 6.
                </div>
            </div>

            <!-- Dubbel spel instellingen -->
            <div class="border-t pt-4 mt-4">
                <h3 class="font-medium text-gray-700 mb-2">Dubbel spel (2x tegen elkaar)</h3>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="hidden" name="dubbel_bij_2_judokas" value="0">
                        <input type="checkbox" name="dubbel_bij_2_judokas" value="1"
                               {{ old('dubbel_bij_2_judokas', $toernooi->dubbel_bij_2_judokas ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">2 judoka's <span class="text-gray-400 text-sm">(2w)</span></span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="hidden" name="dubbel_bij_3_judokas" value="0">
                        <input type="checkbox" name="dubbel_bij_3_judokas" value="1"
                               {{ old('dubbel_bij_3_judokas', $toernooi->dubbel_bij_3_judokas ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">3 judoka's <span class="text-gray-400 text-sm">(6w)</span></span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="hidden" name="dubbel_bij_4_judokas" value="0">
                        <input type="checkbox" name="dubbel_bij_4_judokas" value="1"
                               {{ old('dubbel_bij_4_judokas', $toernooi->dubbel_bij_4_judokas ?? false) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">4 judoka's <span class="text-gray-400 text-sm">(12w)</span></span>
                    </label>
                </div>
            </div>

            <!-- Poule indeling sortering -->
            <div class="border-t pt-4 mt-4">
                <h3 class="font-medium text-gray-700 mb-2">Poule Indeling Sortering</h3>
                <p class="text-gray-500 text-sm mb-3">Bepaalt de volgorde waarin judoka's over poules worden verdeeld.</p>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 {{ ($toernooi->judoka_code_volgorde ?? 'gewicht_band') === 'gewicht_band' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" name="judoka_code_volgorde" value="gewicht_band"
                               {{ ($toernooi->judoka_code_volgorde ?? 'gewicht_band') === 'gewicht_band' ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600">
                        <div>
                            <span class="font-medium">Leeftijd → Gewicht → Band</span>
                            <span class="block text-xs text-gray-500">Binnen gewichtsklasse: eerst zwarte banden, dan bruine, etc.</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 {{ ($toernooi->judoka_code_volgorde ?? 'gewicht_band') === 'band_gewicht' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" name="judoka_code_volgorde" value="band_gewicht"
                               {{ ($toernooi->judoka_code_volgorde ?? 'gewicht_band') === 'band_gewicht' ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600">
                        <div>
                            <span class="font-medium">Leeftijd → Band → Gewicht</span>
                            <span class="block text-xs text-gray-500">Eerst hogere banden bij elkaar, dan lagere banden bij elkaar.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Prioriteit volgorde -->
            <div class="border-t pt-4 mt-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-gray-700 font-medium">Prioriteit:</span>
                    <div id="prioriteit-container" class="flex gap-2">
                        <div class="prioriteit-item bg-blue-100 border border-blue-300 rounded px-3 py-1 cursor-move text-sm" draggable="true" data-key="groepsgrootte">1. 👥 Groepsgrootte</div>
                        <div class="prioriteit-item bg-blue-100 border border-blue-300 rounded px-3 py-1 cursor-move text-sm" draggable="true" data-key="bandkleur">2. 🥋 Bandkleur</div>
                        <div class="prioriteit-item bg-blue-100 border border-blue-300 rounded px-3 py-1 cursor-move text-sm" draggable="true" data-key="clubspreiding">3. 🏠 Clubspreiding</div>
                    </div>
                    <span class="text-gray-400 text-xs">(sleep om te wisselen)</span>
                </div>
                <input type="hidden" name="verdeling_prioriteiten" id="prioriteit_input" value='["groepsgrootte","bandkleur","clubspreiding"]'>
            </div>

            <!-- Wedstrijdsysteem per leeftijdsklasse -->
            <div class="border-t pt-4 mt-4">
                <h3 class="font-medium text-gray-700 mb-2">Wedstrijdsysteem per Leeftijdsklasse</h3>

                <p class="text-xs text-gray-500 mb-3">
                    Bij kruisfinale: aantal doorgeplaatsten wordt automatisch bepaald op basis van het aantal poules (doel: 4-6 judoka's in kruisfinale)
                </p>

                @php
                    $wedstrijdSysteem = old('wedstrijd_systeem', $toernooi->wedstrijd_systeem) ?? [];
                    $leeftijdsklassen = [
                        'minis' => "Mini's",
                        'a_pupillen' => 'A-pupillen',
                        'b_pupillen' => 'B-pupillen',
                        'dames_15' => 'Dames -15',
                        'heren_15' => 'Heren -15',
                        'dames_18' => 'Dames -18',
                        'heren_18' => 'Heren -18',
                        'dames' => 'Dames',
                        'heren' => 'Heren',
                    ];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    @foreach($leeftijdsklassen as $key => $label)
                    <div class="flex items-center justify-between p-2 border rounded bg-gray-50">
                        <span class="text-sm font-medium">{{ $label }}</span>
                        <select name="wedstrijd_systeem[{{ $key }}]" class="border rounded px-2 py-1 text-sm bg-white">
                            <option value="poules" {{ ($wedstrijdSysteem[$key] ?? 'poules') == 'poules' ? 'selected' : '' }}>Poules</option>
                            <option value="poules_kruisfinale" {{ ($wedstrijdSysteem[$key] ?? '') == 'poules_kruisfinale' ? 'selected' : '' }}>Poules + Kruisfinale</option>
                            <option value="eliminatie" {{ ($wedstrijdSysteem[$key] ?? '') == 'eliminatie' ? 'selected' : '' }}>Direct eliminatie</option>
                        </select>
                    </div>
                    @endforeach
                </div>

                <div class="mt-3 p-3 bg-blue-50 rounded text-sm text-blue-800">
                    <strong>Poules:</strong> Iedereen tegen iedereen, elke poule eigen podium<br>
                    <strong>Poules + Kruisfinale:</strong> Na poules strijden top X om overall klassering<br>
                    <strong>Direct eliminatie:</strong> Knock-out systeem (2 x verlies = uit)
                </div>
            </div>
        </div>

        <!-- WEDSTRIJDSCHEMA'S -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Wedstrijdschema's</h2>
            <p class="text-sm text-gray-600 mb-4">
                Bepaal de volgorde van wedstrijden per poulegrootte. Sleep wedstrijden om de volgorde aan te passen.
            </p>

            @php
                $standaardSchemas = [
                    2 => [[1,2], [2,1]],
                    3 => [[1,2], [1,3], [2,3], [2,1], [3,2], [3,1]],
                    4 => [[1,2], [3,4], [2,3], [1,4], [2,4], [1,3]],
                    5 => [[1,2], [3,4], [1,5], [2,3], [4,5], [1,3], [2,4], [3,5], [1,4], [2,5]],
                    6 => [[1,2], [3,4], [5,6], [1,3], [2,5], [4,6], [3,5], [2,4], [1,6], [2,3], [4,5], [3,6], [1,4], [2,6], [1,5]],
                    7 => [[1,2], [3,4], [5,6], [1,7], [2,3], [4,5], [6,7], [1,3], [2,4], [5,7], [3,6], [1,4], [2,5], [3,7], [4,6], [1,5], [2,6], [4,7], [1,6], [3,5], [2,7]],
                ];
                $opgeslagenSchemas = old('wedstrijd_schemas', $toernooi->wedstrijd_schemas) ?? [];
            @endphp

            <div class="space-y-4" x-data="wedstrijdSchemas()">
                <!-- Tabs voor poulegrootte -->
                <div class="flex border-b">
                    @foreach([2,3,4,5,6,7] as $grootte)
                    <button type="button"
                            @click="activeTab = {{ $grootte }}"
                            :class="activeTab === {{ $grootte }} ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 font-medium border-b-2 -mb-px transition-colors text-sm">
                        {{ $grootte }} judoka's
                    </button>
                    @endforeach
                </div>

                <!-- Schema per grootte -->
                @foreach([2,3,4,5,6,7] as $grootte)
                @php
                    $schema = $opgeslagenSchemas[$grootte] ?? $standaardSchemas[$grootte];
                    $aantalWed = count($schema);
                @endphp
                <div x-show="activeTab === {{ $grootte }}" x-cloak class="pt-2">
                    <div class="flex items-start gap-6">
                        <!-- Visueel schema -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-sm font-medium text-gray-700">{{ $aantalWed }} wedstrijden</span>
                                <span class="text-xs text-gray-400">
                                    @if($grootte <= 3)(dubbele round-robin)@else(enkelvoudige round-robin)@endif
                                </span>
                            </div>
                            <div class="schema-container grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2" data-grootte="{{ $grootte }}">
                                @foreach($schema as $idx => $wed)
                                <div class="wed-item flex items-center justify-center gap-1 bg-gray-100 border-2 border-gray-300 rounded-lg px-3 py-2 cursor-move hover:bg-blue-50 hover:border-blue-300 transition-colors"
                                     draggable="true" data-wit="{{ $wed[0] }}" data-blauw="{{ $wed[1] }}">
                                    <span class="text-xs text-gray-400 mr-1">{{ $idx + 1 }}.</span>
                                    <span class="font-bold text-gray-700">{{ $wed[0] }}</span>
                                    <span class="text-gray-400">-</span>
                                    <span class="font-bold text-blue-600">{{ $wed[1] }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Legenda -->
                        <div class="w-32 flex-shrink-0">
                            <div class="text-xs font-medium text-gray-500 mb-2">Positie in poule:</div>
                            @for($i = 1; $i <= $grootte; $i++)
                            <div class="flex items-center gap-2 text-sm py-0.5">
                                <span class="w-6 h-6 flex items-center justify-center bg-gray-200 rounded font-bold text-gray-700">{{ $i }}</span>
                                <span class="text-gray-500">Judoka {{ $i }}</span>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Hidden inputs voor alle schemas -->
                <input type="hidden" name="wedstrijd_schemas" id="wedstrijd_schemas_input"
                       value='@json($opgeslagenSchemas ?: $standaardSchemas)'>

                <div class="text-xs text-gray-400 mt-2">
                    💡 Tip: De volgorde is geoptimaliseerd zodat judoka's rust krijgen tussen wedstrijden.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('voorkeur-container');
            const hiddenInput = document.getElementById('poule_grootte_voorkeur_input');
            const addBtn = document.getElementById('add-voorkeur-btn');
            const addSelect = document.getElementById('add-voorkeur-select');

            function updateHiddenInput() {
                const items = container.querySelectorAll('.voorkeur-item');
                const voorkeur = Array.from(items).map(item => parseInt(item.dataset.grootte));
                hiddenInput.value = JSON.stringify(voorkeur);
            }

            // Drag and drop
            let draggedItem = null;

            container.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('voorkeur-item')) {
                    draggedItem = e.target;
                    e.target.style.opacity = '0.5';
                }
            });

            container.addEventListener('dragend', function(e) {
                if (e.target.classList.contains('voorkeur-item')) {
                    e.target.style.opacity = '1';
                    draggedItem = null;
                }
            });

            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                const target = e.target.closest('.voorkeur-item');
                if (target && target !== draggedItem) {
                    const rect = target.getBoundingClientRect();
                    const midX = rect.left + rect.width / 2;
                    if (e.clientX < midX) {
                        container.insertBefore(draggedItem, target);
                    } else {
                        container.insertBefore(draggedItem, target.nextSibling);
                    }
                }
            });

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                updateHiddenInput();
            });

            // Remove button
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-voorkeur')) {
                    e.target.closest('.voorkeur-item').remove();
                    updateHiddenInput();
                }
            });

            // Add button
            addBtn.addEventListener('click', function() {
                const grootte = addSelect.value;
                // Check if already exists
                const existing = container.querySelector(`[data-grootte="${grootte}"]`);
                if (existing) {
                    existing.classList.add('ring-2', 'ring-red-500');
                    setTimeout(() => existing.classList.remove('ring-2', 'ring-red-500'), 1000);
                    return;
                }

                const item = document.createElement('div');
                item.className = 'voorkeur-item flex items-center bg-blue-100 border-2 border-blue-300 rounded-lg px-4 py-2 cursor-move';
                item.draggable = true;
                item.dataset.grootte = grootte;
                item.innerHTML = `
                    <span class="font-bold text-blue-800 text-lg mr-2">${grootte}</span>
                    <span class="text-blue-600 text-sm">judoka's</span>
                    <button type="button" class="ml-3 text-gray-400 hover:text-red-500 remove-voorkeur" title="Verwijder">&times;</button>
                `;
                container.appendChild(item);
                updateHiddenInput();
            });

            // ========== PRIORITEIT VOLGORDE DRAG & DROP ==========
            const prioriteitContainer = document.getElementById('prioriteit-container');
            const prioriteitInput = document.getElementById('prioriteit_input');

            function updatePrioriteitInput() {
                const items = prioriteitContainer.querySelectorAll('.prioriteit-item');
                const prioriteit = Array.from(items).map(item => item.dataset.key);
                prioriteitInput.value = JSON.stringify(prioriteit);
            }

            let draggedPrioriteitItem = null;

            prioriteitContainer.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('prioriteit-item')) {
                    draggedPrioriteitItem = e.target;
                    e.target.style.opacity = '0.5';
                }
            });

            prioriteitContainer.addEventListener('dragend', function(e) {
                if (e.target.classList.contains('prioriteit-item')) {
                    e.target.style.opacity = '1';
                    draggedPrioriteitItem = null;
                }
            });

            prioriteitContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                const target = e.target.closest('.prioriteit-item');
                if (target && target !== draggedPrioriteitItem) {
                    const rect = target.getBoundingClientRect();
                    const midX = rect.left + rect.width / 2;
                    if (e.clientX < midX) {
                        prioriteitContainer.insertBefore(draggedPrioriteitItem, target);
                    } else {
                        prioriteitContainer.insertBefore(draggedPrioriteitItem, target.nextSibling);
                    }
                }
            });

            prioriteitContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                updatePrioriteitInput();
                // Update numbers
                const items = prioriteitContainer.querySelectorAll('.prioriteit-item');
                const labels = { groepsgrootte: '👥 Groepsgrootte', bandkleur: '🥋 Bandkleur', clubspreiding: '🏠 Clubspreiding' };
                items.forEach((item, idx) => {
                    item.textContent = `${idx + 1}. ${labels[item.dataset.key]}`;
                });
            });

            // ========== WEDSTRIJDSCHEMA DRAG & DROP ==========
            document.querySelectorAll('.schema-container').forEach(container => {
                let draggedWed = null;

                container.addEventListener('dragstart', function(e) {
                    if (e.target.classList.contains('wed-item')) {
                        draggedWed = e.target;
                        e.target.style.opacity = '0.5';
                    }
                });

                container.addEventListener('dragend', function(e) {
                    if (e.target.classList.contains('wed-item')) {
                        e.target.style.opacity = '1';
                        draggedWed = null;
                    }
                });

                container.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    const target = e.target.closest('.wed-item');
                    if (target && target !== draggedWed) {
                        const rect = target.getBoundingClientRect();
                        const midX = rect.left + rect.width / 2;
                        if (e.clientX < midX) {
                            container.insertBefore(draggedWed, target);
                        } else {
                            container.insertBefore(draggedWed, target.nextSibling);
                        }
                    }
                });

                container.addEventListener('drop', function(e) {
                    e.preventDefault();
                    updateWedstrijdNumbers(container);
                    updateWedstrijdSchemasInput();
                });
            });

            function updateWedstrijdNumbers(container) {
                const items = container.querySelectorAll('.wed-item');
                items.forEach((item, idx) => {
                    const numSpan = item.querySelector('span:first-child');
                    if (numSpan) numSpan.textContent = `${idx + 1}.`;
                });
            }

            function updateWedstrijdSchemasInput() {
                const schemas = {};
                document.querySelectorAll('.schema-container').forEach(container => {
                    const grootte = parseInt(container.dataset.grootte);
                    const wedstrijden = [];
                    container.querySelectorAll('.wed-item').forEach(item => {
                        wedstrijden.push([parseInt(item.dataset.wit), parseInt(item.dataset.blauw)]);
                    });
                    schemas[grootte] = wedstrijden;
                });
                document.getElementById('wedstrijd_schemas_input').value = JSON.stringify(schemas);
            }

        });

        // Alpine component voor tabs
        function wedstrijdSchemas() {
            return { activeTab: 4 };
        }
        </script>

        <!-- GEWICHT -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Weging</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Weging verplicht checkbox -->
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input type="hidden" name="weging_verplicht" value="0">
                        <input type="checkbox" name="weging_verplicht" value="1"
                               {{ old('weging_verplicht', $toernooi->weging_verplicht ?? true) ? 'checked' : '' }}
                               class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-3">
                            <span class="font-medium text-gray-700">Weging verplicht</span>
                            <span class="block text-sm text-gray-500">Uitschakelen voor toernooien zonder weegplicht</span>
                        </span>
                    </label>
                </div>

                <!-- Max wegingen -->
                <div class="flex items-center gap-2">
                    <label for="max_wegingen" class="text-gray-700 font-medium">Max aantal wegingen:</label>
                    <input type="text" name="max_wegingen" id="max_wegingen"
                           value="{{ old('max_wegingen', $toernooi->max_wegingen) }}"
                           placeholder="-" class="w-12 border rounded px-2 py-1 text-center">
                </div>

                <!-- Judoka's per coach -->
                <div class="flex items-center gap-2">
                    <label for="judokas_per_coach" class="text-gray-700 font-medium">Judoka's per coach kaart:</label>
                    <input type="number" name="judokas_per_coach" id="judokas_per_coach"
                           value="{{ old('judokas_per_coach', $toernooi->judokas_per_coach ?? 5) }}"
                           class="w-16 border rounded px-2 py-1 text-center" min="1" max="20">
                    <span class="text-sm text-gray-500">(toegang tot dojo)</span>
                </div>
            </div>

            <div class="max-w-md border-t pt-4">
                <label for="gewicht_tolerantie" class="block text-gray-700 font-medium mb-1">Gewichtstolerantie (kg)</label>
                <input type="number" name="gewicht_tolerantie" id="gewicht_tolerantie"
                       value="{{ old('gewicht_tolerantie', $toernooi->gewicht_tolerantie) }}"
                       class="w-full border rounded px-3 py-2" min="0" max="5" step="0.1">
                <p class="text-gray-500 text-sm mt-1">
                    Hoeveel kg mag een judoka boven de gewichtsklasse-limiet wegen?
                    Standaard: 0.5 kg. Gebruik 0.3 voor strikter beleid.
                </p>
            </div>
        </div>

        <!-- GEWICHTSKLASSEN PER LEEFTIJD -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start mb-4 pb-2 border-b">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Leeftijds- en Gewichtsklassen</h2>
                    <p class="text-gray-600 text-sm mt-1">Pas leeftijdsgrenzen en gewichtsklassen aan per categorie.</p>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" id="gescheiden-toggle" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                        <span>Jongens/meiden gescheiden (Mini's & Pupillen)</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" id="btn-jbn-2025" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded text-sm">
                            JBN 2025
                        </button>
                        <button type="button" id="btn-jbn-2026" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm">
                            JBN 2026
                        </button>
                    </div>
                </div>
            </div>

            @php
                $gewichtsklassen = $toernooi->getAlleGewichtsklassen();
            @endphp

            <div id="gewichtsklassen-container" class="space-y-3">
                @foreach($gewichtsklassen as $key => $data)
                <div class="gewichtsklasse-item border rounded-lg p-4 bg-gray-50" data-key="{{ $key }}">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Max leeftijd:</label>
                            <input type="number" name="gewichtsklassen_leeftijd[{{ $key }}]"
                                   value="{{ $data['max_leeftijd'] ?? 99 }}"
                                   class="leeftijd-input w-16 border rounded px-2 py-1 text-center font-bold {{ ($data['max_leeftijd'] ?? 99) < 99 ? 'text-blue-600' : 'text-gray-400' }}"
                                   min="5" max="99">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Naam:</label>
                            <input type="text" name="gewichtsklassen_label[{{ $key }}]"
                                   value="{{ $data['label'] }}"
                                   class="label-input border rounded px-2 py-1 font-medium text-gray-800 w-32">
                        </div>
                        <button type="button" class="remove-categorie ml-auto text-red-400 hover:text-red-600 text-lg" title="Verwijder categorie">&times;</button>
                    </div>
                    <div>
                        <label class="text-gray-600 text-sm">Gewichtsklassen:</label>
                        <input type="text" name="gewichtsklassen[{{ $key }}]"
                               value="{{ implode(', ', $data['gewichten']) }}"
                               class="gewichten-input w-full border rounded px-3 py-2 font-mono text-sm mt-1"
                               placeholder="-20, -23, -26, +26">
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-4 flex gap-2">
                <button type="button" id="add-categorie" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                    + Categorie toevoegen
                </button>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                <strong>JBN 2025:</strong> -8, -10, -12, -15, -18 (huidige regels)<br>
                <strong>JBN 2026:</strong> -7, -9, -11, -13, -15 (nieuwe regels)
            </div>

            <input type="hidden" name="gewichtsklassen_json" id="gewichtsklassen_json_input">
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gewichtsklassen-container');
            const jsonInput = document.getElementById('gewichtsklassen_json_input');

            // JBN presets (gemengd = default, gescheiden = uitzondering)
            const jbn2025Gemengd = @json(\App\Models\Toernooi::getJbn2025GewichtsklassenGemengd());
            const jbn2026Gemengd = @json(\App\Models\Toernooi::getJbn2026GewichtsklassenGemengd());
            const jbn2025Gescheiden = @json(\App\Models\Toernooi::getJbn2025Gewichtsklassen());
            const jbn2026Gescheiden = @json(\App\Models\Toernooi::getJbn2026Gewichtsklassen());
            const gescheidenToggle = document.getElementById('gescheiden-toggle');

            function updateJsonInput() {
                const items = container.querySelectorAll('.gewichtsklasse-item');
                const data = {};
                items.forEach(item => {
                    const key = item.dataset.key;
                    const leeftijd = parseInt(item.querySelector('.leeftijd-input').value) || 99;
                    const label = item.querySelector('.label-input').value;
                    const gewichten = item.querySelector('.gewichten-input').value
                        .split(',')
                        .map(g => g.trim())
                        .filter(g => g);
                    data[key] = { label, max_leeftijd: leeftijd, gewichten };
                });
                jsonInput.value = JSON.stringify(data);
            }

            function renderCategorieen(data) {
                container.innerHTML = '';
                let index = 0;
                for (const [key, item] of Object.entries(data)) {
                    const div = document.createElement('div');
                    div.className = 'gewichtsklasse-item border rounded-lg p-4 bg-gray-50';
                    div.dataset.key = key;
                    const leeftijdClass = item.max_leeftijd < 99 ? 'text-blue-600' : 'text-gray-400';
                    div.innerHTML = `
                        <div class="flex items-center gap-4 mb-2">
                            <div class="flex items-center gap-2">
                                <label class="text-gray-600 text-sm">Max leeftijd:</label>
                                <input type="number" name="gewichtsklassen_leeftijd[${key}]"
                                       value="${item.max_leeftijd}"
                                       class="leeftijd-input w-16 border rounded px-2 py-1 text-center font-bold ${leeftijdClass}"
                                       min="5" max="99">
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-gray-600 text-sm">Naam:</label>
                                <input type="text" name="gewichtsklassen_label[${key}]"
                                       value="${item.label}"
                                       class="label-input border rounded px-2 py-1 font-medium text-gray-800 w-32">
                            </div>
                            <button type="button" class="remove-categorie ml-auto text-red-400 hover:text-red-600 text-lg" title="Verwijder categorie">&times;</button>
                        </div>
                        <div>
                            <label class="text-gray-600 text-sm">Gewichtsklassen:</label>
                            <input type="text" name="gewichtsklassen[${key}]"
                                   value="${item.gewichten.join(', ')}"
                                   class="gewichten-input w-full border rounded px-3 py-2 font-mono text-sm mt-1"
                                   placeholder="-20, -23, -26, +26">
                        </div>
                    `;
                    container.appendChild(div);
                    index++;
                }
                updateJsonInput();
            }

            // JBN buttons
            document.getElementById('btn-jbn-2025').addEventListener('click', () => {
                if (confirm('Dit vervangt alle huidige instellingen met JBN 2025 regels. Doorgaan?')) {
                    renderCategorieen(gescheidenToggle.checked ? jbn2025Gescheiden : jbn2025Gemengd);
                }
            });

            document.getElementById('btn-jbn-2026').addEventListener('click', () => {
                if (confirm('Dit vervangt alle huidige instellingen met JBN 2026 regels. Doorgaan?')) {
                    renderCategorieen(gescheidenToggle.checked ? jbn2026Gescheiden : jbn2026Gemengd);
                }
            });

            // Add category
            document.getElementById('add-categorie').addEventListener('click', () => {
                const items = container.querySelectorAll('.gewichtsklasse-item');
                const newKey = 'custom_' + Date.now();
                const div = document.createElement('div');
                div.className = 'gewichtsklasse-item border rounded-lg p-4 bg-gray-50';
                div.dataset.key = newKey;
                div.innerHTML = `
                    <div class="flex items-center gap-4 mb-2">
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Max leeftijd:</label>
                            <input type="number" name="gewichtsklassen_leeftijd[${newKey}]"
                                   value="99"
                                   class="leeftijd-input w-16 border rounded px-2 py-1 text-center font-bold text-gray-400"
                                   min="5" max="99">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Naam:</label>
                            <input type="text" name="gewichtsklassen_label[${newKey}]"
                                   value="Nieuwe categorie"
                                   class="label-input border rounded px-2 py-1 font-medium text-gray-800 w-32">
                        </div>
                        <button type="button" class="remove-categorie ml-auto text-red-400 hover:text-red-600 text-lg" title="Verwijder categorie">&times;</button>
                    </div>
                    <div>
                        <label class="text-gray-600 text-sm">Gewichtsklassen:</label>
                        <input type="text" name="gewichtsklassen[${newKey}]"
                               value=""
                               class="gewichten-input w-full border rounded px-3 py-2 font-mono text-sm mt-1"
                               placeholder="-20, -23, -26, +26">
                    </div>
                `;
                container.appendChild(div);
                updateJsonInput();
            });

            // Remove category
            container.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-categorie')) {
                    if (confirm('Deze categorie verwijderen?')) {
                        e.target.closest('.gewichtsklasse-item').remove();
                        updateJsonInput();
                    }
                }
            });

            // Update styling on leeftijd change
            container.addEventListener('input', (e) => {
                if (e.target.classList.contains('leeftijd-input')) {
                    const val = parseInt(e.target.value) || 99;
                    e.target.classList.toggle('text-blue-600', val < 99);
                    e.target.classList.toggle('text-gray-400', val >= 99);
                }
                updateJsonInput();
            });

            // Initial update
            updateJsonInput();
        });
        </script>

        <!-- ACTIES -->
        <div class="flex justify-between items-center">
            <a href="{{ route('toernooi.show', $toernooi) }}" class="text-gray-600 hover:text-gray-800">
                Annuleren
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg">
                Instellingen Opslaan
            </button>
        </div>
    </form>
    </div>

    <!-- TAB: ORGANISATIE -->
    <div x-show="activeTab === 'organisatie'" x-cloak>

    <!-- VRIJWILLIGERS LINKS -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="{ copied: null }">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Vrijwilligers Links</h2>
        <p class="text-gray-600 mb-4">
            Deel deze links met je vrijwilligers. Elke rol heeft een unieke geheime link - geen wachtwoord nodig.
            <br><span class="text-sm text-gray-500">Na klikken verdwijnt de code uit de adresbalk.</span>
        </p>

        <div class="space-y-4">
            @php
                $rollen = [
                    'hoofdjury' => ['icon' => '⚖️', 'naam' => 'Hoofdjury', 'desc' => 'Overzicht alle poules'],
                    'weging' => ['icon' => '⚖️', 'naam' => 'Weging', 'desc' => 'Weeglijst en registratie'],
                    'mat' => ['icon' => '🥋', 'naam' => 'Mat', 'desc' => 'Wedstrijden per mat'],
                    'spreker' => ['icon' => '🎙️', 'naam' => 'Spreker', 'desc' => 'Omroep interface'],
                    'dojo' => ['icon' => '🚪', 'naam' => 'Dojo', 'desc' => 'Scanner bij ingang'],
                ];
            @endphp

            @foreach($rollen as $rol => $info)
            <div class="p-4 border rounded-lg bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">{{ $info['icon'] }}</span>
                        <div>
                            <h3 class="font-bold">{{ $info['naam'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $info['desc'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $toernooi->getRoleUrl($rol) }}'); copied = '{{ $rol }}'; setTimeout(() => copied = null, 2000)"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm whitespace-nowrap">
                            <span x-show="copied !== '{{ $rol }}'">Kopieer link</span>
                            <span x-show="copied === '{{ $rol }}'" x-cloak>Gekopieerd!</span>
                        </button>
                        <a href="{{ $toernooi->getRoleUrl($rol) }}" target="_blank"
                           class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded text-sm" title="Test link">
                            Test
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-bold text-blue-800 mb-2">Voorbeeld bericht voor WhatsApp/Email:</h4>
            <div class="bg-white p-3 rounded border text-sm text-gray-700">
                Hoi! Morgen is het toernooi. Klik op je link om in te loggen:<br><br>
                @foreach($rollen as $rol => $info)
                👉 <strong>{{ $info['naam'] }}</strong><br>
                @endforeach
            </div>
            <p class="text-xs text-blue-600 mt-2">Stuur elke vrijwilliger alleen zijn/haar eigen link!</p>
        </div>
    </div>

    <!-- BLOKTIJDEN -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Bloktijden</h2>
        <p class="text-gray-600 mb-4">
            Stel de weeg- en starttijden in per blok. Deze tijden worden getoond op weegkaarten.
        </p>

        <form action="{{ route('toernooi.bloktijden', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="text-left py-2 px-3 font-medium">Blok</th>
                            <th class="text-left py-2 px-3 font-medium">Weging Start</th>
                            <th class="text-left py-2 px-3 font-medium">Weging Einde</th>
                            <th class="text-left py-2 px-3 font-medium">Start Wedstrijden</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blokken as $blok)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-3 font-medium">Blok {{ $blok->nummer }}</td>
                            <td class="py-2 px-3">
                                <input type="time" name="blokken[{{ $blok->id }}][weging_start]"
                                       value="{{ $blok->weging_start?->format('H:i') }}"
                                       class="border rounded px-2 py-1 w-28">
                            </td>
                            <td class="py-2 px-3">
                                <input type="time" name="blokken[{{ $blok->id }}][weging_einde]"
                                       value="{{ $blok->weging_einde?->format('H:i') }}"
                                       class="border rounded px-2 py-1 w-28">
                            </td>
                            <td class="py-2 px-3">
                                <input type="time" name="blokken[{{ $blok->id }}][starttijd]"
                                       value="{{ $blok->starttijd?->format('H:i') }}"
                                       class="border rounded px-2 py-1 w-28">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                <strong>Tip:</strong> De weger ziet een countdown timer en krijgt een rode waarschuwing wanneer de weegtijd voorbij is. De weging wordt handmatig gesloten via de knop in de weging interface.
            </div>

            <div class="mt-4 text-right">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">
                    Bloktijden Opslaan
                </button>
            </div>
        </form>
    </div>

    <!-- WACHTWOORDEN (legacy - voor oude systeem) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Wachtwoorden (Legacy)</h2>
        <p class="text-gray-600 mb-4">
            <span class="text-orange-600 text-sm">Oude methode - gebruik liever de Vrijwilligers Links hierboven.</span><br>
            Wachtwoord login pagina:
            <a href="{{ route('toernooi.auth.login', $toernooi) }}" class="text-blue-600 hover:underline" target="_blank">
                {{ route('toernooi.auth.login', $toernooi) }}
            </a>
        </p>

        <form action="{{ route('toernooi.wachtwoorden', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">👑</span>
                        <div>
                            <h3 class="font-bold">Admin</h3>
                            <p class="text-sm text-gray-500">Volledig beheer</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('admin'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_admin" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">⚖️</span>
                        <div>
                            <h3 class="font-bold">Jury</h3>
                            <p class="text-sm text-gray-500">Hoofdtafel overzicht</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('jury'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_jury" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">⚖️</span>
                        <div>
                            <h3 class="font-bold">Weging</h3>
                            <p class="text-sm text-gray-500">Alleen weeglijst</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('weging'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_weging" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🥋</span>
                        <div>
                            <h3 class="font-bold">Mat</h3>
                            <p class="text-sm text-gray-500">Wedstrijdschema per mat</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('mat'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_mat" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg col-span-2">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🎙️</span>
                        <div>
                            <h3 class="font-bold">Spreker</h3>
                            <p class="text-sm text-gray-500">Omroepen wedstrijden</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('spreker'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_spreker" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 rounded text-sm text-yellow-800">
                <strong>Let op:</strong> Laat een veld leeg om het huidige wachtwoord te behouden.
                Wachtwoorden worden versleuteld opgeslagen.
            </div>

            <div class="mt-4 text-right">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">
                    Wachtwoorden Opslaan
                </button>
            </div>
        </form>
    </div>

    </div><!-- End TAB: ORGANISATIE -->

</div>

<script>
// Auto-save for toernooi settings
(function() {
    const form = document.getElementById('toernooi-form');
    if (!form) return;

    const status = document.getElementById('save-status');
    let saveTimeout = null;
    let isDirty = false;  // Track if form has unsaved changes

    function showStatus(text, type) {
        status.textContent = text;
        status.classList.remove('hidden', 'text-gray-400', 'text-green-600', 'text-red-600');
        status.classList.add(type === 'success' ? 'text-green-600' : type === 'error' ? 'text-red-600' : 'text-gray-400');
    }

    function markDirty() {
        isDirty = true;
    }

    function markClean() {
        isDirty = false;
    }

    function autoSave() {
        if (!isDirty) return;

        showStatus('Opslaan...', 'default');

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok || response.redirected) {
                showStatus('✓ Opgeslagen', 'success');
                markClean();
                setTimeout(() => status.classList.add('hidden'), 2000);
            } else {
                showStatus('✗ Fout bij opslaan', 'error');
            }
        })
        .catch(() => {
            showStatus('✗ Fout bij opslaan', 'error');
        });
    }

    // Listen for changes on all form elements
    form.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('change', () => {
            markDirty();
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(autoSave, 500);
        });
        // For text inputs, also listen to input event with longer debounce
        if (el.type === 'text' || el.type === 'number' || el.tagName === 'TEXTAREA') {
            el.addEventListener('input', () => {
                markDirty();
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(autoSave, 1500);
            });
        }
    });

    // Warn before leaving if there are unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
@endsection
