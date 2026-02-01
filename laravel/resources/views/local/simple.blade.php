<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lokale Server - Synchronisatie</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .pulse-green { animation: pulse-green 2s infinite; }
        .pulse-orange { animation: pulse-orange 2s infinite; }
        .pulse-red { animation: pulse-red 2s infinite; }

        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
        }
        @keyframes pulse-orange {
            0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(249, 115, 22, 0); }
        }
        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-lg">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Lokale Server</h1>
            <p class="text-gray-600 mt-2">Synchronisatie Beheer</p>
        </div>

        <!-- Internet Status Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div id="status-indicator"
                         class="w-6 h-6 rounded-full bg-gray-400">
                    </div>
                    <div>
                        <div class="text-lg font-semibold" id="status-label">Controleren...</div>
                        <div class="text-sm text-gray-500" id="status-latency"></div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Laatste sync</div>
                    <div class="font-medium" id="last-sync">-</div>
                </div>
            </div>
        </div>

        <!-- Sync Button -->
        <button id="sync-button"
                onclick="startSync()"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-6 px-6 rounded-2xl shadow-lg mb-4 text-xl transition-all disabled:bg-gray-400 disabled:cursor-not-allowed">
            <span id="sync-text">Synchroniseer Nu</span>
            <span id="sync-spinner" class="hidden">
                <svg class="animate-spin inline-block w-6 h-6 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </button>

        <!-- Queue Status -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Wachtende wijzigingen</span>
                <span id="queue-count" class="text-2xl font-bold text-blue-600">0</span>
            </div>
        </div>

        <!-- Switch to Local Button -->
        <button id="local-button"
                onclick="switchToLocal()"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-6 px-6 rounded-2xl shadow-lg text-xl transition-all">
            Schakel naar Lokaal
        </button>

        <!-- Info Message -->
        <div id="message" class="mt-6 p-4 rounded-xl hidden"></div>

        <!-- Toernooi Selector (if multiple) -->
        @if(isset($toernooien) && $toernooien->count() > 1)
        <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
            <label class="block text-gray-600 mb-2">Selecteer Toernooi</label>
            <select id="toernooi-select" class="w-full p-3 border rounded-xl text-lg">
                @foreach($toernooien as $t)
                    <option value="{{ $t->id }}" {{ isset($toernooi) && $toernooi->id == $t->id ? 'selected' : '' }}>
                        {{ $t->naam }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <!-- Help Text -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p><strong>Synchroniseer Nu</strong> = Download nieuwste data van cloud</p>
            <p class="mt-2"><strong>Schakel naar Lokaal</strong> = Gebruik deze laptop als server</p>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let statusCheckInterval;
        let isSyncing = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            checkInternetStatus();
            checkQueueStatus();

            // Check internet status every 10 seconds
            statusCheckInterval = setInterval(() => {
                checkInternetStatus();
                checkQueueStatus();
            }, 10000);
        });

        async function checkInternetStatus() {
            try {
                const response = await fetch('/local-server/internet-status');
                const data = await response.json();

                updateStatusDisplay(data);
            } catch (e) {
                updateStatusDisplay({ status: 'offline', latency: null });
            }
        }

        async function checkQueueStatus() {
            try {
                const toernooiId = document.getElementById('toernooi-select')?.value || {{ $toernooi->id ?? 0 }};
                const response = await fetch(`/local-server/queue-status?toernooi_id=${toernooiId}`);
                const data = await response.json();

                document.getElementById('queue-count').textContent = data.pending || 0;
            } catch (e) {
                // Ignore errors
            }
        }

        function updateStatusDisplay(data) {
            const indicator = document.getElementById('status-indicator');
            const label = document.getElementById('status-label');
            const latency = document.getElementById('status-latency');

            // Remove all pulse classes
            indicator.classList.remove('pulse-green', 'pulse-orange', 'pulse-red', 'bg-green-500', 'bg-orange-500', 'bg-red-500', 'bg-gray-400');

            switch (data.status) {
                case 'good':
                    indicator.classList.add('bg-green-500', 'pulse-green');
                    label.textContent = 'Internet: Goed';
                    label.className = 'text-lg font-semibold text-green-600';
                    break;
                case 'poor':
                    indicator.classList.add('bg-orange-500', 'pulse-orange');
                    label.textContent = 'Internet: Matig';
                    label.className = 'text-lg font-semibold text-orange-600';
                    break;
                case 'offline':
                    indicator.classList.add('bg-red-500', 'pulse-red');
                    label.textContent = 'Internet: Offline';
                    label.className = 'text-lg font-semibold text-red-600';
                    break;
            }

            if (data.latency) {
                latency.textContent = `${data.latency}ms`;
            } else {
                latency.textContent = '';
            }

            if (data.last_sync_at) {
                document.getElementById('last-sync').textContent = data.last_sync_at;
            }
        }

        async function startSync() {
            if (isSyncing) return;
            isSyncing = true;

            const button = document.getElementById('sync-button');
            const text = document.getElementById('sync-text');
            const spinner = document.getElementById('sync-spinner');

            button.disabled = true;
            text.textContent = 'Bezig...';
            spinner.classList.remove('hidden');

            try {
                const toernooiId = document.getElementById('toernooi-select')?.value || {{ $toernooi->id ?? 0 }};
                const response = await fetch('/local-server/sync-now', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ toernooi_id: toernooiId }),
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('success', `Synchronisatie geslaagd! ${data.records_synced} records bijgewerkt.`);
                } else {
                    showMessage('error', 'Synchronisatie mislukt: ' + (data.errors?.[0] || 'Onbekende fout'));
                }
            } catch (e) {
                showMessage('error', 'Verbinding mislukt. Controleer internet.');
            } finally {
                isSyncing = false;
                button.disabled = false;
                text.textContent = 'Synchroniseer Nu';
                spinner.classList.add('hidden');
                checkInternetStatus();
                checkQueueStatus();
            }
        }

        function switchToLocal() {
            if (confirm('Weet je zeker dat je wilt overschakelen naar lokale modus?\n\nAlle tablets en telefoons zullen verbinding maken met deze laptop.')) {
                window.location.href = '/local-server/activate';
            }
        }

        function showMessage(type, text) {
            const msg = document.getElementById('message');
            msg.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');

            if (type === 'success') {
                msg.classList.add('bg-green-100', 'text-green-800');
            } else {
                msg.classList.add('bg-red-100', 'text-red-800');
            }

            msg.textContent = text;
            msg.classList.remove('hidden');

            setTimeout(() => {
                msg.classList.add('hidden');
            }, 5000);
        }
    </script>
</body>
</html>
