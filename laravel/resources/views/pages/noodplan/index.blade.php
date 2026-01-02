@extends('layouts.app')

@section('title', 'Case of Emergency')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Case of Emergency</h1>
            <p class="text-gray-600 mt-1 italic">Not in Vane - print your backup</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>Momentopname: <span class="font-mono">{{ now()->format('H:i:s') }}</span></p>
            <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
                &larr; Terug naar Dashboard
            </a>
        </div>
    </div>

    <!-- VOOR HET TOERNOOI -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            VOOR HET TOERNOOI (backup)
        </h2>

        <div class="space-y-4">
            <!-- Poules per blok -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Poules per blok</h3>
                    <p class="text-sm text-gray-500">Alle judoka's per poule</p>
                </div>
                <div class="flex gap-2">
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.poules', [$toernooi, $blok->nummer]) }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Blok {{ $blok->nummer }}
                    </a>
                    @endforeach
                    <a href="{{ route('toernooi.noodplan.poules', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                        Alle
                    </a>
                </div>
            </div>

            <!-- Overzichten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Overzichten</h3>
                    <p class="text-sm text-gray-500">Weeglijst, zaaloverzicht, instellingen, contactlijst</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.weeglijst', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Weeglijst
                    </a>
                    <a href="{{ route('toernooi.noodplan.zaaloverzicht', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Zaaloverzicht
                    </a>
                    <a href="{{ route('toernooi.noodplan.instellingen', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Instellingen
                    </a>
                    <a href="{{ route('toernooi.noodplan.contactlijst', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Contactlijst
                    </a>
                </div>
            </div>

            <!-- Poule Export -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Poule Export</h3>
                    <p class="text-sm text-gray-500">Alle poules per blok (1 tab per blok, gesorteerd op mat)</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.export-poules', [$toernooi, 'xlsx']) }}"
                       class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                        Excel (.xlsx)
                    </a>
                    <a href="{{ route('toernooi.noodplan.export-poules', [$toernooi, 'csv']) }}"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        CSV
                    </a>
                </div>
            </div>

            <!-- Weegkaarten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded" x-data="{ openWeeg: false, openCoach: false }">
                <div>
                    <h3 class="font-medium">Weegkaarten</h3>
                    <p class="text-sm text-gray-500">Per judoka (QR + gegevens)</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.weegkaarten', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                        Alle
                    </a>
                    <div class="relative">
                        <button @click="openWeeg = !openWeeg" type="button"
                                class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                            Per club ▼
                        </button>
                        <div x-show="openWeeg" @click.away="openWeeg = false" x-cloak
                             class="absolute right-0 mt-1 w-48 bg-white border rounded shadow-lg z-10 max-h-64 overflow-y-auto">
                            @foreach($clubs as $club)
                            <a href="{{ route('toernooi.noodplan.weegkaarten.club', [$toernooi, $club]) }}" target="_blank"
                               class="block px-4 py-2 text-sm hover:bg-gray-100">
                                {{ $club->naam }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coachkaarten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded" x-data="{ open: false }">
                <div>
                    <h3 class="font-medium">Coachkaarten</h3>
                    <p class="text-sm text-gray-500">Toegang dojo</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.coachkaarten', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                        Alle
                    </a>
                    <div class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                            Per club ▼
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-1 w-48 bg-white border rounded shadow-lg z-10 max-h-64 overflow-y-auto">
                            @foreach($clubs as $club)
                            <a href="{{ route('toernooi.noodplan.coachkaarten.club', [$toernooi, $club]) }}" target="_blank"
                               class="block px-4 py-2 text-sm hover:bg-gray-100">
                                {{ $club->naam }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lege templates -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Lege wedstrijdschema's</h3>
                    <p class="text-sm text-gray-500">Handmatig invullen bij uitval</p>
                </div>
                <div class="flex gap-2">
                    @for($i = 2; $i <= 7; $i++)
                    <a href="{{ route('toernooi.noodplan.leeg-schema', [$toernooi, $i]) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        {{ $i }}
                    </a>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <!-- TIJDENS DE WEDSTRIJD -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">🔴</span>
            TIJDENS DE WEDSTRIJD (live)
        </h2>

        <div class="space-y-4">
            <!-- Gecorrigeerde poules -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Gecorrigeerde poules</h3>
                    <p class="text-sm text-gray-500">Na overpoulen - huidige stand</p>
                </div>
                <div class="flex gap-2">
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.poules', [$toernooi, $blok->nummer]) }}" target="_blank"
                       class="px-3 py-2 bg-orange-600 text-white rounded text-sm hover:bg-orange-700">
                        Blok {{ $blok->nummer }}
                    </a>
                    @endforeach
                    <a href="{{ route('toernooi.noodplan.poules', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                        Alle
                    </a>
                </div>
            </div>

            <!-- Aangepast zaaloverzicht -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Aangepast zaaloverzicht</h3>
                    <p class="text-sm text-gray-500">Na overpoulen/matverdeling</p>
                </div>
                <div>
                    <a href="{{ route('toernooi.noodplan.zaaloverzicht', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-orange-600 text-white rounded text-sm hover:bg-orange-700">
                        Print
                    </a>
                </div>
            </div>

            <!-- Ingevulde wedstrijdschema's -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Ingevulde wedstrijdschema's</h3>
                    <p class="text-sm text-gray-500">Met scores - per blok</p>
                </div>
                <div class="flex gap-2">
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.wedstrijdschemas', [$toernooi, $blok->nummer]) }}" target="_blank"
                       class="px-3 py-2 bg-orange-600 text-white rounded text-sm hover:bg-orange-700">
                        Blok {{ $blok->nummer }}
                    </a>
                    @endforeach
                    <a href="{{ route('toernooi.noodplan.wedstrijdschemas', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                        Alle
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTIEVE POULES -->
    @if($actievePoules->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">⚡</span>
            ACTIEVE POULES (klik voor huidige staat)
        </h2>

        <div class="space-y-2">
            @foreach($actievePoules as $poule)
            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded">
                <div>
                    <span class="font-bold text-yellow-800">Mat {{ $poule->mat_nummer }}</span>
                    <span class="text-gray-600 ml-2">
                        {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                        @if($poule->naam)
                        - {{ $poule->naam }}
                        @endif
                    </span>
                    <span class="text-sm text-gray-500 ml-2">({{ $poule->judokas->count() }} judoka's)</span>
                </div>
                <a href="{{ route('toernooi.noodplan.poule-schema', [$toernooi, $poule]) }}" target="_blank"
                   class="px-3 py-2 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">
                    Print huidige staat
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="bg-gray-100 rounded-lg p-6 text-center text-gray-500">
        <p>Geen actieve poules op dit moment</p>
    </div>
    @endif

    <!-- Info box -->
    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
        <h3 class="font-bold text-blue-800 mb-2">Tip voor noodgevallen</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• Print de backup documenten <strong>voor</strong> het toernooi begint</li>
            <li>• Bewaar prints bij de hoofdjury tafel</li>
            <li>• Lege wedstrijdschema's: vul handmatig in bij stroomuitval</li>
            <li>• Contactlijst: bel coaches bij problemen</li>
        </ul>
    </div>
</div>
@endsection
