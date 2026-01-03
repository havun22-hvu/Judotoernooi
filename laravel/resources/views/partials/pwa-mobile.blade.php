{{-- PWA Mobile Component - Include in smartphone apps (dojo, mat, weging, spreker) --}}
@php
    $appVersion = config('toernooi.version', '1.0.0');
    $appVersionDate = config('toernooi.version_date', '');
@endphp

{{-- Settings Button (top right corner) --}}
<button onclick="document.getElementById('pwa-settings-modal').classList.remove('hidden')"
        class="fixed top-3 right-3 z-40 bg-white/20 hover:bg-white/30 text-white p-2 rounded-full">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
    </svg>
</button>

{{-- Settings/About Modal --}}
<div id="pwa-settings-modal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-sm text-gray-800 overflow-hidden">
        <div class="bg-blue-800 text-white p-4">
            <h2 class="text-xl font-bold">Instellingen</h2>
        </div>

        <div class="p-4 space-y-4">
            {{-- Install App --}}
            <div id="pwa-install-section" class="hidden">
                <button id="pwa-install-btn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Installeer App
                </button>
                <p class="text-sm text-gray-500 mt-2 text-center">Installeer voor snellere toegang</p>
            </div>

            {{-- Update Available --}}
            <div id="pwa-update-section" class="hidden">
                <button id="pwa-update-btn" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Update Beschikbaar - Installeren
                </button>
            </div>

            {{-- Force Refresh --}}
            <button onclick="forceRefresh()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Forceer Update
            </button>

            <hr>

            {{-- About --}}
            <div class="text-center">
                <h3 class="font-bold text-lg">JudoToernooi</h3>
                <p class="text-gray-600">Versie {{ $appVersion }}</p>
                @if($appVersionDate)
                <p class="text-gray-500 text-sm">{{ $appVersionDate }}</p>
                @endif
                <p id="pwa-sw-version" class="text-gray-400 text-xs mt-1"></p>
            </div>
        </div>

        <div class="p-4 border-t">
            <button onclick="document.getElementById('pwa-settings-modal').classList.add('hidden')"
                    class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg font-medium">
                Sluiten
            </button>
        </div>
    </div>
</div>

{{-- PWA Install Banner (bottom of screen) --}}
<div id="pwa-install-banner" class="hidden fixed bottom-0 left-0 right-0 bg-green-600 text-white p-4 z-50 safe-area-bottom">
    <div class="flex items-center justify-between max-w-lg mx-auto">
        <div class="flex items-center gap-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            <div>
                <p class="font-bold">Installeer de app</p>
                <p class="text-sm text-green-100">Voor snellere toegang</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="hidePwaInstallBanner()" class="px-3 py-1 text-green-200 hover:text-white">Later</button>
            <button onclick="installPwa()" class="bg-white text-green-600 px-4 py-1 rounded font-medium">Installeer</button>
        </div>
    </div>
</div>

{{-- Update Banner (top of screen) --}}
<div id="pwa-update-banner" class="hidden fixed top-0 left-0 right-0 bg-orange-500 text-white p-3 z-50 safe-area-top">
    <div class="flex items-center justify-between max-w-lg mx-auto">
        <p class="font-medium">Nieuwe versie beschikbaar!</p>
        <button onclick="applyUpdate()" class="bg-white text-orange-600 px-4 py-1 rounded font-medium">Update</button>
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
            if (deferredPrompt && !localStorage.getItem('pwa-install-dismissed')) {
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
        localStorage.setItem('pwa-install-dismissed', Date.now());
    }

    // Bind install button
    document.getElementById('pwa-install-btn')?.addEventListener('click', installPwa);

    // Service Worker Updates
    let newWorker = null;

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(reg => {
            // Check for updates every 60 seconds
            setInterval(() => reg.update(), 60000);

            reg.addEventListener('updatefound', () => {
                newWorker = reg.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New version available
                        document.getElementById('pwa-update-section').classList.remove('hidden');
                        document.getElementById('pwa-update-banner').classList.remove('hidden');
                    }
                });
            });

            // Get current SW version
            if (reg.active) {
                const channel = new MessageChannel();
                channel.port1.onmessage = (event) => {
                    document.getElementById('pwa-sw-version').textContent = `SW: v${event.data.version}`;
                };
                reg.active.postMessage('CHECK_UPDATE', [channel.port2]);
            }
        });

        // Listen for SW messages
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data.type === 'SW_UPDATED') {
                console.log('SW updated to:', event.data.version);
                // Reload to get new version
                window.location.reload();
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
        // Clear caches and reload
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
            }).then(() => {
                // Unregister and re-register service worker
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

    // Bind update button
    document.getElementById('pwa-update-btn')?.addEventListener('click', applyUpdate);
</script>
