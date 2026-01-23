@extends('layouts.app')

@section('title', 'Instellingen')

@section('content')
<!-- Fixed toast voor autosave status -->
<div id="save-status" class="fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-sm font-medium hidden bg-white border"></div>

<div class="max-w-4xl mx-auto" x-data="{ activeTab: '{{ request('tab', 'toernooi') }}' }">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-800">Instellingen</h1>
            <!-- Verplaatst naar fixed toast -->
        </div>
        <div class="flex items-center gap-4">
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
        @if(auth()->user()?->email === 'henkvu@gmail.com' || session("toernooi_{$toernooi->id}_rol") === 'admin')
        <button type="button"
                @click="activeTab = 'test'"
                :class="activeTab === 'test' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-6 py-3 font-medium border-b-2 -mb-px transition-colors">
            🧪 Test
        </button>
        @endif
    </div>

    <!-- WAARSCHUWING: Niet-gecategoriseerde judoka's -->
    @php
        $nietGecategoriseerdAantal = $toernooi->countNietGecategoriseerd();
    @endphp
    @if($nietGecategoriseerdAantal > 0)
    <div id="niet-gecategoriseerd-alert"
         class="mb-6 p-4 bg-red-100 border-2 border-red-500 rounded-lg animate-error-blink"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => $el.classList.remove('animate-error-blink'), 10000)">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <p class="font-bold text-red-800">{{ $nietGecategoriseerdAantal }} judoka('s) niet gecategoriseerd!</p>
                    <p class="text-sm text-red-700">Geen categorie past bij deze judoka('s). Pas de categorie-instellingen aan.</p>
                </div>
            </div>
            <a href="{{ route('toernooi.judoka.index', $toernooi) }}?filter=niet_gecategoriseerd"
               class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-medium">
                Bekijk lijst
            </a>
        </div>
    </div>
    @endif

    <!-- WAARSCHUWING: Overlappende categorieën -->
    @if(isset($overlapWarning) && $overlapWarning)
    <div id="overlap-warning-alert"
         class="mb-6 p-4 bg-orange-100 border-2 border-orange-500 rounded-lg animate-error-blink"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => $el.classList.remove('animate-error-blink'), 10000)">
        <div class="flex items-start gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-bold text-orange-800">Overlappende categorieën gedetecteerd!</p>
                <p class="text-sm text-orange-700">{{ $overlapWarning }}</p>
                <p class="text-xs text-orange-600 mt-1">Judoka's kunnen in meerdere categorieën passen. Pas de categorie-instellingen aan (geslacht of bandfilter).</p>
            </div>
        </div>
    </div>
    @endif

    <!-- TAB: TOERNOOI -->
    <div x-show="activeTab === 'toernooi'" x-cloak>
    <form action="{{ route('toernooi.update', $toernooi) }}" method="POST" id="toernooi-form" data-loading="Instellingen opslaan...">
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

            <!-- Eliminatie Type (KO systeem) -->
            <div class="border-t pt-4 mt-4">
                <h3 class="font-medium text-gray-700 mb-2">Knock-out Systeem</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Kies het type eliminatie bracket voor gewichtsklassen met "Direct eliminatie"
                </p>

                @php
                    $eliminatieType = old('eliminatie_type', $toernooi->eliminatie_type) ?? 'dubbel';
                @endphp

                <div class="flex gap-4">
                    <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-blue-50 {{ $eliminatieType === 'dubbel' ? 'border-blue-500 bg-blue-50' : 'bg-white' }}">
                        <input type="radio" name="eliminatie_type" value="dubbel"
                               {{ $eliminatieType === 'dubbel' ? 'checked' : '' }}
                               class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <span class="font-medium text-sm">Dubbel Eliminatie</span>
                            <span class="block text-xs text-gray-500 mt-1">
                                Alle verliezers krijgen herkansing in B-groep.<br>
                                Meer wedstrijden, iedereen minimaal 2x judoën.<br>
                                <span class="text-blue-600">Aanbevolen voor jeugdtoernooien</span>
                            </span>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-blue-50 {{ $eliminatieType === 'ijf' ? 'border-blue-500 bg-blue-50' : 'bg-white' }}">
                        <input type="radio" name="eliminatie_type" value="ijf"
                               {{ $eliminatieType === 'ijf' ? 'checked' : '' }}
                               class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <span class="font-medium text-sm">IJF Repechage</span>
                            <span class="block text-xs text-gray-500 mt-1">
                                Officieel systeem: alleen verliezers van 1/4 finale<br>
                                (die verloren van finalisten) krijgen herkansing.<br>
                                <span class="text-orange-600">Minder wedstrijden, strenger</span>
                            </span>
                        </div>
                    </label>
                </div>

                <!-- Aantal bronzen medailles -->
                <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-sm font-medium text-amber-800 mb-2">
                        🥉 Aantal bronzen medailles
                    </p>
                    @php
                        $aantalBrons = old('aantal_brons', $toernooi->aantal_brons) ?? 2;
                    @endphp
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="aantal_brons" value="2"
                                   {{ $aantalBrons == 2 ? 'checked' : '' }}
                                   class="w-4 h-4 text-amber-600">
                            <span class="text-sm">
                                <strong>2 bronzen</strong>
                                <span class="text-gray-500">(2 brons wedstrijden, 2 winnaars)</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="aantal_brons" value="1"
                                   {{ $aantalBrons == 1 ? 'checked' : '' }}
                                   class="w-4 h-4 text-amber-600">
                            <span class="text-sm">
                                <strong>1 brons</strong>
                                <span class="text-gray-500">(kleine finale, 1 winnaar)</span>
                            </span>
                        </label>
                    </div>
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
                $rawSchemas = old('wedstrijd_schemas', $toernooi->wedstrijd_schemas);
                $opgeslagenSchemas = is_array($rawSchemas) ? $rawSchemas : [];
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
                    $saved = $opgeslagenSchemas[$grootte] ?? null;
                    $schema = is_array($saved) ? $saved : $standaardSchemas[$grootte];
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
                if (window.triggerAutoSave) window.triggerAutoSave();
            });

            // Remove button
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-voorkeur')) {
                    e.target.closest('.voorkeur-item').remove();
                    updateHiddenInput();
                    if (window.triggerAutoSave) window.triggerAutoSave();
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
                if (window.triggerAutoSave) window.triggerAutoSave();
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
                const labels = { leeftijd: '📅 Leeftijd', gewicht: '🏋️ Gewicht', band: '🥋 Band' };
                items.forEach((item, idx) => {
                    item.textContent = `${idx + 1}. ${labels[item.dataset.key]}`;
                });
                if (window.triggerAutoSave) window.triggerAutoSave();
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
                    if (window.triggerAutoSave) window.triggerAutoSave();
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

                <!-- Coach in/uitcheck systeem -->
                <div class="flex items-center gap-3 mt-4 p-3 bg-gray-50 rounded-lg">
                    <input type="checkbox" name="coach_incheck_actief" id="coach_incheck_actief"
                           value="1" {{ old('coach_incheck_actief', $toernooi->coach_incheck_actief) ? 'checked' : '' }}
                           class="w-5 h-5 text-blue-600 rounded">
                    <div>
                        <label for="coach_incheck_actief" class="text-gray-700 font-medium">Coach in/uitcheck bij dojo</label>
                        <p class="text-sm text-gray-500">Coaches moeten eerst uitchecken voordat kaart kan worden overgedragen</p>
                    </div>
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

        <!-- CATEGORIEËN INSTELLING -->
        @php
            // Lees preset type uit gewichtsklassen JSON
            $gewichtsklassenData = $toernooi->gewichtsklassen;
            if (!is_array($gewichtsklassenData)) {
                $gewichtsklassenData = [];
            }
            $categorieType = $gewichtsklassenData['_preset_type'] ?? 'geen_standaard';
            $eigenPresetId = $gewichtsklassenData['_eigen_preset_id'] ?? null;

            // Als er een eigen preset is opgeslagen, gebruik 'eigen' als type
            if ($eigenPresetId) {
                $categorieType = 'eigen';
            }

            // Backwards compatibility
            if ($categorieType === 'geen_standaard' && ($toernooi->gebruik_gewichtsklassen ?? false)) {
                $categorieType = 'jbn_2026';
            }
        @endphp
        <div id="categorieen" class="bg-white rounded-lg shadow p-6 mb-6" x-data="{ categorieType: '{{ $categorieType }}' }">
            <div class="flex justify-between items-start mb-4 pb-2 border-b">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Categorieën Instelling</h2>
                    <p class="text-gray-600 text-sm mt-1">Kies een startpunt en pas categorieën aan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Hoofdkeuze: radio buttons -->
                    <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                        <label class="cursor-pointer">
                            <input type="radio" name="categorie_type" value="jbn_2025"
                                   x-model="categorieType"
                                   @change="if($event.target.checked) loadJbn2025()"
                                   class="sr-only peer">
                            <span class="block px-3 py-2 rounded text-sm peer-checked:bg-white peer-checked:shadow peer-checked:font-medium">
                                JBN 2025
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="categorie_type" value="jbn_2026"
                                   x-model="categorieType"
                                   @change="if($event.target.checked) loadJbn2026()"
                                   class="sr-only peer">
                            <span class="block px-3 py-2 rounded text-sm peer-checked:bg-white peer-checked:shadow peer-checked:font-medium">
                                JBN 2026
                            </span>
                        </label>
                        <label class="cursor-pointer" id="eigen-preset-radio-label">
                            <input type="radio" name="categorie_type" value="eigen"
                                   x-model="categorieType"
                                   @change="if($event.target.checked) loadEigenPreset()"
                                   class="sr-only peer">
                            <span class="block px-3 py-2 rounded text-sm text-gray-400 peer-checked:bg-green-100 peer-checked:shadow peer-checked:font-medium peer-checked:text-green-800" id="eigen-preset-naam-display">
                                Eigen preset...
                            </span>
                        </label>
                    </div>
                    <!-- Eigen presets -->
                    <select id="eigen-presets-dropdown" class="border rounded px-2 py-2 text-sm bg-white min-w-[120px]">
                        <option value="">Preset...</option>
                    </select>
                    <button type="button" id="btn-save-preset" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm" title="Huidige configuratie opslaan">
                        💾 Opslaan
                    </button>
                </div>
            </div>

            <!-- Preset opslaan modal -->
            <div id="preset-save-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-bold mb-4" id="preset-modal-title">Preset opslaan</h3>
                    <div id="preset-modal-content"></div>
                </div>
            </div>

            <!-- Sorteer prioriteit: altijd tonen, bepaalt volgorde binnen harde criteria -->
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <!-- Prioriteit drag & drop -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-yellow-800 text-sm font-medium">Sorteer prioriteit:</span>
                    <button type="button"
                            x-data="{ open: false }"
                            @click="open = !open"
                            class="relative text-yellow-600 hover:text-yellow-800">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-yellow-200 text-xs font-bold">i</span>
                        <div x-show="open"
                             @click.away="open = false"
                             x-transition
                             class="absolute left-0 top-6 z-50 w-72 p-3 bg-white border border-yellow-300 rounded-lg shadow-lg text-sm text-gray-700">
                            <p class="font-medium mb-1">Sorteer volgorde binnen categorie</p>
                            <p class="text-xs">Bepaalt hoe judoka's worden gesorteerd voordat ze over poules worden verdeeld.</p>
                            <ul class="text-xs mt-2 space-y-1">
                                <li><strong>Leeftijd:</strong> Jongste judoka's eerst</li>
                                <li><strong>Gewicht:</strong> Lichtste judoka's eerst</li>
                                <li><strong>Band:</strong> Lagere banden eerst (wit → zwart)</li>
                            </ul>
                        </div>
                    </button>
                    <div id="prioriteit-container" class="flex gap-2">
                        @php
                            $prioriteiten = $toernooi->verdeling_prioriteiten ?? ['leeftijd', 'gewicht', 'band'];
                            // Backwards compatibility: filter old keys, keep only valid ones
                            $validKeys = ['leeftijd', 'gewicht', 'band'];
                            $prioriteiten = array_values(array_filter($prioriteiten, fn($k) => in_array($k, $validKeys)));
                            // Ensure all 3 keys exist
                            foreach ($validKeys as $key) {
                                if (!in_array($key, $prioriteiten)) {
                                    $prioriteiten[] = $key;
                                }
                            }
                            $prioriteiten = array_slice(array_unique($prioriteiten), 0, 3);
                            $labels = ['leeftijd' => '📅 Leeftijd', 'gewicht' => '🏋️ Gewicht', 'band' => '🥋 Band'];
                        @endphp
                        @foreach($prioriteiten as $idx => $key)
                        <div class="prioriteit-item bg-yellow-100 border border-yellow-300 rounded px-3 py-1 cursor-move text-sm" draggable="true" data-key="{{ $key }}">{{ $idx + 1 }}. {{ $labels[$key] ?? $key }}</div>
                        @endforeach
                    </div>
                    <span class="text-yellow-600 text-xs">(sleep om te wisselen)</span>
                </div>
                <input type="hidden" name="verdeling_prioriteiten" id="prioriteit_input" value='@json($prioriteiten)'>

            </div>

            @php
                $gewichtsklassen = $toernooi->getAlleGewichtsklassen();
            @endphp

            <!-- Container wordt gevuld door JavaScript met één template voor alle categorieën -->
            <div id="gewichtsklassen-container" class="space-y-3"></div>

            <!-- Initiële data voor JavaScript -->
            <script>
                window.initieleGewichtsklassen = @json($gewichtsklassen);
                window.initieleWedstrijdSysteem = @json(old('wedstrijd_systeem', $toernooi->wedstrijd_systeem) ?? []);
            </script>

            <div class="mt-4 flex gap-2">
                <button type="button" id="add-categorie" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                    + Categorie toevoegen
                </button>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                <strong>JBN 2025:</strong> U8, U10, U12, U15, U18, U21 (vaste gewichtsklassen)<br>
                <strong>JBN 2026:</strong> U7/U9 dynamisch, U11+ vaste klassen (M/V gescheiden)
            </div>

            <input type="hidden" name="gewichtsklassen_json" id="gewichtsklassen_json_input">
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gewichtsklassen-container');
            const jsonInput = document.getElementById('gewichtsklassen_json_input');

            // JBN presets (gemengd = default, gescheiden = uitzondering)
            const jbn2025 = @json(\App\Models\Toernooi::getJbn2025Gewichtsklassen());
            const jbn2026 = @json(\App\Models\Toernooi::getJbn2026Gewichtsklassen());

            function updateJsonInput() {
                const items = container.querySelectorAll('.gewichtsklasse-item');
                const data = {};

                // Sla preset type op
                const presetRadio = document.querySelector('input[name="categorie_type"]:checked');
                const presetDropdown = document.getElementById('eigen-presets-dropdown');
                if (presetRadio) {
                    data._preset_type = presetRadio.value;
                }
                if (presetDropdown && presetDropdown.value) {
                    data._eigen_preset_id = presetDropdown.value;
                }

                items.forEach(item => {
                    const key = item.dataset.key;
                    const leeftijd = parseInt(item.querySelector('.leeftijd-input').value) || 99;
                    const label = item.querySelector('.label-input').value;
                    const toonLabel = item.querySelector('.toon-label-checkbox')?.checked ?? true;
                    const geslacht = item.querySelector('.geslacht-select')?.value || 'gemengd';
                    const maxKg = parseFloat(item.querySelector('.max-kg-input')?.value) || 0;
                    const maxLft = parseInt(item.querySelector('.max-lft-input')?.value) || 0;
                    const bandFilter = item.querySelector('.band-filter-select')?.value || null;
                    const gewichten = item.querySelector('.gewichten-input')?.value
                        .split(',')
                        .map(g => g.trim())
                        .filter(g => g) || [];
                    const systeem = item.querySelector('.systeem-select')?.value || 'poules';
                    data[key] = { label, toon_label_in_titel: toonLabel, max_leeftijd: leeftijd, geslacht, max_kg_verschil: maxKg, max_leeftijd_verschil: maxLft, band_filter: bandFilter, gewichten, wedstrijd_systeem: systeem };
                });
                jsonInput.value = JSON.stringify(data);
            }

            // Global functies voor radio buttons
            window.loadGeenStandaard = function() {
                if (confirm('Dit maakt alle categorieën leeg. Je moet zelf categorieën toevoegen. Doorgaan?')) {
                    container.innerHTML = '';
                    updateJsonInput();
                } else {
                    // Reset radio naar vorige waarde
                    document.querySelector('input[name="categorie_type"][value="jbn_2026"]').checked = true;
                }
            }

            window.loadJbn2025 = function() {
                if (confirm('Dit vervangt alle huidige instellingen met JBN 2025 regels. Doorgaan?')) {
                    renderCategorieen(jbn2025);
                } else {
                    document.querySelector('input[name="categorie_type"][value="jbn_2026"]').checked = true;
                }
            }

            window.loadJbn2026 = function() {
                if (confirm('Dit vervangt alle huidige instellingen met JBN 2026 regels. Doorgaan?')) {
                    renderCategorieen(jbn2026);
                } else {
                    document.querySelector('input[name="categorie_type"][value="jbn_2026"]').checked = true;
                }
            }

            // Load eigen preset when radio button is clicked
            window.loadEigenPreset = function() {
                const presetId = document.getElementById('eigen-presets-dropdown').value;
                if (!presetId) return;

                const preset = eigenPresets.find(p => p.id == presetId);
                if (preset && preset.configuratie) {
                    renderCategorieen(preset.configuratie);
                }
            }

            // Toggle gewichtsklassen visibility based on max_kg_verschil
            window.toggleGewichtsklassen = function(input) {
                const item = input.closest('.gewichtsklasse-item');
                const gewichtenContainer = item.querySelector('.gewichten-container');
                const dynamischLabel = item.querySelector('.dynamisch-label');
                const maxKg = parseFloat(input.value) || 0;

                if (maxKg > 0) {
                    gewichtenContainer?.classList.add('hidden');
                    dynamischLabel?.classList.remove('hidden');
                } else {
                    gewichtenContainer?.classList.remove('hidden');
                    dynamischLabel?.classList.add('hidden');
                }
                updateJsonInput();
            }

            // Sorteer categorieën: jong→oud, gewicht licht→zwaar, band laag→hoog
            function sorteerCategorieen(data) {
                const bandFilterVolgorde = {
                    '': 0, 'tm_wit': 1, 'tm_geel': 2, 'tm_oranje': 3, 'tm_groen': 4, 'tm_blauw': 5, 'tm_bruin': 6,
                    'vanaf_geel': 10, 'vanaf_oranje': 11, 'vanaf_groen': 12, 'vanaf_blauw': 13, 'vanaf_bruin': 14, 'vanaf_zwart': 15
                };

                const sorted = Object.entries(data).sort((a, b) => {
                    const [, itemA] = a;
                    const [, itemB] = b;

                    // 1. Leeftijd: jong → oud
                    const leeftijdA = itemA.max_leeftijd || 99;
                    const leeftijdB = itemB.max_leeftijd || 99;
                    if (leeftijdA !== leeftijdB) return leeftijdA - leeftijdB;

                    // 2. Gewicht: licht → zwaar (eerste gewicht uit array)
                    const gewichtA = itemA.gewichten?.[0] ? parseFloat(itemA.gewichten[0].replace(/[^\d.]/g, '')) : 0;
                    const gewichtB = itemB.gewichten?.[0] ? parseFloat(itemB.gewichten[0].replace(/[^\d.]/g, '')) : 0;
                    if (gewichtA !== gewichtB) return gewichtA - gewichtB;

                    // 3. Band: laag → hoog
                    const bandA = bandFilterVolgorde[itemA.band_filter || ''] || 0;
                    const bandB = bandFilterVolgorde[itemB.band_filter || ''] || 0;
                    return bandA - bandB;
                });
                return sorted;
            }

            // Single template function for all categories (DRY principle)
            function createCategorieElement(key, item) {
                const div = document.createElement('div');
                div.className = 'gewichtsklasse-item border rounded-lg p-4 bg-gray-50 cursor-move';
                div.dataset.key = key;
                div.draggable = true;

                const leeftijdValue = item.max_leeftijd < 99 ? item.max_leeftijd : '';
                const label = item.label || '';
                const geslacht = item.geslacht || 'gemengd';
                const toonLabel = item.toon_label_in_titel ?? true;
                const maxKg = item.max_kg_verschil || 0;
                const maxLft = item.max_leeftijd_verschil || 0;

                // Support both old band_tot and new band_filter
                let bandFilter = item.band_filter || item.band_tot || '';
                if (bandFilter && !bandFilter.includes('_')) {
                    bandFilter = 'tm_' + bandFilter;
                }

                // Get wedstrijd systeem from initieleWedstrijdSysteem
                const systeem = window.initieleWedstrijdSysteem?.[key] || 'poules';

                const gewichtenHidden = maxKg > 0 ? 'hidden' : '';
                const dynamischHidden = maxKg > 0 ? '' : 'hidden';

                div.innerHTML = `
                    <div class="flex flex-wrap items-center gap-3 mb-2">
                        <div class="drag-handle text-gray-400 hover:text-gray-600 cursor-grab active:cursor-grabbing" title="Sleep om te verplaatsen">☰</div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Naam:</label>
                            <input type="text" name="gewichtsklassen_label[${key}]"
                                   value="${label}"
                                   placeholder="Categorie naam"
                                   class="label-input border rounded px-2 py-1 font-medium text-gray-800 w-44">
                        </div>
                        <div class="flex items-center gap-1">
                            <input type="checkbox" name="gewichtsklassen_toon_label[${key}]"
                                   class="toon-label-checkbox"
                                   ${toonLabel ? 'checked' : ''}>
                            <label class="text-gray-500 text-xs">in titel</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm whitespace-nowrap">Max:</label>
                            <input type="number" name="gewichtsklassen_leeftijd[${key}]"
                                   value="${leeftijdValue}"
                                   placeholder="99"
                                   class="leeftijd-input w-12 border rounded px-1 py-1 text-center font-bold text-blue-600 placeholder-gray-400"
                                   min="5" max="99">
                            <span class="text-xs text-gray-500">jr</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <select name="gewichtsklassen_geslacht[${key}]"
                                    class="geslacht-select border rounded px-1 py-1 text-sm bg-white w-16">
                                <option value="gemengd" ${geslacht === 'gemengd' ? 'selected' : ''}>M&V</option>
                                <option value="M" ${geslacht === 'M' ? 'selected' : ''}>M</option>
                                <option value="V" ${geslacht === 'V' ? 'selected' : ''}>V</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <select name="wedstrijd_systeem[${key}]"
                                    class="systeem-select border rounded px-2 py-1 text-sm bg-white">
                                <option value="poules" ${systeem === 'poules' ? 'selected' : ''}>Poules</option>
                                <option value="poules_kruisfinale" ${systeem === 'poules_kruisfinale' ? 'selected' : ''}>Kruisfinale</option>
                                <option value="eliminatie" ${systeem === 'eliminatie' ? 'selected' : ''}>Eliminatie</option>
                            </select>
                        </div>
                        <button type="button" class="remove-categorie ml-auto text-red-400 hover:text-red-600 text-lg" title="Verwijder categorie">&times;</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm whitespace-nowrap">Δkg:</label>
                            <input type="number" name="gewichtsklassen_max_kg[${key}]"
                                   value="${maxKg}"
                                   class="max-kg-input w-12 border rounded px-1 py-1 text-center text-sm"
                                   min="0" max="10" step="0.5"
                                   onchange="toggleGewichtsklassen(this)">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm whitespace-nowrap">Δlft:</label>
                            <input type="number" name="gewichtsklassen_max_lft[${key}]"
                                   value="${maxLft}"
                                   class="max-lft-input w-12 border rounded px-1 py-1 text-center text-sm"
                                   min="0" max="5" step="1"
                                   title="0 = categorie limiet, 1-2 = max jaren verschil in poule">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-gray-600 text-sm">Band:</label>
                            <select name="gewichtsklassen_band_filter[${key}]"
                                    class="band-filter-select border rounded px-2 py-1 text-sm bg-white">
                                <option value="" ${!bandFilter ? 'selected' : ''}>Alle banden</option>
                                <optgroup label="t/m (beginners)">
                                    <option value="tm_wit" ${bandFilter === 'tm_wit' ? 'selected' : ''}>t/m wit</option>
                                    <option value="tm_geel" ${bandFilter === 'tm_geel' ? 'selected' : ''}>t/m geel</option>
                                    <option value="tm_oranje" ${bandFilter === 'tm_oranje' ? 'selected' : ''}>t/m oranje</option>
                                    <option value="tm_groen" ${bandFilter === 'tm_groen' ? 'selected' : ''}>t/m groen</option>
                                    <option value="tm_blauw" ${bandFilter === 'tm_blauw' ? 'selected' : ''}>t/m blauw</option>
                                    <option value="tm_bruin" ${bandFilter === 'tm_bruin' ? 'selected' : ''}>t/m bruin</option>
                                </optgroup>
                                <optgroup label="vanaf (gevorderden)">
                                    <option value="vanaf_geel" ${bandFilter === 'vanaf_geel' ? 'selected' : ''}>vanaf geel</option>
                                    <option value="vanaf_oranje" ${bandFilter === 'vanaf_oranje' ? 'selected' : ''}>vanaf oranje</option>
                                    <option value="vanaf_groen" ${bandFilter === 'vanaf_groen' ? 'selected' : ''}>vanaf groen</option>
                                    <option value="vanaf_blauw" ${bandFilter === 'vanaf_blauw' ? 'selected' : ''}>vanaf blauw</option>
                                    <option value="vanaf_bruin" ${bandFilter === 'vanaf_bruin' ? 'selected' : ''}>vanaf bruin</option>
                                    <option value="vanaf_zwart" ${bandFilter === 'vanaf_zwart' ? 'selected' : ''}>vanaf zwart</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="gewichten-container flex-1 ${gewichtenHidden}">
                            <input type="text" name="gewichtsklassen[${key}]"
                                   value="${(item.gewichten || []).join(', ')}"
                                   class="gewichten-input w-full border rounded px-3 py-2 font-mono text-sm"
                                   placeholder="-20, -23, -26, +26">
                        </div>
                        <div class="dynamisch-label text-sm text-blue-600 italic ${dynamischHidden}">
                            Dynamische indeling
                        </div>
                    </div>
                `;
                return div;
            }

            function renderCategorieen(data, sorteer = true) {
                container.innerHTML = '';
                const entries = sorteer ? sorteerCategorieen(data) : Object.entries(data);
                for (const [key, item] of entries) {
                    container.appendChild(createCategorieElement(key, item));
                }
                updateJsonInput();
            }

            // Initial load - render saved categories
            if (window.initieleGewichtsklassen && Object.keys(window.initieleGewichtsklassen).length > 0) {
                renderCategorieen(window.initieleGewichtsklassen, false);
            }

            // Eigen presets
            const presetsDropdown = document.getElementById('eigen-presets-dropdown');
            let eigenPresets = [];

            // Load user presets on page load (only for organisator)
            // Opgeslagen eigen preset ID (uit gewichtsklassen JSON)
            const opgeslagenEigenPresetId = {{ $eigenPresetId ?? 'null' }};

            async function loadEigenPresets() {
                @if(Auth::guard('organisator')->check())
                try {
                    const response = await fetch('{{ route("organisator.presets.index") }}', {
                        credentials: 'same-origin'
                    });
                    if (response.ok) {
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            eigenPresets = await response.json();
                            presetsDropdown.innerHTML = '<option value="">Eigen preset...</option>';
                            eigenPresets.forEach(preset => {
                                const option = document.createElement('option');
                                option.value = preset.id;
                                option.textContent = preset.naam;
                                presetsDropdown.appendChild(option);
                            });
                            // Selecteer opgeslagen preset in dropdown en activeer radio button
                            if (opgeslagenEigenPresetId) {
                                huidigePresetId = opgeslagenEigenPresetId;
                                // Find preset name and show in radio
                                const savedPreset = eigenPresets.find(p => p.id == opgeslagenEigenPresetId);
                                if (savedPreset) {
                                    huidigePresetNaam = savedPreset.naam;
                                    // Selecteer in dropdown
                                    presetsDropdown.value = opgeslagenEigenPresetId;
                                    // Show AND activate radio button with preset name
                                    setTimeout(() => updateEigenPresetRadio(savedPreset.naam, true), 0);
                                }
                            }
                        }
                    }
                } catch (e) {
                    // Silently fail - presets are optional
                }
                @endif
            }
            loadEigenPresets();

            // Track currently loaded preset
            let huidigePresetId = opgeslagenEigenPresetId;
            let huidigePresetNaam = null;

            // DOM elements for eigen preset radio
            const eigenPresetRadioLabel = document.getElementById('eigen-preset-radio-label');
            const eigenPresetNaamDisplay = document.getElementById('eigen-preset-naam-display');
            const eigenPresetRadio = document.querySelector('input[name="categorie_type"][value="eigen"]');

            // Show/hide eigen preset radio button with name
            function updateEigenPresetRadio(naam, activate = false) {
                if (naam) {
                    eigenPresetNaamDisplay.textContent = naam;
                    eigenPresetRadioLabel.style.display = '';
                    if (activate) {
                        eigenPresetRadio.checked = true;
                        // Also update Alpine state
                        const alpineRoot = document.querySelector('[x-data*="categorieType"]');
                        if (alpineRoot && alpineRoot._x_dataStack) {
                            alpineRoot._x_dataStack[0].categorieType = 'eigen';
                        }
                    }
                } else {
                    eigenPresetRadioLabel.style.display = 'none';
                }
                updateJsonInput();
            }

            // Load selected preset
            presetsDropdown.addEventListener('change', () => {
                const presetId = presetsDropdown.value;
                if (!presetId) {
                    huidigePresetId = null;
                    huidigePresetNaam = null;
                    updateEigenPresetRadio(null);
                    return;
                }

                const preset = eigenPresets.find(p => p.id == presetId);
                if (!preset) return;

                if (confirm(`Preset "${preset.naam}" laden? Dit vervangt alle huidige instellingen.`)) {
                    renderCategorieen(preset.configuratie);
                    huidigePresetId = preset.id;
                    huidigePresetNaam = preset.naam;
                    // Show and activate eigen preset radio
                    updateEigenPresetRadio(preset.naam, true);
                } else {
                    // User cancelled - reset to previous state
                    presetsDropdown.value = huidigePresetId || '';
                }
            });

            // Preset modal helpers
            const presetModal = document.getElementById('preset-save-modal');
            const presetModalTitle = document.getElementById('preset-modal-title');
            const presetModalContent = document.getElementById('preset-modal-content');

            function showPresetModal(title, content) {
                presetModalTitle.textContent = title;
                presetModalContent.innerHTML = content;
                presetModal.classList.remove('hidden');
            }

            function hidePresetModal() {
                presetModal.classList.add('hidden');
            }

            // Close modal on backdrop click
            presetModal.addEventListener('click', (e) => {
                if (e.target === presetModal) hidePresetModal();
            });

            // Collect current configuration
            function collectConfiguratie() {
                const configuratie = {};
                container.querySelectorAll('.gewichtsklasse-item').forEach(item => {
                    const key = item.dataset.key;
                    configuratie[key] = {
                        max_leeftijd: parseInt(item.querySelector('.leeftijd-input')?.value) || 99,
                        label: item.querySelector('.label-input')?.value || key,
                        geslacht: item.querySelector('.geslacht-select')?.value || 'gemengd',
                        max_kg_verschil: parseFloat(item.querySelector('.max-kg-input')?.value) || 0,
                        max_leeftijd_verschil: parseInt(item.querySelector('.max-lft-input')?.value) || 0,
                        band_filter: item.querySelector('.band-filter-select')?.value || '',
                        gewichten: (item.querySelector('.gewichten-input')?.value || '').split(',').map(s => s.trim()).filter(s => s),
                        wedstrijd_systeem: item.querySelector('.systeem-select')?.value || 'poules',
                    };
                });
                return configuratie;
            }

            // Save preset to server
            async function savePreset(naam, overschrijven = false) {
                const configuratie = collectConfiguratie();
                try {
                    const response = await fetch('{{ route("organisator.presets.store") }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ naam, configuratie, overschrijven })
                    });

                    if (response.ok) {
                        const data = await response.json();
                        hidePresetModal();
                        showAppToast(overschrijven ? '✓ Preset bijgewerkt' : '✓ Preset opgeslagen', 'success');
                        await loadEigenPresets();
                        if (data.id) {
                            presetsDropdown.value = data.id;
                            huidigePresetId = data.id;
                            huidigePresetNaam = naam;
                        }
                    } else {
                        const data = await response.json();
                        showAppToast('✗ ' + (data.message || 'Kon preset niet opslaan'), 'error');
                    }
                } catch (e) {
                    console.error('Fout bij opslaan:', e);
                    showAppToast('✗ Er ging iets mis bij het opslaan', 'error');
                }
            }

            // Save current config as preset
            document.getElementById('btn-save-preset').addEventListener('click', () => {
                if (huidigePresetId && huidigePresetNaam) {
                    // Show 3-button modal
                    showPresetModal('Preset opslaan', `
                        <p class="mb-4 text-gray-600">Je hebt <strong>"${huidigePresetNaam}"</strong> geladen. Wat wil je doen?</p>
                        <div class="flex flex-col gap-2">
                            <button onclick="savePreset('${huidigePresetNaam}', true)" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                📝 "${huidigePresetNaam}" overschrijven
                            </button>
                            <button onclick="showNewPresetInput()" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                ➕ Nieuwe preset maken
                            </button>
                            <button onclick="hidePresetModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">
                                Annuleren
                            </button>
                        </div>
                    `);
                } else {
                    // Show new preset input directly
                    showNewPresetInput();
                }
            });

            // Show input for new preset name
            window.showNewPresetInput = function() {
                showPresetModal('Nieuwe preset', `
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Naam voor preset:</label>
                        <input type="text" id="new-preset-naam" class="w-full border rounded px-3 py-2" placeholder="Bijv. Mijn toernooi preset" autofocus>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="saveNewPreset()" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                            💾 Opslaan
                        </button>
                        <button onclick="hidePresetModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">
                            Annuleren
                        </button>
                    </div>
                `);
                setTimeout(() => document.getElementById('new-preset-naam')?.focus(), 100);
            };

            // Save new preset from input
            window.saveNewPreset = function() {
                const input = document.getElementById('new-preset-naam');
                const naam = input?.value?.trim();
                if (!naam) {
                    showAppToast('Vul een naam in', 'error');
                    return;
                }
                savePreset(naam, false);
            };

            // Make savePreset and hidePresetModal available globally
            window.savePreset = savePreset;
            window.hidePresetModal = hidePresetModal;

            // Add category - uses same template function as renderCategorieen (DRY)
            document.getElementById('add-categorie').addEventListener('click', () => {
                const newKey = 'custom_' + Date.now();
                // Default values for new category (dynamic grouping enabled)
                const newItem = {
                    label: '',
                    max_leeftijd: 99,
                    geslacht: 'gemengd',
                    max_kg_verschil: 3,
                    max_leeftijd_verschil: 2,
                    band_filter: '',
                    gewichten: [],
                    toon_label_in_titel: true
                };
                container.appendChild(createCategorieElement(newKey, newItem));
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

            // Update JSON on any input change in categories container
            container.addEventListener('input', (e) => {
                updateJsonInput();
            });
            container.addEventListener('change', (e) => {
                updateJsonInput();
            });

            // Drag & Drop for reordering categories
            let draggedItem = null;

            container.addEventListener('dragstart', (e) => {
                const item = e.target.closest('.gewichtsklasse-item');
                if (item) {
                    draggedItem = item;
                    item.classList.add('opacity-50', 'border-dashed', 'border-blue-400');
                    e.dataTransfer.effectAllowed = 'move';
                }
            });

            container.addEventListener('dragend', (e) => {
                const item = e.target.closest('.gewichtsklasse-item');
                if (item) {
                    item.classList.remove('opacity-50', 'border-dashed', 'border-blue-400');
                }
                draggedItem = null;
                // Remove all drag-over styling
                container.querySelectorAll('.gewichtsklasse-item').forEach(el => {
                    el.classList.remove('border-t-4', 'border-t-blue-500');
                });
            });

            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                const targetItem = e.target.closest('.gewichtsklasse-item');
                if (targetItem && targetItem !== draggedItem) {
                    // Remove previous indicators
                    container.querySelectorAll('.gewichtsklasse-item').forEach(el => {
                        el.classList.remove('border-t-4', 'border-t-blue-500');
                    });
                    // Add indicator to target
                    targetItem.classList.add('border-t-4', 'border-t-blue-500');
                }
            });

            container.addEventListener('drop', (e) => {
                e.preventDefault();
                const targetItem = e.target.closest('.gewichtsklasse-item');
                if (targetItem && draggedItem && targetItem !== draggedItem) {
                    // Get positions
                    const items = [...container.querySelectorAll('.gewichtsklasse-item')];
                    const draggedIndex = items.indexOf(draggedItem);
                    const targetIndex = items.indexOf(targetItem);

                    // Insert before or after based on position
                    if (draggedIndex < targetIndex) {
                        targetItem.after(draggedItem);
                    } else {
                        targetItem.before(draggedItem);
                    }

                    // Update JSON
                    updateJsonInput();
                }
                // Remove indicators
                container.querySelectorAll('.gewichtsklasse-item').forEach(el => {
                    el.classList.remove('border-t-4', 'border-t-blue-500');
                });
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

    <!-- VRIJWILLIGERS (device toegangen met binding) -->
    @include('pages.toernooi.partials.device-toegangen')

    <!-- SNELKOPPELINGEN -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Snelkoppelingen</h2>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('toernooi.pagina-builder.index', $toernooi) }}" target="_blank" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Pagina Builder
            </a>
            <a href="{{ route('toernooi.noodplan.index', $toernooi) }}" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 flex items-center gap-2" title="In Case of Emergency - Break Glass">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Noodplan
            </a>
        </div>
    </div>

    <!-- CHAT SERVER (Reverb) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="reverbStatus()">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Chat Server</h2>
        <p class="text-gray-600 mb-4">
            Realtime chat tussen hoofdjury en vrijwilligers (matten, weging, spreker, dojo).
        </p>

        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" :class="running ? 'bg-green-500' : 'bg-red-500'"></span>
                <span class="font-medium" x-text="running ? 'Actief' : 'Gestopt'"></span>
            </div>

            <template x-if="!local">
                <div class="flex gap-2">
                    <button type="button" @click="start()" :disabled="running || loading"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <span x-show="!loading">Start</span>
                        <span x-show="loading">...</span>
                    </button>
                    <button type="button" @click="stop()" :disabled="!running || loading"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <span x-show="!loading">Stop</span>
                        <span x-show="loading">...</span>
                    </button>
                </div>
            </template>

            <template x-if="local">
                <span class="text-sm text-gray-500">(Alleen beschikbaar op production server)</span>
            </template>
        </div>

        <p class="text-sm text-gray-500 mt-3" x-show="message" x-text="message"></p>
    </div>

    <script>
    function reverbStatus() {
        return {
            running: false,
            loading: false,
            local: false,
            message: '',
            init() {
                this.checkStatus();
            },
            async checkStatus() {
                try {
                    const res = await fetch('{{ route("toernooi.reverb.status", $toernooi) }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.running = data.running;
                    this.local = data.local || false;
                } catch (e) {
                    this.message = 'Kon status niet ophalen';
                }
            },
            async start() {
                this.loading = true;
                try {
                    const res = await fetch('{{ route("toernooi.reverb.start", $toernooi) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.message = data.message;
                    await this.checkStatus();
                } catch (e) {
                    this.message = 'Fout bij starten';
                }
                this.loading = false;
            },
            async stop() {
                this.loading = true;
                try {
                    const res = await fetch('{{ route("toernooi.reverb.stop", $toernooi) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.message = data.message;
                    await this.checkStatus();
                } catch (e) {
                    this.message = 'Fout bij stoppen';
                }
                this.loading = false;
            }
        }
    }
    </script>

    <!-- INSCHRIJVING & PORTAAL -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Inschrijving & Portaal</h2>
        <p class="text-gray-600 mb-4">
            Bepaal hoe judoka's in het systeem komen en wat budoscholen zelf kunnen doen via het portaal.
        </p>

        <form action="{{ route('toernooi.portaal.instellingen', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <!-- Portaal Modus -->
                <div class="p-4 border rounded-lg bg-gray-50">
                    <label for="portaal_modus" class="block font-bold text-gray-800 mb-2">Portaal modus</label>
                    <select name="portaal_modus" id="portaal_modus"
                            class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="toggleMollieOptie()">
                        <option value="uit" {{ ($toernooi->portaal_modus ?? 'uit') === 'uit' ? 'selected' : '' }}>
                            Uit - Alleen bekijken (organisator beheert alles)
                        </option>
                        <option value="mutaties" {{ ($toernooi->portaal_modus ?? 'uit') === 'mutaties' ? 'selected' : '' }}>
                            Alleen mutaties - Budoscholen kunnen wijzigen, niet inschrijven
                        </option>
                        <option value="volledig" {{ ($toernooi->portaal_modus ?? 'uit') === 'volledig' ? 'selected' : '' }}>
                            Volledig - Budoscholen kunnen inschrijven én wijzigen
                        </option>
                    </select>
                    <p class="text-sm text-gray-500 mt-2">
                        <strong>Tip:</strong> Budoscholen kunnen hun judoka's altijd <em>bekijken</em>, ongeacht deze instelling.
                    </p>
                </div>

                <!-- Uitleg per modus -->
                <div class="text-sm text-gray-600 p-3 bg-blue-50 rounded-lg">
                    <strong>Wanneer welke modus?</strong>
                    <ul class="list-disc ml-5 mt-1 space-y-1">
                        <li><strong>Uit:</strong> Je importeert zelf via CSV of voegt handmatig judoka's toe</li>
                        <li><strong>Alleen mutaties:</strong> Inschrijving via extern systeem, budoscholen corrigeren gewicht/band via portaal</li>
                        <li><strong>Volledig:</strong> Budoscholen schrijven zelf in via het portaal</li>
                    </ul>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg">
                        Opslaan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    function toggleMollieOptie() {
        const modus = document.getElementById('portaal_modus').value;
        const mollieSection = document.getElementById('mollie-section');
        if (mollieSection) {
            // Mollie optie alleen relevant bij 'volledig' modus
            const hint = mollieSection.querySelector('.mollie-hint');
            if (hint) {
                hint.style.display = modus === 'volledig' ? 'none' : 'block';
            }
        }
    }
    // Init on page load
    document.addEventListener('DOMContentLoaded', toggleMollieOptie);
    </script>

    <!-- ONLINE BETALINGEN -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" id="mollie-section">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Online Betalingen</h2>

        <!-- Hint als portaal niet op volledig staat -->
        <div class="mollie-hint p-3 bg-yellow-50 border border-yellow-200 rounded-lg mb-4 {{ ($toernooi->portaal_modus ?? 'uit') === 'volledig' ? 'hidden' : '' }}">
            <p class="text-yellow-800 text-sm">
                <strong>Let op:</strong> Online betalingen zijn alleen zinvol als het portaal op "Volledig" staat (nieuwe inschrijvingen).
                Bij "Uit" of "Alleen mutaties" regel je de betaling extern.
            </p>
        </div>

        <p class="text-gray-600 mb-4">
            Activeer online betalingen via iDEAL. Coaches moeten dan eerst betalen voordat judoka's definitief ingeschreven zijn.
        </p>

        <form action="{{ route('toernooi.betalingen.instellingen', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 border rounded-lg bg-gray-50">
                    <div>
                        <h3 class="font-bold">Online betalingen actief</h3>
                        <p class="text-sm text-gray-500">Coaches moeten betalen bij inschrijving</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="betaling_actief" value="1" class="sr-only peer"
                               {{ $toernooi->betaling_actief ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="inschrijfgeld" class="block text-gray-700 font-medium mb-1">Inschrijfgeld per judoka</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">€</span>
                            <input type="number" name="inschrijfgeld" id="inschrijfgeld" step="0.01" min="0"
                                   value="{{ old('inschrijfgeld', $toernooi->inschrijfgeld ?? '15.00') }}"
                                   class="w-full border rounded px-3 py-2 pl-8" placeholder="15.00">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Bijv. 15.00 voor €15 per judoka</p>
                    </div>
                </div>

                <!-- Mollie Account Koppeling -->
                <div class="p-4 border rounded-lg {{ $toernooi->mollie_onboarded ? 'bg-green-50 border-green-200' : 'bg-gray-50' }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold flex items-center gap-2">
                                @if($toernooi->mollie_onboarded)
                                <span class="text-green-600">✓</span>
                                @endif
                                Mollie Account
                            </h3>
                            @if($toernooi->mollie_onboarded)
                            <p class="text-sm text-green-700">
                                Gekoppeld: {{ $toernooi->mollie_organization_name ?? 'Onbekend' }}
                                <span class="text-gray-500">({{ $toernooi->mollie_mode }})</span>
                            </p>
                            @else
                            <p class="text-sm text-gray-500">Koppel je Mollie account om betalingen te ontvangen</p>
                            @endif
                        </div>
                        <div>
                            @if($toernooi->mollie_onboarded)
                            <form action="{{ route('mollie.disconnect', $toernooi) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Weet je zeker dat je de Mollie koppeling wilt verbreken?')">
                                @csrf
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    Ontkoppelen
                                </button>
                            </form>
                            @else
                            <a href="{{ route('mollie.authorize', $toernooi) }}" target="_blank"
                               class="bg-pink-500 hover:bg-pink-600 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2">
                                <span>Koppel Mollie</span>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>

                @if($toernooi->betaling_actief)
                <div class="p-4 bg-green-50 rounded-lg">
                    <h4 class="font-bold text-green-800 mb-2">Betalingen overzicht</h4>
                    @php
                        $totaalBetaald = $toernooi->betalingen()->where('status', 'paid')->sum('bedrag');
                        $aantalBetaaldeJudokas = $toernooi->judokas()->whereNotNull('betaald_op')->count();
                        $aantalOnbetaaldeJudokas = $toernooi->judokas()->whereNull('betaald_op')->where(function($q) {
                            $q->whereNotNull('geboortejaar')
                              ->whereNotNull('geslacht')
                              ->whereNotNull('band')
                              ->whereNotNull('gewicht');
                        })->count();
                    @endphp
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-green-600">€{{ number_format($totaalBetaald, 2, ',', '.') }}</p>
                            <p class="text-sm text-gray-600">Totaal ontvangen</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600">{{ $aantalBetaaldeJudokas }}</p>
                            <p class="text-sm text-gray-600">Betaalde judoka's</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-orange-600">{{ $aantalOnbetaaldeJudokas }}</p>
                            <p class="text-sm text-gray-600">Wachtend op betaling</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="mt-4 text-right">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">
                    Betaling Instellingen Opslaan
                </button>
            </div>
        </form>
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
                                       value="{{ $blok->weging_start?->format('H:i') ?? '08:00' }}"
                                       step="900"
                                       class="border rounded px-2 py-1 w-28">
                            </td>
                            <td class="py-2 px-3">
                                <input type="time" name="blokken[{{ $blok->id }}][weging_einde]"
                                       value="{{ $blok->weging_einde?->format('H:i') ?? '09:00' }}"
                                       step="900"
                                       class="border rounded px-2 py-1 w-28">
                            </td>
                            <td class="py-2 px-3">
                                <input type="time" name="blokken[{{ $blok->id }}][starttijd]"
                                       value="{{ $blok->starttijd?->format('H:i') ?? '09:00' }}"
                                       step="900"
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

    <!-- NOODKNOP: HEROPEN VOORBEREIDING -->
    @if($toernooi->weegkaarten_gemaakt_op)
    <div class="bg-red-50 border-2 border-red-300 rounded-lg shadow p-6 mb-6" x-data="{ showConfirm: false, wachtwoord: '' }">
        <h2 class="text-xl font-bold text-red-800 mb-2 flex items-center gap-2">
            <span class="text-2xl">⚠️</span> Noodknop: Heropen Voorbereiding
        </h2>
        <p class="text-red-700 mb-4">
            Voorbereiding is afgerond op <strong>{{ $toernooi->weegkaarten_gemaakt_op->format('d-m-Y H:i') }}</strong>.
            Judoka's, poules en blokken zijn nu read-only.
        </p>

        <div class="bg-red-100 border border-red-300 rounded p-3 mb-4 text-sm text-red-800">
            <strong>⚠️ LET OP - ALLEEN GEBRUIKEN BIJ NOOD!</strong>
            <ul class="list-disc list-inside mt-2">
                <li>Er kunnen 2 sets weegkaarten ontstaan (oud vs nieuw) → VERWARREND!</li>
                <li>Weegkaarten tonen aanmaakdatum/tijd om versies te onderscheiden</li>
                <li>Na wijzigingen: opnieuw "Maak weegkaarten" klikken</li>
                <li>Oude geprinte weegkaarten zijn dan ONGELDIG</li>
            </ul>
        </div>

        <button @click="showConfirm = true" x-show="!showConfirm"
                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg">
            Heropen Voorbereiding
        </button>

        <div x-show="showConfirm" x-cloak class="bg-white border border-red-300 rounded-lg p-4 mt-4">
            <p class="font-medium text-red-800 mb-3">Bevestig met het organisator wachtwoord:</p>
            <form action="{{ route('toernooi.heropen-voorbereiding', $toernooi) }}" method="POST" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm text-gray-600 mb-1">Wachtwoord</label>
                    <input type="password" name="wachtwoord" x-model="wachtwoord" required
                           class="w-full border rounded px-3 py-2" placeholder="Voer wachtwoord in">
                </div>
                <button type="submit" :disabled="!wachtwoord"
                        class="px-6 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-bold rounded-lg">
                    Bevestig Heropenen
                </button>
                <button type="button" @click="showConfirm = false; wachtwoord = ''"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
                    Annuleren
                </button>
            </form>
        </div>
    </div>
    @endif

    </div><!-- End TAB: ORGANISATIE -->

    <!-- TAB: TEST (alleen voor admin) -->
    @if(auth()->user()?->email === 'henkvu@gmail.com' || session("toernooi_{$toernooi->id}_rol") === 'admin')
    <div x-show="activeTab === 'test'" x-cloak>

    <!-- RESET ALLES -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center gap-2">
            <span class="text-2xl">💥</span> Reset Alles
        </h2>
        <p class="text-gray-600 mb-4">
            Verwijder ALLE wedstrijden van ALLE categorieën en haal alles van de matten. Terug naar start!
        </p>

        <form action="{{ route('toernooi.blok.reset-alles', $toernooi) }}" method="POST"
              onsubmit="return confirm('🚨 WEET JE HET ZEKER?\n\nDit verwijdert ALLE wedstrijden van ALLE categorieën!\n\nJudoka\'s blijven behouden.')">
            @csrf
            <button type="submit" class="px-8 py-4 bg-red-600 hover:bg-red-700 text-white text-xl font-bold rounded-lg shadow-lg transition-all hover:scale-105">
                🔥 RESET ALLES 🔥
            </button>
        </form>

        <div class="mt-4 p-3 bg-red-50 rounded text-sm text-red-800">
            <strong>⚠️ Dit verwijdert:</strong>
            <ul class="list-disc list-inside mt-1">
                <li>Alle wedstrijden (alle blokken, alle matten)</li>
                <li>Alle mat-toewijzingen</li>
                <li>Alle doorstuur-status</li>
            </ul>
            <strong class="block mt-2">✓ Dit blijft behouden:</strong> Judoka's, clubs, poule-indelingen
        </div>
    </div>

    <!-- DEBUG INFO -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center gap-2">
            <span class="text-2xl">🐛</span> Debug Info
        </h2>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="p-3 bg-gray-50 rounded">
                <span class="font-medium">Totaal judoka's:</span>
                <span class="float-right">{{ $toernooi->judokas()->count() }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded">
                <span class="font-medium">Totaal poules:</span>
                <span class="float-right">{{ $toernooi->poules()->count() }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded">
                <span class="font-medium">Totaal wedstrijden:</span>
                <span class="float-right">{{ \App\Models\Wedstrijd::whereIn('poule_id', $toernooi->poules()->pluck('id'))->count() }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded">
                <span class="font-medium">Gespeelde wedstrijden:</span>
                <span class="float-right">{{ \App\Models\Wedstrijd::whereIn('poule_id', $toernooi->poules()->pluck('id'))->where('is_gespeeld', true)->count() }}</span>
            </div>
        </div>
    </div>

    </div><!-- End TAB: TEST -->
    @endif

</div>

<script>
// Toggle eliminatie gewichtsklassen visibility
function toggleEliminatieGewichtsklassen(selectElement) {
    const leeftijdsklasse = selectElement.dataset.leeftijdsklasse;
    const container = document.getElementById('eliminatie-gewichtsklassen-' + leeftijdsklasse);
    if (container) {
        if (selectElement.value === 'eliminatie') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wedstrijd-systeem-select').forEach(function(select) {
        toggleEliminatieGewichtsklassen(select);
    });
});

// Toggle password visibility
function togglePassword(button) {
    const input = button.parentElement.querySelector('input');
    const eyeClosed = button.querySelector('.eye-closed');
    const eyeOpen = button.querySelector('.eye-open');

    if (input.type === 'password') {
        input.type = 'text';
        eyeClosed.classList.add('hidden');
        eyeOpen.classList.remove('hidden');
    } else {
        input.type = 'password';
        eyeClosed.classList.remove('hidden');
        eyeOpen.classList.add('hidden');
    }
}
</script>

<script>
// Auto-save for toernooi settings
// Global trigger function for drag & drop handlers
window.triggerAutoSave = function() {};

(function() {
    const form = document.getElementById('toernooi-form');
    if (!form) return;

    const status = document.getElementById('save-status');
    let saveTimeout = null;
    let isDirty = false;  // Track if form has unsaved changes

    function showStatus(text, type) {
        status.textContent = text;
        status.classList.remove('hidden', 'text-gray-400', 'text-green-600', 'text-red-600', 'bg-green-100', 'bg-red-100', 'bg-gray-100', 'border-green-300', 'border-red-300', 'border-gray-300');
        if (type === 'success') { status.classList.add('text-green-600', 'bg-green-100', 'border-green-300'); } else if (type === 'error') { status.classList.add('text-red-600', 'bg-red-100', 'border-red-300'); } else { status.classList.add('text-gray-600', 'bg-gray-100', 'border-gray-300'); }
    }

    function markDirty() {
        isDirty = true;
    }

    function markClean() {
        isDirty = false;
    }

    // Expose trigger for drag & drop handlers
    window.triggerAutoSave = function() {
        markDirty();
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(autoSave, 500);
    };

    function autoSave() {
        if (!isDirty) return;

        // Ensure JSON is up-to-date before saving
        if (typeof updateJsonInput === 'function') {
            updateJsonInput();
        }

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
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server response:', text);
                    throw new Error('Server error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                showStatus('✓ Opgeslagen', 'success');
                markClean();
                setTimeout(() => status.classList.add('hidden'), 2000);

                // Update overlap warning banner dynamically
                const overlapAlert = document.getElementById('overlap-warning-alert');
                if (data.overlapWarning) {
                    if (overlapAlert) {
                        overlapAlert.querySelector('.text-sm').textContent = data.overlapWarning;
                        overlapAlert.classList.remove('hidden');
                    } else {
                        // Create banner if it doesn't exist
                        const banner = document.createElement('div');
                        banner.id = 'overlap-warning-alert';
                        banner.className = 'mb-6 p-4 bg-orange-100 border-2 border-orange-500 rounded-lg';
                        banner.innerHTML = `
                            <div class="flex items-start gap-3">
                                <span class="text-2xl">⚠️</span>
                                <div>
                                    <p class="font-bold text-orange-800">Overlappende categorieën gedetecteerd!</p>
                                    <p class="text-sm text-orange-700">${data.overlapWarning}</p>
                                    <p class="text-xs text-orange-600 mt-1">Judoka's kunnen in meerdere categorieën passen. Pas de categorie-instellingen aan.</p>
                                </div>
                            </div>
                        `;
                        const tabsContainer = document.querySelector('.flex.border-b.mb-6');
                        if (tabsContainer) {
                            tabsContainer.after(banner);
                        }
                    }
                } else if (overlapAlert) {
                    overlapAlert.classList.add('hidden');
                }
            } else {
                showStatus('✗ Fout bij opslaan', 'error');
            }
        })
        .catch((error) => {
            console.error('Auto-save error:', error);
            showStatus('✗ Fout bij opslaan', 'error');
        });
    }

    // Listen for changes on all form elements (using event delegation for dynamic elements)
    form.addEventListener('change', (e) => {
        if (e.target.matches('input, select, textarea')) {
            markDirty();
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(autoSave, 500);
        }
    });
    form.addEventListener('input', (e) => {
        if (e.target.matches('input[type="text"], input[type="number"], textarea')) {
            markDirty();
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(autoSave, 1500);
        }
    });

    // Reset dirty flag on form submit (prevents false warning)
    form.addEventListener('submit', (e) => {
        // Ensure JSON is up-to-date before submit
        if (typeof updateJsonInput === 'function') {
            updateJsonInput();
        }
        markClean();

        // Save scroll position to restore after reload
        sessionStorage.setItem('toernooi_edit_scroll', window.scrollY);
    });

    // Restore scroll position after form submit (if success message present)
    @if(session('success'))
    const savedScroll = sessionStorage.getItem('toernooi_edit_scroll');
    if (savedScroll) {
        window.scrollTo(0, parseInt(savedScroll));
        sessionStorage.removeItem('toernooi_edit_scroll');
    }
    @endif

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
