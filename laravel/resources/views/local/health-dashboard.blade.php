<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5">
    <title>Health Dashboard - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen text-white p-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">System Health Dashboard</h1>
                <p class="text-gray-400">Real-time monitoring</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-400">Laatste update</div>
                <div class="font-mono">{{ now()->format('H:i:s') }}</div>
            </div>
        </div>

        <!-- This Server Identity -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6 border-2 {{ config('local-server.role') === 'primary' ? 'border-blue-500' : 'border-orange-500' }}">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm text-gray-400">Deze laptop</div>
                    <div class="text-2xl font-bold">{{ config('local-server.device_name') ?: gethostname() }}</div>
                </div>
                <div class="text-right">
                    @if(config('local-server.role') === 'primary')
                        <span class="px-4 py-2 bg-blue-600 text-white text-xl font-bold rounded">PRIMARY</span>
                    @elseif(config('local-server.role') === 'standby')
                        <span class="px-4 py-2 bg-orange-600 text-white text-xl font-bold rounded">STANDBY</span>
                    @else
                        <span class="px-4 py-2 bg-gray-600 text-white font-bold rounded">NIET INGESTELD</span>
                    @endif
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-400 font-mono">
                IP: {{ config('local-server.ip') ?? 'Niet geconfigureerd' }}
            </div>
        </div>

        <!-- Main Status Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <!-- Cloud Sync -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Cloud Sync</span>
                    <span class="w-3 h-3 rounded-full {{ $cloudOnline ? 'bg-green-500' : 'bg-red-500' }}"></span>
                </div>
                <div class="text-lg font-bold {{ $cloudOnline ? 'text-green-400' : 'text-red-400' }}">
                    {{ $cloudOnline ? 'ONLINE' : 'OFFLINE' }}
                </div>
            </div>

            <!-- Standby Server -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Standby</span>
                    <span class="w-3 h-3 rounded-full {{ $standbyOnline ? 'bg-green-500' : 'bg-orange-500' }}"></span>
                </div>
                <div class="text-lg font-bold {{ $standbyOnline ? 'text-green-400' : 'text-orange-400' }}">
                    {{ $standbyOnline ? 'ACTIEF' : 'GEEN' }}
                </div>
            </div>

            <!-- Network -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Netwerk</span>
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                </div>
                <div class="text-lg font-bold text-green-400">STABIEL</div>
            </div>

            <!-- Backup -->
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Backup</span>
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                </div>
                <div class="text-lg font-bold text-green-400">ACTUEEL</div>
            </div>
        </div>

        <!-- Device Status -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Device Status</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($devices as $device)
                <div class="bg-gray-700 rounded p-3">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $device['name'] }}</span>
                        <span class="w-2 h-2 rounded-full {{ $device['online'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ $device['type'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Toernooien -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Actieve Toernooien</h2>
            @forelse($toernooien as $toernooi)
            <div class="bg-gray-700 rounded p-4 mb-3">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="font-bold">{{ $toernooi->naam }}</div>
                        <div class="text-sm text-gray-400">
                            {{ $toernooi->judokas()->count() }} judoka's |
                            {{ $toernooi->poules()->whereNotNull('mat_id')->count() }} actieve poules
                        </div>
                    </div>
                    <div class="text-right">
                        @if($toernooi->isWedstrijddagGestart())
                            <span class="px-2 py-1 bg-green-600 text-white text-sm rounded">ACTIEF</span>
                        @else
                            <span class="px-2 py-1 bg-yellow-600 text-white text-sm rounded">VOORBEREIDING</span>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <p class="text-gray-400">Geen toernooien vandaag</p>
            @endforelse
        </div>

        <!-- Quick Actions -->
        <div class="flex gap-4">
            <a href="{{ route('local.dashboard') }}"
               class="flex-1 py-3 bg-gray-700 text-white text-center font-bold rounded-lg hover:bg-gray-600">
                ← Dashboard
            </a>
            <a href="{{ route('local.sync') }}" target="_blank"
               class="flex-1 py-3 bg-blue-600 text-white text-center font-bold rounded-lg hover:bg-blue-700">
                Sync Data (JSON)
            </a>
        </div>
    </div>
</body>
</html>
