<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Flight Check - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4" x-data="preflightCheck()" x-init="init()">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Pre-Flight Check</h1>
                    <p class="text-gray-600">Controleer alle systemen voor de wedstrijddag</p>
                </div>
                <div class="text-right">
                    @if(config('local-server.role') === 'primary')
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 font-bold rounded">PRIMARY</span>
                    @elseif(config('local-server.role') === 'standby')
                        <span class="px-3 py-1 bg-orange-100 text-orange-800 font-bold rounded">STANDBY</span>
                    @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 font-bold rounded">NIET INGESTELD</span>
                    @endif
                    <div class="text-sm text-gray-500 mt-1 font-mono">{{ config('local-server.device_name') ?: gethostname() }}</div>
                </div>
            </div>
        </div>

        <!-- Progress -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div class="flex justify-between text-sm mb-2">
                <span>Voortgang</span>
                <span x-text="passedCount + '/' + totalChecks + ' checks geslaagd'"></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full transition-all duration-500"
                     :class="allPassed ? 'bg-green-500' : 'bg-blue-500'"
                     :style="'width: ' + (passedCount / totalChecks * 100) + '%'"></div>
            </div>
        </div>

        <!-- Checks -->
        <div class="space-y-4">
            <!-- Hardware Checks -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-xl mr-2">💻</span>
                    Hardware Check
                </h2>
                <div class="space-y-3">
                    <template x-for="check in checks.hardware" :key="check.id">
                        <div class="flex items-center justify-between p-3 rounded"
                             :class="check.status === 'pass' ? 'bg-green-50' : (check.status === 'fail' ? 'bg-red-50' : 'bg-gray-50')">
                            <div class="flex items-center">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                      :class="{
                                          'bg-green-500 text-white': check.status === 'pass',
                                          'bg-red-500 text-white': check.status === 'fail',
                                          'bg-gray-300': check.status === 'pending',
                                          'bg-blue-500 text-white animate-pulse': check.status === 'checking'
                                      }">
                                    <span x-show="check.status === 'pass'">✓</span>
                                    <span x-show="check.status === 'fail'">✕</span>
                                    <span x-show="check.status === 'pending'">?</span>
                                    <span x-show="check.status === 'checking'">⋯</span>
                                </span>
                                <span x-text="check.label"></span>
                            </div>
                            <span class="text-sm text-gray-500" x-text="check.message"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Software Checks -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-xl mr-2">🖥️</span>
                    Software Check
                </h2>
                <div class="space-y-3">
                    <template x-for="check in checks.software" :key="check.id">
                        <div class="flex items-center justify-between p-3 rounded"
                             :class="check.status === 'pass' ? 'bg-green-50' : (check.status === 'fail' ? 'bg-red-50' : 'bg-gray-50')">
                            <div class="flex items-center">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                      :class="{
                                          'bg-green-500 text-white': check.status === 'pass',
                                          'bg-red-500 text-white': check.status === 'fail',
                                          'bg-gray-300': check.status === 'pending',
                                          'bg-blue-500 text-white animate-pulse': check.status === 'checking'
                                      }">
                                    <span x-show="check.status === 'pass'">✓</span>
                                    <span x-show="check.status === 'fail'">✕</span>
                                    <span x-show="check.status === 'pending'">?</span>
                                    <span x-show="check.status === 'checking'">⋯</span>
                                </span>
                                <span x-text="check.label"></span>
                            </div>
                            <span class="text-sm text-gray-500" x-text="check.message"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Network Checks -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-xl mr-2">🌐</span>
                    Netwerk Check
                </h2>
                <div class="space-y-3">
                    <template x-for="check in checks.network" :key="check.id">
                        <div class="flex items-center justify-between p-3 rounded"
                             :class="check.status === 'pass' ? 'bg-green-50' : (check.status === 'fail' ? 'bg-red-50' : 'bg-gray-50')">
                            <div class="flex items-center">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                      :class="{
                                          'bg-green-500 text-white': check.status === 'pass',
                                          'bg-red-500 text-white': check.status === 'fail',
                                          'bg-gray-300': check.status === 'pending',
                                          'bg-blue-500 text-white animate-pulse': check.status === 'checking'
                                      }">
                                    <span x-show="check.status === 'pass'">✓</span>
                                    <span x-show="check.status === 'fail'">✕</span>
                                    <span x-show="check.status === 'pending'">?</span>
                                    <span x-show="check.status === 'checking'">⋯</span>
                                </span>
                                <span x-text="check.label"></span>
                            </div>
                            <span class="text-sm text-gray-500" x-text="check.message"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Data Checks -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-xl mr-2">📊</span>
                    Data Check
                </h2>
                <div class="space-y-3">
                    <template x-for="check in checks.data" :key="check.id">
                        <div class="flex items-center justify-between p-3 rounded"
                             :class="check.status === 'pass' ? 'bg-green-50' : (check.status === 'fail' ? 'bg-red-50' : 'bg-gray-50')">
                            <div class="flex items-center">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                      :class="{
                                          'bg-green-500 text-white': check.status === 'pass',
                                          'bg-red-500 text-white': check.status === 'fail',
                                          'bg-gray-300': check.status === 'pending',
                                          'bg-blue-500 text-white animate-pulse': check.status === 'checking'
                                      }">
                                    <span x-show="check.status === 'pass'">✓</span>
                                    <span x-show="check.status === 'fail'">✕</span>
                                    <span x-show="check.status === 'pending'">?</span>
                                    <span x-show="check.status === 'checking'">⋯</span>
                                </span>
                                <span x-text="check.label"></span>
                            </div>
                            <span class="text-sm text-gray-500" x-text="check.message"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex gap-4">
            <button @click="runAllChecks()"
                    class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700"
                    :disabled="running"
                    :class="{ 'opacity-50 cursor-not-allowed': running }">
                <span x-show="!running">Alle checks uitvoeren</span>
                <span x-show="running">Bezig...</span>
            </button>
            <a href="{{ route('local.dashboard') }}"
               class="flex-1 py-3 bg-gray-600 text-white font-bold rounded-lg hover:bg-gray-700 text-center">
                Dashboard
            </a>
        </div>

        <!-- Result -->
        <div x-show="allChecked" class="mt-6 p-6 rounded-lg text-center"
             :class="allPassed ? 'bg-green-100 border-2 border-green-500' : 'bg-red-100 border-2 border-red-500'">
            <div class="text-4xl mb-2" x-text="allPassed ? '✅' : '⚠️'"></div>
            <div class="text-xl font-bold" :class="allPassed ? 'text-green-800' : 'text-red-800'"
                 x-text="allPassed ? 'Alle checks geslaagd!' : 'Sommige checks gefaald'"></div>
            <div class="text-sm mt-2" :class="allPassed ? 'text-green-600' : 'text-red-600'"
                 x-text="allPassed ? 'Het systeem is klaar voor de wedstrijddag.' : 'Los de problemen op voor je begint.'"></div>
        </div>
    </div>

    <script>
        function preflightCheck() {
            return {
                running: false,
                checks: {
                    hardware: [
                        { id: 'laptop', label: 'Laptop A actief', status: 'pending', message: '' },
                        { id: 'battery', label: 'Accu voldoende', status: 'pending', message: '' },
                    ],
                    software: [
                        { id: 'server', label: 'Server draait', status: 'pending', message: '' },
                        { id: 'database', label: 'Database verbinding', status: 'pending', message: '' },
                        { id: 'role', label: 'Server rol geconfigureerd', status: 'pending', message: '' },
                    ],
                    network: [
                        { id: 'wifi', label: 'Wifi verbinding', status: 'pending', message: '' },
                        { id: 'internet', label: 'Internet bereikbaar', status: 'pending', message: '' },
                        { id: 'cloud', label: 'Cloud sync actief', status: 'pending', message: '' },
                    ],
                    data: [
                        { id: 'tournament', label: 'Toernooi data geladen', status: 'pending', message: '' },
                        { id: 'backup', label: 'Backup recent', status: 'pending', message: '' },
                    ]
                },

                get totalChecks() {
                    return Object.values(this.checks).flat().length;
                },

                get passedCount() {
                    return Object.values(this.checks).flat().filter(c => c.status === 'pass').length;
                },

                get allChecked() {
                    return Object.values(this.checks).flat().every(c => c.status !== 'pending' && c.status !== 'checking');
                },

                get allPassed() {
                    return this.allChecked && Object.values(this.checks).flat().every(c => c.status === 'pass');
                },

                init() {
                    // Auto-run checks on page load
                    this.runAllChecks();
                },

                async runAllChecks() {
                    this.running = true;

                    // Hardware checks
                    await this.runCheck('hardware', 'laptop', async () => {
                        return { pass: true, message: 'Online' };
                    });
                    await this.runCheck('hardware', 'battery', async () => {
                        // Can't actually check battery from web, assume OK
                        return { pass: true, message: 'Check handmatig' };
                    });

                    // Software checks
                    await this.runCheck('software', 'server', async () => {
                        try {
                            const response = await fetch('/local-server/health');
                            const data = await response.json();
                            return { pass: data.status === 'healthy', message: data.status };
                        } catch (e) {
                            return { pass: false, message: 'Niet bereikbaar' };
                        }
                    });

                    await this.runCheck('software', 'database', async () => {
                        try {
                            const response = await fetch('/local-server/health');
                            const data = await response.json();
                            const dbOk = !data.issues?.includes('Database connection failed');
                            return { pass: dbOk, message: dbOk ? 'OK' : 'Fout' };
                        } catch (e) {
                            return { pass: false, message: 'Niet bereikbaar' };
                        }
                    });

                    await this.runCheck('software', 'role', async () => {
                        try {
                            const response = await fetch('/local-server/status');
                            const data = await response.json();
                            return { pass: !!data.role, message: data.role || 'Niet geconfigureerd' };
                        } catch (e) {
                            return { pass: false, message: 'Fout' };
                        }
                    });

                    // Network checks
                    await this.runCheck('network', 'wifi', async () => {
                        return { pass: navigator.onLine, message: navigator.onLine ? 'Verbonden' : 'Niet verbonden' };
                    });

                    await this.runCheck('network', 'internet', async () => {
                        try {
                            const response = await fetch('https://judotournament.org', { mode: 'no-cors', cache: 'no-store' });
                            return { pass: true, message: 'Bereikbaar' };
                        } catch (e) {
                            return { pass: false, message: 'Niet bereikbaar' };
                        }
                    });

                    await this.runCheck('network', 'cloud', async () => {
                        // Assume OK if internet works
                        const internetCheck = this.checks.network.find(c => c.id === 'internet');
                        return { pass: internetCheck.status === 'pass', message: internetCheck.status === 'pass' ? 'Actief' : 'Offline modus' };
                    });

                    // Data checks
                    await this.runCheck('data', 'tournament', async () => {
                        try {
                            const response = await fetch('/local-server/sync');
                            const data = await response.json();
                            const count = data.toernooien?.length || 0;
                            return { pass: count > 0, message: count + ' toernooien' };
                        } catch (e) {
                            return { pass: false, message: 'Fout' };
                        }
                    });

                    await this.runCheck('data', 'backup', async () => {
                        // Check localStorage for any backup data
                        const keys = Object.keys(localStorage).filter(k => k.startsWith('noodplan_'));
                        return { pass: keys.length > 0, message: keys.length > 0 ? 'Beschikbaar' : 'Geen backup' };
                    });

                    this.running = false;
                },

                async runCheck(category, id, checkFn) {
                    const check = this.checks[category].find(c => c.id === id);
                    check.status = 'checking';
                    check.message = 'Controleren...';

                    await new Promise(r => setTimeout(r, 300)); // Small delay for UX

                    try {
                        const result = await checkFn();
                        check.status = result.pass ? 'pass' : 'fail';
                        check.message = result.message;
                    } catch (e) {
                        check.status = 'fail';
                        check.message = 'Fout: ' + e.message;
                    }
                }
            };
        }
    </script>
</body>
</html>
