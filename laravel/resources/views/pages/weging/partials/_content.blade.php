@php $pwaApp = 'weging'; @endphp

<!-- Main container -->
<div class="flex flex-col h-full">
    <!-- TOP: Scanner area (45% height) -->
    <div class="bg-blue-800/50 rounded-lg p-3 mb-2 flex flex-col" style="height: 45%;">
        <!-- Scanner area -->
        <div class="flex-1 flex items-center justify-center">
            <!-- Scan button (when not scanning) -->
            <button id="scan-button" onclick="startScanner()"
                    class="bg-green-600 hover:bg-green-700 text-white rounded-full w-28 h-28 flex flex-col items-center justify-center shadow-lg">
                <span class="text-3xl mb-1">📷</span>
                <span class="font-bold text-sm">Scan</span>
            </button>

            <!-- Scanner (when scanning) -->
            <div id="scanner-container" class="text-center w-full" style="display: none;">
                <div id="qr-reader" style="width: 100%; max-width: 300px; min-height: 200px; margin: 0 auto;"></div>
                <button onclick="stopScanner()" class="mt-2 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold">
                    Stop
                </button>
            </div>
        </div>

        <!-- Search input -->
        <div class="mt-1">
            <input type="text" id="search-input"
                   placeholder="Of zoek op naam..."
                   oninput="searchJudoka(this.value)"
                   class="w-full border-2 border-blue-500 bg-blue-800 rounded-lg px-3 py-2 text-center text-sm focus:border-white focus:outline-none placeholder-blue-300 text-white">
            <!-- Search results dropdown -->
            <div id="search-results" class="hidden mt-1 bg-white rounded-lg shadow-lg max-h-40 overflow-y-auto">
            </div>
        </div>
    </div>

    <!-- MIDDLE: Instructies (alleen zichtbaar als geen judoka geselecteerd) -->
    <div id="empty-state" class="flex-1 flex flex-col mb-2">
        <div class="bg-white rounded-lg shadow p-3 text-gray-800">
            <h2 class="font-bold mb-1">Instructies</h2>
            <ol class="list-decimal list-inside text-sm space-y-0.5">
                <li>Scan QR-code op weegkaart</li>
                <li>Voer gewicht in</li>
                <li>Bevestig met groene knop</li>
            </ol>
        </div>
    </div>

    <!-- OVERLAY: Gewicht invoer (over HELE scherm, gecentreerd) -->
    <div id="judoka-section" class="hidden fixed inset-0 z-50 bg-black/80" style="padding-top: env(safe-area-inset-top);">
        <div class="h-full flex items-center justify-center p-3">
            <div class="bg-white rounded-2xl w-full max-w-xs p-3 shadow-2xl">
                <!-- Header met sluiten knop -->
                <div class="flex justify-between items-center mb-2">
                    <div id="judoka-info-compact" class="flex-1 text-gray-800 min-w-0">
                        <!-- Filled by JS -->
                    </div>
                    <button onclick="clearSelection()" class="text-gray-400 hover:text-gray-600 text-3xl leading-none ml-2 flex-shrink-0">&times;</button>
                </div>

                <!-- Weight input -->
                <div class="mb-2">
                    <div class="relative">
                        <input type="text" id="weight-input" readonly
                               class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 text-3xl text-center font-bold text-gray-900 bg-white"
                               placeholder="0.0">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">kg</span>
                    </div>
                </div>

                <!-- Numpad -->
                <div class="grid grid-cols-4 gap-1 mb-2">
                    <button type="button" onclick="np('7')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">7</button>
                    <button type="button" onclick="np('8')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">8</button>
                    <button type="button" onclick="np('9')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">9</button>
                    <button type="button" onclick="np('C')" class="bg-red-200 text-red-700 rounded py-3 text-2xl font-bold">C</button>
                    <button type="button" onclick="np('4')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">4</button>
                    <button type="button" onclick="np('5')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">5</button>
                    <button type="button" onclick="np('6')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">6</button>
                    <button type="button" onclick="np('.')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">.</button>
                    <button type="button" onclick="np('1')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">1</button>
                    <button type="button" onclick="np('2')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">2</button>
                    <button type="button" onclick="np('3')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">3</button>
                    <button type="button" onclick="np('0')" class="bg-gray-200 text-gray-900 rounded py-3 text-2xl font-bold">0</button>
                </div>

                <!-- Register button -->
                <button onclick="registreerGewicht()" id="register-btn"
                        class="w-full bg-green-600 active:bg-green-800 disabled:bg-gray-300 text-white font-bold py-3 rounded-lg text-lg">
                    ✓ Registreer
                </button>

                <!-- Feedback message -->
                <div id="feedback" class="hidden mt-2 p-2 rounded-lg text-center text-sm font-medium"></div>
            </div>
        </div>
    </div>

    <!-- Hidden judoka info element for backwards compatibility -->
    <div id="judoka-info" class="hidden"></div>

    <!-- BOTTOM: Stats + History (fixed at bottom) -->
    <div class="bg-white rounded-lg shadow p-2 text-gray-800">
        <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium">Recent gewogen</span>
            <span class="text-lg font-bold text-green-600" id="stats">0</span>
        </div>
        <div id="history-list" class="space-y-1 max-h-24 overflow-y-auto text-sm">
            <p class="text-gray-400 text-center">Nog geen judoka's gewogen</p>
        </div>
    </div>
