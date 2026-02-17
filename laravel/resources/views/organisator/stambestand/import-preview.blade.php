@extends('layouts.app')

@section('title', 'Import Preview - Stambestand')

@section('content')
@php
    $veldInfo = [
        'naam' => ['label' => 'Naam', 'verplicht' => true, 'uitleg' => 'Volledige naam judoka'],
        'geboortejaar' => ['label' => 'Geboortejaar', 'verplicht' => true, 'uitleg' => 'Bijv. 2015'],
        'geslacht' => ['label' => 'Geslacht', 'verplicht' => true, 'uitleg' => 'M of V'],
        'gewicht' => ['label' => 'Gewicht', 'verplicht' => false, 'uitleg' => 'In kg, bijv. 32.5'],
        'band' => ['label' => 'Band', 'verplicht' => false, 'uitleg' => 'Wit, Geel, Oranje, etc.'],
    ];

    $heeftWaarschuwingen = collect($analyse['detectie'])->contains(fn($d) => $d['waarschuwing']);
    $gekoppeldeKolommen = collect($analyse['detectie'])->filter(fn($d) => $d['csv_index'] !== null)->count();
@endphp

{{-- Statistieken balk --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
            <div class="text-2xl font-bold text-blue-600">{{ $analyse['totaal_rijen'] }}</div>
            <div class="text-sm text-gray-600">Judoka's</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-green-600">{{ count($analyse['header']) }}</div>
            <div class="text-sm text-gray-600">Kolommen</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-purple-600">{{ $gekoppeldeKolommen }}</div>
            <div class="text-sm text-gray-600">Gekoppeld</div>
        </div>
        <div>
            <div class="text-2xl font-bold {{ $heeftWaarschuwingen ? 'text-orange-600' : 'text-green-600' }}">
                {{ $heeftWaarschuwingen ? 'Check nodig' : 'OK' }}
            </div>
            <div class="text-sm text-gray-600">Status</div>
        </div>
    </div>
</div>

{{-- Header --}}
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Import Preview</h1>
        <p class="text-gray-600">Controleer de kolom toewijzing voordat je importeert</p>
    </div>
    <a href="{{ route('organisator.stambestand.index', $organisator) }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
        &larr; Terug
    </a>
</div>

{{-- Status melding --}}
@if($heeftWaarschuwingen)
    <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded">
        <p class="text-yellow-800 font-medium">Er zijn problemen gevonden. Sleep de kolom-knoppen om te corrigeren.</p>
    </div>
@else
    <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-6 rounded">
        <p class="text-green-700 font-medium">Alle kolommen automatisch herkend. Controleer of het klopt.</p>
    </div>
@endif

    <form action="{{ route('organisator.stambestand.import.confirm', $organisator) }}" method="POST" id="import-form" data-loading="Judoka's importeren...">
        @csrf

        {{-- Kolom Mapping Tabel --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold mb-2">Kolom Toewijzing</h2>
            <p class="text-gray-600 mb-4">Sleep een kolom-knop naar een andere rij om te verwisselen.</p>

            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left border w-40">App veld</th>
                        <th class="px-3 py-2 text-left border w-48">CSV kolom</th>
                        <th class="px-3 py-2 text-left border">Voorbeeld data</th>
                        <th class="px-3 py-2 text-left border w-48">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($veldInfo as $veld => $info)
                        @php
                            $detectie = $analyse['detectie'][$veld] ?? ['csv_index' => null, 'waarschuwing' => null];
                        @endphp
                        <tr class="mapping-row {{ $detectie['waarschuwing'] ? 'bg-yellow-50' : '' }}" data-veld="{{ $veld }}">
                            <td class="px-3 py-2 border">
                                <strong>{{ $info['label'] }}</strong>
                                @if($info['verplicht'])<span class="text-red-500">*</span>@endif
                                <br>
                                <span class="text-xs text-gray-500">{{ $info['uitleg'] }}</span>
                            </td>
                            <td class="px-3 py-2 border drop-zone {{ $veld === 'naam' ? 'multi-drop' : '' }}" data-multi="{{ $veld === 'naam' ? 'true' : 'false' }}">
                                @if($detectie['csv_index'] !== null)
                                    <div class="kolom-chip cursor-move bg-blue-500 text-white px-3 py-1 rounded inline-flex items-center gap-2"
                                         draggable="true"
                                         data-index="{{ $detectie['csv_index'] }}">
                                        <span class="drag-handle">&#10495;</span>
                                        <span class="kolom-naam">{{ $analyse['header'][$detectie['csv_index']] }}</span>
                                    </div>
                                @endif
                                <input type="hidden" name="mapping[{{ $veld }}]" value="{{ $detectie['csv_index'] }}" class="mapping-input">
                                @if($veld === 'naam')
                                <span class="text-xs text-gray-400 ml-2 multi-hint">(sleep meerdere kolommen)</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 border font-mono text-xs">
                                <span class="voorbeeld-data">-</span>
                            </td>
                            <td class="px-3 py-2 border status-cell">
                                @if($detectie['waarschuwing'])
                                    <span class="text-yellow-600 font-bold text-xs">{{ $detectie['waarschuwing'] }}</span>
                                @elseif($detectie['csv_index'] !== null)
                                    <span class="text-green-600">OK</span>
                                @else
                                    <span class="text-gray-400">Niet gekoppeld</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Niet-gekoppelde kolommen --}}
            <div class="mt-4 pt-4 border-t">
                <p class="text-sm text-gray-600 mb-2">Niet-gekoppelde CSV kolommen (sleep naar een veld hierboven):</p>
                <div id="unclaimed-chips" class="flex flex-wrap gap-2">
                    {{-- Wordt gevuld door JS --}}
                </div>
            </div>
        </div>

        {{-- Preview tabel --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-2">Bestand Preview</h2>
            <p class="text-gray-600 mb-4">Eerste 5 regels van je bestand ({{ $analyse['totaal_rijen'] }} rijen totaal)</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border" id="preview-table">
                    <thead>
                        {{-- Rij 1: CSV kolom namen (uit bestand) --}}
                        <tr class="bg-gray-200">
                            <th class="px-2 py-1 border text-left text-xs text-gray-500" rowspan="2">#</th>
                            @foreach($analyse['header'] as $index => $kolom)
                                <th class="px-2 py-1 text-left border preview-header" data-col="{{ $index }}">
                                    <span class="text-xs text-gray-500 block">CSV kolom:</span>
                                    {{ $kolom }}
                                </th>
                            @endforeach
                        </tr>
                        {{-- Rij 2: Webapp velden (waar het naartoe gaat) --}}
                        <tr class="bg-gray-100">
                            @foreach($analyse['header'] as $index => $kolom)
                                <th class="px-2 py-1 text-left border mapped-to-cell" data-col="{{ $index }}">
                                    <span class="text-xs text-gray-500 block">App veld:</span>
                                    <span class="mapped-to text-gray-400">-</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analyse['preview_data'] as $rowIndex => $row)
                            <tr>
                                <td class="px-2 py-1 border text-gray-400">{{ $rowIndex + 1 }}</td>
                                @foreach($row as $colIndex => $cell)
                                    <td class="px-2 py-1 border preview-cell" data-col="{{ $colIndex }}">{{ $cell ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="bg-white rounded-lg shadow p-4 flex justify-end">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                Importeer {{ $analyse['totaal_rijen'] }} judoka's
            </button>
        </div>
    </form>

<style>
.kolom-chip {
    user-select: none;
    transition: all 0.15s;
    background: #1f2937 !important;
    border: none;
    color: white !important;
    font-weight: 500;
    font-size: 0.8rem;
}
.kolom-chip:hover {
    background: #374151 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.kolom-chip .drag-handle {
    opacity: 0.5;
}
.kolom-chip.dragging {
    opacity: 0.5;
}
.kolom-chip.unclaimed {
    background: #9ca3af !important;
}
.drop-zone {
    min-height: 40px;
    transition: all 0.15s;
}
.drop-zone.drag-over {
    background-color: #f3f4f6;
    outline: 2px dashed #6b7280;
}
.drop-zone.multi-drop {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.drop-zone.multi-drop .kolom-chip {
    margin-right: 0;
}
.multi-hint {
    flex-shrink: 0;
}
.preview-header.highlighted {
    background-color: #f0fdf4;
}
.mapped-to-cell.highlighted {
    background-color: #f0fdf4;
}
.mapped-to-cell.highlighted .mapped-to {
    color: #166534;
    font-weight: 600;
}
.mapped-to-cell.unused {
    background-color: #fef2f2;
}
.mapped-to-cell.unused .mapped-to {
    color: #991b1b;
}
</style>

<script>
const previewData = @json($analyse['preview_data']);
const header = @json($analyse['header']);
const veldLabels = @json(collect($veldInfo)->mapWithKeys(fn($v, $k) => [$k => $v['label']]));
const initDetectie = @json($analyse['detectie']);

const kolomMapping = {};
const multiVeldKolommen = {
    'naam': []
};

document.addEventListener('DOMContentLoaded', function() {
    for (const [veld, info] of Object.entries(initDetectie)) {
        if (info.csv_index !== null) {
            kolomMapping[info.csv_index] = veld;
            if (veld === 'naam') {
                multiVeldKolommen['naam'].push(info.csv_index);
            }
        }
    }

    updateUnclaimedChips();
    updateAlleVoorbeelden();
    setupDragDrop();
});

function createChip(kolomIndex, isUnclaimed = false) {
    const chip = document.createElement('div');
    chip.className = `kolom-chip cursor-move ${isUnclaimed ? 'bg-gray-500' : 'bg-blue-500'} text-white px-3 py-1 rounded inline-flex items-center gap-2`;
    chip.draggable = true;
    chip.dataset.index = kolomIndex;
    chip.innerHTML = `<span class="drag-handle">\u2807</span><span class="kolom-naam">${header[kolomIndex]}</span>`;
    return chip;
}

function updateUnclaimedChips() {
    const container = document.getElementById('unclaimed-chips');
    container.innerHTML = '';

    for (let i = 0; i < header.length; i++) {
        if (kolomMapping[i] === undefined) {
            const chip = createChip(i, true);
            container.appendChild(chip);
            setupChipDrag(chip);
        }
    }
}

function setupDragDrop() {
    document.querySelectorAll('.kolom-chip').forEach(setupChipDrag);

    document.querySelectorAll('.drop-zone').forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const droppedIndex = parseInt(e.dataTransfer.getData('text/plain'));
            const targetVeld = this.closest('tr').dataset.veld;
            const isMulti = this.dataset.multi === 'true';

            const bronVeld = kolomMapping[droppedIndex];

            if (isMulti) {
                if (bronVeld === targetVeld) {
                    const dropX = e.clientX;
                    const chips = Array.from(this.querySelectorAll('.kolom-chip'));
                    const draggedChip = this.querySelector(`.kolom-chip[data-index="${droppedIndex}"]`);

                    let insertBefore = null;
                    for (const chip of chips) {
                        if (chip === draggedChip) continue;
                        const rect = chip.getBoundingClientRect();
                        if (dropX < rect.left + rect.width / 2) {
                            insertBefore = chip;
                            break;
                        }
                    }

                    if (insertBefore) {
                        this.insertBefore(draggedChip, insertBefore);
                    } else {
                        this.insertBefore(draggedChip, this.querySelector('input'));
                    }

                    const newOrder = Array.from(this.querySelectorAll('.kolom-chip'))
                        .map(c => parseInt(c.dataset.index));
                    multiVeldKolommen[targetVeld] = newOrder;

                    updateAlleInputs();
                    updateAlleVoorbeelden();
                    return;
                }

                if (bronVeld && bronVeld !== targetVeld) {
                    removeFromMultiVeld(bronVeld, droppedIndex);
                    const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                    const oldChip = bronZone?.querySelector(`.kolom-chip[data-index="${droppedIndex}"]`);
                    if (oldChip) oldChip.remove();
                }

                kolomMapping[droppedIndex] = targetVeld;
                if (!multiVeldKolommen[targetVeld]) multiVeldKolommen[targetVeld] = [];
                multiVeldKolommen[targetVeld].push(droppedIndex);

                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
                updateAlleInputs();
                updateAlleVoorbeelden();
                return;
            }

            const bestaandeChip = this.querySelector('.kolom-chip');
            const bestaandeIndex = bestaandeChip ? parseInt(bestaandeChip.dataset.index) : null;

            if (bestaandeIndex !== null && bronVeld) {
                kolomMapping[droppedIndex] = targetVeld;
                kolomMapping[bestaandeIndex] = bronVeld;

                const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                const gedropteChip = bronZone.querySelector('.kolom-chip');

                bronZone.insertBefore(bestaandeChip, bronZone.querySelector('input'));
                this.insertBefore(gedropteChip, this.querySelector('input'));
            } else if (bestaandeIndex !== null && !bronVeld) {
                delete kolomMapping[bestaandeIndex];
                kolomMapping[droppedIndex] = targetVeld;

                bestaandeChip.remove();

                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
            } else if (!bronVeld) {
                kolomMapping[droppedIndex] = targetVeld;

                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
            } else {
                kolomMapping[droppedIndex] = targetVeld;

                const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                const gedropteChip = bronZone.querySelector('.kolom-chip');
                this.insertBefore(gedropteChip, this.querySelector('input'));
            }

            updateAlleInputs();
            updateAlleVoorbeelden();
        });
    });

    const unclaimedContainer = document.getElementById('unclaimed-chips');
    unclaimedContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
    });
    unclaimedContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        const droppedIndex = parseInt(e.dataTransfer.getData('text/plain'));
        const bronVeld = kolomMapping[droppedIndex];

        if (bronVeld) {
            removeFromMultiVeld(bronVeld, droppedIndex);
            delete kolomMapping[droppedIndex];

            const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
            const chip = bronZone?.querySelector(`.kolom-chip[data-index="${droppedIndex}"]`);
            if (chip) chip.remove();

            updateUnclaimedChips();
            updateAlleInputs();
            updateAlleVoorbeelden();
        }
    });
}

