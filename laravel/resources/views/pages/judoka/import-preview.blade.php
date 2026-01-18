@extends('layouts.app')

@section('title', 'Import Preview')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Import Preview</h1>

    @php
        $veldInfo = [
            'naam' => ['label' => 'Naam', 'verplicht' => true, 'uitleg' => 'Volledige naam judoka'],
            'club' => ['label' => 'Club', 'verplicht' => false, 'uitleg' => 'Vereniging/sportclub'],
            'geboortejaar' => ['label' => 'Geboortejaar', 'verplicht' => true, 'uitleg' => 'Bijv. 2015'],
            'geslacht' => ['label' => 'Geslacht', 'verplicht' => true, 'uitleg' => 'M of V'],
            'gewicht' => ['label' => 'Gewicht', 'verplicht' => false, 'uitleg' => 'In kg, bijv. 32.5'],
            'band' => ['label' => 'Band', 'verplicht' => false, 'uitleg' => 'Wit, Geel, Oranje, etc.'],
            'gewichtsklasse' => ['label' => 'Gewichtsklasse', 'verplicht' => false, 'uitleg' => '-30, +60, etc.'],
        ];
        $heeftWaarschuwingen = collect($analyse['detectie'])->contains(fn($d) => $d['waarschuwing']);
    @endphp

    {{-- Status --}}
    @if($heeftWaarschuwingen)
        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6">
            <p class="text-yellow-800 font-bold">Er zijn problemen gevonden. Sleep de kolom-knoppen om te corrigeren.</p>
        </div>
    @else
        <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700">Alle kolommen automatisch herkend. Controleer of het klopt.</p>
        </div>
    @endif

    <form action="{{ route('toernooi.judoka.import.confirm', $toernooi) }}" method="POST" id="import-form">
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
                            <td class="px-3 py-2 border drop-zone">
                                @if($detectie['csv_index'] !== null)
                                    <div class="kolom-chip cursor-move bg-blue-500 text-white px-3 py-1 rounded inline-flex items-center gap-2"
                                         draggable="true"
                                         data-index="{{ $detectie['csv_index'] }}">
                                        <span class="drag-handle">⠿</span>
                                        <span class="kolom-naam">{{ $analyse['header'][$detectie['csv_index']] }}</span>
                                    </div>
                                @endif
                                <input type="hidden" name="mapping[{{ $veld }}]" value="{{ $detectie['csv_index'] }}" class="mapping-input">
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
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-1 border text-left">#</th>
                            @foreach($analyse['header'] as $index => $kolom)
                                <th class="px-2 py-1 text-left border preview-header" data-col="{{ $index }}">
                                    {{ $kolom }}
                                    <div class="mapped-to text-xs font-normal text-green-600"></div>
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
        <div class="flex justify-between items-center">
            <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="text-gray-600 hover:text-gray-800">
                ← Ander bestand uploaden
            </a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded text-lg">
                Importeer {{ $analyse['totaal_rijen'] }} judoka's
            </button>
        </div>
    </form>
</div>

<style>
.kolom-chip {
    user-select: none;
    transition: all 0.15s;
}
.kolom-chip:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.kolom-chip.dragging {
    opacity: 0.5;
}
.kolom-chip.unclaimed {
    background-color: #6b7280 !important;
}
.drop-zone {
    min-height: 40px;
    transition: all 0.15s;
}
.drop-zone.drag-over {
    background-color: #dbeafe;
    outline: 2px dashed #3b82f6;
}
.preview-header.highlighted, .preview-cell.highlighted {
    background-color: #dcfce7;
}
</style>

<script>
const previewData = @json($analyse['preview_data']);
const header = @json($analyse['header']);
const veldLabels = @json(collect($veldInfo)->mapWithKeys(fn($v, $k) => [$k => $v['label']]));
const initDetectie = @json($analyse['detectie']);

// Track welke kolom waar staat: kolomIndex -> veld (of null)
const kolomMapping = {};

