@extends('layouts.app')

@section('title', __('Case of Emergency'))

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🆘 {{ __('Noodplan') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Exports, backups en prints voor als het mis gaat') }}</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>{{ __('Momentopname') }}: <span class="font-mono">{{ now()->format('H:i:s') }}</span></p>
            <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
                &larr; {{ __('Terug naar Dashboard') }}
            </a>
        </div>
    </div>

    <!-- Uitleg -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
        <h2 class="font-bold text-amber-800 mb-2">{{ __('Wat is dit?') }}</h2>
        <p class="text-sm text-amber-700 mb-2">
            {{ __('Dit is je vangnet bij stroomuitval, servercrash of internetstoring. Download vóór het toernooi begint de belangrijkste exports en bewaar ze op een USB-stick of laptop.') }}
        </p>
        <p class="text-sm text-amber-700">
            <strong>{{ __('Tip') }}:</strong> {{ __('De browser slaat automatisch uitslagen op in localStorage. Als de server crasht maar je laptop nog werkt, kun je de "Live wedstrijd schema\'s" nog steeds printen met alle scores.') }}
        </p>
    </div>

    <!-- OFFLINE MODUS BANNER -->
    <div x-data="offlineDetector()" x-init="init()" x-show="isOffline" x-cloak
         class="bg-orange-100 border-l-4 border-orange-500 p-4 mb-6 rounded">
        <div class="flex items-center">
            <span class="text-2xl mr-3">⚠️</span>
            <div>
                <h3 class="font-bold text-orange-800">{{ __('Offline Modus') }}</h3>
                <p class="text-orange-700 text-sm">{{ __('Server niet bereikbaar. Je kunt nog steeds printen vanuit de lokale backup (localStorage).') }}</p>
            </div>
        </div>
    </div>

    <script>
        function offlineDetector() {
            return {
                isOffline: false,
                init() {
                    this.checkConnection();
                    setInterval(() => this.checkConnection(), 10000);
                    window.addEventListener('online', () => this.isOffline = false);
                    window.addEventListener('offline', () => this.isOffline = true);
                },
                async checkConnection() {
                    try {
                        const response = await fetch('{{ route("toernooi.noodplan.sync-data", $toernooi->routeParams()) }}', {
                            method: 'HEAD',
                            cache: 'no-store'
                        });
                        this.isOffline = !response.ok;
                    } catch (e) {
                        this.isOffline = true;
                    }
                }
            };
        }
    </script>

    <!-- OFFLINE PAKKET -->
    @if(!$isFreeTier)
    <div class="bg-indigo-50 border-2 border-indigo-300 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-indigo-800 flex items-center gap-2">
                    <span>📦</span> {{ __('OFFLINE PAKKET') }}
                </h2>
                <p class="text-sm text-indigo-600 mt-1">
                    {{ __('Download een standalone HTML bestand met alle toernooi data. Werkt zonder internet - dubbelklik om te openen in een browser.') }}
                </p>
                <ul class="mt-2 text-sm text-indigo-500 list-disc list-inside">
                    <li>{{ __('Weeglijst, zaaloverzicht, wedstrijdschema\'s') }}</li>
                    <li>{{ __('Score invoer (opslaat lokaal in browser)') }}</li>
                    <li>{{ __('Upload resultaten terug naar server als weer online') }}</li>
                </ul>
            </div>
            <a href="{{ route('toernooi.noodplan.offline-pakket', $toernooi->routeParams()) }}"
               class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-lg whitespace-nowrap ml-4">
                {{ __('Download (.html)') }}
            </a>
        </div>
    </div>
    @else
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6 opacity-75">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-500 flex items-center gap-2">
                    <span>🔒</span> {{ __('OFFLINE PAKKET') }}
                    <span class="text-sm font-normal text-gray-400">- {{ __('Premium') }}</span>
                </h2>
                <p class="text-sm text-gray-400 mt-1">{{ __('Beschikbaar met een betaald abonnement.') }}</p>
            </div>
            <a href="{{ route('toernooi.upgrade', $toernooi->routeParams()) }}"
               class="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500 font-medium whitespace-nowrap ml-4">
                {{ __('Upgrade') }}
            </a>
        </div>
    </div>
    @endif

    <!-- POULE EXPORT -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            {{ __('POULE EXPORT (backup)') }}
        </h2>

        <div class="space-y-4">
            <!-- Poule Export -->
            <div class="p-4 bg-green-50 border border-green-200 rounded">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-green-800">{{ __('Volledige poule-indeling') }}</h3>
                    <div class="flex gap-2">
                        <a href="{{ route('toernooi.noodplan.export-poules', $toernooi->routeParamsWith(['format' => 'xlsx'])) }}"
                           class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium">
                            Excel
                        </a>
                        <a href="{{ route('toernooi.noodplan.export-poules', $toernooi->routeParamsWith(['format' => 'csv'])) }}"
                           class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                            CSV
                        </a>
                    </div>
                </div>
                <ul class="mt-2 text-sm text-green-600 list-disc list-inside">
                    <li>{{ __('Per blok een tab') }}</li>
                    <li>{{ __('Gesorteerd op mat') }}</li>
                    <li>{{ __('Met leeftijds-/gewichtsklasse') }}</li>
                </ul>
            </div>

            <!-- JSON Download voor offline gebruik -->
            <div class="p-4 bg-purple-50 border border-purple-200 rounded" x-data="jsonDownloader()">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-purple-800">{{ __('Offline Backup (JSON)') }}</h3>
                        <p class="text-sm text-purple-600">{{ __('Voor lokale server bij internetstoring - laad in via "Laad JSON backup" hieronder') }}</p>
                    </div>
                    <button @click="download()" type="button"
                            class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 font-medium">
                        {{ __('Download backup') }}
                    </button>
                </div>
            </div>

            <script>
                function jsonDownloader() {
                    return {
                        toernooiId: {{ $toernooi->id }},
                        toernooiNaam: '{{ $toernooi->slug }}',

                        async download() {
                            try {
                                // Probeer eerst van server
                                const response = await fetch('{{ route("toernooi.noodplan.sync-data", $toernooi->routeParams()) }}');
                                if (!response.ok) throw new Error('Server error');
                                const data = await response.json();
                                this.saveAsFile(data);
                            } catch (e) {
                                // Fallback naar localStorage
                                const storageKey = `noodplan_${this.toernooiId}_poules`;
                                const data = localStorage.getItem(storageKey);
                                if (data) {
                                    this.saveAsFile(JSON.parse(data));
                                } else {
                                    alert('Geen data beschikbaar (server offline en geen lokale cache).');
                                }
                            }
                        },

                        saveAsFile(data) {
                            const timestamp = new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-');
                            const filename = `backup_${this.toernooiNaam}_${timestamp}.json`;
                            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                        }
                    };
                }
            </script>

            <!-- Weeglijsten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">{{ __('Weeglijsten') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Alfabetisch per blok, met invulvak') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.weeglijst', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        {{ __('Alle') }}
                    </a>
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.weeglijst', $toernooi->routeParamsWith(['blok' => $blok->nummer])) }}" target="_blank"
                       class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                        {{ $blok->nummer }}
                    </a>
                    @endforeach
                </div>
            </div>

            <!-- Weegkaarten -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded" x-data="{ open: false }">
                <div>
                    <h3 class="font-medium">{{ __('Weegkaarten') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Per judoka (QR + gegevens)') }}</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.weegkaarten', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        {{ __('Alle') }}
                    </a>
                    <div class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                            {{ __('Per club') }} ▼
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-1 w-48 bg-white border rounded shadow-lg z-10 max-h-64 overflow-y-auto">
                            @foreach($clubs as $club)
                            <a href="{{ route('toernooi.noodplan.weegkaarten.club', $toernooi->routeParamsWith(['club' => $club])) }}" target="_blank"
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
                    <h3 class="font-medium">{{ __('Coachkaarten') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Toegang dojo') }}</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.coachkaarten', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        {{ __('Alle') }}
                    </a>
                    <div class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                            {{ __('Per club') }} ▼
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-1 w-48 bg-white border rounded shadow-lg z-10 max-h-64 overflow-y-auto">
                            @foreach($clubs as $club)
                            <a href="{{ route('toernooi.noodplan.coachkaarten.club', $toernooi->routeParamsWith(['club' => $club])) }}" target="_blank"
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
                    <h3 class="font-medium">{{ __('Contactlijst') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Coach contactgegevens per club') }}</p>
                </div>
                <a href="{{ route('toernooi.noodplan.contactlijst', $toernooi->routeParams()) }}" target="_blank"
                   class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    {{ __('Bekijken') }}
                </a>
            </div>

            <!-- Lege wedstrijdschema's -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">{{ __('Lege wedstrijdschema\'s') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Handmatig invullen') }}</p>
                </div>
                <div class="flex gap-2">
                    @for($i = 2; $i <= 7; $i++)
                    <a href="{{ route('toernooi.noodplan.leeg-schema', $toernooi->routeParamsWith(['aantal' => $i])) }}" target="_blank"
                       class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                        {{ $i }}
                    </a>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <!-- POULES PRINTEN (VOORBEREIDING) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            {{ __('POULES PRINTEN (voorbereiding)') }}
        </h2>
        <p class="text-sm text-gray-600 mb-4">{{ __('Print poule-overzichten per blok/mat vóór het toernooi begint. Handig om uit te delen aan tafelofficiëls.') }}</p>

        <div class="space-y-4">
            @foreach($blokken as $blok)
            <div class="p-3 bg-blue-50 border border-blue-200 rounded">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-blue-800">Blok {{ $blok->nummer }}</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                    @php
                        $matten = $blok->poules->pluck('mat_nummer')->unique()->sort();
                    @endphp
                    @foreach($matten as $matNummer)
                    <a href="{{ route('toernooi.poule.index', $toernooi->routeParams()) }}?blok={{ $blok->nummer }}&mat={{ $matNummer }}" target="_blank"
                       class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Mat {{ $matNummer }}
                    </a>
                    @endforeach
                    <a href="{{ route('toernooi.poule.index', $toernooi->routeParams()) }}?blok={{ $blok->nummer }}" target="_blank"
                       class="px-3 py-2 bg-blue-800 text-white rounded text-sm hover:bg-blue-900">
                        {{ __('Alle matten') }}
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- TIJDENS DE WEDSTRIJD -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="liveBackup()" x-init="init()">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">🏆</span>
            {{ __('TIJDENS DE WEDSTRIJD') }}
            <span x-show="syncStatus === 'connected'" class="ml-3 inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                <span class="w-2 h-2 rounded-full bg-green-500 mr-1 animate-pulse"></span>
                Live
            </span>
            <span x-show="syncStatus === 'disconnected'" class="ml-3 inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                <span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span>
                {{ __('Backup modus') }}
            </span>
        </h2>

        <div class="space-y-4">
            <!-- Status info + laden van JSON backup -->
            <div class="p-3 rounded text-sm flex items-center justify-between" :class="syncStatus === 'connected' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-orange-50 border border-orange-200 text-orange-700'">
                <div>
                    <span x-text="uitslagCount"></span> {{ __('uitslagen in backup') }} | {{ __('Laatste sync') }}: <span x-text="laatsteSync || '{{ __('Nog geen data') }}'"></span>
                </div>
                <div class="flex gap-2">
                    <label class="px-3 py-1 bg-white border rounded text-xs cursor-pointer hover:bg-gray-50">
                        📁 {{ __('Laad JSON backup') }}
                        <input type="file" accept=".json" @change="loadJsonBackup($event)" class="hidden">
                    </label>
                </div>
            </div>

            <!-- Ingevulde schema's (matrix) - judoka's ingevuld, uitslagen leeg -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">{{ __('Ingevulde schema\'s (matrix)') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Judoka\'s ingevuld, uitslagen leeg - voor handmatig invullen') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.ingevuld-schemas', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        {{ __('Alle') }}
                    </a>
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.ingevuld-schemas', $toernooi->routeParamsWith(['blok' => $blok->nummer])) }}" target="_blank"
                       class="px-3 py-2 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                        {{ $blok->nummer }}
                    </a>
                    @endforeach
                </div>
            </div>

            <!-- Live wedstrijd schema's - met uitslagen -->
            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded">
                <div>
                    <h3 class="font-medium text-yellow-800">{{ __('Live wedstrijd schema\'s') }}</h3>
                    <p class="text-sm text-yellow-600">{{ __('Met alle al gespeelde wedstrijden + punten') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.live-schemas', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">
                        {{ __('Alle') }}
                    </a>
                    @foreach($blokken as $blok)
                    <a href="{{ route('toernooi.noodplan.live-schemas', $toernooi->routeParamsWith(['blok' => $blok->nummer])) }}" target="_blank"
                       class="px-3 py-2 bg-yellow-500 text-white rounded text-sm hover:bg-yellow-600">
                        {{ $blok->nummer }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        function liveBackup() {
            return {
                syncStatus: 'disconnected',
                uitslagCount: 0,
                laatsteSync: null,
                hasData: false,
                toernooiId: {{ $toernooi->id }},

                init() {
                    this.loadFromStorage();
                    setInterval(() => this.loadFromStorage(), 1000);
                },

                loadFromStorage() {
                    const storageKey = `noodplan_${this.toernooiId}_poules`;
                    const syncKey = `noodplan_${this.toernooiId}_laatste_sync`;
                    const countKey = `noodplan_${this.toernooiId}_count`;

                    const data = localStorage.getItem(storageKey);
                    this.hasData = !!data;

                    const count = localStorage.getItem(countKey);
                    this.uitslagCount = count ? parseInt(count) : 0;

                    const sync = localStorage.getItem(syncKey);
                    if (sync) {
                        const date = new Date(sync);
                        this.laatsteSync = date.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
                    }

                    if (sync) {
                        const syncDate = new Date(sync);
                        const now = new Date();
                        const diffMs = now - syncDate;
                        this.syncStatus = diffMs < 120000 ? 'connected' : 'disconnected';
                    } else {
                        this.syncStatus = 'disconnected';
                    }
                },

                printLive(blokNummer) {
                    const storageKey = `noodplan_${this.toernooiId}_poules`;
                    const data = localStorage.getItem(storageKey);
                    if (!data) {
                        alert('Geen backup data beschikbaar. Open eerst de mat interface om data te synchroniseren.');
                        return;
                    }

                    const parsed = JSON.parse(data);
                    // Filter by blok if specified
                    if (blokNummer !== null && parsed.poules) {
                        parsed.poules = parsed.poules.filter(p => p.blok_nummer == blokNummer);
                    }
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(this.generateLiveHTML(parsed, blokNummer));
                    printWindow.document.close();
                },

                generateLiveHTML(data, blokNummer) {
                    const timestamp = new Date().toLocaleString('nl-NL');
                    const blokLabel = blokNummer ? ` - Blok ${blokNummer}` : '';
                    const totalPoules = data.poules ? data.poules.length : 0;
                    let html = `<!DOCTYPE html>
<html><head>
<title>Live Wedstrijd Schema's${blokLabel} - ${timestamp}</title>
<style>
    @media print {
        .no-print { display: none !important; }
        .poule-page { page-break-after: always; }
        .poule-page:last-of-type { page-break-after: avoid; }
        .poule-page.print-exclude { display: none !important; }
        /* Force colors to print */
        .schema-table, .schema-table * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        .header-row, .header-row th, .header-row th div, .header-row th * {
            background: #1f2937 !important;
            color: white !important;
        }
        .header-row .sub-header { color: #9ca3af !important; }
        .score-cel.inactief { background: #1f2937 !important; }
        .totaal-cel { background: #f3f4f6 !important; color: #000 !important; }
        .plts-cel { background: #fef9c3 !important; color: #000 !important; }
        .gespeeld { background: #d1fae5 !important; }
        .poule-page.landscape { page: landscape; }
        .title-row td { background: #1f2937 !important; color: white !important; }
        .info-row td { background: #f3f4f6 !important; }
    }
    @page { size: A4 portrait; margin: 0.5cm; }
    @page landscape { size: A4 landscape; margin: 0.5cm; }
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 10px; }
    .print-toolbar { padding: 12px 16px; background: #fef3c7; margin-bottom: 15px; border-radius: 8px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .toolbar-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .toolbar-controls { display: flex; align-items: center; gap: 15px; }
    .title-row td { background: #1f2937; color: white; padding: 6px 12px; font-size: 11px; border: none; }
    .info-row td { background: #f3f4f6; padding: 6px 12px; font-size: 12px; border: none; border-bottom: 2px solid #333; }
    .poule-checkbox { display: flex; align-items: center; gap: 8px; }
    .poule-checkbox input { width: 18px; height: 18px; cursor: pointer; }
    .schema-table { width: auto; border-collapse: collapse; }
    .schema-table th, .schema-table td { border: 1px solid #333; }
    .header-row { background: #1f2937; color: white; }
    .header-row th { border-color: #374151; font-size: 11px; padding: 4px 2px; }
    .sub-header { font-size: 9px; font-weight: normal; color: #9ca3af; }
    .nr-cel { width: 28px; font-size: 13px; text-align: center; }
    .naam-cel { font-size: 12px; padding: 4px 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .score-cel { width: 22px; text-align: center; font-size: 12px; height: 36px; }
    .score-cel.inactief { background: #1f2937; }
    .score-cel.w-cel { border-right: 1px solid #ccc; }
    .score-cel.j-cel { border-left: none; border-right: 2px solid #333; }
    .totaal-cel { width: 30px; background: #f3f4f6; text-align: center; font-size: 12px; font-weight: bold; }
    .plts-cel { width: 30px; background: #fef9c3; text-align: center; font-size: 12px; }
    .gespeeld { background: #d1fae5; }
    .poule-page.print-exclude { opacity: 0.5; }
    .poule-page { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px dashed #ccc; }
    .poule-page.landscape { page: landscape; }
</style>
</head><body>
<div class="print-toolbar no-print">
    <div class="toolbar-row">
        <div class="toolbar-controls">
            <strong>LIVE BACKUP${blokLabel} - ${timestamp}</strong>
            <span style="color:#666">|</span>
            <button onclick="selectAll(true)" style="background:none;border:none;color:#2563eb;cursor:pointer;font-size:13px;">Alles aan</button>
            <button onclick="selectAll(false)" style="background:none;border:none;color:#666;cursor:pointer;font-size:13px;">Alles uit</button>
            <span id="print-counter" style="color:#666;font-size:13px;">${totalPoules} van ${totalPoules} geselecteerd</span>
        </div>
        <button onclick="window.print()" style="padding: 8px 16px; background: #ca8a04; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Print geselecteerde</button>
    </div>
</div>
<scr` + `ipt>
function selectAll(checked) {
    document.querySelectorAll('.poule-page').forEach(el => {
        const cb = el.querySelector('input[type=checkbox]');
        if (cb) {
            cb.checked = checked;
            togglePoule(cb);
        }
    });
}
function togglePoule(cb) {
    const page = cb.closest('.poule-page');
    if (cb.checked) {
        page.classList.remove('print-exclude');
    } else {
        page.classList.add('print-exclude');
    }
    updateCounter();
}
function updateCounter() {
    const total = document.querySelectorAll('.poule-page').length;
    const selected = document.querySelectorAll('.poule-page:not(.print-exclude)').length;
    document.getElementById('print-counter').textContent = selected + ' van ' + total + ' geselecteerd';
}
function abbreviateClub(name) {
    if (!name) return '-';
    // Vervang veelvoorkomende woorden met afkortingen
    let abbr = name
        .replace(/Judoschool/gi, 'J.S.')
        .replace(/Sportcentrum/gi, 'S.C.')
        .replace(/Sportvereniging/gi, 'S.V.')
        .replace(/Judovereniging/gi, 'J.V.')
        .replace(/Judo Vereniging/gi, 'J.V.');
    // Max 15 karakters
    return abbr.length > 15 ? abbr.substring(0, 14) + '…' : abbr;
}
<\/script>`;

                    if (!data.poules || data.poules.length === 0) {
                        html += '<p>Geen poules gevonden in backup.</p></body></html>';
                        return html;
                    }

                    data.poules.forEach(poule => {
                        const judokas = poule.judokas || [];
                        const wedstrijden = poule.wedstrijden || [];
                        const aantal = judokas.length;

                        if (aantal < 2) return;

                        // Build schema from actual wedstrijden (supports dubbele potjes)
                        const judokaIdToNr = {};
                        judokas.forEach((j, idx) => judokaIdToNr[j.id] = idx + 1);

                        const schema = wedstrijden.map(w => [
                            judokaIdToNr[w.judoka_wit_id],
                            judokaIdToNr[w.judoka_blauw_id]
                        ]).filter(s => s[0] && s[1]);

                        // Landscape als meer dan 6 wedstrijden
                        const isLandscape = schema.length > 6;

                        // Create wedstrijd lookup by positie for correct column mapping
                        const wedstrijdByPositie = {};
                        wedstrijden.forEach((w, idx) => {
                            wedstrijdByPositie[idx] = w;
                        });

                        // Bereken totaal kolommen: Nr + Naam + (wedstrijden * 2) + WP + JP + Plts
                        const totalCols = 5 + (schema.length * 2);

                        html += `<div class="poule-page ${isLandscape ? 'landscape' : ''}">
                            <table class="schema-table">
                                <thead>
                                    <tr class="title-row">
                                        <td colspan="${totalCols}">
                                            <div style="display:flex;justify-content:space-between;">
                                                <span>${data.toernooi_naam || 'Toernooi'}</span>
                                                <span>${data.toernooi_datum || ''}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="info-row">
                                        <td colspan="${totalCols}">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <div class="poule-checkbox no-print">
                                                    <input type="checkbox" checked onchange="togglePoule(this)">
                                                    <strong>Poule #${poule.nummer} - ${poule.titel || ''}</strong>
                                                </div>
                                                <span>Mat ${poule.mat_nummer || '?'} | Blok ${poule.blok_nummer || '?'}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="header-row">
                                        <th class="nr-cel">Nr</th>
                                        <th class="naam-cel" style="text-align:left">Naam</th>`;

                        schema.forEach((_, idx) => {
                            html += `<th colspan="2" style="min-width:48px;text-align:center"><div style="font-weight:bold">${idx + 1}</div><div class="sub-header">W &nbsp; J</div></th>`;
                        });

                        html += `<th class="totaal-cel">WP</th><th class="totaal-cel">JP</th><th class="plts-cel">Plts</th></tr></thead><tbody>`;

                        judokas.forEach((judoka, idx) => {
                            const judokaNr = idx + 1;
                            let totaalWP = 0, totaalJP = 0, heeftGespeeld = false;

                            html += `<tr><td class="nr-cel" style="font-weight:bold">${judokaNr}</td>
                                <td class="naam-cel">${judoka.naam || 'Onbekend'} <span style="color:#999;font-size:10px">(${abbreviateClub(judoka.club)})</span></td>`;

                            schema.forEach((schemaWed, wedIdx) => {
                                const witNr = schemaWed[0];
                                const blauwNr = schemaWed[1];
                                const participates = (judokaNr === witNr || judokaNr === blauwNr);

                                let wp = '', jp = '';

                                if (participates) {
                                    const match = wedstrijdByPositie[wedIdx];

                                    if (match && match.is_gespeeld) {
                                        heeftGespeeld = true;
                                        if (judokaNr === witNr) {
                                            wp = match.winnaar_id == judoka.id ? '2' : (match.winnaar_id ? '0' : '1');
                                            jp = match.score_wit !== null ? String(match.score_wit) : '0';
                                        } else {
                                            wp = match.winnaar_id == judoka.id ? '2' : (match.winnaar_id ? '0' : '1');
                                            jp = match.score_blauw !== null ? String(match.score_blauw) : '0';
                                        }
                                        totaalWP += parseInt(wp);
                                        totaalJP += parseInt(jp);
                                    }
                                    const cellClass = (wp !== '') ? 'gespeeld' : '';
                                    html += `<td class="score-cel w-cel ${cellClass}">${wp}</td><td class="score-cel j-cel ${cellClass}">${jp}</td>`;
                                } else {
                                    html += `<td class="score-cel w-cel inactief"></td><td class="score-cel j-cel inactief"></td>`;
                                }
                            });

                            html += `<td class="totaal-cel">${heeftGespeeld ? totaalWP : ''}</td>
                                <td class="totaal-cel">${heeftGespeeld ? totaalJP : ''}</td>
                                <td class="plts-cel"></td></tr>`;
                        });

                        html += `</tbody></table>
                            <div style="margin-top:6px;font-size:10px;color:#666"><strong>W</strong> = Wedstrijdpunten | <strong>J</strong> = Judopunten | Plts = handmatig</div>
                        </div>`;
                    });

                    html += '</body></html>';
                    return html;
                },

                loadJsonBackup(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        try {
                            const data = JSON.parse(e.target.result);

                            // Valideer dat het juiste toernooi is
                            if (data.toernooi_id && data.toernooi_id !== this.toernooiId) {
                                if (!confirm(`Dit backup bestand is van een ander toernooi (ID: ${data.toernooi_id}). Toch laden?`)) {
                                    return;
                                }
                            }

                            // Sla op in localStorage
                            const storageKey = `noodplan_${this.toernooiId}_poules`;
                            const syncKey = `noodplan_${this.toernooiId}_laatste_sync`;
                            const countKey = `noodplan_${this.toernooiId}_count`;

                            localStorage.setItem(storageKey, JSON.stringify(data));
                            localStorage.setItem(syncKey, new Date().toISOString());

                            // Tel uitslagen
                            let count = 0;
                            if (data.poules) {
                                data.poules.forEach(p => {
                                    if (p.wedstrijden) {
                                        p.wedstrijden.forEach(w => {
                                            if (w.is_gespeeld) count++;
                                        });
                                    }
                                });
                            }
                            localStorage.setItem(countKey, count.toString());

                            // Update UI
                            this.loadFromStorage();
                            alert(`Backup geladen: ${data.poules?.length || 0} poules, ${count} uitslagen`);
                        } catch (err) {
                            alert('Ongeldig JSON bestand: ' + err.message);
                        }
                    };
                    reader.readAsText(file);
                    event.target.value = ''; // Reset input
                },

                generateSchema(n) {
                    // Standard round-robin algorithm
                    const schema = [];
                    const players = [];
                    for (let i = 1; i <= n; i++) players.push(i);
                    if (n % 2 === 1) players.push(null); // bye

                    const numRounds = players.length - 1;
                    const half = players.length / 2;

                    for (let round = 0; round < numRounds; round++) {
                        for (let i = 0; i < half; i++) {
                            const p1 = players[i];
                            const p2 = players[players.length - 1 - i];
                            if (p1 !== null && p2 !== null) {
                                schema.push([p1, p2]);
                            }
                        }
                        // Rotate players (keep first fixed)
                        players.splice(1, 0, players.pop());
                    }
                    return schema;
                }
            };
        }
    </script>

    <!-- NETWERK CONFIGURATIE -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="networkConfig()">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">🌐</span>
            NETWERK CONFIGURATIE
        </h2>

        <!-- Algemene tips -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <h4 class="font-bold text-gray-700 mb-2">Altijd (bij elk scenario)</h4>
            <ul class="text-sm text-gray-600 space-y-1">
                <li><strong>Laptops</strong> voor hoofd-apps (mat interface, hoofdjury) — muis is preciezer</li>
                <li><strong>Tablets</strong> voor vrijwilligers (wegen, dojo check-in, spreker)</li>
                <li><strong>Papieren backup</strong> — schrijf uitslagen mee per mat</li>
                <li><strong>Hotspot</strong> van tevoren klaarzetten op telefoon (naam + wachtwoord noteren)</li>
            </ul>
        </div>

        <!-- Scenario keuze -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-3">Welk scenario past bij jouw sporthal?</h3>
            <div class="grid md:grid-cols-3 gap-3">
                <button type="button" @click="scenario = 'A'"
                        :class="scenario === 'A' ? 'border-green-500 bg-green-50 ring-2 ring-green-300' : 'border-gray-300 hover:border-green-300'"
                        class="p-4 border-2 rounded-lg text-left transition-all">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xl">📶</span>
                        <strong class="text-green-800">A: Goed bereik</strong>
                    </div>
                    <p class="text-xs text-gray-600">Sporthal WiFi is snel en stabiel</p>
                </button>
                <button type="button" @click="scenario = 'B'"
                        :class="scenario === 'B' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-300' : 'border-gray-300 hover:border-blue-300'"
                        class="p-4 border-2 rounded-lg text-left transition-all">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xl">📡</span>
                        <strong class="text-blue-800">B: Slecht bereik</strong>
                    </div>
                    <p class="text-xs text-gray-600">Eigen netwerk (Deco / hubs / LAN)</p>
                </button>
                <button type="button" @click="scenario = 'C'"
                        :class="scenario === 'C' ? 'border-red-500 bg-red-50 ring-2 ring-red-300' : 'border-gray-300 hover:border-red-300'"
                        class="p-4 border-2 rounded-lg text-left transition-all">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xl">🔴</span>
                        <strong class="text-red-800">C: Geen internet / crash</strong>
                    </div>
                    <p class="text-xs text-gray-600">Lokale server, geen publieke app</p>
                </button>
            </div>
        </div>

        <!-- SCENARIO A -->
        <div x-show="scenario === 'A'" x-cloak>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <h4 class="font-bold text-green-800 mb-2">Scenario A: Goed internet bereik</h4>
                <div class="grid md:grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="font-medium text-green-700">Netwerk:</span> Sporthal WiFi<br>
                        <span class="font-medium text-green-700">Server:</span> judotournament.org (online)
                    </div>
                    <div>
                        <span class="font-medium text-green-700">Publieke PWA:</span> Ja (live scores voor publiek)<br>
                        <span class="font-medium text-green-700">Vrijwilligers PWA:</span> Ja (mat, wegen, dojo, spreker)
                    </div>
                </div>
                <p class="text-xs text-green-600 mt-2">Eigen Deco optioneel als backup netwerk</p>
            </div>
            <table class="w-full text-sm border-collapse mb-4">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left w-1/3">Storing</th>
                        <th class="border p-2 text-left">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-yellow-50">
                        <td class="border p-2 font-medium">Internet valt weg</td>
                        <td class="border p-2">Hotspot aan op telefoon, alle tablets + laptops op hotspot</td>
                    </tr>
                    <tr class="bg-orange-50">
                        <td class="border p-2 font-medium">Internet + hotspot onmogelijk</td>
                        <td class="border p-2">Lokale server starten → scenario C toepassen</td>
                    </tr>
                    <tr class="bg-red-50">
                        <td class="border p-2 font-medium">Online server crash</td>
                        <td class="border p-2">Lokale server starten → scenario C toepassen</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SCENARIO B -->
        <div x-show="scenario === 'B'" x-cloak>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-bold text-blue-800 mb-2">Scenario B: Slecht WiFi bereik</h4>
                <div class="grid md:grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="font-medium text-blue-700">Netwerk:</span> Eigen lokaal netwerk (Deco's / hubs / LAN)<br>
                        <span class="font-medium text-blue-700">Internet:</span> Via LAN-aansluiting sporthal<br>
                        <span class="font-medium text-blue-700">Server:</span> judotournament.org (online)
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Publieke PWA:</span> Nee (beperkte capaciteit)<br>
                        <span class="font-medium text-blue-700">Vrijwilligers PWA:</span> Ja (mat, wegen, dojo, spreker)
                    </div>
                </div>
                <p class="text-xs text-blue-600 mt-2">Deco's: max ~60 devices op 4 units. LAN-kabels voor laptops aanbevolen.</p>
            </div>
            <table class="w-full text-sm border-collapse mb-4">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left w-1/3">Storing</th>
                        <th class="border p-2 text-left">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-yellow-50">
                        <td class="border p-2 font-medium">Deco / eigen netwerk uitval</td>
                        <td class="border p-2">Herstarten Deco's, of overschakelen op LAN-kabels voor laptops</td>
                    </tr>
                    <tr class="bg-red-50">
                        <td class="border p-2 font-medium">Online server crash</td>
                        <td class="border p-2">Lokale server starten → scenario C toepassen</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SCENARIO C -->
        <div x-show="scenario === 'C'" x-cloak>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <h4 class="font-bold text-red-800 mb-2">Scenario C: Geen internet / server crash</h4>
                <div class="grid md:grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="font-medium text-red-700">Netwerk:</span> Eigen lokaal netwerk (verplicht)<br>
                        <span class="font-medium text-red-700">Server:</span> Lokale server op primaire laptop
                    </div>
                    <div>
                        <span class="font-medium text-red-700">Publieke PWA:</span> Nee<br>
                        <span class="font-medium text-red-700">Vrijwilligers PWA:</span> Ja, op lokaal IP
                    </div>
                </div>
            </div>
            <table class="w-full text-sm border-collapse mb-4">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left w-1/3">Storing</th>
                        <th class="border p-2 text-left">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-orange-50">
                        <td class="border p-2 font-medium">Primaire laptop crash</td>
                        <td class="border p-2">Standby laptop starten, alle tablets naar standby IP overschakelen</td>
                    </tr>
                    <tr class="bg-red-50">
                        <td class="border p-2 font-medium">Standby ook stuk</td>
                        <td class="border p-2">Papieren backup — verder op papier</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Netwerk gegevens (altijd zichtbaar) -->
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <!-- Eigen Router -->
            <div class="p-4 bg-blue-50 border border-blue-200 rounded">
                <h4 class="font-bold text-blue-800 mb-2 text-sm">📡 Eigen Router</h4>
                @if($toernooi->eigen_router_ssid)
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-blue-700">SSID:</span>
                            <div class="flex items-center gap-1">
                                <code class="bg-blue-100 px-1.5 py-0.5 rounded font-mono text-blue-900 text-xs">{{ $toernooi->eigen_router_ssid }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->eigen_router_ssid }}')" class="text-blue-600 hover:text-blue-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @if($toernooi->eigen_router_wachtwoord)
                        <div class="flex justify-between items-center">
                            <span class="text-blue-700">Ww:</span>
                            <div class="flex items-center gap-1">
                                <code class="bg-blue-100 px-1.5 py-0.5 rounded font-mono text-blue-900 text-xs">{{ $toernooi->eigen_router_wachtwoord }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->eigen_router_wachtwoord }}')" class="text-blue-600 hover:text-blue-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    <p class="text-blue-400 italic text-xs">Niet ingesteld</p>
                @endif
            </div>

            <!-- Hotspot -->
            <div class="p-4 bg-orange-50 border border-orange-200 rounded">
                <h4 class="font-bold text-orange-800 mb-2 text-sm">📱 Hotspot (backup)</h4>
                @if($toernooi->hotspot_ssid)
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-orange-700">SSID:</span>
                            <div class="flex items-center gap-1">
                                <code class="bg-orange-100 px-1.5 py-0.5 rounded font-mono text-orange-900 text-xs">{{ $toernooi->hotspot_ssid }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->hotspot_ssid }}')" class="text-orange-600 hover:text-orange-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @if($toernooi->hotspot_wachtwoord)
                        <div class="flex justify-between items-center">
                            <span class="text-orange-700">Ww:</span>
                            <div class="flex items-center gap-1">
                                <code class="bg-orange-100 px-1.5 py-0.5 rounded font-mono text-orange-900 text-xs">{{ $toernooi->hotspot_wachtwoord }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->hotspot_wachtwoord }}')" class="text-orange-600 hover:text-orange-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    <p class="text-orange-400 italic text-xs">Niet ingesteld</p>
                @endif
            </div>

            <!-- Server IP's -->
            <div class="p-4 bg-green-50 border border-green-200 rounded">
                <h4 class="font-bold text-green-800 mb-2 text-sm">💻 Lokale Server IP's</h4>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-green-700">Primary:</span>
                        @if($toernooi->local_server_primary_ip)
                        <div class="flex items-center gap-1">
                            <code class="bg-green-100 px-1.5 py-0.5 rounded font-mono text-green-900 text-xs">{{ $toernooi->local_server_primary_ip }}:8000</code>
                            <button @click="copyToClipboard('http://{{ $toernooi->local_server_primary_ip }}:8000')" class="text-green-600 hover:text-green-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                        @else
                        <span class="text-gray-400 italic text-xs">Niet ingesteld</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-green-700">Standby:</span>
                        @if($toernooi->local_server_standby_ip)
                        <div class="flex items-center gap-1">
                            <code class="bg-green-100 px-1.5 py-0.5 rounded font-mono text-green-900 text-xs">{{ $toernooi->local_server_standby_ip }}:8000</code>
                            <button @click="copyToClipboard('http://{{ $toernooi->local_server_standby_ip }}:8000')" class="text-green-600 hover:text-green-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                        @else
                        <span class="text-gray-400 italic text-xs">Niet ingesteld</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Instellen knop -->
        <div class="flex justify-end">
            <button @click="showEditModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium text-sm">
                ⚙️ Netwerkinstellingen configureren
            </button>
        </div>

        <!-- Edit Modal -->
        <div x-show="showEditModal" x-cloak
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
             @click.self="showEditModal = false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold mb-4">Netwerkinstellingen configureren</h3>

                <form method="POST" action="{{ route('toernooi.local-server-ips', $toernooi->routeParams()) }}">
                    @csrf
                    @method('PUT')

                    <!-- Eigen router -->
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                        <h4 class="font-bold text-blue-800 mb-3">📡 Eigen Router (Deco / hubs)</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">WiFi naam (SSID)</label>
                                <input type="text" name="eigen_router_ssid"
                                       value="{{ $toernooi->eigen_router_ssid }}"
                                       placeholder="JudoToernooi-WiFi"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Wachtwoord</label>
                                <input type="text" name="eigen_router_wachtwoord"
                                       value="{{ $toernooi->eigen_router_wachtwoord }}"
                                       placeholder="wachtwoord123"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- Hotspot -->
                    <div class="mb-6 p-4 bg-orange-50 rounded-lg">
                        <h4 class="font-bold text-orange-800 mb-3">📱 Mobiele Hotspot (backup)</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hotspot naam (SSID)</label>
                                <input type="text" name="hotspot_ssid"
                                       value="{{ $toernooi->hotspot_ssid }}"
                                       placeholder="iPhone van Henk"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Wachtwoord</label>
                                <input type="text" name="hotspot_wachtwoord"
                                       value="{{ $toernooi->hotspot_wachtwoord }}"
                                       placeholder="wachtwoord123"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- Server IP's -->
                    <div class="mb-6 p-4 bg-green-50 rounded-lg">
                        <h4 class="font-bold text-green-800 mb-3">💻 Lokale Server IP-adressen</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Primary Server IP</label>
                                <input type="text" name="local_server_primary_ip"
                                       value="{{ $toernooi->local_server_primary_ip }}"
                                       placeholder="192.168.1.100"
                                       class="w-full px-3 py-2 border rounded-lg font-mono">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Standby Server IP</label>
                                <input type="text" name="local_server_standby_ip"
                                       value="{{ $toernooi->local_server_standby_ip }}"
                                       placeholder="192.168.1.101"
                                       class="w-full px-3 py-2 border rounded-lg font-mono">
                            </div>
                        </div>
                        <p class="text-xs text-green-600 mt-2">IP opzoeken: <code class="bg-green-100 px-1 rounded">ipconfig</code> (Windows) of <code class="bg-green-100 px-1 rounded">ifconfig</code> (Mac/Linux)</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showEditModal = false"
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                            Annuleren
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Opslaan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Copied toast -->
        <div x-show="showCopied" x-transition
             class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50">
            ✓ Gekopieerd naar klembord!
        </div>
    </div>

    <script>
        function networkConfig() {
            return {
                scenario: 'A',
                showEditModal: false,
                showCopied: false,

                copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showCopied = true;
                        setTimeout(() => this.showCopied = false, 2000);
                    });
                }
            };
        }
    </script>

    <!-- STAP-VOOR-STAP INSTRUCTIES -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            STAP-VOOR-STAP: LOKALE SERVER STARTEN
        </h2>

        <div class="space-y-4">
            <!-- Voorbereiding -->
            <div class="p-4 bg-gray-50 border border-gray-200 rounded">
                <h3 class="font-bold text-gray-800 mb-2">🗓️ Avond ervoor</h3>
                <ol class="text-sm text-gray-700 space-y-2 list-decimal list-inside">
                    <li>Download de <strong>JSON backup</strong> (knop hierboven)</li>
                    <li>Bewaar op USB-stick én laptop</li>
                    <li>Controleer 5G hotspot op telefoon (naam + wachtwoord)</li>
                    @if($toernooi->heeft_eigen_router)
                    <li>Test eigen router + USB-tethering met telefoon</li>
                    @endif
                    <li>Test lokale server op laptop (zie commando hieronder)</li>
                    <li><strong>Print deze pagina!</strong></li>
                </ol>
            </div>

            @if($toernooi->heeft_eigen_router)
            <!-- Bij internet storing (MET eigen router) -->
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
                <h3 class="font-bold text-yellow-800 mb-2">⚠️ Bij internet storing (WiFi sporthal werkt niet)</h3>
                <ol class="text-sm text-yellow-700 space-y-2 list-decimal list-inside">
                    <li>Zet <strong>5G hotspot</strong> aan op telefoon</li>
                    <li>Verbind router met hotspot:
                        <ul class="ml-4 mt-1 list-disc text-xs">
                            <li><strong>USB-tethering</strong> (makkelijkst): telefoon via USB aan router</li>
                            <li><strong>WiFi-bridge</strong>: in router-app hotspot als bron instellen</li>
                        </ul>
                    </li>
                    <li>Klaar! Tablets blijven op dezelfde WiFi, internet loopt nu via 5G</li>
                </ol>
            </div>

            <!-- Bij server crash (MET eigen router) -->
            <div class="p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="font-bold text-red-800 mb-2">🔴 Bij cloud server crash</h3>
                <ol class="text-sm text-red-700 space-y-2 list-decimal list-inside">
                    <li>Start lokale server op laptop:
                        <code class="block bg-red-100 p-2 rounded mt-1 text-xs font-mono">cd judotoernooi/laravel && php artisan serve --host=0.0.0.0 --port=8000</code>
                    </li>
                    <li>Tablets gaan automatisch naar lokale server (zelfde WiFi!)</li>
                </ol>
            </div>
            @else
            <!-- Bij storing (ZONDER eigen router) -->
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
                <h3 class="font-bold text-yellow-800 mb-2">⚠️ Bij internet/WiFi storing</h3>
                <ol class="text-sm text-yellow-700 space-y-2 list-decimal list-inside">
                    <li>Zet <strong>5G hotspot</strong> aan op telefoon</li>
                    <li>Verbind alle tablets met de hotspot</li>
                    <li>Klaar! Cloud server werkt gewoon via 5G</li>
                </ol>
            </div>

            <!-- Bij server crash (ZONDER eigen router) -->
            <div class="p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="font-bold text-red-800 mb-2">🔴 Bij cloud server crash</h3>
                <ol class="text-sm text-red-700 space-y-2 list-decimal list-inside">
                    <li>Start eigen router (bijv. Deco)</li>
                    <li>Verbind laptop én tablets met router WiFi</li>
                    <li>Start lokale server op laptop:
                        <code class="block bg-red-100 p-2 rounded mt-1 text-xs font-mono">cd judotoernooi/laravel && php artisan serve --host=0.0.0.0 --port=8000</code>
                    </li>
                    <li>Open op tablets: <code class="bg-red-100 px-1 rounded">http://[laptop-ip]:8000</code></li>
                </ol>
            </div>
            @endif

            <!-- Papier backup -->
            <div class="p-4 bg-purple-50 border border-purple-200 rounded">
                <h3 class="font-bold text-purple-800 mb-2">📄 Noodgeval: verder op papier</h3>
                <p class="text-sm text-purple-700 mb-2">Als helemaal niets meer werkt:</p>
                <ol class="text-sm text-purple-700 space-y-1 list-decimal list-inside">
                    <li>Gebruik de geprinte wedstrijdschema's (zie "Ingevulde schema's" hierboven)</li>
                    <li>Vul scores handmatig in op papier</li>
                    <li>Na afloop: voer alles in via de cloud wanneer internet weer werkt</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Checklist -->
    <div class="p-4 bg-blue-50 rounded-lg">
        <h3 class="font-bold text-blue-800 mb-2">✅ Checklist vóór het toernooi</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>☐ Download <strong>Excel backup</strong> (poule-indeling)</li>
            <li>☐ Download <strong>JSON backup</strong> (alle wedstrijddata)</li>
            <li>☐ Test 5G hotspot op telefoon (werkt internet?)</li>
            @if($toernooi->heeft_eigen_router)
            <li>☐ Test eigen router + USB-tethering met telefoon</li>
            @endif
            <li>☐ Test lokale server: <code class="bg-blue-100 px-1 rounded text-xs">php artisan serve --host=0.0.0.0</code></li>
            <li>☐ <strong>Print deze pagina!</strong></li>
        </ul>
    </div>
</div>
@endsection
