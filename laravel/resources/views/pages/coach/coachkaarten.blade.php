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

        <!-- Info box -->
        <div class="bg-purple-50 border border-purple-200 text-purple-800 px-4 py-3 rounded mb-6">
            <p class="font-medium">Coach kaarten voor je begeleiders</p>
            <p class="text-sm mt-1">Elke coach kaart geeft toegang tot de dojo (judozaal). Deel de link met de begeleider zodat zij hun foto kunnen toevoegen.</p>
        </div>

        @if(!($blokkenIngedeeld ?? false) && $aantalJudokas > 0)
        <!-- Warning: blokken nog niet ingedeeld -->
        <div class="bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded mb-6">
            <p class="font-medium">Let op: voorlopig aantal</p>
            <p class="text-sm mt-1">Het definitieve aantal coachkaarten wordt bepaald na de poule-indeling. Dit aantal kan nog wijzigen.</p>
        </div>
        @endif

        <!-- Check-in legenda -->
        @if($toernooi->coach_incheck_actief)
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded mb-6">
            <p class="font-medium">Check-in systeem actief</p>
            <div class="flex gap-4 mt-2 text-sm">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 bg-green-500 rounded-full"></span> Ingecheckt
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Uitgecheckt
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 bg-gray-300 rounded-full"></span> Niet geactiveerd
                </span>
            </div>
        </div>
        @endif

        <!-- Coach Kaarten Grid -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Coach Kaarten ({{ $coachKaarten->count() }})</h2>
            </div>

            @if($coachKaarten->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                @foreach($coachKaarten as $index => $kaart)
                <div class="border rounded-lg overflow-hidden bg-white shadow-sm"
                     x-data="{ showHistory: false, copied: false }">
                    <!-- Card Header with status indicator -->
                    <div class="px-4 py-2 bg-gray-50 border-b flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Kaart #{{ $index + 1 }}</span>
                        @if($toernooi->coach_incheck_actief)
                            @if($kaart->isIngecheckt())
                            <span class="flex items-center gap-1 text-sm text-green-600">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                In ({{ $kaart->ingecheckt_op->format('H:i') }})
                            </span>
                            @elseif($kaart->is_geactiveerd)
                            <span class="flex items-center gap-1 text-sm text-orange-600">
                                <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                                Uit
                            </span>
                            @else
                            <span class="flex items-center gap-1 text-sm text-gray-400">
                                <span class="w-2 h-2 bg-gray-300 rounded-full"></span>
                                Ongebruikt
                            </span>
                            @endif
                        @endif
                    </div>

                    <!-- Card Body -->
                    <div class="p-4">
                        <div class="flex gap-4">
                            <!-- Photo or placeholder -->
                            <div class="w-20 h-24 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden">
                                @if($kaart->foto)
                                <img src="{{ $kaart->getFotoUrl() }}" alt="{{ $kaart->naam }}"
                                     class="w-full h-full object-cover">
                                @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                @endif
                            </div>

                            <!-- Info -->
                            <div class="flex-grow">
                                <p class="font-bold text-gray-800 text-lg">
                                    @if($kaart->naam)
                                    {{ $kaart->naam }}
                                    @else
                                    <span class="text-gray-400 italic font-normal">Nog niet ingevuld</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-600">{{ $club->naam }}</p>

                                <p class="text-sm mt-2">
                                    @if($kaart->is_geactiveerd)
                                    <span class="text-green-600">Geactiveerd {{ $kaart->geactiveerd_op?->format('d-m H:i') }}</span>
                                    @else
                                    <span class="text-orange-600">Nog niet geactiveerd</span>
                                    @endif
                                </p>

                                <p class="text-sm font-mono mt-1">
                                    <span class="text-gray-500">PIN:</span>
                                    <span class="font-bold text-purple-700 tracking-wider">{{ $kaart->pincode }}</span>
                                </p>

                                <!-- Overdracht count -->
                                @if($kaart->wisselingen->count() > 1)
                                <p class="text-xs text-blue-600 mt-1">
                                    {{ $kaart->wisselingen->count() }} coaches hebben deze kaart gebruikt
                                </p>
                                @endif
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t">
                            @if($kaart->wisselingen->count() > 0)
                            <button @click="showHistory = !showHistory"
                                    class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-sm">
                                Geschiedenis
                            </button>
                            @endif

                            <button
                                @click="navigator.clipboard.writeText('Coach kaart {{ $club->naam }}\nLink: {{ $kaart->getShowUrl() }}\nPIN: {{ $kaart->pincode }}'); copied = true; setTimeout(() => copied = false, 2000)"
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

                        <!-- History panel -->
                        <div x-show="showHistory" x-collapse class="mt-4 pt-4 border-t">
                            <h4 class="font-medium text-gray-700 mb-3">Geschiedenis van deze kaart</h4>

                            @foreach($kaart->wisselingen as $wisseling)
                            <div class="flex gap-3 mb-3 pb-3 @if(!$loop->last) border-b @endif">
                                <!-- Small photo -->
                                <div class="w-12 h-14 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                                    @if($wisseling->foto)
                                    <img src="{{ asset('storage/' . $wisseling->foto) }}"
                                         alt="{{ $wisseling->naam }}"
                                         class="w-full h-full object-cover">
                                    @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    @endif
                                </div>

                                <div class="flex-grow">
                                    <p class="font-medium text-gray-800">{{ $wisseling->naam }}</p>
                                    <p class="text-xs text-gray-500">{{ $wisseling->device_info ?? 'Onbekend device' }}</p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Geactiveerd: {{ $wisseling->geactiveerd_op?->format('d-m H:i') }}
                                        @if($wisseling->overgedragen_op)
                                        <br>Overgedragen: {{ $wisseling->overgedragen_op->format('d-m H:i') }}
                                        @else
                                        <span class="text-green-600 font-medium"> (huidige)</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @endforeach

                            <!-- Check-in history for today -->
                            @if($kaart->checkinsVandaag->count() > 0)
                            <div class="mt-3 pt-3 border-t">
                                <h5 class="text-sm font-medium text-gray-600 mb-2">Check-ins vandaag</h5>
                                @foreach($kaart->checkinsVandaag as $checkin)
                                <div class="flex items-center gap-2 text-sm py-1">
                                    @if($checkin->isIn())
                                    <span class="text-green-600">▶</span>
                                    <span>In om {{ $checkin->created_at->format('H:i') }}</span>
                                    @elseif($checkin->isGeforceerd())
                                    <span class="text-red-600">⏹</span>
                                    <span>Geforceerd uit om {{ $checkin->created_at->format('H:i') }}</span>
                                    @else
                                    <span class="text-orange-600">◀</span>
                                    <span>Uit om {{ $checkin->created_at->format('H:i') }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
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
