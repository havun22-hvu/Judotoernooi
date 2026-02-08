<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title id="page-title">Offline Pakket</title>
<style>
/* Reset & Base */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; font-size: 14px; }

/* Layout */
.header { background: #1e3a5f; color: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
.header h1 { font-size: 18px; font-weight: 700; }
.header .meta { font-size: 12px; opacity: 0.8; }
.container { max-width: 1200px; margin: 0 auto; padding: 16px; }

/* Tabs */
.tabs { display: flex; gap: 4px; background: white; padding: 8px; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); flex-wrap: wrap; }
.tab { padding: 10px 20px; border: none; background: #f3f4f6; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
.tab:hover { background: #e5e7eb; }
.tab.active { background: #1e3a5f; color: white; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* Cards */
.card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px; overflow: hidden; }
.card-header { padding: 12px 16px; font-weight: 700; font-size: 15px; border-bottom: 2px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.card-body { padding: 16px; }

/* Tables */
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #6b7280; position: sticky; top: 0; }
tr:hover { background: #f9fafb; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-sm { font-size: 12px; }
.text-muted { color: #9ca3af; }
.text-green { color: #059669; }
.text-orange { color: #d97706; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-orange { background: #fef3c7; color: #92400e; }
.badge-gray { background: #f3f4f6; color: #4b5563; }
.badge-red { background: #fee2e2; color: #991b1b; }

/* Buttons */
.btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #1e3a5f; color: white; }
.btn-primary:hover { background: #2d4a6f; }
.btn-success { background: #059669; color: white; }
.btn-success:hover { background: #047857; }
.btn-sm { padding: 4px 10px; font-size: 12px; }

/* Filter */
.filter-bar { display: flex; gap: 8px; margin-bottom: 12px; align-items: center; flex-wrap: wrap; }
.filter-bar select { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }

/* Weeglijst specific */
.gewicht-input { width: 60px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 4px; text-align: center; font-size: 13px; }

/* Matrix Schema */
.schema-table { width: auto; border-collapse: collapse; font-size: 12px; }
.schema-table th, .schema-table td { border: 1px solid #374151; padding: 4px; }
.schema-header { background: #1f2937; color: white; }
.schema-header th { border-color: #4b5563; font-size: 11px; }
.schema-sub { font-size: 9px; font-weight: normal; color: #9ca3af; }
.schema-nr { width: 28px; text-align: center; font-weight: bold; font-size: 13px; }
.schema-naam { padding: 4px 6px; font-size: 12px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.schema-score { width: 22px; text-align: center; font-size: 12px; height: 36px; }
.schema-score.inactive { background: #1f2937; }
.schema-score.played { background: #d1fae5; }
.schema-score.w-col { border-right: 1px solid #9ca3af; }
.schema-score.j-col { border-left: none; border-right: 2px solid #374151; }
.schema-total { width: 30px; background: #f3f4f6; text-align: center; font-weight: bold; }
.schema-place { width: 30px; background: #fef9c3; text-align: center; }
.schema-title-row td { background: #1f2937; color: white; padding: 6px 12px; font-size: 11px; border: none; }
.schema-info-row td { background: #f3f4f6; padding: 6px 12px; font-size: 12px; border: none; border-bottom: 2px solid #374151; }

/* Score input modal */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; justify-content: center; align-items: center; }
.modal-overlay.active { display: flex; }
.modal { background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 400px; }
.modal h3 { font-size: 16px; margin-bottom: 16px; }
.modal .form-group { margin-bottom: 12px; }
.modal label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; color: #4b5563; }
.modal input[type="number"] { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
.modal .winner-btn { display: block; width: 100%; padding: 12px; margin-bottom: 8px; border: 2px solid #d1d5db; border-radius: 8px; background: white; cursor: pointer; text-align: left; font-size: 14px; transition: all 0.2s; }
.modal .winner-btn:hover { border-color: #1e3a5f; background: #f0f4ff; }
.modal .winner-btn.selected { border-color: #059669; background: #d1fae5; }
.modal .actions { display: flex; gap: 8px; margin-top: 16px; }
.modal .actions .btn { flex: 1; justify-content: center; }

/* Upload status */
.upload-status { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: none; }
.upload-status.success { display: block; background: #d1fae5; color: #065f46; }
.upload-status.error { display: block; background: #fee2e2; color: #991b1b; }

/* Print */
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .header { position: static; }
    .tab-content { display: block !important; page-break-before: always; }
    .tab-content:first-of-type { page-break-before: avoid; }
    .card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
    .schema-header, .schema-header th { background: #1f2937 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .schema-score.inactive { background: #1f2937 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .schema-score.played { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .schema-total { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .schema-place { background: #fef9c3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .schema-title-row td { background: #1f2937 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1 id="header-title">Offline Pakket</h1>
        <div class="meta" id="header-meta"></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;" class="no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn btn-success" id="upload-btn" onclick="uploadResultaten()" style="display:none">Upload Resultaten</button>
    </div>
</div>

<div class="container">
    <div id="upload-status" class="upload-status"></div>

    <div class="tabs no-print">
        <button class="tab active" onclick="switchTab('weeglijst')">Weeglijst</button>
        <button class="tab" onclick="switchTab('zaaloverzicht')">Zaaloverzicht</button>
        <button class="tab" onclick="switchTab('schemas')">Wedstrijdschema's</button>
        <button class="tab" onclick="switchTab('scores')">Score Invoer</button>
    </div>

    <!-- WEEGLIJST -->
    <div id="tab-weeglijst" class="tab-content active">
        <div class="filter-bar no-print">
            <label>Blok:</label>
            <select id="weeg-blok-filter" onchange="renderWeeglijst()">
                <option value="">Alle blokken</option>
            </select>
        </div>
        <div id="weeglijst-content"></div>
    </div>

    <!-- ZAALOVERZICHT -->
    <div id="tab-zaaloverzicht" class="tab-content">
        <div id="zaaloverzicht-content"></div>
    </div>

    <!-- WEDSTRIJDSCHEMA'S -->
    <div id="tab-schemas" class="tab-content">
        <div class="filter-bar no-print">
            <label>Blok:</label>
            <select id="schema-blok-filter" onchange="renderSchemas()">
                <option value="">Alle blokken</option>
            </select>
        </div>
        <div id="schemas-content"></div>
    </div>

    <!-- SCORE INVOER -->
    <div id="tab-scores" class="tab-content">
        <div class="filter-bar no-print">
            <label>Blok:</label>
            <select id="score-blok-filter" onchange="renderScores()">
                <option value="">Alle blokken</option>
            </select>
            <label style="margin-left:12px">Status:</label>
            <select id="score-status-filter" onchange="renderScores()">
                <option value="">Alle</option>
                <option value="open">Nog te spelen</option>
                <option value="gespeeld">Gespeeld</option>
            </select>
        </div>
        <div id="scores-content"></div>
    </div>
</div>

<!-- Score Modal -->
<div id="score-modal" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <h3 id="modal-title">Score invoeren</h3>
        <div id="modal-body"></div>
    </div>
</div>

<script>
const DATA = {!! $jsonData !!};

// localStorage key for offline scores
const STORAGE_KEY = 'offline_' + DATA.toernooi.id;

// Lookup maps
const clubMap = {};
DATA.clubs.forEach(c => clubMap[c.id] = c.naam);
const judokaMap = {};
DATA.judokas.forEach(j => judokaMap[j.id] = j);
const blokMap = {};
DATA.blokken.forEach(b => blokMap[b.id] = b);
const matMap = {};
DATA.matten.forEach(m => matMap[m.id] = m);

// Load offline scores from localStorage
let offlineScores = {};
try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) offlineScores = JSON.parse(stored);
} catch(e) {}

// Initialize page
document.getElementById('page-title').textContent = DATA.toernooi.naam + ' - Offline Pakket';
document.getElementById('header-title').textContent = DATA.toernooi.naam + ' - Offline Pakket';
document.getElementById('header-meta').textContent = 'Datum: ' + DATA.toernooi.datum + ' | Gegenereerd: ' + DATA.generated_at;

// Populate blok filters
['weeg-blok-filter', 'schema-blok-filter', 'score-blok-filter'].forEach(id => {
    const sel = document.getElementById(id);
    DATA.blokken.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = 'Blok ' + b.nummer;
        sel.appendChild(opt);
    });
});

// Show upload button if there are offline scores
function updateUploadBtn() {
    const btn = document.getElementById('upload-btn');
    const count = Object.keys(offlineScores).length;
    btn.style.display = count > 0 ? '' : 'none';
    btn.textContent = 'Upload Resultaten (' + count + ')';
}
updateUploadBtn();

// Tab switching
function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}

// Get effective wedstrijd data (server + offline overrides)
function getWedstrijdData(w) {
    if (offlineScores[w.id]) {
        return { ...w, ...offlineScores[w.id], is_gespeeld: true };
    }
    return w;
}

// =================== WEEGLIJST ===================

function renderWeeglijst() {
    const blokFilter = document.getElementById('weeg-blok-filter').value;
    let html = '';

    // Group judokas by blok via poules
    const judokasPerBlok = {};
    DATA.blokken.forEach(b => judokasPerBlok[b.id] = new Set());

    DATA.poules.forEach(p => {
        if (p.blok_id && judokasPerBlok[p.blok_id]) {
            p.judoka_ids.forEach(jid => judokasPerBlok[p.blok_id].add(jid));
        }
    });

    const blokken = blokFilter ? DATA.blokken.filter(b => b.id == blokFilter) : DATA.blokken;

    blokken.forEach(blok => {
        const judokaIds = Array.from(judokasPerBlok[blok.id] || []);
        const judokas = judokaIds.map(id => judokaMap[id]).filter(Boolean)
            .filter(j => j.aanwezigheid !== 'afwezig')
            .sort((a, b) => (a.naam || '').localeCompare(b.naam || ''));

        if (judokas.length === 0) return;

        html += '<div class="card">';
        html += '<div class="card-header">Blok ' + blok.nummer + ' <span class="text-muted text-sm">(' + judokas.length + ' judoka\'s)</span></div>';
        html += '<div class="card-body"><table>';
        html += '<thead><tr><th>#</th><th>Naam</th><th>Club</th><th>Categorie</th><th>Gewichtsklasse</th><th class="text-center">Gewicht</th><th class="text-center no-print">Invoer</th></tr></thead>';
        html += '<tbody>';

        judokas.forEach((j, idx) => {
            const gewicht = j.gewicht_gewogen || j.gewicht || '';
            html += '<tr>';
            html += '<td class="text-muted">' + (idx + 1) + '</td>';
            html += '<td><strong>' + esc(j.naam) + '</strong></td>';
            html += '<td class="text-sm">' + esc(j.club_naam || '-') + '</td>';
            html += '<td class="text-sm">' + esc(j.leeftijdsklasse || '-') + '</td>';
            html += '<td class="text-sm">' + esc(j.gewichtsklasse || '-') + '</td>';
            html += '<td class="text-center">' + (gewicht ? gewicht + ' kg' : '-') + '</td>';
            html += '<td class="text-center no-print"><input type="number" step="0.1" class="gewicht-input" placeholder="kg"></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    document.getElementById('weeglijst-content').innerHTML = html || '<p class="text-muted">Geen judoka\'s gevonden.</p>';
}

// =================== ZAALOVERZICHT ===================

function renderZaaloverzicht() {
    let html = '';

    DATA.blokken.forEach(blok => {
        const poules = DATA.poules.filter(p => p.blok_id === blok.id && p.mat_nummer)
            .sort((a, b) => (a.mat_nummer || 0) - (b.mat_nummer || 0));

        if (poules.length === 0) return;

        html += '<div class="card">';
        html += '<div class="card-header">Blok ' + blok.nummer + '</div>';
        html += '<div class="card-body"><table>';
        html += '<thead><tr><th>Mat</th><th>Poule</th><th>Categorie</th><th>Gewichtsklasse</th><th class="text-center">Judoka\'s</th></tr></thead>';
        html += '<tbody>';

        poules.forEach(p => {
            const judokas = p.judoka_ids.map(id => judokaMap[id]).filter(Boolean);
            const actief = judokas.filter(j => j.aanwezigheid !== 'afwezig');
            html += '<tr>';
            html += '<td><strong>Mat ' + p.mat_nummer + '</strong></td>';
            html += '<td>Poule ' + p.nummer + '</td>';
            html += '<td class="text-sm">' + esc(p.leeftijdsklasse || '-') + '</td>';
            html += '<td class="text-sm">' + esc(p.gewichtsklasse || '-') + '</td>';
            html += '<td class="text-center">' + actief.length + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    document.getElementById('zaaloverzicht-content').innerHTML = html || '<p class="text-muted">Geen poules met mat-indeling gevonden.</p>';
}

// =================== WEDSTRIJDSCHEMA'S ===================

function getStandaardSchema(n, bestOfThree) {
    const schemas = {
        2: bestOfThree ? [[1,2],[2,1],[1,2]] : [[1,2],[2,1]],
        3: [[1,2],[1,3],[2,3],[2,1],[3,2],[3,1]],
        4: [[1,2],[3,4],[2,3],[1,4],[2,4],[1,3]],
        5: [[1,2],[3,4],[1,5],[2,3],[4,5],[1,3],[2,4],[3,5],[1,4],[2,5]],
        6: [[1,2],[3,4],[5,6],[1,3],[2,5],[4,6],[3,5],[2,4],[1,6],[2,3],[4,5],[3,6],[1,4],[2,6],[1,5]],
        7: [[1,2],[3,4],[5,6],[1,7],[2,3],[4,5],[6,7],[1,3],[2,4],[5,7],[3,6],[1,4],[2,5],[3,7],[4,6],[1,5],[2,6],[4,7],[1,6],[3,5],[2,7]]
    };
    return schemas[n] || [];
}

function renderSchemas() {
    const blokFilter = document.getElementById('schema-blok-filter').value;
    let html = '';
    const schemas = DATA.toernooi.wedstrijd_schemas || {};
    const bestOfThree = DATA.toernooi.best_of_three_bij_2 || false;

    const poules = DATA.poules
        .filter(p => p.mat_id && (!blokFilter || p.blok_id == blokFilter))
        .sort((a, b) => {
            if (a.blok_nummer !== b.blok_nummer) return (a.blok_nummer || 0) - (b.blok_nummer || 0);
            return (a.mat_nummer || 0) - (b.mat_nummer || 0);
        });

    poules.forEach(poule => {
        const judokas = poule.judoka_ids.map(id => judokaMap[id]).filter(Boolean)
            .filter(j => j.aanwezigheid !== 'afwezig');
        const aantal = judokas.length;
        if (aantal < 2) return;

        const wedstrijden = DATA.wedstrijden.filter(w => w.poule_id === poule.id)
            .sort((a, b) => (a.volgorde || 0) - (b.volgorde || 0));

        // Build schema from actual wedstrijden
        const judokaIdToNr = {};
        judokas.forEach((j, idx) => judokaIdToNr[j.id] = idx + 1);

        const schema = wedstrijden.length > 0
            ? wedstrijden.map(w => [judokaIdToNr[w.judoka_wit_id], judokaIdToNr[w.judoka_blauw_id]]).filter(s => s[0] && s[1])
            : (schemas[aantal] || getStandaardSchema(aantal, bestOfThree && aantal === 2));

        const totalCols = 5 + (schema.length * 2);

        html += '<div class="card" style="margin-bottom:20px;overflow-x:auto;">';
        html += '<table class="schema-table"><thead>';

        // Title row
        html += '<tr class="schema-title-row"><td colspan="' + totalCols + '">';
        html += '<div style="display:flex;justify-content:space-between">';
        html += '<span>' + esc(DATA.toernooi.naam) + '</span>';
        html += '<span>' + esc(DATA.toernooi.datum) + '</span>';
        html += '</div></td></tr>';

        // Info row
        html += '<tr class="schema-info-row"><td colspan="' + totalCols + '">';
        html += '<strong>Poule ' + poule.nummer + '</strong>';
        html += ' - ' + esc(poule.leeftijdsklasse || '') + ' ' + esc(poule.gewichtsklasse || '');
        html += ' | Mat ' + (poule.mat_nummer || '?') + ' | Blok ' + (poule.blok_nummer || '?');
        html += '</td></tr>';

        // Header row
        html += '<tr class="schema-header"><th class="schema-nr">Nr</th><th class="schema-naam" style="text-align:left">Naam</th>';
        schema.forEach((_, idx) => {
            html += '<th colspan="2" style="min-width:48px;text-align:center"><div style="font-weight:bold">' + (idx + 1) + '</div><div class="schema-sub">W &nbsp; J</div></th>';
        });
        html += '<th class="schema-total">WP</th><th class="schema-total">JP</th><th class="schema-place">Plts</th></tr></thead>';

        // Body rows
        html += '<tbody>';
        judokas.forEach((judoka, idx) => {
            const nr = idx + 1;
            let totWP = 0, totJP = 0, hasPlayed = false;

            html += '<tr><td class="schema-nr">' + nr + '</td>';
            html += '<td class="schema-naam">' + esc(judoka.naam) + ' <span style="color:#999;font-size:10px">(' + abbreviate(judoka.club_naam) + ')</span></td>';

            schema.forEach((pair, wedIdx) => {
                const witNr = pair[0], blauwNr = pair[1];
                const participates = (nr === witNr || nr === blauwNr);

                if (!participates) {
                    html += '<td class="schema-score w-col inactive"></td><td class="schema-score j-col inactive"></td>';
                    return;
                }

                const match = wedstrijden[wedIdx] ? getWedstrijdData(wedstrijden[wedIdx]) : null;
                let wp = '', jp = '';

                if (match && match.is_gespeeld) {
                    hasPlayed = true;
                    if (nr === witNr) {
                        wp = match.winnaar_id == judoka.id ? '2' : (match.winnaar_id ? '0' : '1');
                        jp = match.score_wit !== null && match.score_wit !== undefined ? String(match.score_wit) : '0';
                    } else {
                        wp = match.winnaar_id == judoka.id ? '2' : (match.winnaar_id ? '0' : '1');
                        jp = match.score_blauw !== null && match.score_blauw !== undefined ? String(match.score_blauw) : '0';
                    }
                    totWP += parseInt(wp);
                    totJP += parseInt(jp);
                }

                const cls = wp !== '' ? ' played' : '';
                html += '<td class="schema-score w-col' + cls + '">' + wp + '</td>';
                html += '<td class="schema-score j-col' + cls + '">' + jp + '</td>';
            });

            html += '<td class="schema-total">' + (hasPlayed ? totWP : '') + '</td>';
            html += '<td class="schema-total">' + (hasPlayed ? totJP : '') + '</td>';
            html += '<td class="schema-place"></td></tr>';
        });

        html += '</tbody></table>';
        html += '<div style="padding:8px 12px;font-size:10px;color:#666"><strong>W</strong> = Wedstrijdpunten | <strong>J</strong> = Judopunten | Plts = handmatig</div>';
        html += '</div>';
    });

    document.getElementById('schemas-content').innerHTML = html || '<p class="text-muted">Geen poules met mat-indeling en wedstrijden gevonden.</p>';
}

// =================== SCORE INVOER ===================

function renderScores() {
    const blokFilter = document.getElementById('score-blok-filter').value;
    const statusFilter = document.getElementById('score-status-filter').value;
    let html = '';

    const poules = DATA.poules
        .filter(p => p.mat_id && (!blokFilter || p.blok_id == blokFilter))
        .sort((a, b) => {
            if (a.blok_nummer !== b.blok_nummer) return (a.blok_nummer || 0) - (b.blok_nummer || 0);
            return (a.mat_nummer || 0) - (b.mat_nummer || 0);
        });

    poules.forEach(poule => {
        const wedstrijden = DATA.wedstrijden.filter(w => w.poule_id === poule.id)
            .sort((a, b) => (a.volgorde || 0) - (b.volgorde || 0));

        if (wedstrijden.length === 0) return;

        // Filter by status
        const filtered = wedstrijden.filter(w => {
            const wd = getWedstrijdData(w);
            if (statusFilter === 'open') return !wd.is_gespeeld;
            if (statusFilter === 'gespeeld') return wd.is_gespeeld;
            return true;
        });

        if (filtered.length === 0) return;

        html += '<div class="card">';
        html += '<div class="card-header">';
        html += 'Poule ' + poule.nummer + ' - ' + esc(poule.leeftijdsklasse || '') + ' ' + esc(poule.gewichtsklasse || '');
        html += ' <span class="text-muted text-sm">Mat ' + (poule.mat_nummer || '?') + ' | Blok ' + (poule.blok_nummer || '?') + '</span>';
        html += '</div>';
        html += '<div class="card-body"><table>';
        html += '<thead><tr><th>#</th><th>Wit</th><th>Blauw</th><th class="text-center">Status</th><th class="text-center">Score</th><th class="text-center no-print">Actie</th></tr></thead>';
        html += '<tbody>';

        filtered.forEach((w, idx) => {
            const wd = getWedstrijdData(w);
            const wit = judokaMap[w.judoka_wit_id];
            const blauw = judokaMap[w.judoka_blauw_id];
            const isGespeeld = wd.is_gespeeld;
            const isOffline = !!offlineScores[w.id];

            html += '<tr>';
            html += '<td class="text-muted">' + (w.volgorde || (idx + 1)) + '</td>';
            html += '<td>' + esc(wit ? wit.naam : '?') + '</td>';
            html += '<td>' + esc(blauw ? blauw.naam : '?') + '</td>';

            if (isGespeeld) {
                const winnaar = judokaMap[wd.winnaar_id];
                html += '<td class="text-center"><span class="badge ' + (isOffline ? 'badge-orange' : 'badge-green') + '">' + (isOffline ? 'Offline' : 'Gespeeld') + '</span></td>';
                html += '<td class="text-center text-sm">' + esc(winnaar ? winnaar.naam : 'Gelijk') + ' (' + (wd.score_wit || 0) + '-' + (wd.score_blauw || 0) + ')</td>';
            } else {
                html += '<td class="text-center"><span class="badge badge-gray">Open</span></td>';
                html += '<td class="text-center text-muted">-</td>';
            }

            html += '<td class="text-center no-print"><button class="btn btn-sm btn-primary" onclick="openScoreModal(' + w.id + ')">Score</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    document.getElementById('scores-content').innerHTML = html || '<p class="text-muted">Geen wedstrijden gevonden.</p>';
}

function openScoreModal(wedstrijdId) {
    const w = DATA.wedstrijden.find(w => w.id === wedstrijdId);
    if (!w) return;

    const wd = getWedstrijdData(w);
    const wit = judokaMap[w.judoka_wit_id];
    const blauw = judokaMap[w.judoka_blauw_id];

    document.getElementById('modal-title').textContent = 'Wedstrijd ' + (w.volgorde || w.id);

    let html = '<div style="margin-bottom:12px;font-size:13px;color:#6b7280;">Kies de winnaar en vul de scores in:</div>';

    html += '<button class="winner-btn" id="winner-' + w.judoka_wit_id + '" onclick="selectWinner(' + w.judoka_wit_id + ',' + w.id + ')">';
    html += '<strong style="color:#1e3a5f">WIT:</strong> ' + esc(wit ? wit.naam : '?');
    html += '</button>';

    html += '<button class="winner-btn" id="winner-' + w.judoka_blauw_id + '" onclick="selectWinner(' + w.judoka_blauw_id + ',' + w.id + ')">';
    html += '<strong style="color:#2563eb">BLAUW:</strong> ' + esc(blauw ? blauw.naam : '?');
    html += '</button>';

    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">';
    html += '<div class="form-group"><label>Score Wit</label><input type="number" id="modal-score-wit" min="0" value="' + (wd.score_wit || 0) + '"></div>';
    html += '<div class="form-group"><label>Score Blauw</label><input type="number" id="modal-score-blauw" min="0" value="' + (wd.score_blauw || 0) + '"></div>';
    html += '</div>';

    html += '<input type="hidden" id="modal-wedstrijd-id" value="' + w.id + '">';
    html += '<input type="hidden" id="modal-winnaar-id" value="' + (wd.winnaar_id || '') + '">';

    html += '<div class="actions">';
    html += '<button class="btn" style="background:#e5e7eb" onclick="closeModal()">Annuleren</button>';
    html += '<button class="btn btn-success" onclick="saveScore()">Opslaan</button>';
    html += '</div>';

    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('score-modal').classList.add('active');

    // Pre-select winner if already set
    if (wd.winnaar_id) {
        selectWinner(wd.winnaar_id, w.id);
    }
}

let selectedWinnerId = null;
function selectWinner(judokaId, wedstrijdId) {
    selectedWinnerId = judokaId;
    document.getElementById('modal-winnaar-id').value = judokaId;

    // Update button styles
    const w = DATA.wedstrijden.find(w => w.id === wedstrijdId);
    if (!w) return;

    document.querySelectorAll('.winner-btn').forEach(btn => btn.classList.remove('selected'));
    const btn = document.getElementById('winner-' + judokaId);
    if (btn) btn.classList.add('selected');
}

function saveScore() {
    const wedstrijdId = parseInt(document.getElementById('modal-wedstrijd-id').value);
    const winnaarId = parseInt(document.getElementById('modal-winnaar-id').value);
    const scoreWit = parseInt(document.getElementById('modal-score-wit').value) || 0;
    const scoreBlauw = parseInt(document.getElementById('modal-score-blauw').value) || 0;

    if (!winnaarId) {
        alert('Selecteer eerst een winnaar.');
        return;
    }

    // Save to offline scores
    offlineScores[wedstrijdId] = {
        winnaar_id: winnaarId,
        score_wit: scoreWit,
        score_blauw: scoreBlauw,
    };

    // Persist to localStorage
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(offlineScores));
    } catch(e) {}

    updateUploadBtn();
    closeModal();
    renderScores();
    renderSchemas();
}

function closeModal() {
    document.getElementById('score-modal').classList.remove('active');
    selectedWinnerId = null;
}

// =================== UPLOAD ===================

async function uploadResultaten() {
    const count = Object.keys(offlineScores).length;
    if (count === 0) {
        alert('Geen offline scores om te uploaden.');
        return;
    }

    if (!confirm('Upload ' + count + ' offline score(s) naar de server?\n\nScores die op de server al gespeeld zijn worden overgeslagen.')) {
        return;
    }

    const resultaten = Object.entries(offlineScores).map(([wedstrijdId, score]) => ({
        wedstrijd_id: parseInt(wedstrijdId),
        winnaar_id: score.winnaar_id,
        score_wit: score.score_wit,
        score_blauw: score.score_blauw,
    }));

    const statusEl = document.getElementById('upload-status');

    try {
        // Try to determine upload URL from current location or data
        const currentUrl = window.location.href;
        let uploadUrl;

        // If opened from server (has proper URL), use relative path
        if (currentUrl.startsWith('http')) {
            const base = currentUrl.substring(0, currentUrl.lastIndexOf('/'));
            uploadUrl = base + '/upload-resultaten';
        } else {
            // Opened from file - ask user for server URL
            const serverUrl = prompt('Voer de server URL in (bijv. https://judotournament.org):', 'https://judotournament.org');
            if (!serverUrl) return;
            uploadUrl = serverUrl.replace(/\/$/, '') + '/' + DATA.toernooi.slug + '/noodplan/upload-resultaten';
        }

        const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ resultaten }),
        });

        const result = await response.json();

        if (result.success) {
            statusEl.className = 'upload-status success';
            statusEl.textContent = 'Upload geslaagd! ' + result.synced + ' score(s) opgeslagen, ' + result.skipped + ' overgeslagen (al gespeeld op server).';

            // Clear uploaded scores from localStorage
            offlineScores = {};
            try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
            updateUploadBtn();
            renderScores();
        } else {
            throw new Error(result.error || 'Upload mislukt');
        }
    } catch(e) {
        statusEl.className = 'upload-status error';
        statusEl.textContent = 'Upload mislukt: ' + e.message + '. Probeer het later opnieuw of voer de scores handmatig in.';
    }
}

// =================== HELPERS ===================

function esc(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function abbreviate(name) {
    if (!name) return '-';
    let abbr = name
        .replace(/Judoschool/gi, 'J.S.')
        .replace(/Sportcentrum/gi, 'S.C.')
        .replace(/Sportvereniging/gi, 'S.V.')
        .replace(/Judovereniging/gi, 'J.V.')
        .replace(/Judo Vereniging/gi, 'J.V.');
    return abbr.length > 15 ? abbr.substring(0, 14) + '\u2026' : abbr;
}

// Initial render
renderWeeglijst();
renderZaaloverzicht();
renderSchemas();
renderScores();
</script>
</body>
</html>
