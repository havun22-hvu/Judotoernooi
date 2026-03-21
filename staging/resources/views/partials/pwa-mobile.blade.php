{{--
    PWA Mobile Component

    Usage: @include('partials.pwa-mobile', ['pwaApp' => 'dojo'])

    Options for pwaApp: 'dojo', 'weging', 'mat', 'spreker', 'admin' (default)
--}}
@php
    $appVersion = config('toernooi.version', '1.0.0');
    $appVersionDate = config('toernooi.version_date', '');
    $pwaApp = $pwaApp ?? 'admin';

    $pwaConfig = [
        'dojo' => [
            'name' => 'Dojo Scanner',
            'manifest' => '/manifest-dojo.json',
            'device' => 'Smartphone',
            'deviceIcon' => '📱',
            'deviceAdvice' => __('Deze app werkt het beste op een smartphone met camera.'),
        ],
        'weging' => [
            'name' => 'Weging',
            'manifest' => '/manifest-weging.json',
            'device' => 'Smartphone / Tablet',
            'deviceIcon' => '📱',
            'deviceAdvice' => __('Gebruik een smartphone (met camera voor QR) of tablet.'),
        ],
        'mat' => [
            'name' => 'Mat Interface',
            'manifest' => '/manifest-mat.json',
            'device' => 'PC / Laptop / Tablet',
            'deviceIcon' => '💻',
            'deviceAdvice' => __('Aanbevolen: laptop of tablet in landscape modus.'),
        ],
        'spreker' => [
            'name' => 'Spreker',
            'manifest' => '/manifest-spreker.json',
            'device' => 'iPad / Tablet',
            'deviceIcon' => '📋',
            'deviceAdvice' => __('Aanbevolen: iPad of tablet in landscape modus.'),
        ],
        'admin' => [
            'name' => 'JudoToernooi',
            'manifest' => '/manifest.json',
            'device' => 'PC / Laptop',
            'deviceIcon' => '🖥️',
            'deviceAdvice' => __('Admin interface werkt het beste op een groot scherm.'),
        ],
    ];

    $config = $pwaConfig[$pwaApp] ?? $pwaConfig['admin'];
@endphp

{{-- Settings Button (top right corner) — hidden if already in header --}}
<button id="pwa-settings-floating-btn"
        onclick="document.getElementById('pwa-settings-modal').classList.remove('hidden')"
        class="fixed top-3 right-3 z-40 bg-white/20 hover:bg-white/30 text-white p-2 rounded-full"
        title="{{ __('Instellingen') }}"
        style="display: none;">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
    </svg>
</button>
<script>
    // Show floating button only if no inline settings button exists in header
    if (!document.querySelector('header button[onclick*="pwa-settings-modal"]')) {
        document.getElementById('pwa-settings-floating-btn').style.display = '';
    }
</script>

{{-- About/Settings Modal (compact) --}}
<div id="pwa-settings-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-lg shadow-xl w-72 text-gray-800 overflow-hidden">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-900">{{ $config['name'] }}</h2>
            <button onclick="document.getElementById('pwa-settings-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="px-4 py-3 space-y-3 text-sm">
            <div class="flex justify-between items-center">
                <span class="text-gray-500">{{ __('Versie') }}</span>
                <span class="font-medium">v{{ $appVersion }}</span>
            </div>
            @if($appVersionDate)
            <div class="flex justify-between items-center">
                <span class="text-gray-500">{{ __('Update') }}</span>
                <span class="text-gray-700">{{ $appVersionDate }}</span>
            </div>
            @endif
            <div class="flex justify-between items-center">
                <span class="text-gray-500">SW</span>
                <span id="pwa-sw-version" class="text-gray-400 text-xs"></span>
            </div>
            <hr>
            <button onclick="forceRefresh()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded text-sm font-medium">
                {{ __('Forceer Update') }}
            </button>
            <div id="pwa-install-section" class="hidden">
                <button id="pwa-install-btn" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded text-sm font-medium">
                    {{ __('Installeer app') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- PWA Install Banner (bottom of screen) --}}
