<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('QR Login goedkeuren') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
            <p class="text-gray-600">{{ __('QR Login Goedkeuren') }}</p>
        </div>

        @auth('organisator')
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600 mb-2">{{ __('Een apparaat wil inloggen op je account:') }}</p>
                @if($deviceInfo)
                <div class="text-sm text-gray-500 space-y-1">
                    @if(!empty($deviceInfo['browser']))
                    <p><span class="font-medium">{{ __('Browser:') }}</span> {{ $deviceInfo['browser'] }}</p>
                    @endif
                    @if(!empty($deviceInfo['os']))
                    <p><span class="font-medium">{{ __('Besturingssysteem:') }}</span> {{ $deviceInfo['os'] }}</p>
                    @endif
                    @if(!empty($deviceInfo['ip']))
                    <p><span class="font-medium">{{ __('IP-adres:') }}</span> {{ $deviceInfo['ip'] }}</p>
                    @endif
                </div>
                @endif
            </div>

            <div id="approve-section">
                <button onclick="approveLogin()" id="approve-btn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors mb-3">
                    {{ __('Goedkeuren') }}
                </button>
                <a href="{{ route('organisator.dashboard', ['organisator' => auth('organisator')->user()->slug]) }}"
                   class="block text-center text-sm text-gray-500 hover:underline">
                    {{ __('Annuleren') }}
                </a>
            </div>

            <div id="success-section" class="hidden text-center">
                <div class="text-green-500 mb-2">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-lg font-medium text-gray-800">{{ __('Inloggen goedgekeurd!') }}</p>
                <p class="text-sm text-gray-500">{{ __('Het andere apparaat wordt nu ingelogd.') }}</p>
            </div>

            <p id="error-msg" class="text-center text-red-500 text-sm mt-4 hidden"></p>
        @else
            <div class="text-center">
                <p class="text-gray-600 mb-4">{{ __('Je moet eerst ingelogd zijn op dit apparaat om de login goed te keuren.') }}</p>
                <a href="{{ route('login') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    {{ __('Inloggen') }}
                </a>
            </div>
        @endauth
    </div>

@auth('organisator')
<script>
async function approveLogin() {
    const btn = document.getElementById('approve-btn');
    btn.disabled = true;
    btn.textContent = 'Goedkeuren...';

    try {
        const res = await fetch('/auth/qr/approve/{{ $token->token }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('approve-section').classList.add('hidden');
            document.getElementById('success-section').classList.remove('hidden');
        } else {
            document.getElementById('error-msg').textContent = data.message || 'Goedkeuren mislukt';
            document.getElementById('error-msg').classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Goedkeuren';
        }
    } catch (err) {
        document.getElementById('error-msg').textContent = 'Er ging iets mis';
        document.getElementById('error-msg').classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Goedkeuren';
    }
}
</script>
@endauth
</body>
</html>
