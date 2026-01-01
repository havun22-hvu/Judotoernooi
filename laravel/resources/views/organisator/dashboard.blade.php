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
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2">{{ $toernooi->naam }}</h3>
                <p class="text-gray-600 mb-4">
                    {{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : 'Geen datum' }}
                </p>
                <div class="flex items-center justify-between">
                    @if($organisator->isSitebeheerder())
                    <span class="text-sm px-2 py-1 rounded bg-purple-100 text-purple-800">
                        Sitebeheerder
                    </span>
                    @elseif($toernooi->pivot)
                    <span class="text-sm px-2 py-1 rounded
                        @if($toernooi->pivot->rol === 'eigenaar') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($toernooi->pivot->rol) }}
                    </span>
                    @endif
                    <a href="{{ route('toernooi.show', $toernooi) }}"
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Beheer
                    </a>
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
