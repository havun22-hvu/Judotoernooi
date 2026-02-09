<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ isset($toernooi) ? $toernooi->naam : 'Judo Toernooi' }}</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="@yield('manifest', '/manifest.json')">
    <meta name="theme-color" content="#1e40af">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Verberg spinner pijltjes bij number inputs */
        input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        /* Knipperend effect voor 10 seconden */
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .animate-blink-10s {
            animation: blink 0.5s ease-in-out 3;
        }
        .animate-error-blink {
            animation: blink 0.5s ease-in-out 3;
        }
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #fff;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <nav class="bg-blue-800 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="{{ isset($toernooi) ? route('toernooi.show', $toernooi->routeParams()) : route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="text-xl font-bold">{{ isset($toernooi) ? $toernooi->naam : 'Judo Toernooi' }}</a>
                    @if(isset($toernooi))
                    @php
                        $currentRoute = Route::currentRouteName();
                    @endphp
                    <div class="hidden md:flex space-x-4">
                        <a href="{{ route('toernooi.judoka.index', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.judoka') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __("Judoka's") }}</a>
                        <a href="{{ route('toernooi.poule.index', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.poule') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Poules') }}</a>
                        <a href="{{ route('toernooi.blok.index', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ $currentRoute === 'toernooi.blok.index' ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Blokken') }}</a>
                        <a href="{{ route('toernooi.weging.interface', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.weging') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Weging') }}</a>
                        <a href="{{ route('toernooi.wedstrijddag.poules', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.wedstrijddag') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Wedstrijddag') }}</a>
                        <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ $currentRoute === 'toernooi.blok.zaaloverzicht' ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Zaaloverzicht') }}</a>
                        <a href="{{ route('toernooi.mat.interface', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.mat') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Matten') }}</a>
                        <a href="{{ route('toernooi.spreker.interface', $toernooi->routeParams()) }}" class="py-1 border-b-2 {{ str_starts_with($currentRoute, 'toernooi.spreker') ? 'text-white border-white' : 'border-transparent hover:text-blue-200' }}">{{ __('Spreker') }}</a>
                    </div>
                    @endif
                </div>
                <div class="flex items-center space-x-4">
                    {{-- Taalkiezer --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none" title="{{ __('Taal') }}">
                            @include('partials.flag-icon', ['lang' => app()->getLocale()])
                            <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50">
                            <form action="{{ route('locale.switch', 'nl') }}" method="POST">
                                @csrf
                                @if(isset($toernooi) && $toernooi instanceof \App\Models\Toernooi)
                                    <input type="hidden" name="toernooi_id" value="{{ $toernooi->id }}">
                                @endif
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                                    @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
                                </button>
                            </form>
                            <form action="{{ route('locale.switch', 'en') }}" method="POST">
                                @csrf
                                @if(isset($toernooi) && $toernooi instanceof \App\Models\Toernooi)
                                    <input type="hidden" name="toernooi_id" value="{{ $toernooi->id }}">
                                @endif
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                                    @include('partials.flag-icon', ['lang' => 'en']) English
                                </button>
                            </form>
                        </div>
                    </div>

                    @if(Auth::guard('organisator')->check())
                    {{-- Organisator ingelogd - dropdown menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none">
                            @if(Auth::guard('organisator')->user()->isSitebeheerder())
                                👑
                            @else
                                📋
                            @endif
                            {{ Auth::guard('organisator')->user()->naam }}
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50">
                            @if(Auth::guard('organisator')->user()->isSitebeheerder())
                            <a href="{{ route('admin.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Alle Toernooien') }}</a>
                            @endif
                            <a href="{{ route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Mijn Toernooien') }}</a>
                            <a href="{{ route('organisator.instellingen', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Instellingen') }}</a>
                            <a href="{{ route('help') }}" target="_blank" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Help & Handleiding') }} ↗</a>
                            <hr class="my-1">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Uitloggen') }}</button>
                            </form>
                        </div>
                    </div>
                    @elseif(isset($toernooi) && session("toernooi_{$toernooi->id}_rol"))
                    {{-- Toernooi rol ingelogd (local/staging) --}}
                    @php $rol = session("toernooi_{$toernooi->id}_rol"); @endphp
                    <span class="text-blue-200 text-sm">
                        @switch($rol)
                            @case('admin') 👑 Admin @break
                            @case('jury') ⚖️ Jury @break
                            @case('weging') ⚖️ Weging @break
                            @case('mat') 🥋 Mat {{ session("toernooi_{$toernooi->id}_mat") }} @break
                            @case('spreker') 🎙️ Spreker @break
                        @endswitch
                    </span>
                    <form action="{{ route('toernooi.auth.logout', $toernooi->routeParams()) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-blue-200 hover:text-white text-sm">{{ __('Uitloggen') }}</button>
                    </form>
                    @else
                    @if(isset($toernooi) && !app()->environment('production'))
                    <a href="{{ route('toernooi.auth.login', $toernooi->routeParams()) }}" class="text-blue-200 hover:text-white text-sm">{{ __('Inloggen') }}</a>
                    @elseif(!Auth::guard('organisator')->check())
                    <a href="{{ route('login') }}" class="text-blue-200 hover:text-white text-sm">{{ __('Inloggen') }}</a>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </nav>

    {{-- Centrale toast container (fixed bovenaan scherm) --}}
    <div id="app-toast" class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 hidden">
        <div id="app-toast-content" class="px-6 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2">
            <span id="app-toast-message"></span>
            <button onclick="hideAppToast()" class="ml-2 text-current opacity-70 hover:opacity-100">&times;</button>
        </div>
    </div>

    @if(session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast('✓ ' + @json(session('success')), 'success'));
    </script>
    @endif

    @if(session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast('⚠️ ' + @json(session('error')), 'error', 10000));
    </script>
    @endif

    @if(session('warning'))
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast(@json(session('warning')), 'warning'));
    </script>
    @endif

    <main class="@yield('main-class', 'max-w-7xl mx-auto') px-4 py-8 flex-grow">
        @yield('content')
    </main>

    <footer class="bg-gray-800 text-white py-4 mt-auto shrink-0">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 text-xs text-gray-300 mb-1">
                <a href="{{ route('legal.terms') }}" class="hover:text-white">{{ __('Voorwaarden') }}</a>
                <span class="text-gray-500">•</span>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a>
                <span class="text-gray-500">•</span>
                <a href="{{ route('legal.cookies') }}" class="hover:text-white">Cookies</a>
                <span class="text-gray-500">•</span>
                <span>havun22@gmail.com</span>
            </div>
            <div class="text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} Havun
                <span class="mx-1">•</span>
                KvK 98516000
                <span class="mx-1">•</span>
                BTW-vrij (KOR)
            </div>
        </div>
    </footer>

    {{-- Idle Timeout - Auto logout after 20 minutes inactivity --}}
    @if(isset($toernooi) && session("toernooi_{$toernooi->id}_rol"))
    <script>
        (function() {
            const IDLE_TIMEOUT = 20 * 60 * 1000; // 20 minutes in ms
            const WARNING_BEFORE = 2 * 60 * 1000; // Show warning 2 min before
            let idleTimer;
            let warningTimer;
            let warningShown = false;

            function resetTimers() {
                // Clear existing timers
                clearTimeout(idleTimer);
                clearTimeout(warningTimer);
                warningShown = false;

                // Hide warning if shown
                const warning = document.getElementById('idle-warning');
                if (warning) warning.remove();

                // Set warning timer (2 min before logout)
                warningTimer = setTimeout(showWarning, IDLE_TIMEOUT - WARNING_BEFORE);

                // Set logout timer
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
                            <h3 style="font-size:18px;font-weight:bold;margin-bottom:12px;color:#b91c1c;">⚠️ {{ __('Sessie verloopt bijna') }}</h3>
                            <p style="margin-bottom:16px;color:#374151;">{{ __('Je wordt over 2 minuten automatisch uitgelogd wegens inactiviteit.') }}</p>
                            <button onclick="document.getElementById('idle-warning').remove();resetIdleTimers();"
                                    style="background:#2563eb;color:white;padding:10px 24px;border-radius:6px;border:none;cursor:pointer;font-weight:500;">
                                {{ __('Actief blijven') }}
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(warning);
            }

            function doLogout() {
                // Find and submit logout form
                const logoutForm = document.querySelector('form[action*="logout"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    // Fallback: redirect to login
                    window.location.href = '{{ route("toernooi.auth.login", $toernooi->routeParams()) }}';
                }
            }

            // Expose reset function globally for the "stay active" button
            window.resetIdleTimers = resetTimers;

            // Reset on user activity
            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
                document.addEventListener(event, resetTimers, { passive: true });
            });

            // Start timers
            resetTimers();
        })();
    </script>
    @endif

    {{-- Global fetch interceptor - redirect to login on session expire --}}
    <script>
        (function() {
            const originalFetch = window.fetch;
            const loginUrl = '{{ route("login") }}';
            window.fetch = async function(...args) {
                const response = await originalFetch.apply(this, args);
                // 401 = Unauthorized, 419 = Session Expired (CSRF)
                if (response.status === 401 || response.status === 419) {
                    window.location.href = loginUrl;
                    throw new Error('Session expired');
                }
                // Check for redirect to login page (302/303 followed by fetch)
                if (response.redirected && response.url.includes('/organisator/login')) {
                    window.location.href = response.url;
                    throw new Error('Session expired');
                }
                return response;
            };
        })();
    </script>

    {{-- PWA Support (includes Service Worker registration) --}}
    @include('partials.pwa-mobile', ['pwaApp' => $pwaApp ?? 'admin'])

    {{-- Hoofdjury Chat Widget --}}
    <!-- DEBUG: toernooi={{ isset($toernooi) ? $toernooi->id : 'NOT_SET' }} -->
    @if(isset($toernooi))
        @include('partials.chat-widget-hoofdjury', ['toernooi' => $toernooi])
    @endif

    {{-- Loading spinner for forms with data-loading attribute --}}
    <script>
        document.querySelectorAll('form[data-loading]').forEach(form => {
            form.addEventListener('submit', function() {
                const msg = this.dataset.loading || 'Bezig...';
                const overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `<div class="text-center text-white"><div class="loading-spinner mx-auto mb-4"></div><div class="text-lg font-medium">${msg}</div></div>`;
                document.body.appendChild(overlay);
            });
        });
    </script>

    {{-- Globale toast functies --}}
    <script>
        let appToastTimeout = null;
        function showAppToast(message, type = 'success', duration = 4000) {
            const toast = document.getElementById('app-toast');
            const content = document.getElementById('app-toast-content');
            const msg = document.getElementById('app-toast-message');
            if (!toast || !content || !msg) return;

            msg.textContent = message;
            content.className = 'px-6 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2 ';
            if (type === 'success') {
                content.className += 'bg-green-100 text-green-800 border border-green-300';
            } else if (type === 'error') {
                content.className += 'bg-red-100 text-red-800 border border-red-300';
            } else {
                content.className += 'bg-orange-100 text-orange-800 border border-orange-300';
            }
            toast.classList.remove('hidden');

            if (appToastTimeout) clearTimeout(appToastTimeout);
            appToastTimeout = setTimeout(() => hideAppToast(), duration);
        }
        function hideAppToast() {
            const toast = document.getElementById('app-toast');
            if (toast) toast.classList.add('hidden');
            if (appToastTimeout) clearTimeout(appToastTimeout);
        }
    </script>

    {{-- Noodplan Live Backup Sync --}}
    {{-- Automatisch actief op elke toernooi pagina --}}
    @if(isset($toernooi) && $toernooi)
    <script>
        (function() {
            const toernooiId = {{ $toernooi->id }};
            const syncUrl = '{{ route("toernooi.noodplan.sync-data", $toernooi->routeParams()) }}';
            const storageKey = `noodplan_${toernooiId}_poules`;
            const syncKey = `noodplan_${toernooiId}_laatste_sync`;
            const countKey = `noodplan_${toernooiId}_count`;

            let uitslagCount = 0;
            let lastSyncTime = null;
            let syncInterval = null;

            // Status indicator element
            function getOrCreateIndicator() {
                let indicator = document.getElementById('noodplan-sync-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'noodplan-sync-indicator';
                    indicator.className = 'fixed bottom-4 right-4 px-3 py-2 rounded-lg shadow-lg text-xs font-medium z-50 transition-all duration-300';
                    indicator.style.display = 'none';
                    document.body.appendChild(indicator);
                }
                return indicator;
            }

            function updateIndicator(status, message) {
                const indicator = getOrCreateIndicator();
                indicator.style.display = 'block';

                if (status === 'connected') {
                    indicator.className = 'fixed bottom-4 right-4 px-3 py-2 rounded-lg shadow-lg text-xs font-medium z-50 bg-green-100 text-green-800 border border-green-300';
                    indicator.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-2"></span>${message}`;
                } else if (status === 'disconnected') {
                    indicator.className = 'fixed bottom-4 right-4 px-3 py-2 rounded-lg shadow-lg text-xs font-medium z-50 bg-red-100 text-red-800 border border-red-300';
                    indicator.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-2"></span>${message}`;
                } else if (status === 'syncing') {
                    indicator.className = 'fixed bottom-4 right-4 px-3 py-2 rounded-lg shadow-lg text-xs font-medium z-50 bg-orange-100 text-orange-800 border border-orange-300';
                    indicator.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-orange-500 mr-2 animate-pulse"></span>${message}`;
                }
            }

            function saveToStorage(data) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(data));
                    localStorage.setItem(syncKey, new Date().toISOString());

                    // Tel uitslagen (wedstrijden met is_gespeeld = true)
                    let count = 0;
                    if (data.poules) {
                        data.poules.forEach(p => {
                            if (p.wedstrijden) {
                                p.wedstrijden.forEach(w => {
                                    if (w.is_gespeeld) count++;
                                });
                            }
                        });
                    }
                    uitslagCount = count;
                    localStorage.setItem(countKey, count.toString());
                } catch (e) {
                    console.error('Noodplan: localStorage error', e);
                }
            }

            async function sync() {
                try {
                    const response = await fetch(syncUrl);
                    if (!response.ok) throw new Error('Sync failed');

                    const data = await response.json();
                    saveToStorage(data);
                    lastSyncTime = new Date();

                    const time = lastSyncTime.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'});
                    updateIndicator('connected', `Backup actief | ${uitslagCount} uitslagen | ${time}`);
                } catch (e) {
                    console.error('Noodplan: sync error', e);
                    // Toon alleen offline als we langer dan 2 minuten geen sync hebben
                    if (!lastSyncTime || (new Date() - lastSyncTime) > 120000) {
                        updateIndicator('disconnected', 'Offline - backup beschikbaar');
                    }
                }
            }

            function startSync() {
                // Stop bestaande interval
                if (syncInterval) clearInterval(syncInterval);

                // Direct sync
                updateIndicator('syncing', 'Synchroniseren...');
                sync();

                // Daarna elke 30 seconden
                syncInterval = setInterval(sync, 30000);
            }

            // Herstel na slaapstand/visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    startSync();
                }
            });

            // Start sync
            startSync();

            // Laad laatste telling uit storage
            const savedCount = localStorage.getItem(countKey);
            if (savedCount) {
                uitslagCount = parseInt(savedCount) || 0;
            }
        })();
    </script>
    @endif
</body>
</html>
