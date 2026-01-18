@extends('layouts.app')

@section('title', 'Import Preview')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Import Preview</h1>
    <p class="text-gray-600 mb-6">Sleep de CSV kolommen naar het juiste database veld.</p>

    @php
        $veldInfo = [
            'naam' => ['label' => 'Naam', 'verplicht' => true],
            'club' => ['label' => 'Club', 'verplicht' => false],
            'geboortejaar' => ['label' => 'Geboortejaar', 'verplicht' => true],
            'geslacht' => ['label' => 'Geslacht (M/V)', 'verplicht' => true],
            'gewicht' => ['label' => 'Gewicht (kg)', 'verplicht' => false],
            'band' => ['label' => 'Band', 'verplicht' => false],
            'gewichtsklasse' => ['label' => 'Gewichtsklasse', 'verplicht' => false],
        ];
    @endphp

    <form action="{{ route('toernooi.judoka.import.confirm', $toernooi) }}" method="POST" id="import-form">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Links: Database velden (drop zones) --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-700">Database velden</h2>
                <p class="text-sm text-gray-500 mb-4">Sleep CSV kolommen hierheen</p>

                <div class="space-y-3">
                    @foreach($veldInfo as $veld => $info)
                        <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-3 min-h-[60px] transition-all"
                             data-veld="{{ $veld }}">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-700">
                                    {{ $info['label'] }}
                                    @if($info['verplicht'])<span class="text-red-500">*</span>@endif
                                </span>
                                <span class="dropped-info text-sm text-gray-400"></span>
                            </div>
                            <input type="hidden" name="mapping[{{ $veld }}]" value="" class="mapping-input">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Rechts: CSV kolommen (draggables) --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-700">CSV kolommen uit je bestand</h2>
                <p class="text-sm text-gray-500 mb-4">{{ $analyse['totaal_rijen'] }} rijen gevonden</p>

                <div class="space-y-2" id="csv-kolommen">
                    @foreach($analyse['header'] as $index => $kolom)
                        @php
                            $voorbeeldWaarden = [];
                            foreach (array_slice($analyse['preview_data'], 0, 3) as $row) {
                                $val = $row[$index] ?? '';
                                if ($val !== '') $voorbeeldWaarden[] = $val;
                            }
                        @endphp
                        <div class="csv-kolom draggable cursor-move bg-blue-50 border border-blue-200 rounded-lg p-3 hover:bg-blue-100 transition-all"
                             draggable="true"
                             data-kolom-index="{{ $index }}"
                             data-kolom-naam="{{ $kolom }}">
                            <div class="flex items-start gap-3">
                                <span class="text-blue-400 mt-1">⠿</span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-blue-800">{{ $kolom }}</div>
                                    <div class="text-sm text-gray-600 truncate font-mono">
                                        {{ implode(', ', $voorbeeldWaarden) ?: '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preview tabel --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Bestand Preview</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border" id="preview-table">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-1 border text-left">#</th>
                            @foreach($analyse['header'] as $index => $kolom)
                                <th class="px-2 py-1 text-left border preview-header" data-col="{{ $index }}">
                                    <span class="kolom-naam">{{ $kolom }}</span>
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
.drop-zone.drag-over {
    border-color: #3b82f6;
    background-color: #eff6ff;
}
.drop-zone.has-item {
    border-style: solid;
    border-color: #10b981;
    background-color: #ecfdf5;
}
.draggable.dragging {
    opacity: 0.5;
}
.csv-kolom.placed {
    opacity: 0.4;
    cursor: default;
}
.preview-header.highlighted {
    background-color: #dcfce7;
}
.preview-cell.highlighted {
    background-color: #dcfce7;
}
</style>

<script>
const detectie = @json($analyse['detectie']);
const header = @json($analyse['header']);

// Track welke kolom waar geplaatst is
const plaatsingen = {}; // veld -> kolomIndex
const kolomPlaatsingen = {}; // kolomIndex -> veld

// Init: plaats automatisch gedetecteerde kolommen
document.addEventListener('DOMContentLoaded', function() {
    for (const [veld, info] of Object.entries(detectie)) {
        if (info.csv_index !== null) {
            plaatsKolom(veld, info.csv_index);
        }
    }
});

function plaatsKolom(veld, kolomIndex) {
    const dropZone = document.querySelector(`.drop-zone[data-veld="${veld}"]`);
    const csvKolom = document.querySelector(`.csv-kolom[data-kolom-index="${kolomIndex}"]`);

    if (!dropZone || !csvKolom) return;

    // Verwijder eventuele oude plaatsing van dit veld
    if (plaatsingen[veld] !== undefined) {
        const oudeKolom = document.querySelector(`.csv-kolom[data-kolom-index="${plaatsingen[veld]}"]`);
        if (oudeKolom) {
            oudeKolom.classList.remove('placed');
            oudeKolom.setAttribute('draggable', 'true');
        }
        delete kolomPlaatsingen[plaatsingen[veld]];
    }

    // Verwijder eventuele oude plaatsing van deze kolom
    if (kolomPlaatsingen[kolomIndex] !== undefined) {
        const oudeZone = document.querySelector(`.drop-zone[data-veld="${kolomPlaatsingen[kolomIndex]}"]`);
        if (oudeZone) {
            oudeZone.classList.remove('has-item');
            oudeZone.querySelector('.dropped-info').innerHTML = '';
            oudeZone.querySelector('.mapping-input').value = '';
        }
        delete plaatsingen[kolomPlaatsingen[kolomIndex]];
    }

    // Plaats kolom in drop zone
    plaatsingen[veld] = kolomIndex;
    kolomPlaatsingen[kolomIndex] = veld;

    // Update UI
    const kolomNaam = csvKolom.dataset.kolomNaam;
    const voorbeeldData = csvKolom.querySelector('.font-mono').textContent;

    dropZone.classList.add('has-item');
    dropZone.querySelector('.dropped-info').innerHTML = `
        <div class="flex items-center gap-2">
            <span class="font-bold text-green-700">${kolomNaam}</span>
            <button type="button" class="text-red-500 hover:text-red-700" onclick="verwijderPlaatsing('${veld}')">✕</button>
        </div>
        <div class="text-xs text-gray-500 font-mono">${voorbeeldData}</div>
    `;
    dropZone.querySelector('.mapping-input').value = kolomIndex;

    csvKolom.classList.add('placed');
    csvKolom.setAttribute('draggable', 'false');

    // Update preview tabel
    updatePreviewHighlights();
}

function verwijderPlaatsing(veld) {
    const dropZone = document.querySelector(`.drop-zone[data-veld="${veld}"]`);
    const kolomIndex = plaatsingen[veld];

    if (kolomIndex !== undefined) {
        const csvKolom = document.querySelector(`.csv-kolom[data-kolom-index="${kolomIndex}"]`);
        if (csvKolom) {
            csvKolom.classList.remove('placed');
            csvKolom.setAttribute('draggable', 'true');
        }
        delete kolomPlaatsingen[kolomIndex];
    }

    delete plaatsingen[veld];

    dropZone.classList.remove('has-item');
    dropZone.querySelector('.dropped-info').innerHTML = '';
    dropZone.querySelector('.mapping-input').value = '';

    updatePreviewHighlights();
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
    for (const [veld, kolomIndex] of Object.entries(plaatsingen)) {
        document.querySelectorAll(`[data-col="${kolomIndex}"]`).forEach(el => {
            el.classList.add('highlighted');
        });
        const headerLabel = document.querySelector(`.preview-header[data-col="${kolomIndex}"] .mapped-to`);
        if (headerLabel) {
            headerLabel.textContent = '→ ' + veld;
        }
    }
}

// Drag and drop
let draggedElement = null;

document.querySelectorAll('.draggable').forEach(el => {
    el.addEventListener('dragstart', function(e) {
        if (this.classList.contains('placed')) {
            e.preventDefault();
            return;
        }
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    el.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        draggedElement = null;
    });
});

document.querySelectorAll('.drop-zone').forEach(zone => {
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        this.classList.add('drag-over');
    });

    zone.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });

    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');

        if (draggedElement) {
            const veld = this.dataset.veld;
            const kolomIndex = parseInt(draggedElement.dataset.kolomIndex);
            plaatsKolom(veld, kolomIndex);
        }
    });
});
</script>
@endsection
