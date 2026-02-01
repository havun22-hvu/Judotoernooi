<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Auto-Sync - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-2xl mx-auto" x-data="autoSyncApp()">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
            <h1 class="text-3xl font-bold text-gray-800">Data Synchronisatie</h1>
            <p class="text-gray-600 mt-2">
                Server rol: <span class="font-bold text-{{ $role === 'primary' ? 'blue' : 'orange' }}-600">{{ strtoupper($role) }}</span>
            </p>
        </div>

        <!-- Sync Status Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Cloud Synchronisatie</h2>
                <div x-show="syncing" class="flex items-center text-blue-600">
                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Bezig...
                </div>
            </div>

            <!-- Cloud Server Status -->
            <div class="p-4 rounded-lg mb-4" :class="cloudAvailable ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full mr-3" :class="cloudAvailable ? 'bg-green-500' : 'bg-red-500'"></div>
                    <div>
                        <div class="font-medium" :class="cloudAvailable ? 'text-green-800' : 'text-red-800'">
                            Cloud Server: <span x-text="cloudAvailable ? 'ONLINE' : 'OFFLINE'"></span>
                        </div>
                        <div class="text-sm" :class="cloudAvailable ? 'text-green-600' : 'text-red-600'">
                            {{ $cloudUrl }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sync Progress -->
            <div x-show="syncStarted" class="mb-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>Voortgang</span>
                    <span x-text="syncProgress + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full bg-blue-500 transition-all duration-300"
                         :style="'width: ' + syncProgress + '%'"></div>
                </div>
            </div>

            <!-- Sync Log -->
            <div x-show="syncLog.length > 0" class="bg-gray-50 rounded-lg p-4 mb-4 max-h-48 overflow-y-auto">
                <h3 class="font-medium text-gray-700 mb-2">Sync Log:</h3>
                <ul class="text-sm space-y-1">
                    <template x-for="(item, index) in syncLog" :key="index">
                        <li class="flex items-center">
                            <span x-show="item.status === 'success'" class="text-green-500 mr-2">OK</span>
                            <span x-show="item.status === 'error'" class="text-red-500 mr-2">X</span>
                            <span x-show="item.status === 'pending'" class="text-gray-400 mr-2">...</span>
                            <span x-text="item.message"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <!-- Error Messages -->
            <div x-show="errors.length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <h3 class="font-medium text-red-800 mb-2">Fouten:</h3>
                <ul class="text-sm text-red-700 space-y-1">
                    <template x-for="(error, index) in errors" :key="index">
                        <li x-text="error"></li>
                    </template>
                </ul>
            </div>

            <!-- Last Sync Time -->
            <div x-show="lastSync" class="text-sm text-gray-500 mb-4">
                Laatste sync: <span x-text="lastSync"></span>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button @click="startSync()"
                        :disabled="syncing"
                        class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!syncing">Start Synchronisatie</span>
                    <span x-show="syncing">Bezig met synchroniseren...</span>
                </button>

                <button @click="checkCloudStatus()"
                        :disabled="syncing"
                        class="px-4 py-3 bg-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-300 disabled:opacity-50">
                    Check Status
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <div x-show="syncComplete && errors.length === 0"
             x-transition
             class="bg-green-100 border border-green-300 rounded-lg p-6 mb-6 text-center">
            <div class="text-4xl mb-2">OK</div>
            <h3 class="text-xl font-bold text-green-800">Synchronisatie Voltooid!</h3>
            <p class="text-green-700 mt-2">Alle data is bijgewerkt vanaf de cloud server.</p>
        </div>

        <!-- Navigation -->
        <div class="flex gap-4">
            <a href="{{ route('local.dashboard') }}"
               class="flex-1 py-3 bg-gray-600 text-white font-bold rounded-lg hover:bg-gray-700 text-center">
                Terug naar Dashboard
            </a>
            <a href="{{ route('local.preflight') }}"
               class="flex-1 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 text-center">
                Pre-flight Check
            </a>
        </div>

        <!-- Quick Info -->
        <div class="mt-6 p-4 bg-gray-800 text-white rounded-lg text-sm">
            <h3 class="font-bold mb-2">Wat wordt gesynchroniseerd?</h3>
            <ul class="space-y-1 text-gray-300">
                <li>- Toernooien van vandaag</li>
                <li>- Alle poules en deelnemers</li>
                <li>- Wedstrijdresultaten</li>
                <li>- Club informatie</li>
            </ul>
        </div>
    </div>

    <script>
        function autoSyncApp() {
            return {
                syncing: false,
                syncStarted: false,
                syncComplete: false,
                syncProgress: 0,
                cloudAvailable: null,
                lastSync: null,
                syncLog: [],
                errors: [],

                init() {
                    this.checkCloudStatus();
                    this.checkSyncStatus();
                },

                async checkCloudStatus() {
                    try {
                        const response = await fetch('{{ route("local.sync-status") }}');
                        const data = await response.json();
                        this.cloudAvailable = data.cloud_available;
                        this.lastSync = data.last_cloud_sync;
                    } catch (e) {
                        this.cloudAvailable = false;
                    }
                },

                async checkSyncStatus() {
                    try {
                        const response = await fetch('{{ route("local.sync-status") }}');
                        const data = await response.json();
                        if (data.last_cloud_sync) {
                            this.lastSync = new Date(data.last_cloud_sync).toLocaleString('nl-NL');
                        }
                    } catch (e) {
                        // Ignore errors
                    }
                },

                async startSync() {
                    this.syncing = true;
                    this.syncStarted = true;
                    this.syncComplete = false;
                    this.syncProgress = 0;
                    this.syncLog = [];
                    this.errors = [];

                    this.addLog('Verbinden met cloud server...', 'pending');
                    this.syncProgress = 10;

                    try {
                        const response = await fetch('{{ route("local.auto-sync.execute") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            }
                        });

                        this.syncProgress = 50;
                        this.updateLog(0, 'Verbonden met cloud server', 'success');

                        const data = await response.json();

                        if (data.success) {
                            // Log synced items
                            if (data.items && data.items.length > 0) {
                                data.items.forEach(item => {
                                    this.addLog(`${item.type}: ${item.name}`, item.status === 'synced' ? 'success' : 'error');
                                });
                            } else {
                                this.addLog('Geen toernooien gevonden voor vandaag', 'success');
                            }

                            this.syncProgress = 100;
                            this.syncComplete = true;
                        } else {
                            this.errors = data.errors || ['Onbekende fout'];
                        }

                        // Add any errors
                        if (data.errors && data.errors.length > 0) {
                            this.errors = data.errors;
                        }

                        this.lastSync = new Date().toLocaleString('nl-NL');

                    } catch (e) {
                        this.errors.push('Netwerk fout: ' + e.message);
                        this.updateLog(0, 'Verbinding mislukt', 'error');
                    }

                    this.syncing = false;
                },

                addLog(message, status) {
                    this.syncLog.push({ message, status });
                },

                updateLog(index, message, status) {
                    if (this.syncLog[index]) {
                        this.syncLog[index].message = message;
                        this.syncLog[index].status = status;
                    }
                }
            };
        }
    </script>
</body>
</html>