<div id="pwa-install-banner" class="hidden fixed bottom-0 left-0 right-0 bg-green-600 text-white p-4 z-50 safe-area-bottom">
    <div class="flex items-center justify-between max-w-lg mx-auto">
        <div class="flex items-center gap-3">
            <span class="text-2xl">{{ $config['deviceIcon'] }}</span>
            <div>
                <p class="font-bold">{{ __('Installeer') }} {{ $config['name'] }}</p>
                <p class="text-sm text-green-100">{{ $config['device'] }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="hidePwaInstallBanner()" class="px-3 py-1 text-green-200 hover:text-white">{{ __('Later') }}</button>
            <button onclick="installPwa()" class="bg-white text-green-600 px-4 py-1 rounded font-medium">{{ __('Installeer') }}</button>
        </div>
    </div>
</div>

{{-- Update Toast (top of screen - only warning, no choice) --}}
<div id="pwa-update-banner" class="hidden fixed top-0 left-0 right-0 bg-orange-500 text-white p-3 z-50 safe-area-top">
    <div class="flex items-center justify-center max-w-lg mx-auto gap-2">
        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="font-medium">{{ __('Updaten... pagina herlaadt automatisch') }}</p>
    </div>
</div>

<style>
    .safe-area-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    .safe-area-top { padding-top: max(0.75rem, env(safe-area-inset-top)); }
</style>

<script>
    // PWA Install
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Show install options
        document.getElementById('pwa-install-section').classList.remove('hidden');
        // Show banner after 3 seconds if not installed
        setTimeout(() => {
            if (deferredPrompt && !localStorage.getItem('pwa-install-dismissed-{{ $pwaApp }}')) {
                document.getElementById('pwa-install-banner').classList.remove('hidden');
            }
        }, 3000);
    });

    function installPwa() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((result) => {
            if (result.outcome === 'accepted') {
                console.log('PWA installed');
            }
            deferredPrompt = null;
            document.getElementById('pwa-install-section').classList.add('hidden');
            document.getElementById('pwa-install-banner').classList.add('hidden');
        });
    }

    function hidePwaInstallBanner() {
        document.getElementById('pwa-install-banner').classList.add('hidden');
        localStorage.setItem('pwa-install-dismissed-{{ $pwaApp }}', Date.now());
    }

    // Bind install button
    document.getElementById('pwa-install-btn')?.addEventListener('click', installPwa);

    // Service Worker Updates
    let newWorker = null;
    const APP_VERSION = '{{ $appVersion }}';

    if ('serviceWorker' in navigator) {
        // Register with cache-busting query param
        navigator.serviceWorker.register('/sw.js?v=' + APP_VERSION).then(reg => {
            // Immediate update check on load
            reg.update();

            // Check for updates every 30 seconds
            setInterval(() => reg.update(), 30000);

            reg.addEventListener('updatefound', () => {
                newWorker = reg.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New version available - FORCED UPDATE (no user choice)
                        console.log('[PWA] v1.1.4 - Forcing update without user choice');
                        document.getElementById('pwa-update-banner').classList.remove('hidden');
                        // Force update after brief delay (show warning toast)
                        setTimeout(() => {
                            forceRefresh();
                        }, 500);
                    }
                });
            });

            // Get current SW version and update once if needed
            if (reg.active) {
                const channel = new MessageChannel();
                channel.port1.onmessage = (event) => {
                    const swVersion = event.data.version;
                    document.getElementById('pwa-sw-version').textContent = `v${swVersion}`;
                    // Version mismatch - update ONCE (use sessionStorage to prevent loop)
                    const updateKey = 'pwa-updated-' + APP_VERSION;
                    if (swVersion !== APP_VERSION && !sessionStorage.getItem(updateKey)) {
                        console.log('[PWA] Version mismatch - updating once. App:', APP_VERSION, 'SW:', swVersion);
                        sessionStorage.setItem(updateKey, 'true');
                        document.getElementById('pwa-update-banner').classList.remove('hidden');
                        setTimeout(() => forceRefresh(), 500);
                    }
                };
                reg.active.postMessage('CHECK_UPDATE', [channel.port2]);
            }
        });

        // Listen for SW messages
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data.type === 'SW_UPDATED') {
                console.log('SW updated to:', event.data.version);
                // Force clear all caches and reload
                if ('caches' in window) {
                    caches.keys().then(names => {
                        Promise.all(names.map(name => caches.delete(name))).then(() => {
                            window.location.reload(true);
                        });
                    });
                } else {
                    window.location.reload(true);
                }
            }
        });
    }

    function applyUpdate() {
        if (newWorker) {
            newWorker.postMessage('SKIP_WAITING');
        }
        window.location.reload();
    }

    function forceRefresh() {
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
            }).then(() => {
                navigator.serviceWorker.getRegistrations().then(regs => {
                    regs.forEach(reg => reg.unregister());
                }).then(() => {
                    window.location.reload(true);
                });
            });
        } else {
            window.location.reload(true);
        }
    }

</script>
