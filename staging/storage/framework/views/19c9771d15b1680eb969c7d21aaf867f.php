<?php $__env->startSection('title', __('Import Preview')); ?>

<?php $__env->startSection('content'); ?>
<?php
    // Check of toernooi vaste gewichtsklassen heeft
    // Vaste = categorie heeft niet-lege 'gewichten' array (bijv. ['-22', '-25', ...])
    // Dynamisch = categorie heeft max_kg_verschil > 0 EN lege gewichten array
    $config = $toernooi->gewichtsklassen ?? [];

    // Filter metadata keys (beginnen met _) - alleen echte categorieën
    $categorieen = collect($config)->filter(fn($cat, $key) => !str_starts_with($key, '_') && is_array($cat));

    // Simpele check: heeft MINSTENS 1 categorie een niet-lege gewichten array?
    $heeftVasteGewichtsklassen = $categorieen->contains(fn($cat) => !empty($cat['gewichten'] ?? []));

    // Check configuratiefout: Δkg=0 maar geen gewichtsklassen ingevuld
    $foutiefGeconfigureerdeCategorieen = $categorieen->filter(function($cat) {
        $maxKg = $cat['max_kg_verschil'] ?? 0;
        $gewichten = $cat['gewichten'] ?? [];
        // Fout: Δkg=0 (vaste klassen) maar geen gewichten ingevuld
        return $maxKg == 0 && empty($gewichten);
    })->map(fn($cat) => $cat['label'] ?? 'Onbekend')->values()->all();

    $veldInfo = [
        'naam' => ['label' => __('Naam'), 'verplicht' => true, 'uitleg' => __('Volledige naam judoka')],
        'club' => ['label' => __('Club'), 'verplicht' => false, 'uitleg' => __('Vereniging/sportclub')],
        'geboortejaar' => ['label' => __('Geboortejaar'), 'verplicht' => true, 'uitleg' => __('Bijv. 2015')],
        'geslacht' => ['label' => __('Geslacht'), 'verplicht' => true, 'uitleg' => __('M of V')],
        'gewicht' => ['label' => __('Gewicht'), 'verplicht' => false, 'uitleg' => __('In kg, bijv. 32.5')],
        'band' => ['label' => __('Band'), 'verplicht' => false, 'uitleg' => __('Wit, Geel, Oranje, etc.')],
    ];

    // Gewichtsklasse veld alleen tonen als toernooi vaste gewichtsklassen heeft
    if ($heeftVasteGewichtsklassen) {
        $veldInfo['gewichtsklasse'] = ['label' => __('Gewichtsklasse'), 'verplicht' => false, 'uitleg' => __('-30, +60, etc.')];
    }

    $heeftWaarschuwingen = collect($analyse['detectie'])->contains(fn($d) => $d['waarschuwing']);
    $gekoppeldeKolommen = collect($analyse['detectie'])->filter(fn($d) => $d['csv_index'] !== null)->count();
?>


<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
            <div class="text-2xl font-bold text-blue-600"><?php echo e($analyse['totaal_rijen']); ?></div>
            <div class="text-sm text-gray-600"><?php echo e(__("Judoka's")); ?></div>
        </div>
        <div>
            <div class="text-2xl font-bold text-green-600"><?php echo e(count($analyse['header'])); ?></div>
            <div class="text-sm text-gray-600"><?php echo e(__('CSV Kolommen')); ?></div>
        </div>
        <div>
            <div class="text-2xl font-bold text-purple-600"><?php echo e($gekoppeldeKolommen); ?></div>
            <div class="text-sm text-gray-600"><?php echo e(__('Gekoppeld')); ?></div>
        </div>
        <div>
            <div class="text-2xl font-bold <?php echo e($heeftWaarschuwingen ? 'text-orange-600' : 'text-green-600'); ?>">
                <?php echo e($heeftWaarschuwingen ? __('Check nodig') : __('OK')); ?>

            </div>
            <div class="text-sm text-gray-600"><?php echo e(__('Status')); ?></div>
        </div>
    </div>
</div>


