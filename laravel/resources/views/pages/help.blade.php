@extends('layouts.app')

@section('title', 'Help & Handleiding')

@section('content')
<div x-data="helpPage()" class="max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Help & Handleiding</h1>
        <p class="text-gray-600">Alles wat je moet weten over het toernooi management systeem</p>
    </div>

    {{-- Zoekbalk --}}
    <div class="mb-6">
        <div class="relative">
            <input type="text"
                   x-model="searchQuery"
                   @input="filterContent()"
                   placeholder="Zoek in handleiding..."
                   class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <svg class="absolute left-4 top-3.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <button x-show="searchQuery" @click="searchQuery = ''; filterContent()" class="absolute right-4 top-3.5 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p x-show="searchQuery && filteredCount === 0" class="mt-2 text-orange-600">Geen resultaten gevonden voor "<span x-text="searchQuery"></span>"</p>
    </div>

    {{-- Quickstart Card --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg shadow-lg p-6 mb-8" x-show="!searchQuery || 'quickstart snel starten begin'.includes(searchQuery.toLowerCase())">
        <h2 class="text-xl font-bold mb-3">Quickstart - In 5 stappen klaar</h2>
        <ol class="space-y-2">
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">1</span>
                <span><strong>Toernooi aanmaken</strong> - Naam, datum, aantal matten</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">2</span>
                <span><strong>Judoka's importeren</strong> - CSV uploaden met naam, geboortejaar, geslacht</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">3</span>
                <span><strong>Poules genereren</strong> - Automatisch op basis van leeftijd/gewicht</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">4</span>
                <span><strong>Blokken maken</strong> - Poules verdelen over matten en tijdslots</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">5</span>
                <span><strong>Wedstrijddag starten</strong> - Weging, mat interfaces, spreker</span>
            </li>
        </ol>
    </div>

    {{-- Hoofdstukken navigatie --}}
    <div class="bg-white rounded-lg shadow p-4 mb-8" x-show="!searchQuery">
        <h3 class="font-bold text-gray-700 mb-3">Ga naar hoofdstuk</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <a href="#judokas" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Judoka's</a>
            <a href="#poules" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Poules</a>
            <a href="#blokken" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Blokken</a>
            <a href="#weging" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Weging</a>
            <a href="#wedstrijddag" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Wedstrijddag</a>
            <a href="#matten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Matten</a>
            <a href="#spreker" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Spreker</a>
            <a href="#chat" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Chat</a>
        </div>
    </div>

    {{-- Hoofdstukken --}}
    <div class="space-y-6">

        {{-- Judoka's --}}
        <section id="judokas" class="help-section bg-white rounded-lg shadow p-6" data-keywords="judoka import csv uploaden deelnemers">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">🥋</span> Judoka's
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Importeren via CSV</h4>
                <p>Upload een CSV-bestand met minimaal de kolommen: <strong>naam</strong>, <strong>geboortejaar</strong>, <strong>geslacht</strong>.</p>
                <p>Optionele kolommen: club, gewicht, band, gewichtsklasse.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Tips</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Geslacht: M/V of Man/Vrouw of Jongen/Meisje</li>
                    <li>Gewicht in kg (bijv. 32.5)</li>
                    <li>Het systeem herkent kolommen automatisch</li>
                    <li>Sleep kolom-knoppen om mapping te corrigeren</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Handmatig toevoegen</h4>
                <p>Klik op "Judoka toevoegen" om individuele deelnemers toe te voegen.</p>
            </div>
        </section>

        {{-- Poules --}}
        <section id="poules" class="help-section bg-white rounded-lg shadow p-6" data-keywords="poule indeling genereren automatisch groepen">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">👥</span> Poules
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Automatisch genereren</h4>
                <p>Het systeem groepeert judoka's automatisch op basis van:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Geslacht (jongens/meisjes apart)</li>
                    <li>Leeftijdscategorie (geboortejaar)</li>
                    <li>Gewichtsklasse</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Poule grootte</h4>
                <p>Ideaal: 3-5 judoka's per poule. Het systeem splitst automatisch grotere groepen.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Handmatig aanpassen</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Judoka's verplaatsen tussen poules</li>
                    <li>Poules splitsen of samenvoegen</li>
                    <li>Poule namen aanpassen</li>
                </ul>
            </div>
        </section>

        {{-- Blokken --}}
        <section id="blokken" class="help-section bg-white rounded-lg shadow p-6" data-keywords="blok planning mat verdeling tijdslot schema">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">📋</span> Blokken
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat zijn blokken?</h4>
                <p>Een blok is een tijdslot waarin meerdere poules tegelijk worden afgewerkt op verschillende matten.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Blokken maken</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Kies hoeveel matten beschikbaar zijn</li>
                    <li>Wijs poules toe aan blokken</li>
                    <li>Het systeem berekent automatisch de duur</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Automatische verdeling</h4>
                <p>Gebruik "Variabele verdeling" om poules automatisch gelijkmatig te verdelen over blokken.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Gepinde poules</h4>
                <p>Pin een poule aan een specifieke mat als dezelfde judoka's in meerdere poules zitten.</p>
            </div>
        </section>

        {{-- Weging --}}
        <section id="weging" class="help-section bg-white rounded-lg shadow p-6" data-keywords="weging wegen gewicht inchecken aanwezig">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">⚖️</span> Weging
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Weging interface</h4>
                <p>Open de weging interface op een tablet of laptop bij de weegschaal.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Werkwijze</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Zoek judoka op naam of startnummer</li>
                    <li>Vul gewicht in</li>
                    <li>Klik "Opslaan" - judoka is ingecheckt</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Gewichtscontrole</h4>
                <p>Het systeem waarschuwt als een judoka buiten de gewichtsklasse valt.</p>
            </div>
        </section>

        {{-- Wedstrijddag --}}
        <section id="wedstrijddag" class="help-section bg-white rounded-lg shadow p-6" data-keywords="wedstrijddag starten poules actief">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">🏆</span> Wedstrijddag
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Overzicht</h4>
                <p>Het wedstrijddag scherm toont alle poules met hun status:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><span class="text-gray-500">Grijs</span> - Nog niet gestart</li>
                    <li><span class="text-blue-600">Blauw</span> - Actief op mat</li>
                    <li><span class="text-green-600">Groen</span> - Afgerond</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Poule starten</h4>
                <p>Klik op een poule om deze te activeren voor een mat.</p>
            </div>
        </section>

        {{-- Matten --}}
        <section id="matten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="mat interface wedstrijd score punten">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">🥇</span> Mat Interface
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Per mat een device</h4>
                <p>Open de mat interface op een tablet bij elke mat. Selecteer het matnummer.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Wedstrijd invoeren</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Selecteer de actieve poule</li>
                    <li>Kies de wedstrijd</li>
                    <li>Voer de score in (ippon, waza-ari, etc.)</li>
                    <li>Klik "Opslaan"</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Scores</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Ippon (I)</strong> - 10 punten, wedstrijd voorbij</li>
                    <li><strong>Waza-ari (W)</strong> - 7 punten</li>
                    <li><strong>Yuko (Y)</strong> - 5 punten</li>
                    <li><strong>Koka (K)</strong> - 3 punten</li>
                </ul>
            </div>
        </section>

        {{-- Spreker --}}
        <section id="spreker" class="help-section bg-white rounded-lg shadow p-6" data-keywords="spreker omroep oproepen wachtrij">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">🎙️</span> Spreker
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Spreker interface</h4>
                <p>Het spreker scherm toont welke judoka's opgeroepen moeten worden.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Wachtrij</h4>
                <p>Judoka's verschijnen automatisch wanneer hun wedstrijd bijna begint.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Oproep bevestigen</h4>
                <p>Markeer judoka's als "opgeroepen" zodat matten weten wie er aankomt.</p>
            </div>
        </section>

        {{-- Chat --}}
        <section id="chat" class="help-section bg-white rounded-lg shadow p-6" data-keywords="chat berichten communicatie vrijwilligers">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">💬</span> Chat
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Realtime communicatie</h4>
                <p>Chat tussen hoofdjury en alle vrijwilligers (matten, weging, spreker).</p>

                <h4 class="font-semibold text-gray-800 mt-4">Chat openen</h4>
                <p>Klik op het chat-icoontje rechtsonder in beeld.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Berichten sturen</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Selecteer ontvanger (mat, weging, spreker, iedereen)</li>
                    <li>Typ bericht en verstuur</li>
                    <li>Berichten komen direct aan</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Notificaties</h4>
                <p>Nieuwe berichten tonen een toast notificatie en badge op het icoontje.</p>
            </div>
        </section>

    </div>

    {{-- Terug naar boven --}}
    <div class="mt-8 text-center">
        <a href="#" class="text-blue-600 hover:text-blue-800">↑ Terug naar boven</a>
    </div>
</div>

<script>
function helpPage() {
    return {
        searchQuery: '',
        filteredCount: 0,

        filterContent() {
            const query = this.searchQuery.toLowerCase().trim();
            const sections = document.querySelectorAll('.help-section');
            let count = 0;

            sections.forEach(section => {
                const text = section.textContent.toLowerCase();
                const keywords = (section.dataset.keywords || '').toLowerCase();
                const matches = !query || text.includes(query) || keywords.includes(query);

                section.style.display = matches ? 'block' : 'none';
                if (matches) count++;
            });

            this.filteredCount = count;
        }
    }
}
</script>
@endsection
