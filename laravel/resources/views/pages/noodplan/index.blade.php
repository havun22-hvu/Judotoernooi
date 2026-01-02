@extends('layouts.app')

@section('title', 'Case of Emergency')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Case of Emergency</h1>
            <p class="text-gray-600 mt-1 italic">Not in Vane - export your backup</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>Momentopname: <span class="font-mono">{{ now()->format('H:i:s') }}</span></p>
            <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
                &larr; Terug naar Dashboard
            </a>
        </div>
    </div>

    <!-- POULE EXPORT -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            POULE EXPORT (backup)
        </h2>

        <div class="space-y-4">
            <!-- Poule Export -->
            <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded">
                <div>
                    <h3 class="font-medium text-green-800">Volledige poule-indeling</h3>
                    <p class="text-sm text-green-600">1 tab per blok, gesorteerd op mat, met leeftijds-/gewichtsklasse en poule nummers</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.export-poules', [$toernooi, 'xlsx']) }}"
                       class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium">
                        Excel (.xlsx)
                    </a>
                    <a href="{{ route('toernooi.noodplan.export-poules', [$toernooi, 'csv']) }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                        CSV
                    </a>
                </div>
            </div>

            <!-- Weegkaarten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded" x-data="{ open: false }">
                <div>
                    <h3 class="font-medium">Weegkaarten</h3>
                    <p class="text-sm text-gray-500">Per judoka (QR + gegevens)</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.weegkaarten', $toernooi) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        Alle
                    </a>
                    <div class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                            Per club ▼
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
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
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        Alle
                    </a>
                    <div class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
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

            <!-- Contactlijst -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Contactlijst</h3>
                    <p class="text-sm text-gray-500">Coach contactgegevens per club</p>
                </div>
                <a href="{{ route('toernooi.noodplan.contactlijst', $toernooi) }}" target="_blank"
                   class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    Bekijken
                </a>
            </div>

            <!-- Lege wedstrijdschema's -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Lege wedstrijdschema's</h3>
                    <p class="text-sm text-gray-500">Handmatig invullen bij uitval</p>
                </div>
                <div class="flex gap-2">
                    @for($i = 2; $i <= 7; $i++)
                    <a href="{{ route('toernooi.noodplan.leeg-schema', [$toernooi, $i]) }}" target="_blank"
                       class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                        {{ $i }}
                    </a>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <!-- Info box -->
    <div class="p-4 bg-blue-50 rounded-lg">
        <h3 class="font-bold text-blue-800 mb-2">Tip voor noodgevallen</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• Download de Excel backup <strong>voor</strong> het toernooi begint</li>
            <li>• Open in Google Sheets of Excel op je telefoon/tablet</li>
            <li>• Lege wedstrijdschema's: vul handmatig in bij stroomuitval</li>
            <li>• Contactlijst: bel coaches bij problemen</li>
        </ul>
    </div>
</div>
@endsection