// Init
document.addEventListener('DOMContentLoaded', function() {
    // Zet initiële mapping
    for (const [veld, info] of Object.entries(initDetectie)) {
        if (info.csv_index !== null) {
            kolomMapping[info.csv_index] = veld;
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
    chip.innerHTML = `<span class="drag-handle">⠿</span><span class="kolom-naam">${header[kolomIndex]}</span>`;
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
    // Setup bestaande chips
    document.querySelectorAll('.kolom-chip').forEach(setupChipDrag);

    // Setup drop zones
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

            // Welke chip zit hier al?
            const bestaandeChip = this.querySelector('.kolom-chip');
            const bestaandeIndex = bestaandeChip ? parseInt(bestaandeChip.dataset.index) : null;

            // Waar komt de gedropte chip vandaan?
            const bronVeld = kolomMapping[droppedIndex];

            // Swap logica
            if (bestaandeIndex !== null && bronVeld) {
                // Beide hebben een chip -> swap
                kolomMapping[droppedIndex] = targetVeld;
                kolomMapping[bestaandeIndex] = bronVeld;

                // Verplaats chips visueel
                const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                const gedropteChip = bronZone.querySelector('.kolom-chip');

                bronZone.insertBefore(bestaandeChip, bronZone.querySelector('input'));
                this.insertBefore(gedropteChip, this.querySelector('input'));
            } else if (bestaandeIndex !== null && !bronVeld) {
                // Drop van unclaimed naar bezette zone -> verplaats bestaande naar unclaimed
                delete kolomMapping[bestaandeIndex];
                kolomMapping[droppedIndex] = targetVeld;

                // Verwijder bestaande chip
                bestaandeChip.remove();

                // Maak nieuwe chip van gedropte
                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
            } else if (!bronVeld) {
                // Drop van unclaimed naar lege zone
                kolomMapping[droppedIndex] = targetVeld;

                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
            } else {
                // Drop van andere zone naar lege zone
                kolomMapping[droppedIndex] = targetVeld;

                const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                const gedropteChip = bronZone.querySelector('.kolom-chip');
                this.insertBefore(gedropteChip, this.querySelector('input'));
            }

            updateAlleInputs();
            updateAlleVoorbeelden();
        });
    });

    // Drop zone voor unclaimed
    const unclaimedContainer = document.getElementById('unclaimed-chips');
    unclaimedContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
    });
    unclaimedContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        const droppedIndex = parseInt(e.dataTransfer.getData('text/plain'));
        const bronVeld = kolomMapping[droppedIndex];

        if (bronVeld) {
            delete kolomMapping[droppedIndex];
            const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
            const chip = bronZone.querySelector('.kolom-chip');
            if (chip) chip.remove();

            updateUnclaimedChips();
            updateAlleInputs();
            updateAlleVoorbeelden();
        }
    });
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

        // Vind welke kolom bij dit veld hoort
        let kolomIndex = null;
        for (const [idx, v] of Object.entries(kolomMapping)) {
            if (v === veld) {
                kolomIndex = idx;
                break;
            }
        }
        input.value = kolomIndex ?? '';
    });
}

function updateAlleVoorbeelden() {
    document.querySelectorAll('.mapping-row').forEach(row => {
        const veld = row.dataset.veld;
        const voorbeeldCell = row.querySelector('.voorbeeld-data');
        const statusCell = row.querySelector('.status-cell');

        // Vind welke kolom bij dit veld hoort
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
            // Haal voorbeeld data
            const waarden = [];
            for (let i = 0; i < Math.min(3, previewData.length); i++) {
                const val = previewData[i][kolomIndex];
                if (val !== null && val !== '') waarden.push(val);
            }
            voorbeeldCell.textContent = waarden.length > 0 ? waarden.join(', ') : '-';

            // Valideer
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

function valideerData(veld, waarden) {
    if (waarden.length === 0) return 'Geen data';

    for (const val of waarden) {
        if (veld === 'geboortejaar') {
            const num = parseInt(val);
            if (isNaN(num) || num < 1950 || num > 2026) {
                return `Verwacht jaar, gevonden: ${val}`;
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
    // Reset
    document.querySelectorAll('.preview-header, .preview-cell').forEach(el => {
        el.classList.remove('highlighted');
    });
    document.querySelectorAll('.mapped-to').forEach(el => {
        el.textContent = '';
    });

    // Highlight gemapte kolommen
    for (const [kolomIndex, veld] of Object.entries(kolomMapping)) {
        document.querySelectorAll(`[data-col="${kolomIndex}"]`).forEach(el => {
            el.classList.add('highlighted');
        });
        const label = document.querySelector(`.preview-header[data-col="${kolomIndex}"] .mapped-to`);
        if (label) {
            label.textContent = '→ ' + veldLabels[veld];
        }
    }
}
</script>
@endsection
