<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Configuratie - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Server Configuratie</h1>
                <p class="text-gray-600 mt-2">Welke rol heeft deze laptop?</p>
            </div>

            <form method="POST" action="{{ route('local.setup.save') }}" class="space-y-6">
                @csrf

                <!-- Primary Option -->
                <label class="block p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                    <div class="flex items-start">
                        <input type="radio" name="role" value="primary" class="mt-1 mr-3" required
                               {{ old('role', $currentRole) === 'primary' ? 'checked' : '' }}>
                        <div>
                            <div class="font-bold text-gray-800">PRIMARY SERVER (Laptop A)</div>
                            <ul class="mt-2 text-sm text-gray-600 space-y-1">
                                <li>→ Dit is de hoofdserver</li>
                                <li>→ Alle tablets/devices verbinden hiermee</li>
                                <li>→ IP wordt: <span class="font-mono">{{ config('local-server.primary_ip') }}</span></li>
                            </ul>
                        </div>
                    </div>
                </label>

                <!-- Standby Option -->
                <label class="block p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-orange-300 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                    <div class="flex items-start">
                        <input type="radio" name="role" value="standby" class="mt-1 mr-3" required
                               {{ old('role', $currentRole) === 'standby' ? 'checked' : '' }}>
                        <div>
                            <div class="font-bold text-gray-800">STANDBY SERVER (Laptop B)</div>
                            <ul class="mt-2 text-sm text-gray-600 space-y-1">
                                <li>→ Dit is de backup server</li>
                                <li>→ Neemt automatisch over bij crash Primary</li>
                                <li>→ IP wordt: <span class="font-mono">{{ config('local-server.standby_ip') }}</span></li>
                            </ul>
                        </div>
                    </div>
                </label>

                <!-- Device Name -->
                <div>
                    <label for="device_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Apparaat naam <span class="text-gray-400">(voor herkenning in Deco app)</span>
                    </label>
                    @php
                        $hostname = gethostname();
                        $defaultName = old('device_name', $currentDeviceName) ?: $hostname;
                    @endphp
                    <input type="text" name="device_name" id="device_name"
                           value="{{ $defaultName }}"
                           placeholder="bijv. Laptop Jurytafel"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500">
                        Computer: <span class="font-mono font-bold">{{ $hostname }}</span>
                    </p>
                </div>

                <!-- Warning -->
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                    <strong>Let op:</strong> Kies elke rol maar op 1 laptop!
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition-colors">
                    Bevestigen
                </button>
            </form>

            @if($currentRole)
            <div class="mt-6 p-3 bg-gray-50 rounded text-sm text-gray-600">
                <strong>Huidige configuratie:</strong><br>
                Rol: {{ ucfirst($currentRole) }}<br>
                IP: {{ $currentIp }}<br>
                @if($configuredAt)
                Geconfigureerd: {{ $configuredAt }}
                @endif
            </div>
            @endif
        </div>

        <!-- Network Info -->
        <div class="mt-4 p-4 bg-white rounded-lg shadow text-sm text-gray-600">
            <strong>Netwerk informatie:</strong>
            <div class="mt-2 font-mono text-xs">
                @php
                    $localIp = request()->server('SERVER_ADDR') ?? gethostbyname(gethostname());
                @endphp
                Huidig IP: {{ $localIp }}<br>
                Hostname: {{ gethostname() }}
            </div>
        </div>
    </div>
</body>
</html>
