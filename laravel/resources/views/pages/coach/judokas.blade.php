<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - Judoka's</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                        <p x-show="leeftijdsklasse" class="text-xs text-blue-600 mt-1" x-text="'Leeftijdsklasse: ' + leeftijdsklasse"></p>
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
        @endphp

        @if($onvolledigeJudokas->count() > 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <span class="text-yellow-600 text-xl mr-2">⚠</span>
                <div>
                    <p class="font-medium text-yellow-800">{{ $onvolledigeJudokas->count() }} judoka('s) onvolledig</p>
                    <p class="text-sm text-yellow-700">Onvolledige judoka's worden niet doorgestuurd naar de organisator. Vul de ontbrekende gegevens aan.</p>
                </div>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Mijn Judoka's ({{ $volledigeJudokas->count() }} volledig, {{ $onvolledigeJudokas->count() }} onvolledig)</h2>
            </div>

            @if($judokas->count() > 0)
            <div class="divide-y">
                @foreach($judokas as $judoka)
                @php
                    $isOnvolledig = !$judoka->isVolledig();
                    $ontbrekend = $judoka->getOntbrekendeVelden();
                @endphp
                <div class="p-4 hover:bg-gray-50 {{ $isOnvolledig ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}" x-data="{ editing: false }">
                    <!-- View mode -->
                    <div x-show="!editing" class="flex justify-between items-center">
                        <div>
                            <p class="font-medium {{ $isOnvolledig ? 'text-yellow-800' : 'text-gray-800' }}">
                                {{ $judoka->naam }}
                                @if($isOnvolledig)
                                <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded ml-2">Onvolledig</span>
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
                            </p>
                            @if($judoka->leeftijdsklasse)
                            <p class="text-xs text-gray-500 mt-1">{{ $judoka->leeftijdsklasse }}</p>
                            @endif
                            @if($isOnvolledig)
                            <p class="text-xs text-yellow-700 mt-1">Ontbreekt: {{ implode(', ', $ontbrekend) }}</p>
                            @endif
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
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
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
                            </div>
                            <p x-show="leeftijdsklasse" class="text-xs text-blue-600 mt-1" x-text="'Leeftijdsklasse: ' + leeftijdsklasse"></p>
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

        // Bepaal leeftijdsklasse op basis van geboortejaar en geslacht
        // Let op: -15 = max 14 jaar, -12 = max 11 jaar, etc.
        function bepaalLeeftijdsklasse(geboortejaar, geslacht) {
            if (!geboortejaar || !geslacht) return null;

            const huidigJaar = new Date().getFullYear();
            const leeftijd = huidigJaar - parseInt(geboortejaar);

            // Mini's: t/m 7 jaar (< 8)
            if (leeftijd <= 7) return 'minis';
            // A-pupillen: 8-9 jaar (< 10)
            if (leeftijd <= 9) return 'a_pupillen';
            // B-pupillen: 10-11 jaar (< 12)
            if (leeftijd <= 11) return 'b_pupillen';
            // -15: 12-14 jaar (< 15)
            if (leeftijd <= 14) return geslacht === 'V' ? 'dames_15' : 'heren_15';
            // -18: 15-17 jaar (< 18)
            if (leeftijd <= 17) return geslacht === 'V' ? 'dames_18' : 'heren_18';
            // Senioren: 18+
            return geslacht === 'V' ? 'dames' : 'heren';
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
                gewichtsopties: [],

                updateLeeftijdsklasse() {
                    const klasse = bepaalLeeftijdsklasse(this.geboortejaar, this.geslacht);
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
                },

                updateGewichtsklasse() {
                    if (this.gewicht && this.gewichtsopties.length) {
                        this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                    }
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
                gewichtsopties: [],

                init() {
                    this.updateLeeftijdsklasse();
                },

                updateLeeftijdsklasse() {
                    const klasse = bepaalLeeftijdsklasse(this.geboortejaar, this.geslacht);
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
                },

                updateGewichtsklasse() {
                    if (this.gewicht && this.gewichtsopties.length) {
                        this.gewichtsklasse = bepaalGewichtsklasse(parseFloat(this.gewicht), this.gewichtsopties);
                    }
                }
            }
        }
    </script>
</body>
</html>