function removeFromMultiVeld(veld, kolomIndex) {
    if (multiVeldKolommen[veld]) {
        const idx = multiVeldKolommen[veld].indexOf(kolomIndex);
        if (idx > -1) multiVeldKolommen[veld].splice(idx, 1);
    }
}

function setupChipDrag(chip) {
    chip.addEventListener('dragstart', function(e) {
        this.classList.add('dragging');
        e.dataTransfer.setData('text/plain', this.dataset.index);
        e.dataTransfer.effectAllowed = 'move';
    });

    chip.addEventListener('dragend', function() {
        this.classList.remove('dragging');
    });
}

function updateAlleInputs() {
    document.querySelectorAll('.mapping-row').forEach(row => {
        const veld = row.dataset.veld;
        const input = row.querySelector('.mapping-input');
        const isMulti = row.querySelector('.drop-zone')?.dataset.multi === 'true';

        if (isMulti && multiVeldKolommen[veld]?.length > 0) {
            input.value = multiVeldKolommen[veld].join(',');
        } else {
            let kolomIndex = null;
            for (const [idx, v] of Object.entries(kolomMapping)) {
                if (v === veld) {
                    kolomIndex = idx;
                    break;
                }
            }
            input.value = kolomIndex ?? '';
        }
    });
}

function updateAlleVoorbeelden() {
    document.querySelectorAll('.mapping-row').forEach(row => {
        const veld = row.dataset.veld;
        const voorbeeldCell = row.querySelector('.voorbeeld-data');
        const statusCell = row.querySelector('.status-cell');
        const isMulti = row.querySelector('.drop-zone')?.dataset.multi === 'true';

        if (isMulti && multiVeldKolommen[veld]?.length > 0) {
            const indices = multiVeldKolommen[veld];
            const waarden = [];
            for (let i = 0; i < Math.min(3, previewData.length); i++) {
                const parts = indices.map(idx => previewData[i][idx]).filter(v => v);
                if (parts.length > 0) waarden.push(parts.join(' '));
            }
            voorbeeldCell.textContent = waarden.length > 0 ? waarden.join(', ') : '-';

            const waarschuwing = valideerData(veld, waarden);
            if (waarschuwing) {
                statusCell.innerHTML = `<span class="text-yellow-600 font-bold text-xs">${waarschuwing}</span>`;
                row.classList.add('bg-yellow-50');
            } else {
                statusCell.innerHTML = '<span class="text-green-600">OK</span>';
                row.classList.remove('bg-yellow-50');
            }
            return;
        }

        let kolomIndex = null;
        for (const [idx, v] of Object.entries(kolomMapping)) {
            if (v === veld) {
                kolomIndex = parseInt(idx);
                break;
            }
        }

        if (kolomIndex === null) {
            voorbeeldCell.textContent = '-';
            statusCell.innerHTML = '<span class="text-gray-400">Niet gekoppeld</span>';
            row.classList.remove('bg-yellow-50');
        } else {
            const waarden = [];
            for (let i = 0; i < Math.min(3, previewData.length); i++) {
                let val = previewData[i][kolomIndex];
                if (val !== null && val !== '') {
                    if (veld === 'geboortejaar') val = extractJaar(val);
                    waarden.push(val);
                }
            }
            voorbeeldCell.textContent = waarden.length > 0 ? waarden.join(', ') : '-';

            const waarschuwing = valideerData(veld, waarden);
            if (waarschuwing) {
                statusCell.innerHTML = `<span class="text-yellow-600 font-bold text-xs">${waarschuwing}</span>`;
                row.classList.add('bg-yellow-50');
            } else {
                statusCell.innerHTML = '<span class="text-green-600">OK</span>';
                row.classList.remove('bg-yellow-50');
            }
        }
    });

    updatePreviewHighlights();
}