</div>

<script>
// Base URL for API calls (new URL structure: /organisator/toernooi)
const apiBaseUrl = '{{ url("/{$toernooi->organisator->slug}/{$toernooi->slug}") }}';

// State
let selectedJudoka = null;
let weightInput = '';
let scanner = null;
let scannerActive = false;
let history = JSON.parse(localStorage.getItem('weging_history') || '[]');
let totalWeighed = parseInt(localStorage.getItem('weging_total') || '0');

// Clock
function updateClock() {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

// Start scanner
async function startScanner() {
    if (scannerActive) return;

    document.getElementById('scan-button').style.display = 'none';
    document.getElementById('scanner-container').style.display = 'block';

    scanner = new Html5Qrcode("qr-reader");

    try {
        await scanner.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 220, height: 220 },
                aspectRatio: 1.0
            },
            onScanSuccess,
            () => {}
        );
        scannerActive = true;
    } catch (err) {
        console.error('Camera error:', err);
        document.getElementById('qr-reader').innerHTML = `
            <div class="bg-red-900 text-white p-4 rounded-lg text-center">
                <p class="font-bold">Camera niet beschikbaar</p>
                <p class="text-sm mt-1">Gebruik het zoekveld</p>
            </div>
        `;
        setTimeout(() => {
            document.getElementById('scanner-container').style.display = 'none';
            document.getElementById('scan-button').style.display = 'flex';
        }, 2000);
    }
}

// Stop scanner
async function stopScanner() {
    if (!scannerActive || !scanner) return;

    try {
        await scanner.stop();
    } catch (err) {
        console.error('Stop error:', err);
    }

    scannerActive = false;
    scanner = null;

    document.getElementById('scanner-container').style.display = 'none';
    document.getElementById('scan-button').style.display = 'flex';
}

// Handle successful scan
async function onScanSuccess(text) {
    let qrCode = text;
    if (text.includes('/weegkaart/')) {
        qrCode = text.split('/weegkaart/').pop();
    }

    if (navigator.vibrate) navigator.vibrate(100);

    try {
        const response = await fetch(`${apiBaseUrl}/scan-qr`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ qr_code: qrCode })
        });

        const data = await response.json();

        if (data.success) {
            await stopScanner();
            selectJudoka(data.judoka);
        }
    } catch (e) {
        console.error('Scan error:', e);
    }
}

