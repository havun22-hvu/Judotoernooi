@extends('layouts.app')

@section('title', 'Case of Emergency')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🆘 Noodplan</h1>
            <p class="text-gray-600 mt-1">Exports, backups en prints voor als het mis gaat</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>Momentopname: <span class="font-mono">{{ now()->format('H:i:s') }}</span></p>
            <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
                &larr; Terug naar Dashboard
            </a>
        </div>
    </div>

    <!-- Uitleg -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
        <h2 class="font-bold text-amber-800 mb-2">Wat is dit?</h2>
        <p class="text-sm text-amber-700 mb-2">
            Dit is je vangnet bij stroomuitval, servercrash of internetstoring. Download vóór het toernooi begint
            de belangrijkste exports en bewaar ze op een USB-stick of laptop.
        </p>
        <p class="text-sm text-amber-700">
            <strong>Tip:</strong> De browser slaat automatisch uitslagen op in localStorage. Als de server crasht
            maar je laptop nog werkt, kun je de "Live wedstrijd schema's" nog steeds printen met alle scores.
        </p>
    </div>

    <!-- OFFLINE MODUS BANNER -->
    <div x-data="offlineDetector()" x-init="init()" x-show="isOffline" x-cloak
         class="bg-orange-100 border-l-4 border-orange-500 p-4 mb-6 rounded">
        <div class="flex items-center">
            <span class="text-2xl mr-3">⚠️</span>
            <div>
                <h3 class="font-bold text-orange-800">Offline Modus</h3>
                <p class="text-orange-700 text-sm">Server niet bereikbaar. Je kunt nog steeds printen vanuit de lokale backup (localStorage).</p>
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

    <!-- POULE EXPORT -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">📋</span>
            POULE EXPORT (backup)
        </h2>

        <div class="space-y-4">
            <!-- Poule Export -->
            <div class="p-4 bg-green-50 border border-green-200 rounded">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-green-800">Volledige poule-indeling</h3>
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
                    <li>Per blok een tab</li>
                    <li>Gesorteerd op mat</li>
                    <li>Met leeftijds-/gewichtsklasse</li>
                </ul>
            </div>

            <!-- JSON Download voor offline gebruik -->
            <div class="p-4 bg-purple-50 border border-purple-200 rounded" x-data="jsonDownloader()">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-purple-800">Offline Backup (JSON)</h3>
                        <p class="text-sm text-purple-600">Voor lokale server bij internetstoring - laad in via "Laad JSON backup" hieronder</p>
                    </div>
                    <button @click="download()" type="button"
                            class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 font-medium">
                        Download backup
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
                    <h3 class="font-medium">Weeglijsten</h3>
                    <p class="text-sm text-gray-500">Alfabetisch per blok, met invulvak</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.weeglijst', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        Alle
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
                    <h3 class="font-medium">Weegkaarten</h3>
                    <p class="text-sm text-gray-500">Per judoka (QR + gegevens)</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.weegkaarten', $toernooi->routeParams()) }}" target="_blank"
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
                    <h3 class="font-medium">Coachkaarten</h3>
                    <p class="text-sm text-gray-500">Toegang dojo</p>
                </div>
                <div class="flex gap-2 relative">
                    <a href="{{ route('toernooi.noodplan.coachkaarten', $toernooi->routeParams()) }}" target="_blank"
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
                    <h3 class="font-medium">Contactlijst</h3>
                    <p class="text-sm text-gray-500">Coach contactgegevens per club</p>
                </div>
                <a href="{{ route('toernooi.noodplan.contactlijst', $toernooi->routeParams()) }}" target="_blank"
                   class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    Bekijken
                </a>
            </div>

            <!-- Lege wedstrijdschema's -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Lege wedstrijdschema's</h3>
                    <p class="text-sm text-gray-500">Handmatig invullen</p>
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
            POULES PRINTEN (voorbereiding)
        </h2>
        <p class="text-sm text-gray-600 mb-4">Print poule-overzichten per blok/mat vóór het toernooi begint. Handig om uit te delen aan tafelofficiëls.</p>

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
                        Alle matten
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
            TIJDENS DE WEDSTRIJD
            <span x-show="syncStatus === 'connected'" class="ml-3 inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                <span class="w-2 h-2 rounded-full bg-green-500 mr-1 animate-pulse"></span>
                Live
            </span>
            <span x-show="syncStatus === 'disconnected'" class="ml-3 inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                <span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span>
                Backup modus
            </span>
        </h2>

        <div class="space-y-4">
            <!-- Status info + laden van JSON backup -->
            <div class="p-3 rounded text-sm flex items-center justify-between" :class="syncStatus === 'connected' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-orange-50 border border-orange-200 text-orange-700'">
                <div>
                    <span x-text="uitslagCount"></span> uitslagen in backup | Laatste sync: <span x-text="laatsteSync || 'Nog geen data'"></span>
                </div>
                <div class="flex gap-2">
                    <label class="px-3 py-1 bg-white border rounded text-xs cursor-pointer hover:bg-gray-50">
                        📁 Laad JSON backup
                        <input type="file" accept=".json" @change="loadJsonBackup($event)" class="hidden">
                    </label>
                </div>
            </div>

            <!-- Ingevulde schema's (matrix) - judoka's ingevuld, uitslagen leeg -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <h3 class="font-medium">Ingevulde schema's (matrix)</h3>
                    <p class="text-sm text-gray-500">Judoka's ingevuld, uitslagen leeg - voor handmatig invullen</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.ingevuld-schemas', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        Alle
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
                    <h3 class="font-medium text-yellow-800">Live wedstrijd schema's</h3>
                    <p class="text-sm text-yellow-600">Met alle al gespeelde wedstrijden + punten</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('toernooi.noodplan.live-schemas', $toernooi->routeParams()) }}" target="_blank"
                       class="px-3 py-2 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">
                        Alle
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

    <!-- NETWERK & BACKUP CONFIGURATIE -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="networkConfig()">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b flex items-center">
            <span class="mr-2">🌐</span>
            NETWERK & BACKUP CONFIGURATIE
        </h2>

        <!-- Configuratie status -->
        @if($toernooi->heeft_eigen_router)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">📡</span>
                    <div>
                        <strong class="text-blue-800">Configuratie: MET eigen router (TP-Link Deco)</strong>
                        <p class="text-sm text-blue-600">Tablets blijven altijd op dezelfde WiFi, alleen de bron verandert.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">📱</span>
                    <div>
                        <strong class="text-orange-800">Configuratie: ZONDER eigen router (mobiele hotspot als backup)</strong>
                        <p class="text-sm text-orange-600">Bij storing: tablets overzetten naar hotspot.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Scenario overzicht -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-3">Wat te doen bij storingen?</h3>

            @if($toernooi->heeft_eigen_router)
            <!-- MET EIGEN ROUTER -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border p-2 text-left">Situatie</th>
                            <th class="border p-2 text-left">Wat te doen</th>
                            <th class="border p-2 text-left">Tablets verbinden met</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-green-50">
                            <td class="border p-2 font-medium">✅ Normaal</td>
                            <td class="border p-2">Niets, alles werkt</td>
                            <td class="border p-2">
                                <strong>{{ $toernooi->eigen_router_ssid ?: 'Eigen router WiFi' }}</strong> → Cloud
                            </td>
                        </tr>
                        <tr class="bg-yellow-50">
                            <td class="border p-2 font-medium">⚠️ Internet weg</td>
                            <td class="border p-2">Start lokale server op laptop</td>
                            <td class="border p-2">
                                <strong>{{ $toernooi->eigen_router_ssid ?: 'Eigen router WiFi' }}</strong> → Lokale server
                                <br><span class="text-gray-500 text-xs">(tablets hoeven niet te wisselen!)</span>
                            </td>
                        </tr>
                        <tr class="bg-red-50">
                            <td class="border p-2 font-medium">🔴 Alles kapot</td>
                            <td class="border p-2">Print schema's, verder op papier</td>
                            <td class="border p-2 text-gray-500">N.v.t.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @else
            <!-- ZONDER EIGEN ROUTER -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border p-2 text-left">Situatie</th>
                            <th class="border p-2 text-left">Wat te doen</th>
                            <th class="border p-2 text-left">Tablets verbinden met</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-green-50">
                            <td class="border p-2 font-medium">✅ Normaal</td>
                            <td class="border p-2">Niets, alles werkt</td>
                            <td class="border p-2"><strong>Sporthal WiFi</strong> → Cloud</td>
                        </tr>
                        <tr class="bg-yellow-50">
                            <td class="border p-2 font-medium">⚠️ Internet weg</td>
                            <td class="border p-2">
                                1. Zet hotspot aan op telefoon<br>
                                2. Verbind laptop + tablets met hotspot<br>
                                3. Start lokale server
                            </td>
                            <td class="border p-2">
                                <strong>{{ $toernooi->hotspot_ssid ?: 'Mobiele hotspot' }}</strong> → Lokale server
                            </td>
                        </tr>
                        <tr class="bg-orange-50">
                            <td class="border p-2 font-medium">⚠️ WiFi weg</td>
                            <td class="border p-2">Zelfde als hierboven (hotspot)</td>
                            <td class="border p-2">
                                <strong>{{ $toernooi->hotspot_ssid ?: 'Mobiele hotspot' }}</strong> → Lokale server
                            </td>
                        </tr>
                        <tr class="bg-red-50">
                            <td class="border p-2 font-medium">🔴 Alles kapot</td>
                            <td class="border p-2">Print schema's, verder op papier</td>
                            <td class="border p-2 text-gray-500">N.v.t.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <!-- Netwerk gegevens -->
        <div class="grid md:grid-cols-2 gap-4 mb-6">
            <!-- Eigen Router / Hotspot info -->
            @if($toernooi->heeft_eigen_router)
            <div class="p-4 bg-blue-50 border border-blue-200 rounded">
                <h4 class="font-bold text-blue-800 mb-2">📡 Eigen Router (Deco)</h4>
                @if($toernooi->eigen_router_ssid)
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-700">SSID:</span>
                            <div class="flex items-center gap-2">
                                <code class="bg-blue-100 px-2 py-1 rounded font-mono text-blue-900">{{ $toernooi->eigen_router_ssid }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->eigen_router_ssid }}')" class="text-blue-600 hover:text-blue-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @if($toernooi->eigen_router_wachtwoord)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-700">Wachtwoord:</span>
                            <div class="flex items-center gap-2">
                                <code class="bg-blue-100 px-2 py-1 rounded font-mono text-blue-900">{{ $toernooi->eigen_router_wachtwoord }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->eigen_router_wachtwoord }}')" class="text-blue-600 hover:text-blue-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    <p class="text-blue-600 italic text-sm">Nog niet ingesteld</p>
                @endif
            </div>
            @else
            <div class="p-4 bg-orange-50 border border-orange-200 rounded">
                <h4 class="font-bold text-orange-800 mb-2">📱 Mobiele Hotspot (backup)</h4>
                @if($toernooi->hotspot_ssid)
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-orange-700">SSID:</span>
                            <div class="flex items-center gap-2">
                                <code class="bg-orange-100 px-2 py-1 rounded font-mono text-orange-900">{{ $toernooi->hotspot_ssid }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->hotspot_ssid }}')" class="text-orange-600 hover:text-orange-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @if($toernooi->hotspot_wachtwoord)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-orange-700">Wachtwoord:</span>
                            <div class="flex items-center gap-2">
                                <code class="bg-orange-100 px-2 py-1 rounded font-mono text-orange-900">{{ $toernooi->hotspot_wachtwoord }}</code>
                                <button @click="copyToClipboard('{{ $toernooi->hotspot_wachtwoord }}')" class="text-orange-600 hover:text-orange-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    <p class="text-orange-600 italic text-sm">Nog niet ingesteld</p>
                @endif
            </div>
            @endif

            <!-- Server IP's -->
            <div class="p-4 bg-green-50 border border-green-200 rounded">
                <h4 class="font-bold text-green-800 mb-2">💻 Lokale Server IP's</h4>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-green-700">Primary:</span>
                        @if($toernooi->local_server_primary_ip)
                        <div class="flex items-center gap-2">
                            <code class="bg-green-100 px-2 py-1 rounded font-mono text-green-900">http://{{ $toernooi->local_server_primary_ip }}:8000</code>
                            <button @click="copyToClipboard('http://{{ $toernooi->local_server_primary_ip }}:8000')" class="text-green-600 hover:text-green-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                        @else
                        <span class="text-gray-400 italic text-sm">Niet ingesteld</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-green-700">Standby:</span>
                        @if($toernooi->local_server_standby_ip)
                        <div class="flex items-center gap-2">
                            <code class="bg-green-100 px-2 py-1 rounded font-mono text-green-900">http://{{ $toernooi->local_server_standby_ip }}:8000</code>
                            <button @click="copyToClipboard('http://{{ $toernooi->local_server_standby_ip }}:8000')" class="text-green-600 hover:text-green-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                        @else
                        <span class="text-gray-400 italic text-sm">Niet ingesteld</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Instellen knop -->
        <div class="flex justify-end">
            <button @click="showEditModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
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

                    <!-- Router keuze -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Heb je een eigen router (bijv. TP-Link Deco)?</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer" :class="heeftEigenRouter ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
                                <input type="radio" name="heeft_eigen_router" value="1" x-model="heeftEigenRouter" class="text-blue-600">
                                <div>
                                    <strong>Ja, eigen router</strong>
                                    <p class="text-xs text-gray-500">Aanbevolen - tablets hoeven niet te wisselen</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer" :class="!heeftEigenRouter ? 'border-orange-500 bg-orange-50' : 'border-gray-300'">
                                <input type="radio" name="heeft_eigen_router" value="0" x-model="heeftEigenRouter" class="text-orange-600">
                                <div>
                                    <strong>Nee, sporthal WiFi + hotspot</strong>
                                    <p class="text-xs text-gray-500">Backup via mobiele hotspot</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Eigen router velden -->
                    <div x-show="heeftEigenRouter" x-cloak class="mb-6 p-4 bg-blue-50 rounded-lg">
                        <h4 class="font-bold text-blue-800 mb-3">📡 Eigen Router gegevens</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Router WiFi naam (SSID)</label>
                                <input type="text" name="eigen_router_ssid"
                                       value="{{ $toernooi->eigen_router_ssid }}"
                                       placeholder="JudoToernooi-WiFi"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Router wachtwoord</label>
                                <input type="text" name="eigen_router_wachtwoord"
                                       value="{{ $toernooi->eigen_router_wachtwoord }}"
                                       placeholder="wachtwoord123"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- Hotspot velden -->
                    <div x-show="!heeftEigenRouter" x-cloak class="mb-6 p-4 bg-orange-50 rounded-lg">
                        <h4 class="font-bold text-orange-800 mb-3">📱 Mobiele Hotspot gegevens (backup)</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hotspot naam (SSID)</label>
                                <input type="text" name="hotspot_ssid"
                                       value="{{ $toernooi->hotspot_ssid }}"
                                       placeholder="iPhone van Henk"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hotspot wachtwoord</label>
                                <input type="text" name="hotspot_wachtwoord"
                                       value="{{ $toernooi->hotspot_wachtwoord }}"
                                       placeholder="wachtwoord123"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                        </div>
                        <p class="text-xs text-orange-600 mt-2">💡 Tip: Zet de hotspot naam/wachtwoord van tevoren klaar in je telefoon instellingen</p>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Standby Server IP (optioneel)</label>
                                <input type="text" name="local_server_standby_ip"
                                       value="{{ $toernooi->local_server_standby_ip }}"
                                       placeholder="192.168.1.101"
                                       class="w-full px-3 py-2 border rounded-lg font-mono">
                            </div>
                        </div>
                        <p class="text-xs text-green-600 mt-2">💡 IP opzoeken: <code class="bg-green-100 px-1 rounded">ipconfig</code> (Windows) of <code class="bg-green-100 px-1 rounded">ifconfig</code> (Mac/Linux)</p>
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
                showEditModal: false,
                showCopied: false,
                heeftEigenRouter: {{ $toernooi->heeft_eigen_router ? 'true' : 'false' }},

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
                    @if($toernooi->heeft_eigen_router)
                    <li>Test de eigen router - zet aan, verbind laptop</li>
                    <li>Zoek laptop IP op: <code class="bg-gray-200 px-1 rounded">ipconfig</code></li>
                    @else
                    <li>Controleer hotspot instellingen op telefoon</li>
                    <li>Noteer hotspot naam en wachtwoord</li>
                    @endif
                    <li>Vul IP-adressen in via de knop hierboven</li>
                    <li><strong>Print deze pagina!</strong></li>
                </ol>
            </div>

            <!-- Bij storing -->
            <div class="p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="font-bold text-red-800 mb-2">🚨 Bij internet/WiFi storing</h3>
                <ol class="text-sm text-red-700 space-y-2 list-decimal list-inside">
                    @if($toernooi->heeft_eigen_router)
                    <li>Controleer of eigen router aan staat</li>
                    <li>Start lokale server op laptop:
                        <code class="block bg-red-100 p-2 rounded mt-1 text-xs font-mono">cd judotoernooi/laravel && php artisan serve --host=0.0.0.0 --port=8000</code>
                    </li>
                    <li>Tablets gaan automatisch naar lokale server (zelfde WiFi!)</li>
                    @else
                    <li>Zet <strong>mobiele hotspot</strong> aan op telefoon</li>
                    <li>Verbind laptop met hotspot</li>
                    <li>Verbind alle tablets met hotspot</li>
                    <li>Start lokale server op laptop:
                        <code class="block bg-red-100 p-2 rounded mt-1 text-xs font-mono">cd judotoernooi/laravel && php artisan serve --host=0.0.0.0 --port=8000</code>
                    </li>
                    <li>Open op tablets: <code class="bg-red-100 px-1 rounded">http://[laptop-ip]:8000</code></li>
                    @endif
                </ol>
            </div>

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
            @if($toernooi->heeft_eigen_router)
            <li>☐ Test eigen router (Deco) - werkt WiFi?</li>
            <li>☐ Verbind laptop met eigen router, zoek IP op</li>
            @else
            <li>☐ Controleer hotspot instellingen op telefoon</li>
            <li>☐ Noteer hotspot SSID en wachtwoord hierboven</li>
            @endif
            <li>☐ Test lokale server: <code class="bg-blue-100 px-1 rounded text-xs">php artisan serve --host=0.0.0.0</code></li>
            <li>☐ Vul IP-adressen in via "Netwerkinstellingen configureren"</li>
            <li>☐ <strong>Print deze pagina!</strong></li>
        </ul>
    </div>
</div>
@endsection
