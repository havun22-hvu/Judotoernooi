@extends('layouts.app')

@section('title', __('Help & Handleiding'))

@section('content')
<div x-data="helpPage()" class="max-w-4xl mx-auto" style="min-width: 56rem;">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Help & Handleiding') }}</h1>
        <p class="text-gray-600">{{ __('Alles wat je moet weten over het toernooi management systeem') }}</p>
    </div>

    {{-- Zoekbalk --}}
    <div class="mb-6">
        <div class="relative">
            <input type="text"
                   x-model="searchQuery"
                   @input="filterContent()"
                   placeholder="{{ __('Zoek in handleiding...') }}"
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
        <p x-show="searchQuery && filteredCount === 0" class="mt-2 text-orange-600">{{ __('Geen resultaten gevonden voor') }} "<span x-text="searchQuery"></span>"</p>
    </div>

    {{-- Quickstart Card --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg shadow-lg p-6 mb-8" x-show="!searchQuery || 'quickstart snel starten begin'.includes(searchQuery.toLowerCase())">
        <h2 class="text-xl font-bold mb-3">{{ __('Quickstart - Volledige Workflow') }}</h2>

        <h4 class="font-semibold mb-2">{{ __('Voorbereiding (weken/maanden voor toernooi)') }}</h4>
        <ol class="space-y-2 mb-4">
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">1</span>
                <span>{!! __('<strong>Toernooi aanmaken</strong> - Naam, datum, matten, categorieën instellen') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">2</span>
                <span>{!! __('<strong>Judoka\'s importeren</strong> - CSV uploaden of via Coach Portal') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">3</span>
                <span>{!! __('<strong>Valideer judoka\'s</strong> - QR-codes aanmaken (na sluiting inschrijving)') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">4</span>
                <span>{!! __('<strong>Poules genereren</strong> - Automatisch op basis van leeftijd/gewicht') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">5</span>
                <span>{!! __('<strong>Blokverdeling</strong> - Poules over tijdsblokken verdelen') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">6</span>
                <span>{!! __('<strong>Verdeel over matten</strong> - Automatische mat toewijzing, weegkaarten beschikbaar') !!}</span>
            </li>
        </ol>

        <h4 class="font-semibold mb-2">{{ __('Toernooidag') }}</h4>
        <ol class="space-y-2" start="7">
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">7</span>
                <span>{!! __('<strong>Weging</strong> - Judoka\'s wegen met QR scanner') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">8</span>
                <span>{!! __('<strong>Wedstrijddag Poules</strong> - Overpoelen (te zware/afwezige judoka\'s) - zet op de mat') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">9</span>
                <span>{!! __('<strong>Zaaloverzicht</strong> - Verdeel over de matten - Witte chip klikken = wedstrijdschema genereren') !!}</span>
            </li>
            <li class="flex items-start gap-3">
                <span class="bg-white text-blue-800 rounded-full w-6 h-6 flex items-center justify-center font-bold shrink-0">10</span>
                <span>{!! __('<strong>Wedstrijden</strong> - Mat interface voor scores, Spreker voor prijsuitreiking') !!}</span>
            </li>
        </ol>
    </div>

    {{-- Hoofdstukken navigatie --}}
    <div class="bg-white rounded-lg shadow p-4 mb-8" x-show="!searchQuery">
        <h3 class="font-bold text-gray-700 mb-3">{{ __('Ga naar hoofdstuk') }}</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <a href="#categorien" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Categorieën') }}</a>
            <a href="#inschrijving" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Inschrijving') }}</a>
            <a href="#judokas" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Judoka\'s') }}</a>
            <a href="#poules" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Poules') }}</a>
            <a href="#wedstrijdsystemen" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Wedstrijdsystemen') }}</a>
            <a href="#kruisfinales" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Kruisfinales') }}</a>
            <a href="#eliminatie" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Eliminatie') }}</a>
            <a href="#blokken" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Blokken') }}</a>
            <a href="#weegkaarten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Weegkaarten') }}</a>
            <a href="#coachkaarten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Coachkaarten') }}</a>
            <a href="#device-binding" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Device Binding') }}</a>
            <a href="#weging" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Weging') }}</a>
            <a href="#wedstrijddag-poules" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Wedstrijddag Poules') }}</a>
            <a href="#zaaloverzicht" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Zaaloverzicht') }}</a>
            <a href="#matten" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Matten') }}</a>
            <a href="#spreker" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Spreker') }}</a>
            <a href="#dojo" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Dojo Scanner') }}</a>
            <a href="#publiek" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Publieke PWA') }}</a>
            <a href="#puntencompetitie" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-center text-sm">{{ __('Puntencompetitie') }}</a>
        </div>
    </div>

    {{-- Hoofdstukken --}}
    <div class="space-y-6">

        {{-- Categorieën & Presets --}}
        <section id="categorien" class="help-section bg-white rounded-lg shadow p-6" data-keywords="categorie preset jbn gewichtsklasse leeftijd vast variabel dynamisch">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#9881;</span> {{ __('Categorieën & Presets') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Presets kiezen') }}</h4>
                <p>{!! __('Bij <strong>Toernooi Bewerken</strong> kun je kiezen uit:') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>JBN 2025 / JBN 2026</strong> - Standaard gewichtsklassen volgens JBN') !!}</li>
                    <li>{!! __('<strong>Eigen presets</strong> - Eerder opgeslagen configuraties') !!}</li>
                    <li>{!! __('<strong>Handmatig</strong> - Zelf categorieën instellen') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Max kg verschil (gewicht binnen 1 poule)') }}</h4>
                <p class="mb-2">{{ __('Bepaalt hoe judoka\'s qua gewicht over poules worden verdeeld:') }}</p>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Instelling') }}</th>
                            <th class="text-left py-1">{{ __('Werking') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('Max kg = 0 (vast)') }}</strong></td>
                            <td class="py-1">{{ __('Vaste gewichtsklassen zoals JBN (-24kg, -27kg, etc.). Judoka\'s worden ingedeeld op basis van hun gewichtsklasse.') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>{{ __('Max kg > 0 (variabel)') }}</strong></td>
                            <td class="py-1">{{ __('Poules worden samengesteld op basis van werkelijk gewicht. Binnen één poule mag het gewichtsverschil niet groter zijn dan dit getal. Bijv. max kg = 3: een poule kan 28kg, 29kg en 31kg bevatten.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Max leeftijdsverschil (leeftijd binnen 1 poule)') }}</h4>
                <p class="mb-2">{{ __('Bepaalt hoe judoka\'s qua leeftijd over poules worden verdeeld:') }}</p>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Instelling') }}</th>
                            <th class="text-left py-1">{{ __('Werking') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('V.lft = 0 (vast)') }}</strong></td>
                            <td class="py-1">{{ __('Houdt de categorie-leeftijd aan. Judoka\'s worden alleen ingedeeld met leeftijdgenoten binnen dezelfde categorie.') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>{{ __('V.lft > 0 (variabel)') }}</strong></td>
                            <td class="py-1">{{ __('Maximaal leeftijdsverschil binnen één poule. Bijv. v.lft = 1: een 8-jarige en 9-jarige mogen in dezelfde poule, maar niet een 8-jarige en 10-jarige.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Per categorie instellen') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Max leeftijd</strong> - Hoogste leeftijd in deze categorie') !!}</li>
                    <li>{!! __('<strong>Geslacht</strong> - Gemengd, Jongens of Meisjes') !!}</li>
                    <li>{!! __('<strong>Band filter</strong> - Optioneel: t/m oranje, vanaf groen, etc.') !!}</li>
                    <li>{!! __('<strong>Max kg verschil</strong> - 0 = vaste gewichtsklassen, >0 = variabel (max gewichtsverschil binnen 1 poule)') !!}</li>
                    <li>{!! __('<strong>Max leeftijdsverschil (v.lft)</strong> - 0 = categorie leeftijd aanhouden, >0 = max leeftijdsverschil binnen 1 poule') !!}</li>
                    <li>{!! __('<strong>Wedstrijdsysteem</strong> - Poules, Kruisfinale of Eliminatie') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Eigen preset opslaan') }}</h4>
                <p>{{ __('Na configureren kun je de instellingen opslaan als eigen preset voor later gebruik.') }}</p>
            </div>
        </section>

        {{-- Inschrijving --}}
        <section id="inschrijving" class="help-section bg-white rounded-lg shadow p-6" data-keywords="inschrijving aanmelden betalen mollie club coach portal">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128221;</span> {{ __('Inschrijving') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Coach Portal') }}</h4>
                <p>{{ __('Coaches kunnen zelf judoka\'s aanmelden via het Coach Portal:') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Per club wordt automatisch een URL + PIN aangemaakt') }}</li>
                    <li>{!! __('Stuur uitnodigingen via <strong>Clubs</strong> pagina') !!}</li>
                    <li>{{ __('URL en PIN ook direct te kopieren voor WhatsApp') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Betaling (optioneel)') }}</h4>
                <p>{!! __('Koppel je eigen <strong>Mollie account</strong> bij Toernooi Bewerken. Betalingen gaan direct naar jouw rekening.') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Betaling via iDEAL, creditcard, etc.') }}</li>
                    <li>{{ __('Automatische bevestigingsmail naar coach') }}</li>
                    <li>{{ __('Inschrijfgeld per judoka instelbaar') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Handmatige import') }}</h4>
                <p>{!! __('Organisator kan ook CSV/Excel uploaden via <strong>Judoka\'s &gt; Importeren</strong>.') !!}</p>
            </div>
        </section>

        {{-- Judoka's --}}
        <section id="judokas" class="help-section bg-white rounded-lg shadow p-6" data-keywords="judoka import csv uploaden deelnemers valideren qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129355;</span> {{ __('Judoka\'s') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Importeren via CSV/Excel') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Upload bestand (.csv, .xlsx, .xls)') }}</li>
                    <li>{{ __('Systeem detecteert kolommen automatisch') }}</li>
                    <li>{!! __('<strong>Drag & drop</strong> om kolom-mapping te corrigeren') !!}</li>
                    <li>{!! __('Controleer preview en klik <strong>Importeren</strong>') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Verplichte velden') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Naam') }}</li>
                    <li>{{ __('Geboortejaar') }}</li>
                    <li>{{ __('Geslacht (M/V of Jongen/Meisje)') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Optionele velden') }}</h4>
                <p>{{ __('Club, gewicht, band, gewichtsklasse - systeem berekent ontbrekende waarden.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Valideer judoka\'s (belangrijk!)') }}</h4>
                <p>{{ __('Na sluiting inschrijving:') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('Ga naar <strong>Judoka\'s &gt; Valideer</strong>') !!}</li>
                    <li>{{ __('Systeem controleert alle gegevens') }}</li>
                    <li>{!! __('<strong>QR-codes worden nu aangemaakt</strong>') !!}</li>
                    <li>{{ __('Na validatie kunnen coaches geen wijzigingen meer doen') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Import problemen') }}</h4>
                <p>{{ __('Bij ontbrekende gegevens krijgt de club een melding en kan dit corrigeren via het Coach Portal.') }}</p>
            </div>
        </section>

        {{-- Poules --}}
        <section id="poules" class="help-section bg-white rounded-lg shadow p-6" data-keywords="poule indeling genereren automatisch groepen categorie">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128101;</span> {{ __('Poules') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Automatisch genereren') }}</h4>
                <p>{{ __('Het systeem werkt in 4 stappen:') }}</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('<strong>Categoriseren</strong> - Judoka naar categorie (harde criteria: leeftijd, geslacht, band)') !!}</li>
                    <li>{!! __('<strong>Sorteren</strong> - Binnen de categorie op prioriteit (leeftijd/gewicht/band)') !!}</li>
                    <li>{!! __('<strong>Groeperen</strong> - Per categorie') !!}</li>
                    <li>{!! __('<strong>Poules maken</strong> - Verdelen binnen kg/leeftijd limieten') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Poule grootte') }}</h4>
                <p>{!! __('Instelbaar via <strong>poule_grootte_voorkeur</strong> (bv. [5, 4, 6, 3]):') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Eerste getal = ideale grootte (bv. 5 judoka\'s)') }}</li>
                    <li>{{ __('Volgorde = voorkeur bij verdeling') }}</li>
                    <li>{{ __('Groottes niet in lijst = problematisch (rood)') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Handmatig aanpassen') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Drag & drop</strong> - Sleep judoka\'s tussen poules') !!}</li>
                    <li>{{ __('Statistieken updaten automatisch (wedstrijden, gewicht range)') }}</li>
                    <li>{{ __('Poule titels worden automatisch samengesteld') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Sortering aanpassen') }}</h4>
                <p>{!! __('Bij <strong>Instellingen</strong> kun je de sorteer prioriteit aanpassen door te slepen: Leeftijd, Gewicht, Band.') !!}</p>
            </div>
        </section>

        {{-- Wedstrijdsystemen --}}
        <section id="wedstrijdsystemen" class="help-section bg-white rounded-lg shadow p-6" data-keywords="wedstrijdsysteem poule kruisfinale eliminatie knockout double elimination">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127942;</span> {{ __('Wedstrijdsystemen') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <p>{{ __('Per categorie kun je kiezen uit drie wedstrijdsystemen:') }}</p>

                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Systeem') }}</th>
                            <th class="text-left py-1">{{ __('Geschikt voor') }}</th>
                            <th class="text-left py-1">{{ __('Kenmerken') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('Poules') }}</strong></td>
                            <td class="py-1">{{ __('Beginners, recreatief') }}</td>
                            <td class="py-1">{{ __('Iedereen tegen iedereen, veel wedstrijdervaring') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('Kruisfinale') }}</strong></td>
                            <td class="py-1">{{ __('Grotere groepen') }}</td>
                            <td class="py-1">{{ __('Voorrondes + finale met beste judoka\'s') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>{{ __('Eliminatie') }}</strong></td>
                            <td class="py-1">{{ __('Competitief, gevorderden') }}</td>
                            <td class="py-1">{{ __('Double elimination, pas na 2 nederlagen uit') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Instellen') }}</h4>
                <p>{!! __('Bij <strong>Toernooi Bewerken &gt; Categorieën</strong> kies je per leeftijdsgroep het wedstrijdsysteem.') !!}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Achteraf wijzigen') }}</h4>
                <p>{!! __('Op de <strong>Poules</strong> pagina kun je via de dropdown bij elke poule het systeem nog wijzigen (bijv. eliminatie → poules bij te weinig deelnemers).') !!}</p>
            </div>
        </section>

        {{-- Kruisfinales --}}
        <section id="kruisfinales" class="help-section bg-white rounded-lg shadow p-6" data-keywords="kruisfinale finale doorplaatsing winnaar plaats">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127941;</span> {{ __('Kruisfinales') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Hoe werkt het?') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('<strong>Voorrondes</strong> - Judoka\'s spelen in poules (iedereen tegen iedereen)') !!}</li>
                    <li>{!! __('<strong>Doorplaatsing</strong> - Beste judoka\'s (1e of 1e+2e) gaan door') !!}</li>
                    <li>{!! __('<strong>Kruisfinale</strong> - Finale poule met alle doorgeplaatste judoka\'s') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Configuratie') }}</h4>
                <p>{!! __('Bij <strong>Toernooi Instellingen</strong> stel je in hoeveel plaatsen doorgaan:') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>1 plaats</strong> - Alleen de winnaar van elke voorronde') !!}</li>
                    <li>{!! __('<strong>2 plaatsen</strong> - Nummer 1 en 2 van elke voorronde') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Workflow') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Voorrondes worden gespeeld en afgerond') }}</li>
                    <li>{{ __('Systeem selecteert automatisch de beste judoka\'s') }}</li>
                    <li>{{ __('Hoofdjury wijst mat toe aan kruisfinale') }}</li>
                    <li>{{ __('Nieuw wedstrijdschema wordt gegenereerd') }}</li>
                </ol>
            </div>
        </section>

        {{-- Eliminatie --}}
        <section id="eliminatie" class="help-section bg-white rounded-lg shadow p-6" data-keywords="eliminatie knockout dubbel verliezer brons herkansing bracket">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129351;</span> {{ __('Eliminatie (Knock-out)') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Double Elimination') }}</h4>
                <p>{!! __('Bij eliminatie worden judoka\'s pas na <strong>twee nederlagen</strong> uitgeschakeld:') !!}</p>

                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Groep') }}</th>
                            <th class="text-left py-1">{{ __('Wie') }}</th>
                            <th class="text-left py-1">{{ __('Uitkomst') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('A-groep') }}</strong> ({{ __('Hoofdboom') }})</td>
                            <td class="py-1">{{ __('Alle judoka\'s starten hier') }}</td>
                            <td class="py-1">{{ __('Winnaar = Goud, Verliezer finale = Zilver') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>{{ __('B-groep') }}</strong> ({{ __('Herkansing') }})</td>
                            <td class="py-1">{{ __('Verliezers uit A-groep') }}</td>
                            <td class="py-1">{{ __('Winnaar(s) = Brons') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Flow') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('Alle judoka\'s starten in de <strong>A-groep</strong>') !!}</li>
                    <li>{!! __('Verlies in A → naar <strong>B-groep</strong> (herkansing)') !!}</li>
                    <li>{!! __('Verlies in B → <strong>uitgeschakeld</strong>') !!}</li>
                    <li>{!! __('Winnaars B-groep krijgen <strong>brons</strong>') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Aantal brons medailles') }}</h4>
                <p>{!! __('Bij <strong>Toernooi Instellingen</strong> kies je 1 of 2 brons medailles per categorie.') !!}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Wanneer gebruiken?') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Competitieve toernooien') }}</li>
                    <li>{{ __('Gevorderde judoka\'s (groen band en hoger)') }}</li>
                    <li>{{ __('Wanneer snelle progressie gewenst is') }}</li>
                </ul>
            </div>
        </section>

        {{-- Blokken --}}
        <section id="blokken" class="help-section bg-white rounded-lg shadow p-6" data-keywords="blok planning mat verdeling tijdslot schema vastzetten variant">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128203;</span> {{ __('Blokken') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat zijn blokken?') }}</h4>
                <p>{{ __('Een blok is een tijdslot met eigen weegtijd. Doel:') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Gelijke verdeling wedstrijden over de dag') }}</li>
                    <li>{{ __('Aansluitende gewichtsklassen bij elkaar (voor overpoelen)') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Werkwijze') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('Klik <strong>Bereken</strong> - systeem genereert 5 varianten') !!}</li>
                    <li>{{ __('Bekijk varianten (#1-#5) met scores') }}</li>
                    <li>{{ __('Pas eventueel aan met drag & drop') }}</li>
                    <li>{!! __('Klik <strong>Naar Zaaloverzicht</strong> om op te slaan') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Vastzetten') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Klik op pin-icoon om categorie vast te zetten') }}</li>
                    <li>{{ __('Vastgezette categorieeen blijven staan bij herberekenen') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Balans slider') }}</h4>
                <p>{{ __('Verschuif tussen "Gelijke verdeling" en "Aansluiting gewichten" voor verschillende optimalisaties.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Verdeel over matten') }}</h4>
                <p>{!! __('Na blokverdeling klikt u <strong>Naar Zaaloverzicht</strong>. Dit:') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Slaat de blokverdeling op') }}</li>
                    <li>{{ __('Wijst matten automatisch toe') }}</li>
                    <li>{{ __('Maakt weegkaarten beschikbaar (met blok + mat info)') }}</li>
                </ul>
            </div>
        </section>

        {{-- Weegkaarten --}}
        <section id="weegkaarten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="weegkaart printen afdrukken startnummer qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127991;</span> {{ __('Weegkaarten') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat staat op een weegkaart?') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Naam en club') }}</li>
                    <li>{{ __('QR-code (voor scannen bij weging)') }}</li>
                    <li>{{ __('Blok nummer + weegtijden') }}</li>
                    <li>{{ __('Mat nummer') }}</li>
                    <li>{{ __('Classificatie (leeftijd, gewicht, band)') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Vereisten') }}</h4>
                <p>{{ __('Weegkaarten zijn pas beschikbaar na:') }}</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('<strong>Valideer judoka\'s</strong> - QR-codes aangemaakt') !!}</li>
                    <li>{!! __('<strong>Blokverdeling</strong> - Blokken toegewezen') !!}</li>
                    <li>{!! __('<strong>Verdeel over matten</strong> - Matten toegewezen') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Dynamisch') }}</h4>
                <p>{!! __('Weegkaarten worden <strong>live gegenereerd</strong> en tonen altijd actuele info. Wijzigingen in blok/mat zijn direct zichtbaar.') !!}</p>
            </div>
        </section>

        {{-- Coachkaarten --}}
        <section id="coachkaarten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="coachkaart coach begeleider toegang foto device">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128084;</span> {{ __('Coachkaarten') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat zijn coachkaarten?') }}</h4>
                <p>{{ __('Toegangskaarten voor coaches om in de dojo bij de matten te mogen staan.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Aantal berekening') }}</h4>
                <p>{!! __('Gebaseerd op het <strong>grootste blok</strong> van de club (niet totaal aantal judoka\'s):') !!}</p>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Max judoka\'s in een blok') }}</th>
                            <th class="text-left py-1">{{ __('Kaarten') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1">1-5</td><td class="py-1">{{ __('1 kaart') }}</td></tr>
                        <tr class="border-b"><td class="py-1">6-10</td><td class="py-1">{{ __('2 kaarten') }}</td></tr>
                        <tr class="border-b"><td class="py-1">11-15</td><td class="py-1">{{ __('3 kaarten') }}</td></tr>
                        <tr><td class="py-1">16-20</td><td class="py-1">{{ __('4 kaarten') }}</td></tr>
                    </tbody>
                </table>
                <p class="text-xs mt-1">{{ __('Formule: ceil(max_judokas_per_blok / judokas_per_coach)') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Activatie (device binding)') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Coach opent link op telefoon') }}</li>
                    <li>{{ __('Vult naam in en maakt pasfoto') }}</li>
                    <li>{{ __('Kaart wordt gekoppeld aan dit device') }}</li>
                    <li>{!! __('QR-code is <strong>alleen zichtbaar op het geactiveerde device</strong>') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Overdracht') }}</h4>
                <p>{{ __('Coach kan kaart overdragen aan andere coach (bijv. ochtend/middag wissel). Nieuwe coach activeert met eigen naam + foto.') }}</p>
            </div>
        </section>

        {{-- Device Binding --}}
        <section id="device-binding" class="help-section bg-white rounded-lg shadow p-6" data-keywords="device binding url pin vrijwilliger toegang">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128241;</span> {{ __('Device Binding (Vrijwilligers)') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Hoe werkt het?') }}</h4>
                <p>{{ __('Vrijwilligers (mat, weging, spreker, dojo) krijgen toegang via URL + PIN:') }}</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('Organisator maakt toegang aan via <strong>Instellingen &gt; Organisatie</strong>') !!}</li>
                    <li>{{ __('Vrijwilliger ontvangt URL + PIN (via WhatsApp)') }}</li>
                    <li>{{ __('Opent URL, voert PIN in, device wordt gebonden') }}</li>
                    <li>{{ __('Daarna: device wordt herkend, direct naar interface') }}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Toegangen beheren') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Aanmaken/verwijderen per rol') }}</li>
                    <li>{{ __('Device status zien (gebonden / wachtend)') }}</li>
                    <li>{{ __('Device resetten als nodig') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Einde toernooi') }}</h4>
                <p>{{ __('Alle device bindings worden automatisch gereset.') }}</p>
            </div>
        </section>

        {{-- Weging --}}
        <section id="weging" class="help-section bg-white rounded-lg shadow p-6" data-keywords="weging wegen gewicht inchecken aanwezig scanner qr">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#9878;</span> {{ __('Weging') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Twee interfaces') }}</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Versie') }}</th>
                            <th class="text-left py-1">{{ __('Voor wie') }}</th>
                            <th class="text-left py-1">{{ __('Functie') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><strong>{{ __('Weeglijst') }}</strong></td>
                            <td class="py-1">{{ __('Admin/Hoofdjury') }}</td>
                            <td class="py-1">{{ __('Live overzicht alle judoka\'s') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1"><strong>{{ __('QR Scanner') }}</strong></td>
                            <td class="py-1">{{ __('Vrijwilliger (PWA)') }}</td>
                            <td class="py-1">{{ __('Scannen + gewicht invoeren') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('QR Scanner werkwijze') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('<strong>Scan QR-code</strong> van weegkaart OF zoek op naam') !!}</li>
                    <li>{!! __('Voer gewicht in via <strong>numpad</strong>') !!}</li>
                    <li>{!! __('Klik <strong>Registreer</strong>') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Twee gewichtsvelden') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Ingeschreven gewicht</strong> - Van import (voor voorbereiding)') !!}</li>
                    <li>{!! __('<strong>Gewogen gewicht</strong> - Van weging (voor wedstrijddag)') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Aanwezigheid') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('Gewogen = automatisch <strong>aanwezig</strong>') !!}</li>
                    <li>{!! __('Niet gewogen na sluiting weegtijd = <strong>afwezig</strong>') !!}</li>
                </ul>
            </div>
        </section>

        {{-- Wedstrijddag Poules --}}
        <section id="wedstrijddag-poules" class="help-section bg-white rounded-lg shadow p-6" data-keywords="wedstrijddag overpoelen wachtruimte afwezig te zwaar">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128259;</span> {{ __('Wedstrijddag Poules (Overpoelen)') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wanneer nodig?') }}</h4>
                <p>{{ __('Na sluiten weegtijd kunnen judoka\'s buiten hun gewichtsklasse vallen of afwezig zijn.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Afwezige judoka\'s') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Automatisch uit poule verwijderd') }}</li>
                    <li>{!! __('Zichtbaar via <strong>info-icoon</strong> in poule header') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Te zware/lichte judoka\'s') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('Verschijnen in <strong>wachtruimte</strong> (rechts)') !!}</li>
                    <li>{{ __('Sleep naar geschikte poule') }}</li>
                    <li>{!! __('Of gebruik <strong>Zoek match</strong> knop') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Naar Zaaloverzicht sturen') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{!! __('Klik <strong>&#8594;</strong> knop bij de poule') !!}</li>
                    <li>{!! __('Knop wordt <strong>&#10003;</strong> (groen)') !!}</li>
                    <li>{{ __('Poule is nu klaar voor activatie in Zaaloverzicht') }}</li>
                </ol>
            </div>
        </section>

        {{-- Zaaloverzicht --}}
        <section id="zaaloverzicht" class="help-section bg-white rounded-lg shadow p-6" data-keywords="zaaloverzicht activeren chip grijs wit groen wedstrijdschema">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127942;</span> {{ __('Zaaloverzicht & Activatie') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Chip kleuren') }}</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Kleur') }}</th>
                            <th class="text-left py-1">{{ __('Betekenis') }}</th>
                            <th class="text-left py-1">{{ __('Actie') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-gray-400 rounded"></span> {{ __('Grijs') }}</td>
                            <td class="py-1">{{ __('Niet doorgestuurd') }}</td>
                            <td class="py-1">{{ __('Ga naar Wedstrijddag Poules') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-white border border-gray-300 rounded"></span> {{ __('Wit') }}</td>
                            <td class="py-1">{{ __('Klaar voor activatie') }}</td>
                            <td class="py-1"><strong>{{ __('Klik om te activeren') }}</strong></td>
                        </tr>
                        <tr>
                            <td class="py-1"><span class="inline-block w-4 h-4 bg-green-500 rounded"></span> {{ __('Groen') }}</td>
                            <td class="py-1">{{ __('Geactiveerd') }}</td>
                            <td class="py-1">{{ __('Wedstrijden kunnen beginnen') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Activeren') }}</h4>
                <p>{!! __('Klik op een <strong>witte chip</strong>:') !!}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Wedstrijdschema wordt gegenereerd') }}</li>
                    <li>{{ __('Alleen aanwezige judoka\'s komen in schema') }}</li>
                    <li>{{ __('Chip wordt groen') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Poules verplaatsen') }}</h4>
                <p>{{ __('Sleep poules naar andere matten indien nodig.') }}</p>
            </div>
        </section>

        {{-- Matten --}}
        <section id="matten" class="help-section bg-white rounded-lg shadow p-6" data-keywords="mat interface wedstrijd score punten ippon wazaari yuko">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#129351;</span> {{ __('Mat Interface') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Twee versies') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Admin</strong> - Via menu (met navigatie)') !!}</li>
                    <li>{!! __('<strong>Tafeljury</strong> - PWA via URL + PIN (standalone)') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Wedstrijdschema kleuren') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><span class="text-green-600 font-bold">{{ __('Groen') }}</span> - {{ __('Huidige wedstrijd (nu op de mat)') }}</li>
                    <li><span class="text-yellow-600 font-bold">{{ __('Geel') }}</span> - {{ __('Volgende wedstrijd (judoka\'s klaarzetten)') }}</li>
                    <li><span class="text-gray-600 font-bold">{{ __('Grijs') }}</span> - {{ __('Nog niet aan de beurt') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Wedstrijd selecteren (klikken op nummer)') }}</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Klik op') }}</th>
                            <th class="text-left py-1">{{ __('Resultaat') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1"><span class="text-green-600 font-bold">{{ __('Groen') }}</span></td><td class="py-1">{{ __('Bevestiging → geel wordt groen, oud-groen neutraal') }}</td></tr>
                        <tr class="border-b"><td class="py-1"><span class="text-yellow-600 font-bold">{{ __('Geel') }}</span></td><td class="py-1">{{ __('Geel wordt neutraal (deselecteren)') }}</td></tr>
                        <tr class="border-b"><td class="py-1"><span class="text-gray-600 font-bold">{{ __('Grijs') }}</span> ({{ __('geen groen') }})</td><td class="py-1">{{ __('Wordt groen (eerste keuze)') }}</td></tr>
                        <tr class="border-b"><td class="py-1"><span class="text-gray-600 font-bold">{{ __('Grijs') }}</span> ({{ __('wel groen, geen geel') }})</td><td class="py-1">{{ __('Wordt geel') }}</td></tr>
                        <tr><td class="py-1"><span class="text-gray-600 font-bold">{{ __('Grijs') }}</span> ({{ __('wel groen, wel geel') }})</td><td class="py-1">{{ __('Melding: eerst gele uitzetten') }}</td></tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Puntensysteem') }}</h4>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Type') }}</th>
                            <th class="text-left py-1">{{ __('Punten') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1"><strong>{{ __('Winstpunten (WP)') }}</strong></td><td class="py-1">{{ __('Winst = 2, Gelijk = 1, Verlies = 0') }}</td></tr>
                        <tr class="border-b"><td class="py-1">{{ __('Ippon') }}</td><td class="py-1">{{ __('10 judopunten') }}</td></tr>
                        <tr class="border-b"><td class="py-1">{{ __('Waza-ari') }}</td><td class="py-1">{{ __('7 judopunten') }}</td></tr>
                        <tr><td class="py-1">{{ __('Yuko') }}</td><td class="py-1">{{ __('5 judopunten') }}</td></tr>
                    </tbody>
                </table>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Score invoeren') }}</h4>
                <p>{{ __('Via de matrix (WP en JP kolommen):') }}</p>
                <table class="min-w-full text-sm mt-2">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">{{ __('Invoer JP') }}</th>
                            <th class="text-left py-1">{{ __('Resultaat') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b"><td class="py-1"><strong>{{ __('Leeg (blanco)') }}</strong></td><td class="py-1">{{ __('Reset alle scores (WP en JP) voor deze wedstrijd') }}</td></tr>
                        <tr class="border-b"><td class="py-1"><strong>0</strong></td><td class="py-1">{{ __('Gelijkspel: beide WP=1, beide JP=0') }}</td></tr>
                        <tr><td class="py-1"><strong>5, 7, 10</strong></td><td class="py-1">{{ __('Winnaar: deze judoka WP=2, tegenstander WP=0 en JP=0') }}</td></tr>
                    </tbody>
                </table>
                <p class="text-sm text-gray-500 mt-2">{!! __('<strong>Tip:</strong> WP handmatig invoeren vult de tegenstander niet automatisch in.') !!}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Ranking') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Hoogste winstpunten (WP)') }}</li>
                    <li>{{ __('Hoogste judopunten (JP)') }}</li>
                    <li>{{ __('Onderlinge wedstrijd') }}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Poule afronden') }}</h4>
                <p>{!! __('Klik <strong>Poule Klaar</strong> - poule gaat naar spreker voor prijsuitreiking.') !!}</p>
            </div>
        </section>

        {{-- Spreker --}}
        <section id="spreker" class="help-section bg-white rounded-lg shadow p-6" data-keywords="spreker omroep oproepen prijsuitreiking wachtrij">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127908;</span> {{ __('Spreker Interface') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Twee versies') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Admin</strong> - Via menu (met navigatie)') !!}</li>
                    <li>{!! __('<strong>Vrijwilliger</strong> - PWA via URL + PIN (standalone)') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Wachtrij') }}</h4>
                <p>{{ __('Afgeronde poules verschijnen automatisch in de wachtrij met:') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Eindstand met 1e, 2e, 3e plaats') }}</li>
                    <li>{{ __('Judoka namen en clubs') }}</li>
                    <li>{{ __('Categorie informatie') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Prijsuitreiking') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Roep judoka\'s op (1e, 2e, 3e)') }}</li>
                    <li>{{ __('Reik medailles uit') }}</li>
                    <li>{!! __('Markeer als <strong>Uitgereikt</strong>') !!}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Auto-refresh') }}</h4>
                <p>{{ __('Interface vernieuwt automatisch elke 10 seconden.') }}</p>
            </div>
        </section>

        {{-- Dojo Scanner --}}
        <section id="dojo" class="help-section bg-white rounded-lg shadow p-6" data-keywords="dojo scanner coachkaart toegang controle foto verificatie">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128274;</span> {{ __('Dojo Scanner') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat is het?') }}</h4>
                <p>{{ __('QR scanner voor toegangscontrole bij de ingang van de dojo (wedstrijdruimte).') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Toegang') }}</h4>
                <p>{{ __('Vrijwilliger via URL + PIN + device binding (Instellingen > Organisatie > Dojo toegangen).') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Werkwijze') }}</h4>
                <ol class="list-decimal list-inside space-y-1">
                    <li>{{ __('Scan QR-code van coachkaart') }}</li>
                    <li>{!! __('Systeem toont <strong>foto van coach</strong>') !!}</li>
                    <li>{{ __('Vrijwilliger vergelijkt foto met persoon') }}</li>
                    <li>{{ __('Bij match: toegang verlenen') }}</li>
                </ol>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Wisselgeschiedenis') }}</h4>
                <p>{{ __('Bij overdracht van coachkaart toont de scanner ook de wisselgeschiedenis (wie de kaart eerder had).') }}</p>
            </div>
        </section>

        {{-- Publieke PWA --}}
        <section id="publiek" class="help-section bg-white rounded-lg shadow p-6" data-keywords="publiek toeschouwer live uitslagen favorieten">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#128065;</span> {{ __('Publieke PWA (Toeschouwers)') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat is het?') }}</h4>
                <p>{{ __('Openbare pagina voor ouders en toeschouwers. Installeerbaar als PWA op telefoon.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Tabs') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Info</strong> - Toernooi info, tijdschema') !!}</li>
                    <li>{!! __('<strong>Deelnemers</strong> - Zoek judoka\'s, markeer favorieten') !!}</li>
                    <li>{!! __('<strong>Favorieten</strong> - Je gemarkeerde judoka\'s + hun poules') !!}</li>
                    <li>{!! __('<strong>Live Matten</strong> - Wie speelt nu, wie moet klaarmaken') !!}</li>
                    <li>{!! __('<strong>Uitslagen</strong> - Eindstanden per poule') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Live Matten weergave') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li><span class="text-green-600 font-bold">{{ __('Groen') }}</span> - {{ __('Speelt nu') }}</li>
                    <li><span class="text-yellow-600 font-bold">{{ __('Geel') }}</span> - {{ __('Klaar staan') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Notificaties') }}</h4>
                <p>{{ __('Bij favorieten: alerts wanneer judoka bijna aan de beurt is of speelt.') }}</p>
            </div>
        </section>

        {{-- Puntencompetitie (Wimpeltoernooi) --}}
        <section id="puntencompetitie" class="help-section bg-white rounded-lg shadow p-6" data-keywords="puntencompetitie wimpel wimpeltoernooi punten milestone prijsje badge nieuw">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="text-2xl">&#127942;</span> {{ __('Puntencompetitie (Wimpeltoernooi)') }}
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <h4 class="font-semibold text-gray-800">{{ __('Wat is het?') }}</h4>
                <p>{{ __('Een doorlopend puntensysteem waarbij judoka\'s over meerdere toernooien punten sparen. Per gewonnen wedstrijd krijgt een judoka 1 punt. Bij het bereiken van milestones ontvangt de judoka een prijsje.') }}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Punten bijschrijven') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{!! __('<strong>Automatisch</strong> - Zodra een poule klaar is op de mat worden punten direct bijgeschreven') !!}</li>
                    <li>{!! __('<strong>Bulk verwerking</strong> - Via het wimpeltoernooi overzicht kun je onverwerkte toernooien in één keer verwerken') !!}</li>
                    <li>{!! __('<strong>Handmatig</strong> - Punten toevoegen of aftrekken bij een individuele judoka (bijv. correcties of oude standen)') !!}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Nieuwe judoka\'s') }}</h4>
                <p>{!! __('Judoka\'s die voor het eerst verschijnen krijgen een <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded">NIEUW</span> badge. Controleer of er oude punten bijgeschreven moeten worden. Klik op <strong>Bevestigd</strong> om de badge te verwijderen.') !!}</p>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Milestones') }}</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('Configureer milestones bij Instellingen (bijv. 10, 20, 30 punten)') }}</li>
                    <li>{{ __('Per milestone: puntenaantal + omschrijving van het prijsje') }}</li>
                    <li>{{ __('Bij het bereiken van een milestone verschijnt een melding op het overzicht') }}</li>
                </ul>

                <h4 class="font-semibold text-gray-800 mt-4">{{ __('Export') }}</h4>
                <p>{{ __('Download de volledige puntenstand als Excel of CSV via de instellingen pagina.') }}</p>
            </div>
        </section>

    </div>

    {{-- Terug naar boven --}}
    <div class="mt-8 text-center">
        <a href="#" class="text-blue-600 hover:text-blue-800">&#8593; {{ __('Terug naar boven') }}</a>
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
