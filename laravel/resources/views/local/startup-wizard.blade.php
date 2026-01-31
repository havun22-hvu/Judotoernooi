<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedstrijddag Opstarten - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-2xl mx-auto" x-data="startupWizard()">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
            <h1 class="text-3xl font-bold text-gray-800">Wedstrijddag Opstarten</h1>
            <p class="text-gray-600 mt-2">Volg deze stappen om het systeem op te starten</p>
        </div>

        <!-- Progress Bar -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div class="flex justify-between text-sm mb-2">
                <span>Voortgang</span>
                <span x-text="'Stap ' + currentStep + ' van ' + totalSteps"></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full bg-green-500 transition-all duration-500"
                     :style="'width: ' + (currentStep / totalSteps * 100) + '%'"></div>
            </div>
        </div>

        <!-- Step 1: Primary Laptop -->
        <div x-show="currentStep === 1" class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center text-3xl flex-shrink-0">
                    1
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Start Primary Laptop</h2>
                    <p class="text-gray-600 mb-4">
                        Dit is de <strong>hoofdlaptop</strong> waar alle tablets mee verbinden.
                    </p>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-blue-800 mb-2">Wat te doen:</h3>
                        <ol class="list-decimal list-inside text-blue-700 space-y-2">
                            <li>Zet de <strong>eerste laptop</strong> aan</li>
                            <li>Dubbelklik op <code class="bg-blue-100 px-1 rounded">start-server.bat</code></li>
                            <li>Wacht tot de browser opent</li>
                            <li>Kies <strong>"PRIMARY"</strong> in het configuratiescherm</li>
                        </ol>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
                        <strong>Tip:</strong> Noteer de computernaam die wordt getoond - je hebt die straks nodig!
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="nextStep()"
                        class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                    Primary is gestart →
                </button>
            </div>
        </div>

        <!-- Step 2: Standby Laptop -->
        <div x-show="currentStep === 2" class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center text-3xl flex-shrink-0">
                    2
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Start Standby Laptop</h2>
                    <p class="text-gray-600 mb-4">
                        Dit is de <strong>backup laptop</strong> die het overneemt als de Primary crasht.
                    </p>

                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-orange-800 mb-2">Wat te doen:</h3>
                        <ol class="list-decimal list-inside text-orange-700 space-y-2">
                            <li>Zet de <strong>tweede laptop</strong> aan</li>
                            <li>Dubbelklik op <code class="bg-orange-100 px-1 rounded">start-server.bat</code></li>
                            <li>Wacht tot de browser opent</li>
                            <li>Kies <strong>"STANDBY"</strong> in het configuratiescherm</li>
                        </ol>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm">
                        <strong>Optioneel:</strong> Als je geen backup laptop hebt, kun je deze stap overslaan.
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <button @click="prevStep()"
                        class="px-6 py-3 bg-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-300">
                    ← Terug
                </button>
                <div class="flex gap-2">
                    <button @click="currentStep = 4"
                            class="px-4 py-3 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">
                        Overslaan
                    </button>
                    <button @click="nextStep()"
                            class="px-6 py-3 bg-orange-600 text-white font-bold rounded-lg hover:bg-orange-700">
                        Standby is gestart →
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Deco Configuration -->
        <div x-show="currentStep === 3" class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-3xl flex-shrink-0">
                    3
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Configureer Wifi (Deco App)</h2>
                    <p class="text-gray-600 mb-4">
                        Geef de Primary laptop een <strong>vast IP-adres</strong> zodat tablets hem altijd kunnen vinden.
                    </p>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-green-800 mb-2">Wat te doen:</h3>
                        <ol class="list-decimal list-inside text-green-700 space-y-3">
                            <li>Open de <strong>Deco app</strong> op je telefoon</li>
                            <li>Tik op <strong>"Apparaten"</strong> (onderaan)</li>
                            <li>Zoek de Primary laptop op naam
                                <div class="ml-5 mt-1 bg-white p-2 rounded text-sm font-mono">
                                    {{ config('local-server.device_name') ?: gethostname() }}
                                </div>
                            </li>
                            <li>Tik op het apparaat</li>
                            <li>Scroll naar <strong>"IP Reserveren"</strong></li>
                            <li>Vul in: <span class="font-mono font-bold bg-green-200 px-2 py-1 rounded">{{ config('local-server.primary_ip') }}</span></li>
                            <li>Tik op <strong>"Opslaan"</strong></li>
                        </ol>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm">
                        <strong>Waarom?</strong> Tablets zoeken altijd naar IP {{ config('local-server.primary_ip') }}.
                        Door dit IP te reserveren weten ze de Primary laptop te vinden.
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <button @click="prevStep()"
                        class="px-6 py-3 bg-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-300">
                    ← Terug
                </button>
                <button @click="nextStep()"
                        class="px-6 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700">
                    Deco is geconfigureerd →
                </button>
            </div>
        </div>

        <!-- Step 4: Pre-flight Check -->
        <div x-show="currentStep === 4" class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center text-3xl flex-shrink-0">
                    4
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Systeem Controleren</h2>
                    <p class="text-gray-600 mb-4">
                        Voer een snelle controle uit om te zien of alles werkt.
                    </p>

                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-purple-800 mb-2">Wat te doen:</h3>
                        <ol class="list-decimal list-inside text-purple-700 space-y-2">
                            <li>Klik op de knop hieronder</li>
                            <li>Wacht tot alle checks groen zijn</li>
                            <li>Als iets rood is: los het probleem op</li>
                        </ol>
                    </div>

                    <a href="{{ route('local.preflight') }}" target="_blank"
                       class="inline-block px-6 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700">
                        Open Pre-Flight Check →
                    </a>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <button @click="prevStep()"
                        class="px-6 py-3 bg-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-300">
                    ← Terug
                </button>
                <button @click="nextStep()"
                        class="px-6 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700">
                    Checks zijn OK →
                </button>
            </div>
        </div>

        <!-- Step 5: Done! -->
        <div x-show="currentStep === 5" class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="text-center">
                <div class="text-6xl mb-4">🎉</div>
                <h2 class="text-2xl font-bold text-green-700 mb-2">Systeem is klaar!</h2>
                <p class="text-gray-600 mb-6">
                    Alles is opgestart en geconfigureerd. Je kunt nu beginnen met het toernooi.
                </p>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-bold text-green-800 mb-2">Samenvatting:</h3>
                    <ul class="text-green-700 space-y-1">
                        <li>✅ Primary laptop draait</li>
                        <li>✅ Standby laptop draait (backup)</li>
                        <li>✅ Wifi is geconfigureerd</li>
                        <li>✅ Systeem is gecontroleerd</li>
                    </ul>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-bold text-yellow-800 mb-2">Bij problemen tijdens het toernooi:</h3>
                    <ol class="list-decimal list-inside text-yellow-700 space-y-1">
                        <li>Ga naar de <strong>Standby laptop</strong></li>
                        <li>Klik op <strong>"NOODKNOP"</strong></li>
                        <li>Volg de instructies op het scherm</li>
                    </ol>
                </div>

                <div class="flex gap-4 justify-center">
                    <a href="{{ route('local.dashboard') }}"
                       class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                        Naar Dashboard
                    </a>
                    <a href="{{ route('organisator.login') }}"
                       class="px-6 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700">
                        Inloggen & Beginnen
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Reference Card -->
        <div class="bg-gray-800 text-white rounded-lg shadow p-6 mt-6">
            <h3 class="font-bold mb-3">Snelle Referentie</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-400">Primary IP</div>
                    <div class="font-mono">{{ config('local-server.primary_ip') }}</div>
                </div>
                <div>
                    <div class="text-gray-400">Standby IP</div>
                    <div class="font-mono">{{ config('local-server.standby_ip') }}</div>
                </div>
                <div>
                    <div class="text-gray-400">Server Poort</div>
                    <div class="font-mono">{{ config('local-server.port') }}</div>
                </div>
                <div>
                    <div class="text-gray-400">Dashboard URL</div>
                    <div class="font-mono text-xs">http://{{ config('local-server.primary_ip') }}:{{ config('local-server.port') }}/local-server</div>
                </div>
            </div>
        </div>

        <!-- Back to Dashboard -->
        <div class="mt-4 text-center">
            <a href="{{ route('local.dashboard') }}" class="text-gray-500 hover:text-gray-700">
                ← Terug naar Dashboard
            </a>
        </div>
    </div>

    <script>
        function startupWizard() {
            return {
                currentStep: 1,
                totalSteps: 5,

                nextStep() {
                    if (this.currentStep < this.totalSteps) {
                        this.currentStep++;
                    }
                },

                prevStep() {
                    if (this.currentStep > 1) {
                        this.currentStep--;
                    }
                }
            };
        }
    </script>
</body>
</html>
