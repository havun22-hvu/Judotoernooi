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
        <h2 class="text-xl font-bold mb-3">Quickstart - Volledige Workflow</h2>

        <h4 class="font-semibold mb-2">Voorbereiding (weken/maanden voor toernooi)</h4>
        <ol class="space-y-2 mb-4">
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">1</span>
                <span><strong>Toernooi aanmaken</strong> - Naam, datum, matten, categorieën instellen</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">2</span>
                <span><strong>Judoka's importeren</strong> - CSV uploaden of via Coach Portal</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">3</span>
                <span><strong>Valideer judoka's</strong> - QR-codes aanmaken (na sluiting inschrijving)</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">4</span>
                <span><strong>Poules genereren</strong> - Automatisch op basis van leeftijd/gewicht</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">5</span>
                <span><strong>Blokverdeling</strong> - Poules over tijdsblokken verdelen</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">6</span>
                <span><strong>Verdeel over matten</strong> - Automatische mat toewijzing, weegkaarten beschikbaar</span>
            </li>
        </ol>

        <h4 class="font-semibold mb-2">Toernooidag</h4>
        <ol class="space-y-2" start="7">
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">7</span>
                <span><strong>Weging</strong> - Judoka's wegen met QR scanner</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">8</span>
                <span><strong>Wedstrijddag Poules</strong> - Overpoelen (te zware/afwezige judoka's)</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">9</span>
                <span><strong>Zaaloverzicht</strong> - Witte chip klikken = wedstrijdschema genereren</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">10</span>
                <span><strong>Wedstrijden</strong> - Mat interface voor scores, Spreker voor prijsuitreiking</span>
            </li>
        </ol>
    </div>

    {{-- Hoofdstukken navigatie --}}
    <div class="bg-white rounded-lg shadow p-4 mb-8" x-show="!searchQuery">
        <h3 class="font-bold text-gray-700 mb-3">Ga naar hoofdstuk</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <a href="#categorien" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Categorieën</a>
            <a href="#inschrijving" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Inschrijving</a>
            <a href="#judokas" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Judoka's</a>
            <a href="#poules" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Poules</a>
            <a href="#wedstrijdsystemen" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Wedstrijdsystemen</a>
            <a href="#kruisfinales" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Kruisfinales</a>
            <a href="#eliminatie" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Eliminatie</a>
            <a href="#blokken" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Blokken</a>
            <a href="#weegkaarten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Weegkaarten</a>
            <a href="#coachkaarten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Coachkaarten</a>
            <a href="#device-binding" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Device Binding</a>
            <a href="#weging" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Weging</a>
            <a href="#wedstrijddag-poules" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Wedstrijddag Poules</a>
            <a href="#zaaloverzicht" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Zaaloverzicht</a>
            <a href="#matten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Matten</a>
            <a href="#spreker" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Spreker</a>
            <a href="#dojo" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Dojo Scanner</a>
            <a href="#publiek" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">Publieke PWA</a>
        </div>
    </div>

    {{-- Hoofdstukken --}}
    <div class="space-y-6">

        {{-- Categorieën & Presets --}}
        <section id="categorien" class="help-section bg-white rounded-lg shadow p-6" data-keywords="categorie preset jbn gewichtsklasse leeftijd vast variabel dynamisch">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#9881;</span> Categorieën & Presets
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Presets kiezen</h4>
                <p>Bij <strong>Toernooi Bewerken</strong> kun je kiezen uit:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>JBN 2025 / JBN 2026</strong> - Standaard gewichtsklassen volgens JBN</li>
                    <li><strong>Eigen presets</strong> - Eerder opgeslagen configuraties</li>
                    <li><strong>Handmatig</strong> - Zelf categorieën instellen</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Vaste vs Variabele indeling</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Type</th>
                            <th class="text-left py-1">Wanneer</th>
                            <th class="text-left py-1">Werking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>Vast</strong></td>
                            <td class="py-1">Max kg verschil = 0</td>
                            <td class="py-1">JBN gewichtsklassen (-24kg, -27kg, etc.)</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>Variabel</strong></td>
                            <td class="py-1">Max kg verschil > 0</td>
                            <td class="py-1">Poules op basis van werkelijk gewicht</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">Per categorie instellen</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Max leeftijd</strong> - Hoogste leeftijd in deze categorie</li>
                    <li><strong>Geslacht</strong> - Gemengd, Jongens of Meisjes</li>
                    <li><strong>Band filter</strong> - Optioneel: t/m oranje, vanaf groen, etc.</li>
                    <li><strong>Max kg verschil</strong> - 0 = vast, >0 = variabel</li>
                    <li><strong>Wedstrijdsysteem</strong> - Poules, Kruisfinale of Eliminatie</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Eigen preset opslaan</h4>
                <p>Na configureren kun je de instellingen opslaan als eigen preset voor later gebruik.</p>
            </div>
        </section>

        {{-- Inschrijving --}}
        <section id="inschrijving" class="help-section bg-white rounded-lg shadow p-6" data-keywords="inschrijving aanmelden betalen mollie club coach portal">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128221;</span> Inschrijving
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Coach Portal</h4>
                <p>Coaches kunnen zelf judoka's aanmelden via het Coach Portal:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Per club wordt automatisch een URL + PIN aangemaakt</li>
                    <li>Stuur uitnodigingen via <strong>Clubs</strong> pagina</li>
                    <li>URL en PIN ook direct te kopieren voor WhatsApp</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Betaling (optioneel)</h4>
                <p>Bij actieve Mollie koppeling:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Betaling via iDEAL, creditcard, etc.</li>
                    <li>Automatische bevestigingsmail naar coach</li>
                    <li>Twee modi: <strong>Connect</strong> (naar organisator) of <strong>Platform</strong> (via JudoToernooi)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Handmatige import</h4>
                <p>Organisator kan ook CSV/Excel uploaden via <strong>Judoka's &gt; Importeren</strong>.</p>
            </div>
        </section>

        {{-- Judoka's --}}
        <section id="judokas" class="help-section bg-white rounded-lg shadow p-6" data-keywords="judoka import csv uploaden deelnemers valideren qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129355;</span> Judoka's
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Importeren via CSV/Excel</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Upload bestand (.csv, .xlsx, .xls)</li>
                    <li>Systeem detecteert kolommen automatisch</li>
                    <li><strong>Drag & drop</strong> om kolom-mapping te corrigeren</li>
                    <li>Controleer preview en klik <strong>Importeren</strong></li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Verplichte velden</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Naam</li>
                    <li>Geboortejaar</li>
                    <li>Geslacht (M/V of Jongen/Meisje)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Optionele velden</h4>
                <p>Club, gewicht, band, gewichtsklasse - systeem berekent ontbrekende waarden.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Valideer judoka's (belangrijk!)</h4>
                <p>Na sluiting inschrijving:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Ga naar <strong>Judoka's &gt; Valideer</strong></li>
                    <li>Systeem controleert alle gegevens</li>
                    <li><strong>QR-codes worden nu aangemaakt</strong></li>
                    <li>Na validatie kunnen coaches geen wijzigingen meer doen</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Import problemen</h4>
                <p>Bij ontbrekende gegevens krijgt de club een melding en kan dit corrigeren via het Coach Portal.</p>
            </div>
        </section>

        {{-- Poules --}}
        <section id="poules" class="help-section bg-white rounded-lg shadow p-6" data-keywords="poule indeling genereren automatisch groepen categorie">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128101;</span> Poules
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Automatisch genereren</h4>
                <p>Het systeem werkt in 4 stappen:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li><strong>Categoriseren</strong> - Judoka naar categorie (harde criteria: leeftijd, geslacht, band)</li>
                    <li><strong>Sorteren</strong> - Op prioriteit (leeftijd/gewicht/band)</li>
                    <li><strong>Groeperen</strong> - Per categorie</li>
                    <li><strong>Poules maken</strong> - Verdelen binnen kg/leeftijd limieten</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Poule grootte</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Ideaal: 5</strong> judoka's (10 wedstrijden)</li>
                    <li><strong>Minimum: 3</strong> judoka's (6 wedstrijden, dubbele ronde)</li>
                    <li><strong>Maximum: 6</strong> judoka's (15 wedstrijden)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Handmatig aanpassen</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Drag & drop</strong> - Sleep judoka's tussen poules</li>
                    <li>Statistieken updaten automatisch (wedstrijden, gewicht range)</li>
                    <li>Poule titels worden automatisch samengesteld</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Sortering aanpassen</h4>
                <p>Bij <strong>Instellingen</strong> kun je de sorteer prioriteit aanpassen door te slepen: Leeftijd, Gewicht, Band.</p>
            </div>
        </section>

        {{-- Wedstrijdsystemen --}}
        <section id="wedstrijdsystemen" class="help-section bg-white rounded-lg shadow p-6" data-keywords="wedstrijdsysteem poule kruisfinale eliminatie knockout double elimination">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127942;</span> Wedstrijdsystemen
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <p>Per categorie kun je kiezen uit drie wedstrijdsystemen:</p>

                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Systeem</th>
                            <th class="text-left py-1">Geschikt voor</th>
                            <th class="text-left py-1">Kenmerken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>Poules</strong></td>
                            <td class="py-1">Beginners, recreatief</td>
                            <td class="py-1">Iedereen tegen iedereen, veel wedstrijdervaring</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-1"><strong>Kruisfinale</strong></td>
                            <td class="py-1">Grotere groepen</td>
                            <td class="py-1">Voorrondes + finale met beste judoka's</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>Eliminatie</strong></td>
                            <td class="py-1">Competitief, gevorderden</td>
                            <td class="py-1">Double elimination, pas na 2 nederlagen uit</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">Instellen</h4>
                <p>Bij <strong>Toernooi Bewerken &gt; Categorieën</strong> kies je per leeftijdsgroep het wedstrijdsysteem.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Achteraf wijzigen</h4>
                <p>Op de <strong>Poules</strong> pagina kun je via de dropdown bij elke poule het systeem nog wijzigen (bijv. eliminatie → poules bij te weinig deelnemers).</p>
            </div>
        </section>

        {{-- Kruisfinales --}}
        <section id="kruisfinales" class="help-section bg-white rounded-lg shadow p-6" data-keywords="kruisfinale finale doorplaatsing winnaar plaats">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127941;</span> Kruisfinales
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Hoe werkt het?</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li><strong>Voorrondes</strong> - Judoka's spelen in poules (iedereen tegen iedereen)</li>
                    <li><strong>Doorplaatsing</strong> - Beste judoka's (1e of 1e+2e) gaan door</li>
                    <li><strong>Kruisfinale</strong> - Finale poule met alle doorgeplaatste judoka's</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Configuratie</h4>
                <p>Bij <strong>Toernooi Instellingen</strong> stel je in hoeveel plaatsen doorgaan:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>1 plaats</strong> - Alleen de winnaar van elke voorronde</li>
                    <li><strong>2 plaatsen</strong> - Nummer 1 en 2 van elke voorronde</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Workflow</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Voorrondes worden gespeeld en afgerond</li>
                    <li>Systeem selecteert automatisch de beste judoka's</li>
                    <li>Hoofdjury wijst mat toe aan kruisfinale</li>
                    <li>Nieuw wedstrijdschema wordt gegenereerd</li>
                </ol>
            </div>
        </section>

        {{-- Eliminatie --}}
        <section id="eliminatie" class="help-section bg-white rounded-lg shadow p-6" data-keywords="eliminatie knockout dubbel verliezer brons herkansing bracket">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129351;</span> Eliminatie (Knock-out)
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Double Elimination</h4>
                <p>Bij eliminatie worden judoka's pas na <strong>twee nederlagen</strong> uitgeschakeld:</p>

                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Groep</th>
                            <th class="text-left py-1">Wie</th>
                            <th class="text-left py-1">Uitkomst</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>A-groep</strong> (Hoofdboom)</td>
                            <td class="py-1">Alle judoka's starten hier</td>
                            <td class="py-1">Winnaar = Goud, Verliezer finale = Zilver</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>B-groep</strong> (Herkansing)</td>
                            <td class="py-1">Verliezers uit A-groep</td>
                            <td class="py-1">Winnaar(s) = Brons</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">Flow</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Alle judoka's starten in de <strong>A-groep</strong></li>
                    <li>Verlies in A → naar <strong>B-groep</strong> (herkansing)</li>
                    <li>Verlies in B → <strong>uitgeschakeld</strong></li>
                    <li>Winnaars B-groep krijgen <strong>brons</strong></li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Aantal brons medailles</h4>
                <p>Bij <strong>Toernooi Instellingen</strong> kies je 1 of 2 brons medailles per categorie.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Wanneer gebruiken?</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Competitieve toernooien</li>
                    <li>Gevorderde judoka's (groen band en hoger)</li>
                    <li>Wanneer snelle progressie gewenst is</li>
                </ul>
            </div>
        </section>

        {{-- Blokken --}}
        <section id="blokken" class="help-section bg-white rounded-lg shadow p-6" data-keywords="blok planning mat verdeling tijdslot schema vastzetten variant">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128203;</span> Blokken
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat zijn blokken?</h4>
                <p>Een blok is een tijdslot met eigen weegtijd. Doel:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Gelijke verdeling wedstrijden over de dag</li>
                    <li>Aansluitende gewichtsklassen bij elkaar (voor overpoelen)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Werkwijze</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Klik <strong>Bereken</strong> - systeem genereert 5 varianten</li>
                    <li>Bekijk varianten (#1-#5) met scores</li>
                    <li>Pas eventueel aan met drag & drop</li>
                    <li>Klik <strong>Naar Zaaloverzicht</strong> om op te slaan</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Vastzetten</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Klik op pin-icoon om categorie vast te zetten</li>
                    <li>Vastgezette categorieeen blijven staan bij herberekenen</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Balans slider</h4>
                <p>Verschuif tussen "Gelijke verdeling" en "Aansluiting gewichten" voor verschillende optimalisaties.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Verdeel over matten</h4>
                <p>Na blokverdeling klikt u <strong>Naar Zaaloverzicht</strong>. Dit:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Slaat de blokverdeling op</li>
                    <li>Wijst matten automatisch toe</li>
                    <li>Maakt weegkaarten beschikbaar (met blok + mat info)</li>
                </ul>
            </div>
        </section>

        {{-- Weegkaarten --}}
        <section id="weegkaarten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="weegkaart printen afdrukken startnummer qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127991;</span> Weegkaarten
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat staat op een weegkaart?</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Naam en club</li>
                    <li>QR-code (voor scannen bij weging)</li>
                    <li>Blok nummer + weegtijden</li>
                    <li>Mat nummer</li>
                    <li>Classificatie (leeftijd, gewicht, band)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Vereisten</h4>
                <p>Weegkaarten zijn pas beschikbaar na:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li><strong>Valideer judoka's</strong> - QR-codes aangemaakt</li>
                    <li><strong>Blokverdeling</strong> - Blokken toegewezen</li>
                    <li><strong>Verdeel over matten</strong> - Matten toegewezen</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Dynamisch</h4>
                <p>Weegkaarten worden <strong>live gegenereerd</strong> en tonen altijd actuele info. Wijzigingen in blok/mat zijn direct zichtbaar.</p>
            </div>
        </section>

        {{-- Coachkaarten --}}
        <section id="coachkaarten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="coachkaart coach begeleider toegang foto device">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128084;</span> Coachkaarten
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat zijn coachkaarten?</h4>
                <p>Toegangskaarten voor coaches om in de dojo bij de matten te mogen staan.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Aantal berekening</h4>
                <p>Gebaseerd op het <strong>grootste blok</strong> van de club (niet totaal aantal judoka's):</p>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Max judoka's in een blok</th>
                            <th class="text-left py-1">Kaarten</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1">1-5</td><td class="py-1">1 kaart</td></tr>
                        <tr class="border-b"><td class="py-1">6-10</td><td class="py-1">2 kaarten</td></tr>
                        <tr class="border-b"><td class="py-1">11-15</td><td class="py-1">3 kaarten</td></tr>
                        <tr><td class="py-1">16-20</td><td class="py-1">4 kaarten</td></tr>
                    </tbody>
                </table>
                <p class="text-xs mt-1">Formule: ceil(max_judokas_per_blok / judokas_per_coach)</p>

                <h4 class="font-semibold text-gray-800 mt-4">Activatie (device binding)</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Coach opent link op telefoon</li>
                    <li>Vult naam in en maakt pasfoto</li>
                    <li>Kaart wordt gekoppeld aan dit device</li>
                    <li>QR-code is <strong>alleen zichtbaar op het geactiveerde device</strong></li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Overdracht</h4>
                <p>Coach kan kaart overdragen aan andere coach (bijv. ochtend/middag wissel). Nieuwe coach activeert met eigen naam + foto.</p>
            </div>
        </section>

        {{-- Device Binding --}}
        <section id="device-binding" class="help-section bg-white rounded-lg shadow p-6" data-keywords="device binding url pin vrijwilliger toegang">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128241;</span> Device Binding (Vrijwilligers)
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Hoe werkt het?</h4>
                <p>Vrijwilligers (mat, weging, spreker, dojo) krijgen toegang via URL + PIN:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Organisator maakt toegang aan via <strong>Instellingen &gt; Organisatie</strong></li>
                    <li>Vrijwilliger ontvangt URL + PIN (via WhatsApp)</li>
                    <li>Opent URL, voert PIN in, device wordt gebonden</li>
                    <li>Daarna: device wordt herkend, direct naar interface</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Toegangen beheren</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Aanmaken/verwijderen per rol</li>
                    <li>Device status zien (gebonden / wachtend)</li>
                    <li>Device resetten als nodig</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Einde toernooi</h4>
                <p>Alle device bindings worden automatisch gereset.</p>
            </div>
        </section>

        {{-- Weging --}}
        <section id="weging" class="help-section bg-white rounded-lg shadow p-6" data-keywords="weging wegen gewicht inchecken aanwezig scanner qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#9878;</span> Weging
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Twee interfaces</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Versie</th>
                            <th class="text-left py-1">Voor wie</th>
                            <th class="text-left py-1">Functie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>Weeglijst</strong></td>
                            <td class="py-1">Admin/Hoofdjury</td>
                            <td class="py-1">Live overzicht alle judoka's</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>QR Scanner</strong></td>
                            <td class="py-1">Vrijwilliger (PWA)</td>
                            <td class="py-1">Scannen + gewicht invoeren</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">QR Scanner werkwijze</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li><strong>Scan QR-code</strong> van weegkaart OF zoek op naam</li>
                    <li>Voer gewicht in via <strong>numpad</strong></li>
                    <li>Klik <strong>Registreer</strong></li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Twee gewichtsvelden</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Ingeschreven gewicht</strong> - Van import (voor voorbereiding)</li>
                    <li><strong>Gewogen gewicht</strong> - Van weging (voor wedstrijddag)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Aanwezigheid</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Gewogen = automatisch <strong>aanwezig</strong></li>
                    <li>Niet gewogen na sluiting weegtijd = <strong>afwezig</strong></li>
                </ul>
            </div>
        </section>

        {{-- Wedstrijddag Poules --}}
        <section id="wedstrijddag-poules" class="help-section bg-white rounded-lg shadow p-6" data-keywords="wedstrijddag overpoelen wachtruimte afwezig te zwaar">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128259;</span> Wedstrijddag Poules (Overpoelen)
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wanneer nodig?</h4>
                <p>Na sluiten weegtijd kunnen judoka's buiten hun gewichtsklasse vallen of afwezig zijn.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Afwezige judoka's</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Automatisch uit poule verwijderd</li>
                    <li>Zichtbaar via <strong>info-icoon</strong> in poule header</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Te zware/lichte judoka's</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Verschijnen in <strong>wachtruimte</strong> (rechts)</li>
                    <li>Sleep naar geschikte poule</li>
                    <li>Of gebruik <strong>Zoek match</strong> knop</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Naar Zaaloverzicht sturen</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Klik <strong>&#8594;</strong> knop bij de poule</li>
                    <li>Knop wordt <strong>&#10003;</strong> (groen)</li>
                    <li>Poule is nu klaar voor activatie in Zaaloverzicht</li>
                </ol>
            </div>
        </section>

        {{-- Zaaloverzicht --}}
        <section id="zaaloverzicht" class="help-section bg-white rounded-lg shadow p-6" data-keywords="zaaloverzicht activeren chip grijs wit groen wedstrijdschema">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127942;</span> Zaaloverzicht & Activatie
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Chip kleuren</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Kleur</th>
                            <th class="text-left py-1">Betekenis</th>
                            <th class="text-left py-1">Actie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-gray-400 rounded"></span> Grijs</td>
                            <td class="py-1">Niet doorgestuurd</td>
                            <td class="py-1">Ga naar Wedstrijddag Poules</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-white border border-gray-300 rounded"></span> Wit</td>
                            <td class="py-1">Klaar voor activatie</td>
                            <td class="py-1"><strong>Klik om te activeren</strong></td>
                        </tr>
                        <tr>
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-green-500 rounded"></span> Groen</td>
                            <td class="py-1">Geactiveerd</td>
                            <td class="py-1">Wedstrijden kunnen beginnen</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">Activeren</h4>
                <p>Klik op een <strong>witte chip</strong>:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Wedstrijdschema wordt gegenereerd</li>
                    <li>Alleen aanwezige judoka's komen in schema</li>
                    <li>Chip wordt groen</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Poules verplaatsen</h4>
                <p>Sleep poules naar andere matten indien nodig.</p>
            </div>
        </section>

        {{-- Matten --}}
        <section id="matten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="mat interface wedstrijd score punten ippon wazaari yuko">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129351;</span> Mat Interface
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Twee versies</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Admin</strong> - Via menu (met navigatie)</li>
                    <li><strong>Tafeljury</strong> - PWA via URL + PIN (standalone)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Wedstrijdschema kleuren</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><span class="text-green-600 font-bold">Groen</span> - Huidige wedstrijd (nu op de mat)</li>
                    <li><span class="text-yellow-600 font-bold">Geel</span> - Volgende wedstrijd (judoka's klaarzetten)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Puntensysteem</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Type</th>
                            <th class="text-left py-1">Punten</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1"><strong>Winstpunten (WP)</strong></td><td class="py-1">Winst = 2, Verlies = 0</td></tr>
                        <tr class="border-b"><td class="py-1">Ippon</td><td class="py-1">10 judopunten</td></tr>
                        <tr class="border-b"><td class="py-1">Waza-ari</td><td class="py-1">7 judopunten</td></tr>
                        <tr><td class="py-1">Yuko</td><td class="py-1">5 judopunten</td></tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">Ranking</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Hoogste winstpunten (WP)</li>
                    <li>Hoogste judopunten (JP)</li>
                    <li>Onderlinge wedstrijd</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Poule afronden</h4>
                <p>Klik <strong>Poule Klaar</strong> - poule gaat naar spreker voor prijsuitreiking.</p>
            </div>
        </section>

        {{-- Spreker --}}
        <section id="spreker" class="help-section bg-white rounded-lg shadow p-6" data-keywords="spreker omroep oproepen prijsuitreiking wachtrij">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127908;</span> Spreker Interface
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Twee versies</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Admin</strong> - Via menu (met navigatie)</li>
                    <li><strong>Vrijwilliger</strong> - PWA via URL + PIN (standalone)</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Wachtrij</h4>
                <p>Afgeronde poules verschijnen automatisch in de wachtrij met:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Eindstand met 1e, 2e, 3e plaats</li>
                    <li>Judoka namen en clubs</li>
                    <li>Categorie informatie</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Prijsuitreiking</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Roep judoka's op (1e, 2e, 3e)</li>
                    <li>Reik medailles uit</li>
                    <li>Markeer als <strong>Uitgereikt</strong></li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Auto-refresh</h4>
                <p>Interface vernieuwt automatisch elke 10 seconden.</p>
            </div>
        </section>

        {{-- Dojo Scanner --}}
        <section id="dojo" class="help-section bg-white rounded-lg shadow p-6" data-keywords="dojo scanner coachkaart toegang controle foto verificatie">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128274;</span> Dojo Scanner
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat is het?</h4>
                <p>QR scanner voor toegangscontrole bij de ingang van de dojo (wedstrijdruimte).</p>

                <h4 class="font-semibold text-gray-800 mt-4">Toegang</h4>
                <p>Vrijwilliger via URL + PIN + device binding (Instellingen &gt; Organisatie &gt; Dojo toegangen).</p>

                <h4 class="font-semibold text-gray-800 mt-4">Werkwijze</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Scan QR-code van coachkaart</li>
                    <li>Systeem toont <strong>foto van coach</strong></li>
                    <li>Vrijwilliger vergelijkt foto met persoon</li>
                    <li>Bij match: toegang verlenen</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">Wisselgeschiedenis</h4>
                <p>Bij overdracht van coachkaart toont de scanner ook de wisselgeschiedenis (wie de kaart eerder had).</p>
            </div>
        </section>

        {{-- Publieke PWA --}}
        <section id="publiek" class="help-section bg-white rounded-lg shadow p-6" data-keywords="publiek toeschouwer live uitslagen favorieten">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128065;</span> Publieke PWA (Toeschouwers)
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">Wat is het?</h4>
                <p>Openbare pagina voor ouders en toeschouwers. Installeerbaar als PWA op telefoon.</p>

                <h4 class="font-semibold text-gray-800 mt-4">Tabs</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Info</strong> - Toernooi info, tijdschema</li>
                    <li><strong>Deelnemers</strong> - Zoek judoka's, markeer favorieten</li>
                    <li><strong>Favorieten</strong> - Je gemarkeerde judoka's + hun poules</li>
                    <li><strong>Live Matten</strong> - Wie speelt nu, wie moet klaarmaken</li>
                    <li><strong>Uitslagen</strong> - Eindstanden per poule</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Live Matten weergave</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><span class="text-green-600 font-bold">Groen</span> - Speelt nu</li>
                    <li><span class="text-yellow-600 font-bold">Geel</span> - Klaar maken</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">Notificaties</h4>
                <p>Bij favorieten: alerts wanneer judoka bijna aan de beurt is of speelt.</p>
            </div>
        </section>

    </div>

    {{-- Terug naar boven --}}
    <div class="mt-8 text-center">
        <a href="#" class="text-blue-600 hover:text-blue-800">&#8593; Terug naar boven</a>
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
