<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800">JudoToernooi</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">{{ $organisator->naam }}</span>
                    @if($organisator->isSitebeheerder())
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">Sitebeheerder</span>
                    @endif
                    <form action="{{ route('organisator.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">
                            Uitloggen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                @if($organisator->isSitebeheerder())
                    Alle Toernooien
                @else
                    Mijn Toernooien
                @endif
            </h2>
            <a href="{{ route('toernooi.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                Nieuw Toernooi
            </a>
        </div>

        @if($toernooien->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-600 mb-4">Je hebt nog geen toernooien aangemaakt.</p>
            <a href="{{ route('toernooi.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors inline-block">
                Maak je eerste toernooi aan
            </a>
        </div>
        @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($toernooien as $toernooi)
            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <h3 class="text-lg font-bold text-gray-800">{{ $toernooi->naam }}</h3>
                    @if($organisator->isSitebeheerder())
                    <span class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-800">
                        Admin
                    </span>
                    @elseif($toernooi->pivot)
                    <span class="text-xs px-2 py-1 rounded
                        @if($toernooi->pivot->rol === 'eigenaar') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($toernooi->pivot->rol) }}
                    </span>
                    @endif
                </div>

                {{-- Toernooi datum --}}
                <div class="flex items-center text-gray-600 mb-2">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="font-medium">{{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : 'Geen datum' }}</span>
                </div>

                {{-- Statistieken --}}
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-500 mb-3">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $toernooi->judokas_count ?? $toernooi->judokas()->count() }} judoka's
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        {{ $toernooi->poules_count ?? $toernooi->poules()->count() }} poules
                    </div>
                </div>

                {{-- Timestamps --}}
                <div class="text-xs text-gray-400 border-t pt-3 space-y-1">
                    <div class="flex justify-between">
                        <span>Aangemaakt:</span>
                        <span>{{ $toernooi->created_at ? $toernooi->created_at->format('d-m-Y H:i') : '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Laatst bewerkt:</span>
                        <span>{{ $toernooi->updated_at ? $toernooi->updated_at->diffForHumans() : '-' }}</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between mt-4 pt-3 border-t">
                    <a href="{{ route('toernooi.show', $toernooi) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded transition-colors">
                        Start
                    </a>
                    @if($organisator->isSitebeheerder() || ($toernooi->pivot && $toernooi->pivot->rol === 'eigenaar'))
                    <form action="{{ route('toernooi.destroy', $toernooi) }}" method="POST" class="inline"
                          onsubmit="return confirm('Weet je zeker dat je \'{{ $toernooi->naam }}\' wilt verwijderen?\n\nDit verwijdert ALLE data:\n- Judoka\'s\n- Poules\n- Wedstrijden\n\nDit kan niet ongedaan worden!')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-400 hover:text-red-600" title="Verwijder toernooi">
                            🗑️
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>

    {{-- Idle Timeout - Auto logout after 20 minutes inactivity --}}
    <script>
        (function() {
            const IDLE_TIMEOUT = 20 * 60 * 1000; // 20 minutes in ms
            const WARNING_BEFORE = 2 * 60 * 1000; // Show warning 2 min before
            let idleTimer;
            let warningTimer;
            let warningShown = false;

            function resetTimers() {
                clearTimeout(idleTimer);
                clearTimeout(warningTimer);
                warningShown = false;

                const warning = document.getElementById('idle-warning');
                if (warning) warning.remove();

                warningTimer = setTimeout(showWarning, IDLE_TIMEOUT - WARNING_BEFORE);
                idleTimer = setTimeout(doLogout, IDLE_TIMEOUT);
            }

            function showWarning() {
                if (warningShown) return;
                warningShown = true;

                const warning = document.createElement('div');
                warning.id = 'idle-warning';
                warning.innerHTML = `
                    <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
                        <div style="background:white;padding:24px;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                            <h3 style="font-size:18px;font-weight:bold;margin-bottom:12px;color:#b91c1c;">⚠️ Sessie verloopt bijna</h3>
                            <p style="margin-bottom:16px;color:#374151;">Je wordt over 2 minuten automatisch uitgelogd wegens inactiviteit.</p>
                            <button onclick="document.getElementById('idle-warning').remove();resetIdleTimers();"
                                    style="background:#2563eb;color:white;padding:10px 24px;border-radius:6px;border:none;cursor:pointer;font-weight:500;">
                                Actief blijven
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(warning);
            }

            function doLogout() {
                const logoutForm = document.querySelector('form[action*="logout"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    window.location.href = '{{ route("organisator.login") }}';
                }
            }

            window.resetIdleTimers = resetTimers;

            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
                document.addEventListener(event, resetTimers, { passive: true });
            });

            resetTimers();
        })();
    </script>
</body>
</html>
