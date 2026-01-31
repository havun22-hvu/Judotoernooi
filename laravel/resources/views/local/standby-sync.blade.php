<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standby Sync - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen text-white p-4" x-data="standbySync()" x-init="init()">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-orange-500">STANDBY SERVER</h1>
            <p class="text-gray-400 mt-2">Synchroniseert met Primary</p>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Primary Status -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400 mb-2">Primary Server</div>
                <div class="flex items-center">
                    <span class="w-4 h-4 rounded-full mr-2"
                          :class="primaryOnline ? 'bg-green-500 animate-pulse' : 'bg-red-500'"></span>
                    <span class="text-xl font-bold" :class="primaryOnline ? 'text-green-500' : 'text-red-500'"
                          x-text="primaryOnline ? 'ONLINE' : 'OFFLINE'"></span>
                </div>
                <div class="text-xs text-gray-500 mt-2">
                    IP: <span class="font-mono">{{ config('local-server.primary_ip') }}:{{ config('local-server.port') }}</span>
                </div>
            </div>

            <!-- Sync Status -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-400 mb-2">Synchronisatie</div>
                <div class="flex items-center">
                    <span class="w-4 h-4 rounded-full mr-2"
                          :class="syncing ? 'bg-blue-500 animate-spin' : (lastSyncOk ? 'bg-green-500' : 'bg-yellow-500')"></span>
                    <span class="text-xl font-bold" x-text="syncStatusText"></span>
                </div>
                <div class="text-xs text-gray-500 mt-2">
                    Laatste sync: <span x-text="lastSyncTime || '-'"></span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-3xl font-bold text-blue-400" x-text="stats.toernooien">0</div>
                    <div class="text-sm text-gray-400">Toernooien</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-green-400" x-text="stats.poules">0</div>
                    <div class="text-sm text-gray-400">Poules</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-yellow-400" x-text="stats.wedstrijden">0</div>
                    <div class="text-sm text-gray-400">Wedstrijden</div>
                </div>
            </div>
        </div>

        <!-- Log -->
        <div class="bg-gray-800 rounded-lg p-4">
            <div class="flex justify-between items-center mb-3">
                <h2 class="font-bold text-gray-300">Sync Log</h2>
                <button @click="logs = []" class="text-xs text-gray-500 hover:text-gray-300">
                    Wissen
                </button>
            </div>
            <div class="h-48 overflow-y-auto font-mono text-xs space-y-1">
                <template x-for="log in logs.slice().reverse()" :key="log.time">
                    <div :class="{
                        'text-green-400': log.type === 'success',
                        'text-red-400': log.type === 'error',
                        'text-blue-400': log.type === 'info',
                        'text-gray-400': log.type === 'debug'
                    }">
                        <span x-text="log.time"></span> - <span x-text="log.message"></span>
                    </div>
                </template>
                <div x-show="logs.length === 0" class="text-gray-500">
                    Wachten op eerste sync...
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex gap-4">
            <button @click="forcSync()"
                    class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                Force Sync
            </button>
            <a href="{{ route('local.dashboard') }}"
               class="flex-1 py-3 bg-gray-700 text-white font-bold rounded-lg hover:bg-gray-600 text-center">
                Dashboard
            </a>
        </div>

        <!-- Failover Alert -->
        <div x-show="!primaryOnline && missedHeartbeats >= 3"
             class="mt-6 p-4 bg-red-900 border-2 border-red-500 rounded-lg text-center">
            <div class="text-2xl mb-2">⚠️</div>
            <div class="text-xl font-bold text-red-300">PRIMARY OFFLINE</div>
            <div class="text-sm text-red-400 mt-2">
                <span x-text="missedHeartbeats"></span> heartbeats gemist
            </div>
            <button @click="activateAsPrimary()"
                    class="mt-4 px-6 py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">
                Activeer als Primary
            </button>
        </div>
    </div>

    <script>
        function standbySync() {
            return {
                primaryOnline: false,
                syncing: false,
                lastSyncOk: false,
                lastSyncTime: null,
                missedHeartbeats: 0,
                logs: [],
                stats: {
                    toernooien: 0,
                    poules: 0,
                    wedstrijden: 0
                },

                primaryUrl: 'http://{{ config("local-server.primary_ip") }}:{{ config("local-server.port") }}',
                heartbeatInterval: {{ config('local-server.heartbeat_interval') * 1000 }},
                syncInterval: {{ config('local-server.sync_interval') * 1000 }},

                init() {
                    this.log('info', 'Standby sync gestart');
                    this.startHeartbeat();
                    this.startSync();
                },

                log(type, message) {
                    const time = new Date().toLocaleTimeString('nl-NL');
                    this.logs.push({ type, message, time });
                    if (this.logs.length > 100) this.logs.shift();
                },

                get syncStatusText() {
                    if (this.syncing) return 'Synchroniseren...';
                    if (this.lastSyncOk) return 'Gesynchroniseerd';
                    return 'Wachtend';
                },

                startHeartbeat() {
                    setInterval(() => this.checkHeartbeat(), this.heartbeatInterval);
                    this.checkHeartbeat();
                },

                async checkHeartbeat() {
                    try {
                        const response = await fetch(`${this.primaryUrl}/local-server/heartbeat`, {
                            method: 'GET',
                            mode: 'cors',
                            cache: 'no-store',
                            signal: AbortSignal.timeout(3000)
                        });

                        if (response.ok) {
                            this.primaryOnline = true;
                            this.missedHeartbeats = 0;
                            this.log('debug', 'Heartbeat OK');
                        } else {
                            throw new Error('Response not OK');
                        }
                    } catch (e) {
                        this.missedHeartbeats++;
                        this.log('error', `Heartbeat failed (${this.missedHeartbeats}x)`);

                        if (this.missedHeartbeats >= 3) {
                            this.primaryOnline = false;
                        }
                    }
                },

                startSync() {
                    setInterval(() => this.doSync(), this.syncInterval);
                    this.doSync();
                },

                async doSync() {
                    if (!this.primaryOnline) {
                        this.log('debug', 'Skip sync - primary offline');
                        return;
                    }

                    this.syncing = true;

                    try {
                        const response = await fetch(`${this.primaryUrl}/local-server/sync`, {
                            method: 'GET',
                            mode: 'cors',
                            cache: 'no-store',
                            signal: AbortSignal.timeout(10000)
                        });

                        if (!response.ok) throw new Error('Sync response not OK');

                        const data = await response.json();

                        // Store locally
                        await this.storeData(data);

                        // Update stats
                        this.stats.toernooien = data.toernooien?.length || 0;
                        this.stats.poules = data.toernooien?.reduce((sum, t) => sum + (t.poules?.length || 0), 0) || 0;
                        this.stats.wedstrijden = data.toernooien?.reduce((sum, t) =>
                            sum + t.poules?.reduce((ps, p) => ps + (p.wedstrijden?.length || 0), 0) || 0, 0) || 0;

                        this.lastSyncOk = true;
                        this.lastSyncTime = new Date().toLocaleTimeString('nl-NL');
                        this.log('success', `Sync OK: ${this.stats.poules} poules, ${this.stats.wedstrijden} wedstrijden`);

                    } catch (e) {
                        this.lastSyncOk = false;
                        this.log('error', `Sync failed: ${e.message}`);
                    } finally {
                        this.syncing = false;
                    }
                },

                async storeData(data) {
                    // Store in localStorage for each tournament
                    if (data.toernooien) {
                        for (const toernooi of data.toernooien) {
                            const storageKey = `noodplan_${toernooi.toernooi_id}_poules`;
                            localStorage.setItem(storageKey, JSON.stringify(toernooi));
                            localStorage.setItem(`noodplan_${toernooi.toernooi_id}_laatste_sync`, new Date().toISOString());
                        }
                    }

                    // Also send to our own server for database sync
                    try {
                        await fetch('/local-server/receive-sync', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(data)
                        });
                    } catch (e) {
                        // Ignore - localStorage backup is enough
                    }
                },

                async forcSync() {
                    this.log('info', 'Force sync gestart');
                    await this.doSync();
                },

                activateAsPrimary() {
                    if (confirm('Weet je zeker dat je deze server als PRIMARY wilt activeren? Dit moet alleen als de echte Primary niet meer werkt.')) {
                        window.location.href = '{{ route("local.setup") }}';
                    }
                }
            };
        }
    </script>
</body>
</html>
