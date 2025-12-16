<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - Coach Kaarten</title>
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
                <form action="{{ route('coach.portal.logout', $code) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-800">Uitloggen</button>
                </form>
            </div>

            <!-- Navigation tabs -->
            <div class="mt-4 flex space-x-4 border-t pt-4 overflow-x-auto">
                <a href="{{ route('coach.portal.judokas', $code) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Judoka's
                </a>
                <a href="{{ route('coach.portal.coachkaarten', $code) }}"
                   class="text-purple-600 font-medium border-b-2 border-purple-600 px-3 py-1 whitespace-nowrap">
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
            </div>
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

        <!-- Info -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-purple-600">{{ $aantalJudokas }}</p>
                    <p class="text-sm text-purple-700">Judoka's</p>
                </div>
                <div class="text-purple-400 text-2xl">÷</div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-purple-600">{{ $judokasPerCoach }}</p>
                    <p class="text-sm text-purple-700">Per coach</p>
                </div>
                <div class="text-purple-400 text-2xl">=</div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-purple-600">{{ $benodigdAantal }}</p>
                    <p class="text-sm text-purple-700">Coach kaarten</p>
                </div>
            </div>
            <p class="text-sm text-purple-600 mt-3">
                Elke coach kaart geeft toegang tot de dojo (judozaal). Deel de link met de begeleider zodat zij hun foto kunnen toevoegen.
            </p>
        </div>

        <!-- Coach Kaarten -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Coach Kaarten ({{ $coachKaarten->count() }})</h2>
            </div>

            @if($coachKaarten->count() > 0)
            <div class="divide-y">
                @foreach($coachKaarten as $index => $kaart)
                <div class="p-4" x-data="{ showForm: false, copied: false }">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Status indicator -->
                            <div class="w-12 h-12 rounded-full flex items-center justify-center
                                {{ $kaart->is_geactiveerd ? 'bg-green-100' : 'bg-gray-100' }}">
                                @if($kaart->is_geactiveerd && $kaart->foto)
                                <img src="{{ $kaart->getFotoUrl() }}" class="w-12 h-12 rounded-full object-cover">
                                @elseif($kaart->is_geactiveerd)
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                @else
                                <span class="text-gray-400 font-bold">{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <div>
                                <p class="font-medium text-gray-800">
                                    Kaart {{ $index + 1 }}
                                    @if($kaart->naam)
                                    - {{ $kaart->naam }}
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500">
                                    @if($kaart->is_geactiveerd)
                                    <span class="text-green-600">Geactiveerd {{ $kaart->geactiveerd_op?->format('d-m') }}</span>
                                    @if($kaart->is_gescand)
                                    · <span class="text-blue-600">Gescand {{ $kaart->gescand_op?->format('H:i') }}</span>
                                    @endif
                                    @else
                                    <span class="text-orange-600">Nog niet geactiveerd</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if(!$kaart->is_geactiveerd)
                            <button @click="showForm = !showForm" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm">
                                <span x-text="showForm ? 'Annuleer' : 'Naam toewijzen'"></span>
                            </button>
                            @endif

                            <button
                                @click="navigator.clipboard.writeText('{{ $kaart->getShowUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="px-3 py-1.5 rounded text-sm"
                                :class="copied ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'"
                            >
                                <span x-text="copied ? '✓ Gekopieerd' : 'Link kopiëren'"></span>
                            </button>

                            <a href="{{ $kaart->getShowUrl() }}" target="_blank"
                               class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                                Bekijk
                            </a>
                        </div>
                    </div>

                    <!-- Naam toewijzen form -->
                    <div x-show="showForm" x-collapse class="mt-4 pt-4 border-t">
                        <form action="{{ route('coach.portal.coachkaart.toewijzen', [$code, $kaart]) }}" method="POST">
                            @csrf
                            <p class="text-sm text-gray-600 mb-3">Wijs een naam toe aan deze kaart (optioneel - de begeleider kan dit ook zelf invullen bij activatie)</p>

                            @if($organisatieCoaches->count() > 0)
                            <div class="mb-3">
                                <label class="block text-gray-700 text-sm font-medium mb-1">Kies een organisatie coach:</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($organisatieCoaches as $orgCoach)
                                    <label class="flex items-center gap-2 px-3 py-2 border rounded cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="organisatie_coach_id" value="{{ $orgCoach->id }}" class="text-purple-600">
                                        <span>{{ $orgCoach->naam }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mb-3">— of —</p>
                            @endif

                            <div class="flex gap-2">
                                <input type="text" name="naam" placeholder="Voer een nieuwe naam in"
                                       class="flex-1 border rounded px-3 py-2">
                                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded">
                                    Opslaan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center text-gray-500">
                @if($aantalJudokas == 0)
                Voeg eerst judoka's toe om coach kaarten te ontvangen.
                @else
                Geen coach kaarten beschikbaar.
                @endif
            </div>
            @endif
        </div>

        <!-- Instructions -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-bold text-blue-800 mb-2">Hoe werkt het?</h3>
            <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                <li>Deel de link met elke begeleider die mee gaat naar het toernooi</li>
                <li>De begeleider opent de link en vult naam + pasfoto in</li>
                <li>Bij de ingang van de dojo wordt de QR-code gescand</li>
                <li>De foto wordt gecontroleerd - alleen met geldige kaart toegang</li>
            </ol>
        </div>
    </div>
</body>
</html>
