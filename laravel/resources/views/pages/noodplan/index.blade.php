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
            <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
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
                    <p class="text-sm text-gray-500">Handmatig invullen bij uitval</p>
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
            <!-- Status info -->
            <div class="p-3 rounded text-sm" :class="syncStatus === 'connected' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-orange-50 border border-orange-200 text-orange-700'">
                <span x-text="uitslagCount"></span> uitslagen in backup | Laatste sync: <span x-text="laatsteSync || 'Nog geen data'"></span>
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

            <!-- Live wedstrijd schema's - met uitslagen uit backup -->
            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded">
                <div>
                    <h3 class="font-medium text-yellow-800">Live wedstrijd schema's</h3>
                    <p class="text-sm text-yellow-600">Met alle al gespeelde wedstrijden + punten uit backup</p>
                </div>
                <div class="flex gap-2">
                    <button @click="printLive(null)" type="button"
                       class="px-3 py-2 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700"
                       :disabled="!hasData" :class="{ 'opacity-50 cursor-not-allowed': !hasData }">
                        Alle
                    </button>
                    @foreach($blokken as $blok)
                    <button @click="printLive({{ $blok->nummer }})" type="button"
                       class="px-3 py-2 bg-yellow-500 text-white rounded text-sm hover:bg-yellow-600"
                       :disabled="!hasData" :class="{ 'opacity-50 cursor-not-allowed': !hasData }">
                        {{ $blok->nummer }}
                    </button>
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
    }
    @page { size: A4 portrait; margin: 0.5cm; }
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 10px; }
    .print-toolbar { padding: 12px 16px; background: #fef3c7; margin-bottom: 15px; border-radius: 8px; }
    .toolbar-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .toolbar-controls { display: flex; align-items: center; gap: 15px; }
    .poule-header { background: #f3f4f6; border: 2px solid #333; padding: 10px 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
    .poule-checkbox { display: flex; align-items: center; gap: 8px; }
    .poule-checkbox input { width: 18px; height: 18px; cursor: pointer; }
    .schema-table { width: auto; border-collapse: collapse; table-layout: fixed; }
    .schema-table th, .schema-table td { border: 1px solid #333; }
    .header-row { background: #1f2937; color: white; }
    .header-row th { border-color: #374151; font-size: 12px; padding: 5px 3px; }
    .sub-header { font-size: 10px; font-weight: normal; color: #9ca3af; }
    .nr-cel { width: 28px; font-size: 13px; text-align: center; }
    .naam-cel { width: 260px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; padding: 4px 6px; }
    .score-cel { width: 24px; text-align: center; font-size: 12px; height: 28px; }
    .score-cel.inactief { background: #1f2937; }
    .score-cel.w-cel { border-right: 1px solid #ccc; }
    .score-cel.j-cel { border-left: none; border-right: 2px solid #333; }
    .totaal-cel { width: 36px; background: #f3f4f6; text-align: center; font-size: 12px; font-weight: bold; }
    .plts-cel { width: 32px; background: #fef9c3; text-align: center; font-size: 12px; }
    .gespeeld { background: #d1fae5; }
    .poule-page.print-exclude { opacity: 0.5; }
    .poule-page { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px dashed #ccc; }
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

                        // Generate round-robin schema
                        const schema = this.generateSchema(aantal);

                        // Create wedstrijd lookup
                        const wedstrijdMap = {};
                        wedstrijden.forEach(w => {
                            wedstrijdMap[w.judoka_wit_id + '-' + w.judoka_blauw_id] = w;
                        });

                        html += `<div class="poule-page">
                            <div class="poule-header">
                                <div class="poule-checkbox no-print">
                                    <input type="checkbox" checked onchange="togglePoule(this)">
                                    <strong>Poule #${poule.nummer} - ${poule.titel || ''}</strong>
                                </div>
                                <span>Mat ${poule.mat_nummer || '?'} | Blok ${poule.blok_nummer || '?'}</span>
                            </div>
                            <table class="schema-table">
                                <thead><tr class="header-row">
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
                                <td class="naam-cel">${judoka.naam || 'Onbekend'} <span style="color:#999;font-size:10px">(${(judoka.club || '-').substring(0,12)})</span></td>`;

                            schema.forEach(schemaWed => {
                                const witNr = schemaWed[0];
                                const blauwNr = schemaWed[1];
                                const participates = (judokaNr === witNr || judokaNr === blauwNr);

                                let wp = '', jp = '';

                                if (participates) {
                                    const witJudoka = judokas[witNr - 1];
                                    const blauwJudoka = judokas[blauwNr - 1];

                                    if (witJudoka && blauwJudoka) {
                                        const key = witJudoka.id + '-' + blauwJudoka.id;
                                        const match = wedstrijdMap[key];

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