<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?php echo e(__('Import Preview')); ?></h1>
        <p class="text-gray-600"><?php echo e(__('Controleer de kolom toewijzing voordat je importeert')); ?></p>
    </div>
    <a href="<?php echo e(route('toernooi.judoka.import', $toernooi->routeParams())); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
        ← <?php echo e(__('Ander bestand')); ?>

    </a>
</div>


<?php if(!empty($foutiefGeconfigureerdeCategorieen)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-6 rounded">
        <p class="text-red-800 font-medium"><?php echo e(__('Configuratiefout: de volgende categorieën hebben Δkg=0 maar geen gewichtsklassen ingevuld:')); ?></p>
        <p class="text-red-700 mt-1"><?php echo e(implode(', ', $foutiefGeconfigureerdeCategorieen)); ?></p>
        <p class="text-red-600 text-sm mt-2"><?php echo __('Ga naar <a href=":url" class="underline font-medium">toernooi instellingen</a> om dit te corrigeren.', ['url' => route('toernooi.edit', $toernooi->routeParams()) . '#categorieën']); ?></p>
    </div>
<?php endif; ?>


<?php if($heeftWaarschuwingen): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded">
        <p class="text-yellow-800 font-medium"><?php echo e(__('Er zijn problemen gevonden. Sleep de kolom-knoppen om te corrigeren.')); ?></p>
    </div>
<?php else: ?>
    <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-6 rounded">
        <p class="text-green-700 font-medium"><?php echo e(__('Alle kolommen automatisch herkend. Controleer of het klopt.')); ?></p>
    </div>
<?php endif; ?>

    <form action="<?php echo e(route('toernooi.judoka.import.confirm', $toernooi->routeParams())); ?>" method="POST" id="import-form" data-loading="<?php echo e(__("Judoka's importeren...")); ?>">
        <?php echo csrf_field(); ?>

        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold mb-2"><?php echo e(__('Kolom Toewijzing')); ?></h2>
            <p class="text-gray-600 mb-4"><?php echo e(__('Sleep een kolom-knop naar een andere rij om te verwisselen.')); ?></p>

            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left border w-40"><?php echo e(__('App veld')); ?></th>
                        <th class="px-3 py-2 text-left border w-48"><?php echo e(__('CSV kolom')); ?></th>
                        <th class="px-3 py-2 text-left border"><?php echo e(__('Voorbeeld data')); ?></th>
                        <th class="px-3 py-2 text-left border w-48"><?php echo e(__('Status')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $veldInfo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $veld => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $detectie = $analyse['detectie'][$veld] ?? ['csv_index' => null, 'waarschuwing' => null];
                        ?>
                        <tr class="mapping-row <?php echo e($detectie['waarschuwing'] ? 'bg-yellow-50' : ''); ?>" data-veld="<?php echo e($veld); ?>">
                            <td class="px-3 py-2 border">
                                <strong><?php echo e($info['label']); ?></strong>
                                <?php if($info['verplicht']): ?><span class="text-red-500">*</span><?php endif; ?>
                                <br>
                                <span class="text-xs text-gray-500"><?php echo e($info['uitleg']); ?></span>
                            </td>
                            <td class="px-3 py-2 border drop-zone <?php echo e($veld === 'naam' ? 'multi-drop' : ''); ?>" data-multi="<?php echo e($veld === 'naam' ? 'true' : 'false'); ?>">
                                <?php if($detectie['csv_index'] !== null): ?>
                                    <div class="kolom-chip cursor-move bg-blue-500 text-white px-3 py-1 rounded inline-flex items-center gap-2"
                                         draggable="true"
                                         data-index="<?php echo e($detectie['csv_index']); ?>">
                                        <span class="drag-handle">⠿</span>
                                        <span class="kolom-naam"><?php echo e($analyse['header'][$detectie['csv_index']]); ?></span>
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" name="mapping[<?php echo e($veld); ?>]" value="<?php echo e($detectie['csv_index']); ?>" class="mapping-input">
                                <?php if($veld === 'naam'): ?>
                                <span class="text-xs text-gray-400 ml-2 multi-hint">(<?php echo e(__('sleep meerdere kolommen')); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border font-mono text-xs">
                                <span class="voorbeeld-data">-</span>
                            </td>
                            <td class="px-3 py-2 border status-cell">
                                <?php if($detectie['waarschuwing']): ?>
                                    <span class="text-yellow-600 font-bold text-xs"><?php echo e($detectie['waarschuwing']); ?></span>
                                <?php elseif($detectie['csv_index'] !== null): ?>
                                    <span class="text-green-600"><?php echo e(__('OK')); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400"><?php echo e(__('Niet gekoppeld')); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>

            
            <div class="mt-4 pt-4 border-t">
                <p class="text-sm text-gray-600 mb-2"><?php echo e(__('Niet-gekoppelde CSV kolommen (sleep naar een veld hierboven):')); ?></p>
                <div id="unclaimed-chips" class="flex flex-wrap gap-2">
                    
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-2"><?php echo e(__('Bestand Preview')); ?></h2>
            <p class="text-gray-600 mb-4"><?php echo e(__('Eerste 5 regels van je bestand (:totaal rijen totaal)', ['totaal' => $analyse['totaal_rijen']])); ?></p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border" id="preview-table">
                    <thead>
                        
                        <tr class="bg-gray-200">
                            <th class="px-2 py-1 border text-left text-xs text-gray-500" rowspan="2">#</th>
                            <?php $__currentLoopData = $analyse['header']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $kolom): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <th class="px-2 py-1 text-left border preview-header" data-col="<?php echo e($index); ?>">
                                    <span class="text-xs text-gray-500 block"><?php echo e(__('CSV kolom:')); ?></span>
                                    <?php echo e($kolom); ?>

                                </th>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>
                        
                        <tr class="bg-gray-100">
                            <?php $__currentLoopData = $analyse['header']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $kolom): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <th class="px-2 py-1 text-left border mapped-to-cell" data-col="<?php echo e($index); ?>">
                                    <span class="text-xs text-gray-500 block"><?php echo e(__('App veld:')); ?></span>
                                    <span class="mapped-to text-gray-400">-</span>
                                </th>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $analyse['preview_data']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rowIndex => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-2 py-1 border text-gray-400"><?php echo e($rowIndex + 1); ?></td>
                                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $colIndex => $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <td class="px-2 py-1 border preview-cell" data-col="<?php echo e($colIndex); ?>"><?php echo e($cell ?? ''); ?></td>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow p-4 flex justify-end">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                <?php echo e(__("Importeer :aantal judoka's", ['aantal' => $analyse['totaal_rijen']])); ?>

            </button>
        </div>
    </form>

<style>
/* Sleep knoppen - professioneel, neutraal */
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

/* Preview tabel - professioneel */
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
const previewData = <?php echo json_encode($analyse['preview_data'], 15, 512) ?>;
const header = <?php echo json_encode($analyse['header'], 15, 512) ?>;
const veldLabels = <?php echo json_encode(collect($veldInfo)->mapWithKeys(fn($v, $k) => [$k => $v['label']]), 512) ?>;
const initDetectie = <?php echo json_encode($analyse['detectie'], 15, 512) ?>;

// Track welke kolom waar staat: kolomIndex -> veld (of null)
// Voor multi-velden (naam): meerdere kolommen kunnen naar hetzelfde veld wijzen
const kolomMapping = {};

// Track volgorde van kolommen voor multi-velden
const multiVeldKolommen = {
    'naam': [] // Array van kolomIndexen in volgorde
};

// Init
document.addEventListener('DOMContentLoaded', function() {
    // Zet initiële mapping
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
            const isMulti = this.dataset.multi === 'true';

            // Waar komt de gedropte chip vandaan?
            const bronVeld = kolomMapping[droppedIndex];

            // Multi-veld logica (naam): toevoegen aan lijst of herordenen
            if (isMulti) {
                // Als chip al in DIT veld zit -> herordenen
                if (bronVeld === targetVeld) {
                    // Bepaal nieuwe positie op basis van drop locatie
                    const dropX = e.clientX;
                    const chips = Array.from(this.querySelectorAll('.kolom-chip'));
                    const draggedChip = this.querySelector(`.kolom-chip[data-index="${droppedIndex}"]`);

                    // Vind insert positie
                    let insertBefore = null;
                    for (const chip of chips) {
                        if (chip === draggedChip) continue;
                        const rect = chip.getBoundingClientRect();
                        if (dropX < rect.left + rect.width / 2) {
                            insertBefore = chip;
                            break;
                        }
                    }

                    // Verplaats chip visueel
                    if (insertBefore) {
                        this.insertBefore(draggedChip, insertBefore);
                    } else {
                        this.insertBefore(draggedChip, this.querySelector('input'));
                    }

                    // Update array volgorde
                    const newOrder = Array.from(this.querySelectorAll('.kolom-chip'))
                        .map(c => parseInt(c.dataset.index));
                    multiVeldKolommen[targetVeld] = newOrder;

                    updateAlleInputs();
                    updateAlleVoorbeelden();
                    return;
                }

                // Verwijder uit oude locatie als nodig
                if (bronVeld && bronVeld !== targetVeld) {
                    removeFromMultiVeld(bronVeld, droppedIndex);
                    const bronZone = document.querySelector(`tr[data-veld="${bronVeld}"] .drop-zone`);
                    const oldChip = bronZone?.querySelector(`.kolom-chip[data-index="${droppedIndex}"]`);
                    if (oldChip) oldChip.remove();
                }

                // Voeg toe aan multi veld
                kolomMapping[droppedIndex] = targetVeld;
                if (!multiVeldKolommen[targetVeld]) multiVeldKolommen[targetVeld] = [];
                multiVeldKolommen[targetVeld].push(droppedIndex);

                // Maak chip
                const newChip = createChip(droppedIndex);
                this.insertBefore(newChip, this.querySelector('input'));
                setupChipDrag(newChip);

                updateUnclaimedChips();
                updateAlleInputs();
                updateAlleVoorbeelden();
                return;
            }

            // Standaard single-veld logica
            const bestaandeChip = this.querySelector('.kolom-chip');
            const bestaandeIndex = bestaandeChip ? parseInt(bestaandeChip.dataset.index) : null;

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
            // Verwijder uit multi-veld tracking
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
            // Multi-veld: stuur komma-gescheiden lijst van kolom indices
            input.value = multiVeldKolommen[veld].join(',');
        } else {
            // Single veld: vind welke kolom bij dit veld hoort
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

        // Multi-veld: combineer meerdere kolommen
        if (isMulti && multiVeldKolommen[veld]?.length > 0) {
            const indices = multiVeldKolommen[veld];
            const waarden = [];
            for (let i = 0; i < Math.min(3, previewData.length); i++) {
                // Combineer kolommen met spatie
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

        // Single veld logica
        let kolomIndex = null;
        for (const [idx, v] of Object.entries(kolomMapping)) {
            if (v === veld) {
                kolomIndex = parseInt(idx);
                break;
            }
        }

        if (kolomIndex === null) {
            voorbeeldCell.textContent = '-';
            statusCell.innerHTML = '<span class="text-gray-400"><?php echo e(__("Niet gekoppeld")); ?></span>';
            row.classList.remove('bg-yellow-50');
        } else {
            // Haal voorbeeld data
            const waarden = [];
            for (let i = 0; i < Math.min(3, previewData.length); i++) {
                let val = previewData[i][kolomIndex];
                if (val !== null && val !== '') {
                    // Convert dates to year for geboortejaar field
                    if (veld === 'geboortejaar') val = extractJaar(val);
                    waarden.push(val);
                }
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

function extractJaar(val) {
    const str = String(val).trim();
    if (!str) return val;
    const thisYear = new Date().getFullYear();
    const toYyyy = (yy) => yy > 50 ? 1900 + yy : 2000 + yy;

    // --- Phase 1: Clean up ---
    // Strip parentheses/brackets: (2015) → 2015, [24-01-2015] → 24-01-2015
    let clean = str.replace(/^[\(\[\{]+|[\)\]\}]+$/g, '').trim();
    // European comma decimal for Excel serials: 43831,5 → 43831.5
    clean = clean.replace(/^(\d+),(\d+)$/, '$1.$2');

    // --- Phase 2: Numeric values ---
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

    // --- Phase 3: Normalize string separators ---
    let norm = clean.replace(/\\/g, '/');
    // Spaces around separators: "24 - 01 - 2015" → "24-01-2015"
    norm = norm.replace(/\s*([-.\/])\s*/g, '$1');
    // Space-only separators: "24 01 2015" → "24/01/2015"
    norm = norm.replace(/^(\d{1,4})\s+(\d{1,2})\s+(\d{2,4})$/, '$1/$2/$3');

    // --- Phase 4: 4-digit year in any string ---
    const match4 = norm.match(/\b(19\d{2}|20\d{2})\b/);
    if (match4) return parseInt(match4[1]);

    // --- Phase 5: Date with 2-digit year at end (dd-mm-yy) ---
    const matchEnd = norm.match(/^\d{1,2}[-\/.]\d{1,2}[-\/.](\d{2})$/);
    if (matchEnd) return toYyyy(parseInt(matchEnd[1]));

    // --- Phase 6: Date with 2-digit year at start (yy-mm-dd) ---
    const matchStart = norm.match(/^(\d{2})[-\/.]\d{1,2}[-\/.]\d{1,2}$/);
    if (matchStart) {
        const c = toYyyy(parseInt(matchStart[1]));
        if (c >= 1950 && c <= thisYear) return c;
    }

    // --- Phase 7: Compact dates without separators ---
    // YYYYMMDD: 20150124
    const mYmd = norm.match(/^(19\d{2}|20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/);
    if (mYmd) return parseInt(mYmd[1]);
    // DDMMYYYY: 24012015
    const mDmy = norm.match(/^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(19\d{2}|20\d{2})$/);
    if (mDmy) return parseInt(mDmy[3]);
    // DDMMYY: 240115 (6 digits)
    const m6 = norm.match(/^(\d{2})(\d{2})(\d{2})$/);
    if (m6) {
        const dd = parseInt(m6[1]), mm = parseInt(m6[2]), yy = parseInt(m6[3]);
        if (dd >= 1 && dd <= 31 && mm >= 1 && mm <= 12) return toYyyy(yy);
        if (mm >= 1 && mm <= 12 && yy >= 1 && yy <= 31) return toYyyy(dd);
    }

    // --- Phase 8: Dutch month names + ordinals ---
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
            // Run extractJaar - if it returns a valid year, it's OK
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
    // Reset all
    document.querySelectorAll('.preview-header, .preview-cell, .mapped-to-cell').forEach(el => {
        el.classList.remove('highlighted', 'unused');
    });
    document.querySelectorAll('.mapped-to').forEach(el => {
        el.textContent = '-';
    });

    // Build reverse lookup: kolomIndex -> veld
    const kolomNaarVeld = {};
    for (const [kolomIndex, veld] of Object.entries(kolomMapping)) {
        kolomNaarVeld[kolomIndex] = veld;
    }

    // Update each column
    for (let i = 0; i < header.length; i++) {
        const veld = kolomNaarVeld[i];
        const headerCell = document.querySelector(`.preview-header[data-col="${i}"]`);
        const mappedToCell = document.querySelector(`.mapped-to-cell[data-col="${i}"]`);
        const mappedToLabel = mappedToCell?.querySelector('.mapped-to');

        if (veld) {
            // Column is mapped
            headerCell?.classList.add('highlighted');
            mappedToCell?.classList.add('highlighted');
            if (mappedToLabel) {
                mappedToLabel.textContent = veldLabels[veld];
            }
        } else {
            // Column is unused
            mappedToCell?.classList.add('unused');
            if (mappedToLabel) {
                mappedToLabel.textContent = 'Ongebruikt';
            }
        }

        // Convert geboortejaar cells in preview table to show year
        const previewCells = document.querySelectorAll(`.preview-cell[data-col="${i}"]`);
        previewCells.forEach(cell => {
            if (veld === 'geboortejaar') {
                const raw = cell.dataset.raw ?? cell.textContent;
                cell.dataset.raw = raw; // Store original value
                const jaar = extractJaar(raw.trim());
                cell.textContent = (typeof jaar === 'number') ? jaar : raw;
            } else if (cell.dataset.raw) {
                // Restore original value if column was remapped away from geboortejaar
                cell.textContent = cell.dataset.raw;
            }
        });
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/judoka/import-preview.blade.php ENDPATH**/ ?>