// Search functionality
let searchTimeout = null;
async function searchJudoka(query) {
    clearTimeout(searchTimeout);
    const results = document.getElementById('search-results');

    if (query.length < 2) {
        results.classList.add('hidden');
        return;
    }

    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`${apiBaseUrl}/zoeken?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.judokas && data.judokas.length > 0) {
                results.innerHTML = data.judokas.map(j => `
                    <div onclick="selectJudoka(${JSON.stringify(j).replace(/"/g, '&quot;')})"
                         class="p-3 hover:bg-blue-100 cursor-pointer border-b last:border-0">
                        <div class="font-medium text-gray-800">${j.naam}</div>
                        <div class="text-sm text-gray-600">${j.club || 'Geen club'} | ${j.gewichtsklasse} kg</div>
                    </div>
                `).join('');
                results.classList.remove('hidden');
            } else {
                results.innerHTML = '<div class="p-3 text-gray-500 text-center">Geen resultaten</div>';
                results.classList.remove('hidden');
            }
        } catch (e) {
            console.error('Search error:', e);
        }
    }, 300);
}

// Select judoka
function selectJudoka(judoka) {
    selectedJudoka = judoka;
    weightInput = ''; // Always start with empty input field

    document.getElementById('search-results').classList.add('hidden');
    document.getElementById('search-input').value = '';
    document.getElementById('judoka-section').classList.remove('hidden');

    const isGewogen = judoka.gewogen || judoka.gewicht_gewogen;

    // Use previous weighings from server (all weigh stations) if available
    const vorigeWegingen = judoka.vorige_wegingen || [];

    // Compact header info
    document.getElementById('judoka-info-compact').innerHTML = `
        <div class="font-bold text-lg truncate">${judoka.naam}</div>
        <div class="text-sm text-gray-600 flex flex-wrap gap-1">
            <span>${judoka.club || 'Geen club'}</span>
            <span>•</span>
            <span>${judoka.gewichtsklasse || '?'} kg</span>
            ${isGewogen ? `<span class="text-green-600 font-medium">✓ ${judoka.gewicht_gewogen} kg</span>` : ''}
        </div>
        ${vorigeWegingen.length > 0 ? `
        <div class="mt-1 pt-1 border-t border-gray-200 text-xs text-gray-500">
            <span class="font-medium">Vorige (${vorigeWegingen.length}x):</span>
            ${vorigeWegingen.map(w => `<span class="ml-1">${w.gewicht}kg <span class="text-gray-400">(${w.tijd})</span></span>`).join(',')}
        </div>
        ` : ''}
    `;

    updateWeightDisplay();
}

function clearSelection() {
    selectedJudoka = null;
    weightInput = '';
    document.getElementById('judoka-section').classList.add('hidden');
    document.getElementById('feedback').classList.add('hidden');
}

// Numpad - simpel
function np(k) {
    if (k === 'C') weightInput = '';
    else if (k === '.' && !weightInput.includes('.')) weightInput += k;
    else if (k !== '.' && weightInput.length < 5) weightInput += k;
    updateWeightDisplay();
}
// Alias for keyboard handler
const numpadInput = np;

function updateWeightDisplay() {
    const input = document.getElementById('weight-input');
    input.value = weightInput || '';
    input.className = `w-full border-2 rounded-lg px-3 py-2 text-3xl text-center font-bold text-gray-900 bg-white ${weightInput ? 'border-blue-500' : 'border-gray-300'}`;
}

// Register weight
async function registreerGewicht() {
    if (!selectedJudoka || !weightInput) return;

    const btn = document.getElementById('register-btn');
    btn.disabled = true;
    btn.textContent = 'Bezig...';

    const feedback = document.getElementById('feedback');
    const url = `${apiBaseUrl}/weging/${selectedJudoka.id}/registreer`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ gewicht: parseFloat(weightInput) })
        });

        // Check HTTP status first
        if (!response.ok) {
            let errorMsg = `HTTP ${response.status}`;
            if (response.status === 419) {
                errorMsg = 'Sessie verlopen - herlaad pagina';
            } else if (response.status === 404) {
                errorMsg = 'Route niet gevonden';
            } else if (response.status === 500) {
                errorMsg = 'Server fout';
            }
            feedback.className = 'mt-3 p-3 rounded-lg text-center font-medium bg-red-100 text-red-800';
            feedback.textContent = errorMsg;
            feedback.classList.remove('hidden');
            return;
        }

        const data = await response.json();

        if (data.success) {
            // Add to history
            addToHistory(selectedJudoka.naam, weightInput, data.binnen_klasse);

            if (data.binnen_klasse) {
                feedback.className = 'mt-3 p-3 rounded-lg text-center font-medium bg-green-100 text-green-800';
                feedback.textContent = `✓ ${weightInput} kg geregistreerd`;
            } else {
                feedback.className = 'mt-3 p-3 rounded-lg text-center font-medium bg-yellow-100 text-yellow-800';
                feedback.textContent = `⚠️ ${data.opmerking}`;
            }
            feedback.classList.remove('hidden');

            if (navigator.vibrate) navigator.vibrate(100);

            // Clear after 2 seconds
            setTimeout(() => {
                clearSelection();
            }, 2000);
        } else {
            feedback.className = 'mt-3 p-3 rounded-lg text-center font-medium bg-red-100 text-red-800';
            feedback.textContent = data.message || 'Fout bij registreren';
            feedback.classList.remove('hidden');
        }
    } catch (e) {
        console.error('Register error:', e);
        feedback.className = 'mt-3 p-3 rounded-lg text-center font-medium bg-red-100 text-red-800';
        feedback.textContent = 'Geen verbinding met server';
        feedback.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = '✓ Registreer';
    }
}

// Keyboard support (for barcode scanners and physical keyboards)
let barcodeBuffer = '';
let barcodeTimeout = null;
let keyboardReady = false;

// Initialize keyboard listener when DOM is ready
function initKeyboard() {
    if (keyboardReady) return;
    keyboardReady = true;
    console.log('Keyboard support initialized');

    // Use both keydown and keypress for better compatibility
    document.addEventListener('keydown', handleKeyInput, true);

    // Ensure body can receive focus
    document.body.tabIndex = -1;
    document.body.focus();
}

function handleKeyInput(e) {
    // Skip if typing in a text input (except weight-input which is readonly)
    const activeEl = document.activeElement;
    if (activeEl && activeEl.tagName === 'INPUT' && activeEl.id !== 'weight-input' && !activeEl.readOnly) {
        return;
    }

    // If judoka overlay is open, handle numpad input
    const judokaSection = document.getElementById('judoka-section');
    if (selectedJudoka && judokaSection && !judokaSection.classList.contains('hidden')) {
        // Numbers 0-9
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            e.stopPropagation();
            numpadInput(e.key);
            return;
        }
        // Decimal point
        if (e.key === '.' || e.key === ',') {
            e.preventDefault();
            e.stopPropagation();
            numpadInput('.');
            return;
        }
        // Backspace = delete last char
        if (e.key === 'Backspace') {
            e.preventDefault();
            e.stopPropagation();
            weightInput = weightInput.slice(0, -1);
            updateWeightDisplay();
            return;
        }
        // Delete/C = Clear all
        if (e.key === 'Delete' || e.key.toLowerCase() === 'c') {
            e.preventDefault();
            e.stopPropagation();
            numpadInput('C');
            return;
        }
        // Enter = Register
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            registreerGewicht();
            return;
        }
        // Escape = Close
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            clearSelection();
            return;
        }
        return;
    }

    // Barcode scanner mode (when no judoka selected)
    // Scanners type fast and end with Enter
    if (e.key === 'Enter' && barcodeBuffer.length > 3) {
        e.preventDefault();
        e.stopPropagation();
        // Process barcode
        console.log('Barcode scanned:', barcodeBuffer);
        onScanSuccess(barcodeBuffer);
        barcodeBuffer = '';
        clearTimeout(barcodeTimeout);
        return;
    }

    // Build barcode buffer (alphanumeric only)
    if (e.key.length === 1 && /[a-zA-Z0-9\-_]/.test(e.key)) {
        barcodeBuffer += e.key;
        // Clear buffer after 150ms of no input (scanners are fast)
        clearTimeout(barcodeTimeout);
        barcodeTimeout = setTimeout(() => {
            barcodeBuffer = '';
        }, 150);
    }
}

// Initialize keyboard on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKeyboard);
} else {
    initKeyboard();
}

// Also init on window load as fallback
window.addEventListener('load', initKeyboard);

// Re-focus body when clicking outside inputs (helps with barcode scanners)
document.addEventListener('click', function(e) {
    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON') {
        document.body.focus();
    }
});

// History
function addToHistory(naam, gewicht, binnenKlasse) {
    history.unshift({ naam, gewicht, binnenKlasse, tijd: new Date().toISOString() });
    if (history.length > 10) history.pop();
    totalWeighed++;
    localStorage.setItem('weging_history', JSON.stringify(history));
    localStorage.setItem('weging_total', totalWeighed);
    updateHistoryDisplay();
}

// Load history on page load
updateHistoryDisplay();

function updateHistoryDisplay() {
    document.getElementById('stats').textContent = totalWeighed;

    const list = document.getElementById('history-list');
    if (history.length === 0) {
        list.innerHTML = '<p class="text-gray-400 text-center">Nog geen judoka\'s gewogen</p>';
        return;
    }

    list.innerHTML = history.map(h => `
        <div class="flex justify-between items-center py-0.5 ${h.binnenKlasse ? 'text-gray-700' : 'text-yellow-600'}">
            <span class="truncate">${h.naam}</span>
            <span class="font-mono ml-2">${h.gewicht} kg</span>
        </div>
    `).join('');
}
</script>
