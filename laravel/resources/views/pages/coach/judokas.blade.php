<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - Judoka's</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $club->naam }}</h1>
                    <p class="text-gray-600">{{ $toernooi->naam }} - {{ $toernooi->datum->format('d F Y') }}</p>
                </div>
                @if(isset($useCode) && $useCode)
                <form action="{{ route('coach.portal.logout', $code) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-800">Uitloggen</button>
                </form>
                @else
                <form action="{{ route('coach.logout', $uitnodiging->token) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-800">Uitloggen</button>
                </form>
                @endif
            </div>

            <!-- Navigation tabs -->
            <div class="mt-4 flex space-x-4 border-t pt-4 overflow-x-auto">
                @if(isset($useCode) && $useCode)
                <a href="{{ route('coach.portal.judokas', $code) }}"
                   class="text-blue-600 font-medium border-b-2 border-blue-600 px-3 py-1 whitespace-nowrap">
                    Judoka's
                </a>
                <a href="{{ route('coach.portal.coachkaarten', $code) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Coach Kaarten
                </a>
                <a href="{{ route('coach.portal.weegkaarten', $code) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Weegkaarten
                </a>
                <a href="{{ route('coach.portal.resultaten', $code) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Resultaten
                </a>
                @else
                <a href="{{ route('coach.judokas', $uitnodiging->token) }}"
                   class="text-blue-600 font-medium border-b-2 border-blue-600 px-3 py-1 whitespace-nowrap">
                    Judoka's
                </a>
                <a href="{{ route('coach.weegkaarten', $uitnodiging->token) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Weegkaarten
                </a>
                @endif
            </div>

            @if(!$inschrijvingOpen)
            <div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                <strong>Let op:</strong> De inschrijving is gesloten. Je kunt geen judoka's meer toevoegen of bewerken.
            </div>
            @elseif($maxBereikt)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                <strong>Vol:</strong> Het maximum aantal deelnemers ({{ $toernooi->max_judokas }}) is bereikt. Je kunt geen nieuwe judoka's meer toevoegen.
            </div>
            @elseif($bijna80ProcentVol)
            <div class="mt-4 bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded">
                <strong>Bijna vol!</strong> Het toernooi is {{ $bezettingsPercentage }}% vol. Nog {{ $plaatsenOver }} {{ $plaatsenOver == 1 ? 'plek' : 'plekken' }} beschikbaar.
            </div>
            @endif

            @if($toernooi->max_judokas && !$maxBereikt && !$bijna80ProcentVol)
            <div class="mt-4 text-sm text-gray-600">
                Totaal aangemeld: {{ $totaalJudokas }} / {{ $toernooi->max_judokas }} ({{ $bezettingsPercentage }}%)
            </div>
            @endif

            @php
                $heeftEliminatie = !empty($eliminatieGewichtsklassen) && collect($eliminatieGewichtsklassen)->flatten()->isNotEmpty();
            @endphp
            @if($heeftEliminatie)
            <div class="mt-4 bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded text-sm">
                <strong>Eliminatie (afvalsysteem):</strong> Bij sommige categorieën wordt er met eliminatie gespeeld i.p.v. poules.
                <details class="mt-2">
                    <summary class="cursor-pointer text-orange-700 hover:text-orange-900">Welke categorieën?</summary>
                    <ul class="mt-2 ml-4 list-disc space-y-1">
                        @foreach($eliminatieGewichtsklassen as $lkKey => $gewichten)
                            @if(!empty($gewichten))
                            <li>{{ $gewichtsklassen[$lkKey]['label'] ?? $lkKey }}: {{ implode(', ', array_map(fn($g) => $g . ' kg', $gewichten)) }}</li>
                            @endif
                        @endforeach
                    </ul>
                </details>
            </div>
            @endif
        </div>

        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
        @endif

        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-6">
            {{ session('warning') }}
        </div>
        @endif

        <!-- Add Judoka Form -->
        @if($inschrijvingOpen && !$maxBereikt)
        <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="judokaForm()">
            <button @click="open = !open" class="flex justify-between items-center w-full text-left">
                <h2 class="text-xl font-bold text-gray-800">+ Nieuwe Judoka Toevoegen</h2>
                <span x-text="open ? '−' : '+'" class="text-2xl text-gray-500"></span>
            </button>

            <form action="{{ isset($useCode) && $useCode ? route('coach.portal.judoka.store', $code) : route('coach.judoka.store', $uitnodiging->token) }}" method="POST"
                  x-show="open" x-collapse class="mt-4 pt-4 border-t">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Naam *</label>
                        <input type="text" name="naam" required
                               class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Geboortejaar</label>
                        <input type="number" name="geboortejaar" x-model="geboortejaar" @change="updateLeeftijdsklasse()" min="1990" max="{{ date('Y') }}"
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Geslacht</label>
                        <select name="geslacht" x-model="geslacht" @change="updateLeeftijdsklasse()" class="w-full border rounded px-3 py-2">
                            <option value="">Selecteer...</option>
                            <option value="M">Man</option>
                            <option value="V">Vrouw</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Band</label>
                        <select name="band" class="w-full border rounded px-3 py-2">
                            <option value="">Selecteer...</option>
                            <option value="wit">Wit</option>
                            <option value="geel">Geel</option>
                            <option value="oranje">Oranje</option>
                            <option value="groen">Groen</option>
                            <option value="blauw">Blauw</option>
                            <option value="bruin">Bruin</option>
                            <option value="zwart">Zwart</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Gewicht (kg)</label>
                        <input type="number" name="gewicht" x-model="gewicht" @input="updateGewichtsklasse()" step="0.1" min="10" max="200"
                               class="w-full border rounded px-3 py-2" placeholder="bijv. 32.5">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Gewichtsklasse</label>
                        <select name="gewichtsklasse" x-model="gewichtsklasse" class="w-full border rounded px-3 py-2">
                            <option value="">Automatisch bepaald</option>
                            <template x-for="gw in gewichtsopties" :key="gw">
                                <option :value="gw" x-text="gw + ' kg'"></option>
                            </template>
                        </select>
                        <p x-show="leeftijdsklasse" class="text-xs mt-1">
                            <span class="text-blue-600" x-text="'Leeftijdsklasse: ' + leeftijdsklasse"></span>
                            <span x-show="isElim" class="ml-1 text-orange-600 font-medium">(Eliminatie)</span>
                        </p>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Telefoon</label>
                        <input type="tel" name="telefoon"
                               class="w-full border rounded px-3 py-2" placeholder="06-12345678">
                        <p class="text-xs text-gray-500 mt-1">Voor WhatsApp contact</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">* Alleen naam is verplicht. Vul de rest later aan voordat de inschrijving sluit.</p>
                <div class="mt-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded">
                        Toevoegen
                    </button>
                </div>
            </form>
        </div>
        @endif

        <!-- Judokas List -->
        @php
            $volledigeJudokas = $judokas->filter(fn($j) => $j->isVolledig());
            $onvolledigeJudokas = $judokas->filter(fn($j) => !$j->isVolledig());
            $syncedJudokas = $judokas->filter(fn($j) => $j->isSynced() && !$j->isGewijzigdNaSync());
            $gewijzigdJudokas = $judokas->filter(fn($j) => $j->isGewijzigdNaSync());
            $nietSyncedVolledig = $volledigeJudokas->filter(fn($j) => !$j->isSynced() || $j->isGewijzigdNaSync());
            $judokasMetImportWarnings = $judokas->filter(fn($j) => !empty($j->import_warnings));
            $judokasTeCorrigeren = $judokas->filter(fn($j) => $j->isTeCorrigeren());
        @endphp

        <!-- Sync Status Box -->
        @if(isset($useCode) && $useCode)
        <div class="bg-white rounded-lg shadow p-4 mb-6" x-data="{ filter: 'alle' }" @filter-changed.window="filter = $event.detail">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <button @click="filter = 'alle'; $dispatch('filter-changed', 'alle')"
                            :class="filter === 'alle' ? 'bg-gray-200 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="text-gray-600">Alle ({{ $judokas->count() }})</span>
                    </button>
                    <button @click="filter = 'synced'; $dispatch('filter-changed', 'synced')"
                            :class="filter === 'synced' ? 'bg-green-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $syncedJudokas->count() }} gesynced</span>
                    </button>
                    @if($gewijzigdJudokas->count() > 0)
                    <button @click="filter = 'gewijzigd'; $dispatch('filter-changed', 'gewijzigd')"
                            :class="filter === 'gewijzigd' ? 'bg-orange-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $gewijzigdJudokas->count() }} gewijzigd</span>
                    </button>
                    @endif
                    @if($onvolledigeJudokas->count() > 0)
                    <button @click="filter = 'incompleet'; $dispatch('filter-changed', 'incompleet')"
                            :class="filter === 'incompleet' ? 'bg-yellow-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $onvolledigeJudokas->count() }} incompleet</span>
                    </button>
                    @endif
                    @if($nietSyncedVolledig->count() > 0)
                    <button @click="filter = 'klaar'; $dispatch('filter-changed', 'klaar')"
                            :class="filter === 'klaar' ? 'bg-gray-300 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-gray-400 rounded-full"></span>
                        <span class="text-gray-600">{{ $nietSyncedVolledig->count() }} klaar om te syncen</span>
                    </button>
                    @endif
                </div>
                <form action="{{ route('coach.portal.sync', $code) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded flex items-center gap-2"
                            {{ $nietSyncedVolledig->count() === 0 ? 'disabled' : '' }}
                            @class(['opacity-50 cursor-not-allowed' => $nietSyncedVolledig->count() === 0])>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Sync Judoka's
                    </button>
                </form>
            </div>
            <p class="text-xs text-gray-500 mt-2">Sync stuurt alleen volledige judoka's door naar de organisator. Na sync kun je nog wijzigen, maar dan moet je opnieuw syncen.</p>
        </div>
        @endif

        @if($onvolledigeJudokas->count() > 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <span class="text-yellow-600 text-xl mr-2">⚠</span>
                <div>
                    <p class="font-medium text-yellow-800">{{ $onvolledigeJudokas->count() }} judoka('s) onvolledig</p>
                    <p class="text-sm text-yellow-700">Onvolledige judoka's kunnen niet {{ ($betalingActief ?? false) ? 'afgerekend' : 'gesynced' }} worden. Vul de ontbrekende gegevens aan.</p>
                </div>
            </div>
        </div>
        @endif

        @if($judokasTeCorrigeren->count() > 0)
        <div class="bg-orange-50 border-2 border-orange-300 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <span class="text-orange-600 text-xl mr-2">⚠️</span>
                <div class="flex-1">
                    <p class="font-bold text-orange-800">{{ $judokasTeCorrigeren->count() }} judoka('s) vereisen correctie</p>
                    <p class="text-sm text-orange-700 mb-3">Bij de import zijn er problemen gedetecteerd. Pas de gegevens aan en sla op om de problemen op te lossen.</p>
                    <ul class="text-sm text-orange-800 space-y-1">
                        @foreach($judokasTeCorrigeren as $j)
                        <li class="flex items-center gap-2">
                            <span class="font-medium">{{ $j->naam }}:</span>
                            <span class="text-orange-600">{{ $j->import_warnings }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @elseif($judokasMetImportWarnings->count() > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <span class="text-red-600 text-xl mr-2">⚠️</span>
                <div>
                    <p class="font-medium text-red-800">{{ $judokasMetImportWarnings->count() }} judoka('s) met import waarschuwingen</p>
                    <p class="text-sm text-red-700">Er waren problemen bij het importeren van deze judoka's. Controleer de gegevens en pas aan indien nodig.</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Payment Box --}}
        @if($betalingActief ?? false)
        <div class="bg-white rounded-lg shadow p-4 mb-6" x-data="{ filter: 'alle' }" @filter-changed.window="filter = $event.detail">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <button @click="filter = 'alle'; $dispatch('filter-changed', 'alle')"
                            :class="filter === 'alle' ? 'bg-gray-200 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="text-gray-600">Alle ({{ $judokas->count() }})</span>
                    </button>
                    <button @click="filter = 'betaald'; $dispatch('filter-changed', 'betaald')"
                            :class="filter === 'betaald' ? 'bg-green-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $aantalBetaald ?? 0 }} betaald</span>
                    </button>
                    @if(($volledigeOnbetaald ?? collect())->count() > 0)
                    <button @click="filter = 'klaar_betaling'; $dispatch('filter-changed', 'klaar_betaling')"
                            :class="filter === 'klaar_betaling' ? 'bg-blue-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $volledigeOnbetaald->count() }} klaar voor betaling en syncen</span>
                    </button>
                    @endif
                    @if($onvolledigeJudokas->count() > 0)
                    <button @click="filter = 'incompleet'; $dispatch('filter-changed', 'incompleet')"
                            :class="filter === 'incompleet' ? 'bg-yellow-100 font-medium' : 'hover:bg-gray-100'"
                            class="flex items-center gap-1 px-2 py-1 rounded transition-colors">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="text-gray-600">{{ $onvolledigeJudokas->count() }} incompleet</span>
                    </button>
                    @endif
                </div>
                @if(($volledigeOnbetaald ?? collect())->count() > 0)
                <a href="{{ isset($useCode) && $useCode ? route('coach.portal.afrekenen', $code) : route('coach.afrekenen', $uitnodiging->token) }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Afrekenen (€{{ number_format($volledigeOnbetaald->count() * ($inschrijfgeld ?? 0), 2, ',', '.') }})
                </a>
                @else
                <span class="text-gray-400 text-sm">Geen judoka's om af te rekenen</span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Inschrijfgeld: €{{ number_format($inschrijfgeld ?? 0, 2, ',', '.') }} per judoka.
                Alleen volledige judoka's kunnen afgerekend worden.
            </p>
        </div>
        @endif

        <div class="bg-white rounded-lg shadow overflow-hidden" x-data="{ filter: 'alle' }" @filter-changed.window="filter = $event.detail">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Mijn Judoka's ({{ $volledigeJudokas->count() }} volledig, {{ $onvolledigeJudokas->count() }} onvolledig)</h2>
            </div>

            @if($judokas->count() > 0)
            <div class="divide-y">
                @foreach($judokas as $judoka)
                @php
                    $isOnvolledig = !$judoka->isVolledig();
                    $ontbrekend = $judoka->getOntbrekendeVelden();
                    $isSynced = $judoka->isSynced() && !$judoka->isGewijzigdNaSync();
                    $isGewijzigd = $judoka->isGewijzigdNaSync();
                    $isBetaald = $judoka->isBetaald();
                    $isKlaarOmTeSyncen = $judoka->isVolledig() && (!$judoka->isSynced() || $judoka->isGewijzigdNaSync());
                    $isKlaarVoorBetaling = $judoka->isVolledig() && !$judoka->isBetaald();
                    // Check of judoka in eliminatie categorie zit
                    $lkKey = null;
                    if ($judoka->geboortejaar && $judoka->geslacht) {
                        $leeftijd = date('Y') - $judoka->geboortejaar;
                        $lkEnum = \App\Enums\Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $judoka->geslacht);
                        $lkKey = $lkEnum->configKey();
                    }
                    $isEliminatie = $lkKey && $judoka->gewichtsklasse && isset($eliminatieGewichtsklassen[$lkKey]) && in_array($judoka->gewichtsklasse, $eliminatieGewichtsklassen[$lkKey]);

                    // Validatie warnings
                    $warnings = [];
                    if ($judoka->gewicht && $judoka->gewicht > 150) {
                        $warnings[] = "Gewicht {$judoka->gewicht} kg lijkt erg hoog - is dit correct?";
                    }
                    if ($judoka->gewicht && $judoka->gewicht < 15) {
                        $warnings[] = "Gewicht {$judoka->gewicht} kg lijkt erg laag";
                    }
                    if ($judoka->geboortejaar && $judoka->geboortejaar >= date('Y')) {
                        $warnings[] = "Geboortejaar " . $judoka->geboortejaar . " = dit jaar of later?";
                    }
                    if ($judoka->geboortejaar && $judoka->geboortejaar < date('Y') - 50) {
                        $warnings[] = "Leeftijd " . (date('Y') - $judoka->geboortejaar) . " jaar lijkt erg hoog";
                    }
                @endphp
                <div class="p-4 hover:bg-gray-50 {{ $isOnvolledig ? 'bg-yellow-50 border-l-4 border-yellow-400' : ($isBetaald ? 'border-l-4 border-green-500' : ($isGewijzigd ? 'border-l-4 border-orange-400' : ($isSynced ? 'border-l-4 border-green-400' : ''))) }} {{ $judoka->import_warnings ? 'bg-red-50' : (count($warnings) > 0 && !$isOnvolledig ? 'bg-orange-50' : '') }}"
                     x-data="{ editing: false }"
                     x-show="filter === 'alle' || (filter === 'synced' && {{ $isSynced ? 'true' : 'false' }}) || (filter === 'gewijzigd' && {{ $isGewijzigd ? 'true' : 'false' }}) || (filter === 'incompleet' && {{ $isOnvolledig ? 'true' : 'false' }}) || (filter === 'klaar' && {{ $isKlaarOmTeSyncen ? 'true' : 'false' }}) || (filter === 'betaald' && {{ $isBetaald ? 'true' : 'false' }}) || (filter === 'klaar_betaling' && {{ $isKlaarVoorBetaling ? 'true' : 'false' }})">
                    <!-- View mode -->
                    <div x-show="!editing" class="flex justify-between items-center">
                        <div class="flex items-start gap-2">
                            @if($betalingActief ?? false)
                                @if($isBetaald)
                                <span class="text-green-600 mt-1" title="Betaald">€</span>
                                @elseif($isOnvolledig)
                                <span class="text-yellow-500 mt-1" title="Incompleet - kan niet afrekenen">!</span>
                                @else
                                <span class="text-blue-500 mt-1" title="Klaar voor betaling">○</span>
                                @endif
                            @else
                                @if($isSynced)
                                <span class="text-green-500 mt-1" title="Gesynced">✓</span>
                                @elseif($isGewijzigd)
                                <span class="text-orange-500 mt-1" title="Gewijzigd na sync">⟳</span>
                                @elseif($isOnvolledig)
                                <span class="text-yellow-500 mt-1" title="Incompleet">!</span>
                                @else
                                <span class="text-gray-300 mt-1" title="Nog niet gesynced">○</span>
                                @endif
                            @endif
                            <div>
                                <p class="font-medium {{ $isOnvolledig ? 'text-yellow-800' : 'text-gray-800' }}">
                                    {{ $judoka->naam }}
                                    @if($betalingActief ?? false)
                                        @if($isBetaald)
                                        <span class="text-xs bg-green-200 text-green-800 px-2 py-0.5 rounded ml-2">Betaald</span>
                                        @elseif($isOnvolledig)
                                        <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded ml-2">Onvolledig</span>
                                        @endif
                                    @else
                                        @if($isOnvolledig)
                                        <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded ml-2">Onvolledig</span>
                                        @elseif($isGewijzigd)
                                        <span class="text-xs bg-orange-200 text-orange-800 px-2 py-0.5 rounded ml-2">Gewijzigd</span>
                                        @endif
                                    @endif
                                </p>
                            <p class="text-sm text-gray-600">
                                {{ $judoka->geboortejaar ?? '?' }} |
                                {{ $judoka->geslacht === 'M' ? 'Man' : ($judoka->geslacht === 'V' ? 'Vrouw' : '?') }} |
                                {{ $judoka->band ? ucfirst($judoka->band) : '?' }} |
                                {{ $judoka->gewicht ? $judoka->gewicht . ' kg' : '?' }}
                                @if($judoka->gewichtsklasse)
                                ({{ $judoka->gewichtsklasse }} kg)
                                @endif
                                @if($judoka->telefoon)
                                | <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $judoka->telefoon) }}" target="_blank" class="text-green-600 hover:text-green-800" title="WhatsApp {{ $judoka->telefoon }}">WhatsApp</a>
                                @endif
                            </p>
                            @if($judoka->leeftijdsklasse)
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $judoka->leeftijdsklasse }}
                                @if($isEliminatie)
                                <span class="ml-1 text-orange-600 font-medium" title="Eliminatie systeem">(Elim.)</span>
                                @endif
                            </p>
                            @endif
                            @if($isOnvolledig)
                            <p class="text-xs text-yellow-700 mt-1">Ontbreekt: {{ implode(', ', $ontbrekend) }}</p>
                            @endif
                            @if(count($warnings) > 0)
                            <p class="text-xs text-orange-600 mt-1">⚠ {{ implode(' | ', $warnings) }}</p>
                            @endif
                            @if($judoka->import_warnings)
                            <p class="text-xs text-red-600 mt-1">⚠️ Import: {{ $judoka->import_warnings }}</p>
                            @endif
                            </div>
                        </div>
                        @if($inschrijvingOpen)
                        <div class="flex space-x-2">
                            <button @click="editing = true" class="text-blue-600 hover:text-blue-800 text-sm">
                                {{ $isOnvolledig ? 'Aanvullen' : 'Bewerk' }}
                            </button>
                            <form action="{{ isset($useCode) && $useCode ? route('coach.portal.judoka.destroy', [$code, $judoka]) : route('coach.judoka.destroy', [$uitnodiging->token, $judoka]) }}" method="POST"
                                  onsubmit="return confirm('Weet je zeker dat je deze judoka wilt verwijderen?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    Verwijder
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>

                    <!-- Edit mode -->
                    @if($inschrijvingOpen)
                    <div x-show="editing" x-data="judokaEditForm({{ $judoka->geboortejaar ?? 'null' }}, '{{ $judoka->geslacht ?? '' }}', '{{ $judoka->gewichtsklasse ?? '' }}', {{ $judoka->gewicht ?? 'null' }})">
                        <form action="{{ isset($useCode) && $useCode ? route('coach.portal.judoka.update', [$code, $judoka]) : route('coach.judoka.update', [$uitnodiging->token, $judoka]) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-2 md:grid-cols-7 gap-2">
                                <input type="text" name="naam" value="{{ $judoka->naam }}" required
                                       class="border rounded px-2 py-1" placeholder="Naam">
                                <input type="number" name="geboortejaar" x-model="geboortejaar" @change="updateLeeftijdsklasse()"
                                       class="border rounded px-2 py-1" placeholder="Geboortejaar">
                                <select name="geslacht" x-model="geslacht" @change="updateLeeftijdsklasse()" class="border rounded px-2 py-1">
                                    <option value="">Geslacht</option>
                                    <option value="M">Man</option>
                                    <option value="V">Vrouw</option>
                                </select>
                                <select name="band" class="border rounded px-2 py-1">
                                    <option value="">Band</option>
                                    @foreach(['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'] as $band)
                                    <option value="{{ $band }}" {{ $judoka->band === $band ? 'selected' : '' }}>{{ ucfirst($band) }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="gewicht" x-model="gewicht" @input="updateGewichtsklasse()" step="0.1"
                                       class="border rounded px-2 py-1" placeholder="Gewicht kg">
                                <select name="gewichtsklasse" x-model="gewichtsklasse" class="border rounded px-2 py-1">
                                    <option value="">Auto klasse</option>
                                    <template x-for="gw in gewichtsopties" :key="gw">
                                        <option :value="gw" x-text="gw + ' kg'"></option>
                                    </template>
                                </select>
                                <input type="tel" name="telefoon" value="{{ $judoka->telefoon }}"
                                       class="border rounded px-2 py-1" placeholder="Telefoon">
                            </div>
                            <p x-show="leeftijdsklasse" class="text-xs mt-1">
                                <span class="text-blue-600" x-text="'Leeftijdsklasse: ' + leeftijdsklasse"></span>
                                <span x-show="isElim" class="ml-1 text-orange-600 font-medium">(Eliminatie)</span>
                            </p>
                            <div class="mt-2 flex space-x-2">
                                <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Opslaan</button>
                                <button type="button" @click="editing = false" class="bg-gray-300 px-3 py-1 rounded text-sm">Annuleer</button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center text-gray-500">
                Nog geen judoka's opgegeven.
                @if($inschrijvingOpen && !$maxBereikt)
                Voeg hierboven je eerste judoka toe!
                @endif
            </div>
            @endif
        </div>

        <!-- Aanmelding status melding -->
        @if($judokas->count() > 0)
        @php
            $aantalAangemeld = ($betalingActief ?? false)
                ? ($aantalBetaald ?? 0)
                : $syncedJudokas->count();
            $aantalNietAangemeld = $judokas->count() - $aantalAangemeld;
        @endphp
        @if($aantalNietAangemeld > 0)
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center justify-center gap-2 text-blue-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium">
                    {{ $aantalAangemeld }} van {{ $judokas->count() }} judoka's aangemeld
                    @if($aantalNietAangemeld > 0)
                    &mdash; <span class="text-blue-600">{{ $aantalNietAangemeld }} nog niet aangemeld</span>
                    @endif
                </span>
            </div>
        </div>
        @endif
        @endif

        <!-- Info footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
            @if($toernooi->inschrijving_deadline)
            <p>Deadline: {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}</p>
            @endif
            @if($toernooi->max_judokas)
            <p>Totaal deelnemers: {{ $totaalJudokas }} / {{ $toernooi->max_judokas }} ({{ $bezettingsPercentage }}%)</p>
            @else
            <p>Totaal deelnemers: {{ $totaalJudokas }}</p>
            @endif
        </div>
    </div>

    <script>
        // Gewichtsklassen per leeftijdsklasse - gesorteerd oplopend (- eerst, dan +)
        const gewichtsklassenData = @json($gewichtsklassen);
        // Eliminatie gewichtsklassen per leeftijdsklasse
        const eliminatieGewichtsklassen = @json($eliminatieGewichtsklassen ?? []);

        // Sorteer gewichten correct: -20, -23, -26, +26 etc.
        function sortGewichten(gewichten) {
            return [...gewichten].sort((a, b) => {
                const aNum = parseFloat(a.replace('+', '').replace('-', ''));
                const bNum = parseFloat(b.replace('+', '').replace('-', ''));
                const aPlus = a.startsWith('+');
                const bPlus = b.startsWith('+');

                // + gewichten komen altijd na - gewichten
                if (aPlus && !bPlus) return 1;
                if (!aPlus && bPlus) return -1;

                // Anders sorteer op nummer
                return aNum - bNum;
            });
        }

        // Check of een gewichtsklasse eliminatie is
        function isEliminatieCategorie(leeftijdsklasseKey, gewichtsklasse) {
            if (!leeftijdsklasseKey || !gewichtsklasse) return false;
            const elimGewichten = eliminatieGewichtsklassen[leeftijdsklasseKey];
            return elimGewichten && elimGewichten.includes(gewichtsklasse);
        }

        // Bepaal leeftijdsklasse op basis van geboortejaar en geslacht
        // Gebruikt de preset config (gewichtsklassenData) met max_leeftijd per categorie
        function bepaalLeeftijdsklasse(geboortejaar, geslacht) {
            if (!geboortejaar || !geslacht) return null;

            const huidigJaar = new Date().getFullYear();
            const leeftijd = huidigJaar - parseInt(geboortejaar);

            // Loop door config categorieën en vind eerste match
            for (const [key, config] of Object.entries(gewichtsklassenData)) {
                const maxLeeftijd = config.max_leeftijd;
                const catGeslacht = config.geslacht || 'gemengd';

                // Check leeftijd: als max_leeftijd is ingesteld en leeftijd > max, skip
                if (maxLeeftijd && leeftijd >= maxLeeftijd) continue;

                // Check geslacht: als categorie specifiek geslacht heeft
                if (catGeslacht === 'M' && geslacht !== 'M') continue;
                if (catGeslacht === 'V' && geslacht !== 'V') continue;

                return key;
            }

            // Fallback: laatste categorie met passend geslacht
            const keys = Object.keys(gewichtsklassenData);
            return keys[keys.length - 1] || null;
        }

        // Bepaal gewichtsklasse op basis van gewicht
        function bepaalGewichtsklasse(gewicht, gewichtsopties) {
            if (!gewicht || !gewichtsopties.length) return '';

            const sorted = sortGewichten(gewichtsopties);
            for (const klasse of sorted) {
                const isPlusKlasse = klasse.startsWith('+');
                const limiet = parseFloat(klasse.replace('+', '').replace('-', ''));

                if (isPlusKlasse) {
                    return klasse; // Last option
                } else {
                    if (gewicht <= limiet) {
                        return klasse;
                    }
                }
            }
            return sorted[sorted.length - 1] || '';
        }

        // Alpine.js component voor nieuw judoka formulier
        function judokaForm() {
            return {
                open: false,
                geboortejaar: '',
                geslacht: '',
                gewicht: '',
                gewichtsklasse: '',
                leeftijdsklasse: '',
                leeftijdsklasseKey: '',
                gewichtsopties: [],
                isElim: false,

                updateLeeftijdsklasse() {
                    const klasse = bepaalLeeftijdsklasse(this.geboortejaar, this.geslacht);
                    this.leeftijdsklasseKey = klasse;
                    if (klasse && gewichtsklassenData[klasse]) {
                        this.leeftijdsklasse = gewichtsklassenData[klasse].label;
                        this.gewichtsopties = sortGewichten(gewichtsklassenData[klasse].gewichten);
                        // Update gewichtsklasse als gewicht is ingevuld
                        if (this.gewicht && !this.gewichtsklasse) {
                            this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                        }
                    } else {
                        this.leeftijdsklasse = '';
                        this.gewichtsopties = [];
                        this.gewichtsklasse = '';
                    }
                    this.updateIsElim();
                },

                updateGewichtsklasse() {
                    if (this.gewicht && this.gewichtsopties.length) {
                        this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                    }
                    this.updateIsElim();
                },

                updateIsElim() {
                    this.isElim = isEliminatieCategorie(this.leeftijdsklasseKey, this.gewichtsklasse);
                }
            }
        }

        // Alpine.js component voor bewerk formulier
        function judokaEditForm(geboortejaar, geslacht, gewichtsklasse, gewicht) {
            return {
                geboortejaar: geboortejaar,
                geslacht: geslacht,
                gewicht: gewicht,
                gewichtsklasse: gewichtsklasse,
                leeftijdsklasse: '',
                leeftijdsklasseKey: '',
                gewichtsopties: [],
                isElim: false,

                init() {
                    this.updateLeeftijdsklasse();
                },

                updateLeeftijdsklasse() {
                    const klasse = bepaalLeeftijdsklasse(this.geboortejaar, this.geslacht);
                    this.leeftijdsklasseKey = klasse;
                    if (klasse && gewichtsklassenData[klasse]) {
                        this.leeftijdsklasse = gewichtsklassenData[klasse].label;
                        this.gewichtsopties = sortGewichten(gewichtsklassenData[klasse].gewichten);
                        // Behoud gewichtsklasse als nog in opties, anders bepaal op basis van gewicht
                        if (!this.gewichtsopties.includes(this.gewichtsklasse) && this.gewicht) {
                            this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                        }
                    } else {
                        this.leeftijdsklasse = '';
                        this.gewichtsopties = [];
                    }
                    this.updateIsElim();
                },

                updateGewichtsklasse() {
                    if (this.gewicht && this.gewichtsopties.length) {
                        this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                    }
                    this.updateIsElim();
                },

                updateIsElim() {
                    this.isElim = isEliminatieCategorie(this.leeftijdsklasseKey, this.gewichtsklasse);
                }
            }
        }
    </script>
</body>
</html>