function extractJaar(val) {
    const str = String(val).trim();
    if (!str) return val;
    const thisYear = new Date().getFullYear();
    const toYyyy = (yy) => yy > 50 ? 1900 + yy : 2000 + yy;

    let clean = str.replace(/^[\(\[\{]+|[\)\]\}]+$/g, '').trim();
    clean = clean.replace(/^(\d+),(\d+)$/, '$1.$2');

    const num = parseFloat(clean);
    if (!isNaN(num) && String(clean).match(/^[\d.]+$/)) {
        const intVal = Math.floor(num);
        if (intVal < 100) return toYyyy(intVal);
        if (intVal > 30000 && intVal < 60000) {
            const excelEpoch = new Date(1899, 11, 30);
            return new Date(excelEpoch.getTime() + num * 86400000).getFullYear();
        }
        if (intVal >= 1950 && intVal <= thisYear) return intVal;
    }

    let norm = clean.replace(/\\/g, '/');
    norm = norm.replace(/\s*([-.\/])\s*/g, '$1');
    norm = norm.replace(/^(\d{1,4})\s+(\d{1,2})\s+(\d{2,4})$/, '$1/$2/$3');

    const match4 = norm.match(/\b(19\d{2}|20\d{2})\b/);
    if (match4) return parseInt(match4[1]);

    const matchEnd = norm.match(/^\d{1,2}[-\/.]\d{1,2}[-\/.](\d{2})$/);
    if (matchEnd) return toYyyy(parseInt(matchEnd[1]));

    const matchStart = norm.match(/^(\d{2})[-\/.]\d{1,2}[-\/.]\d{1,2}$/);
    if (matchStart) {
        const c = toYyyy(parseInt(matchStart[1]));
        if (c >= 1950 && c <= thisYear) return c;
    }

    const mYmd = norm.match(/^(19\d{2}|20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/);
    if (mYmd) return parseInt(mYmd[1]);
    const mDmy = norm.match(/^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(19\d{2}|20\d{2})$/);
    if (mDmy) return parseInt(mDmy[3]);
    const m6 = norm.match(/^(\d{2})(\d{2})(\d{2})$/);
    if (m6) {
        const dd = parseInt(m6[1]), mm = parseInt(m6[2]), yy = parseInt(m6[3]);
        if (dd >= 1 && dd <= 31 && mm >= 1 && mm <= 12) return toYyyy(yy);
        if (mm >= 1 && mm <= 12 && yy >= 1 && yy <= 31) return toYyyy(dd);
    }

    const nlToEn = {
        'januari':'january','februari':'february','maart':'march','april':'april',
        'mei':'may','juni':'june','juli':'july','augustus':'august',
        'september':'september','oktober':'october','november':'november','december':'december',
        'mrt':'mar','okt':'oct'
    };
    let translated = norm.toLowerCase().replace(/(\d+)\s*(ste|de|e)\b/g, '$1');
    for (const [nl, en] of Object.entries(nlToEn)) {
        translated = translated.replace(nl, en);
    }
    const tsDate = new Date(translated);
    if (!isNaN(tsDate.getTime())) {
        const y = tsDate.getFullYear();
        if (y >= 1950 && y <= thisYear) return y;
    }
    return val;
}

function valideerData(veld, waarden) {
    if (waarden.length === 0) return 'Geen data';

    for (const val of waarden) {
        if (veld === 'geboortejaar') {
            const jaar = extractJaar(val);
            if (typeof jaar !== 'number' || jaar < 1950 || jaar > new Date().getFullYear()) {
                return `Verwacht jaar of datum, gevonden: ${val}`;
            }
        }
        if (veld === 'geslacht') {
            const v = String(val).toUpperCase().trim();
            if (!['M', 'V', 'J', 'JONGEN', 'MEISJE', 'MAN', 'VROUW'].includes(v)) {
                return `Verwacht M/V, gevonden: ${val}`;
            }
        }
        if (veld === 'gewicht') {
            const num = parseFloat(String(val).replace(',', '.'));
            if (isNaN(num) || num < 10 || num > 200) {
                return `Verwacht gewicht, gevonden: ${val}`;
            }
        }
    }
    return null;
}

function updatePreviewHighlights() {
    document.querySelectorAll('.preview-header, .preview-cell, .mapped-to-cell').forEach(el => {
        el.classList.remove('highlighted', 'unused');
    });
    document.querySelectorAll('.mapped-to').forEach(el => {
        el.textContent = '-';
    });

    const kolomNaarVeld = {};
    for (const [kolomIndex, veld] of Object.entries(kolomMapping)) {
        kolomNaarVeld[kolomIndex] = veld;
    }

    for (let i = 0; i < header.length; i++) {
        const veld = kolomNaarVeld[i];
        const headerCell = document.querySelector(`.preview-header[data-col="${i}"]`);
        const mappedToCell = document.querySelector(`.mapped-to-cell[data-col="${i}"]`);
        const mappedToLabel = mappedToCell?.querySelector('.mapped-to');

        if (veld) {
            headerCell?.classList.add('highlighted');
            mappedToCell?.classList.add('highlighted');
            if (mappedToLabel) {
                mappedToLabel.textContent = veldLabels[veld];
            }
        } else {
            mappedToCell?.classList.add('unused');
            if (mappedToLabel) {
                mappedToLabel.textContent = 'Ongebruikt';
            }
        }

        const previewCells = document.querySelectorAll(`.preview-cell[data-col="${i}"]`);
        previewCells.forEach(cell => {
            if (veld === 'geboortejaar') {
                const raw = cell.dataset.raw ?? cell.textContent;
                cell.dataset.raw = raw;
                const jaar = extractJaar(raw.trim());
                cell.textContent = (typeof jaar === 'number') ? jaar : raw;
            } else if (cell.dataset.raw) {
                cell.textContent = cell.dataset.raw;
            }
        });
    }
}
</script>
@endsection
