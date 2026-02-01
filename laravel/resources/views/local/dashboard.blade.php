<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5">
    <title>{{ ucfirst($config['role'] ?? 'Server') }} Dashboard - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        @if($config['role'] === 'primary')
                            <span class="text-blue-600">PRIMARY</span> Server
                        @elseif($config['role'] === 'standby')
                            <span class="text-orange-600">STANDBY</span> Server
                        @else
                            Server Niet Geconfigureerd
                        @endif
                    </h1>
                    <p class="text-gray-600">
                        <span class="font-mono font-bold text-lg">{{ $config['device_name'] ?: gethostname() }}</span>
                    </p>
                </div>
                <div class="text-right text-sm text-gray-500">
                    <p>IP: <span class="font-mono">{{ $config['ip'] ?? 'Niet ingesteld' }}</span></p>
                    <p>Poort: <span class="font-mono">{{ $config['port'] }}</span></p>
                    <a href="{{ route('local.setup') }}" class="text-blue-600 hover:underline">
                        Instellingen wijzigen →
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(!$config['role'])
            <!-- Not configured -->
            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-6 mb-6 rounded">
                <h2 class="text-lg font-bold text-yellow-800">Server niet geconfigureerd</h2>
                <p class="text-yellow-700 mt-2">
                    Deze server moet eerst geconfigureerd worden als Primary of Standby.
                </p>
                <a href="{{ route('local.setup') }}"
                   class="inline-block mt-4 px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">
                    Configureren
                </a>
            </div>
        @else
            <!-- Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Server Status -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 mb-1">Server Status</div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                        <span class="text-lg font-bold text-green-700">Online</span>
                    </div>
                </div>

                <!-- Sync Status -->
                @if($config['role'] === 'standby')
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 mb-1">Primary Status</div>
                    @if($primaryStatus)
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                            <span class="text-lg font-bold text-green-700">Verbonden</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Laatste heartbeat: {{ \Carbon\Carbon::parse($primaryStatus)->format('H:i:s') }}
                        </div>
                    @else
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span>
                            <span class="text-lg font-bold text-red-700">Niet verbonden</span>
                        </div>
                    @endif
                </div>
                @else
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 mb-1">Standby Status</div>
                    @if($standbyStatus)
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                            <span class="text-lg font-bold text-green-700">Verbonden</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Laatste heartbeat: {{ \Carbon\Carbon::parse($standbyStatus)->format('H:i:s') }}
                        </div>
                    @else
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-orange-500 mr-2"></span>
                            <span class="text-lg font-bold text-orange-700">Geen standby</span>
                        </div>
                    @endif
                </div>
                @endif

                <!-- Last Sync -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 mb-1">Laatste Sync</div>
                    @if($lastSync)
                        <div class="text-lg font-bold text-gray-800">
                            {{ \Carbon\Carbon::parse($lastSync)->format('H:i:s') }}
                        </div>
                    @else
                        <div class="text-lg font-bold text-gray-400">-</div>
                    @endif
                </div>
            </div>

            <!-- Today's Tournaments -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Toernooien Vandaag</h2>
                @if($toernooien->isEmpty())
                    <p class="text-gray-500">Geen toernooien gepland voor vandaag.</p>
                @else
                    <div class="space-y-3">
                        @foreach($toernooien as $toernooi)
                            <div class="p-3 bg-gray-50 rounded flex justify-between items-center">
                                <div>
                                    <div class="font-medium">{{ $toernooi->naam }}</div>
                                    <div class="text-sm text-gray-500">
                                        {{ $toernooi->judokas()->count() }} judoka's |
                                        {{ $toernooi->poules()->count() }} poules
                                    </div>
                                </div>
                                @if($toernooi->isWedstrijddagGestart())
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-sm rounded">
                                        Actief
                                    </span>
                                @else
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-sm rounded">
                                        Voorbereiding
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Startup Wizard Button -->
            <a href="{{ route('local.startup-wizard') }}"
               class="block bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-6 mb-6 text-center hover:from-green-600 hover:to-green-700 transition-all">
                <div class="text-4xl mb-2">🚀</div>
                <div class="text-xl font-bold text-white">Wedstrijddag Opstarten</div>
                <div class="text-green-100 text-sm mt-1">Stap-voor-stap handleiding</div>
            </a>

            <!-- Quick Links -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Snelle Links</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <a href="{{ route('local.preflight') }}"
                       class="p-3 bg-green-50 rounded text-center hover:bg-green-100">
                        <div class="text-2xl mb-1">✅</div>
                        <div class="text-sm">Pre-flight Check</div>
                    </a>
                    <a href="{{ route('local.health-dashboard') }}"
                       class="p-3 bg-gray-50 rounded text-center hover:bg-gray-100">
                        <div class="text-2xl mb-1">📊</div>
                        <div class="text-sm">Health Dashboard</div>
                    </a>
                    @if($config['role'] === 'standby')
                    <a href="{{ route('local.standby-sync') }}"
                       class="p-3 bg-orange-50 rounded text-center hover:bg-orange-100">
                        <div class="text-2xl mb-1">🔄</div>
                        <div class="text-sm">Sync Status</div>
                    </a>
                    @else
                    <a href="{{ route('local.auto-sync') }}"
                       class="p-3 bg-blue-50 rounded text-center hover:bg-blue-100">
                        <div class="text-2xl mb-1">☁️</div>
                        <div class="text-sm">Cloud Sync</div>
                    </a>
                    @endif
                    <a href="{{ route('organisator.login') }}"
                       class="p-3 bg-blue-50 rounded text-center hover:bg-blue-100">
                        <div class="text-2xl mb-1">🔐</div>
                        <div class="text-sm">Inloggen</div>
                    </a>
                </div>
            </div>

            <!-- Emergency Button (only for standby) -->
            @if($config['role'] === 'standby')
            <div class="bg-red-50 border-2 border-red-200 rounded-lg shadow p-6 mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-red-800">Nood Overschakeling</h2>
                        <p class="text-sm text-red-600">Alleen gebruiken als Primary niet meer werkt</p>
                    </div>
                    <a href="{{ route('local.emergency-failover') }}"
                       class="px-6 py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">
                        ⚠️ NOODKNOP
                    </a>
                </div>
            </div>
            @endif
        @endif

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>JudoToernooi Lokale Server | Pagina ververst automatisch elke 5 seconden</p>
            <p class="font-mono text-xs mt-1">{{ now()->format('H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
