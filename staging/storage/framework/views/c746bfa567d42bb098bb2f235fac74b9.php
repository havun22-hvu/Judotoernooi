<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> - <?php echo e(isset($toernooi) ? $toernooi->naam : 'Judo Toernooi'); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo $__env->yieldContent('manifest', '/manifest.json'); ?>">
    <meta name="theme-color" content="#1e40af">
    <?php echo $__env->yieldPushContent('seo'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
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
    
    <?php if(session('impersonating_from')): ?>
    <div class="bg-yellow-400 text-yellow-900 px-4 py-2 text-center text-sm font-medium z-[60] relative">
        Je bekijkt als <strong><?php echo e(Auth::guard('organisator')->user()?->naam); ?></strong>
        &mdash;
        <form action="<?php echo e(route('admin.impersonate.stop')); ?>" method="POST" class="inline">
            <?php echo csrf_field(); ?>
            <button type="submit" class="underline font-bold hover:text-yellow-700">Terug naar admin</button>
        </form>
    </div>
    <?php endif; ?>
    
    <nav class="bg-blue-800 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="<?php echo e(isset($toernooi) ? route('toernooi.show', $toernooi->routeParams()) : (Auth::guard('organisator')->user() ? route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) : '/')); ?>" class="text-xl font-bold"><?php echo e(isset($toernooi) ? $toernooi->naam : 'Judo Toernooi'); ?></a>
                    <?php if(isset($toernooi)): ?>
                    <?php
                        $currentRoute = Route::currentRouteName();
                    ?>
                    
                    <div class="hidden md:flex space-x-4">
                        <a href="<?php echo e(route('toernooi.judoka.index', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.judoka') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__("Judoka's")); ?></a>
                        <a href="<?php echo e(route('toernooi.poule.index', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.poule') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Poules')); ?></a>
                        <a href="<?php echo e(route('toernooi.blok.index', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e($currentRoute === 'toernooi.blok.index' ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Blokken')); ?></a>
                        <a href="<?php echo e(route('toernooi.weging.interface', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.weging') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Weging')); ?></a>
                        <a href="<?php echo e(route('toernooi.wedstrijddag.poules', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.wedstrijddag') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Wedstrijddag')); ?></a>
                        <a href="<?php echo e(route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e($currentRoute === 'toernooi.blok.zaaloverzicht' ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Zaaloverzicht')); ?></a>
                        <a href="<?php echo e(route('toernooi.mat.interface', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.mat') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Matten')); ?></a>
                        <a href="<?php echo e(route('toernooi.spreker.interface', $toernooi->routeParams())); ?>" class="py-1 border-b-2 <?php echo e(str_starts_with($currentRoute, 'toernooi.spreker') ? 'text-white border-white' : 'border-transparent hover:text-blue-200'); ?>"><?php echo e(__('Spreker')); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none" title="<?php echo e(__('Taal')); ?>">
                            <?php echo $__env->make('partials.flag-icon', ['lang' => app()->getLocale()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50">
                            <form action="<?php echo e(route('locale.switch', 'nl')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <?php if(isset($toernooi) && $toernooi instanceof \App\Models\Toernooi): ?>
                                    <input type="hidden" name="toernooi_id" value="<?php echo e($toernooi->id); ?>">
                                <?php endif; ?>
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 <?php echo e(app()->getLocale() === 'nl' ? 'font-bold' : ''); ?>">
                                    <?php echo $__env->make('partials.flag-icon', ['lang' => 'nl'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Nederlands
                                </button>
                            </form>
                            <form action="<?php echo e(route('locale.switch', 'en')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <?php if(isset($toernooi) && $toernooi instanceof \App\Models\Toernooi): ?>
                                    <input type="hidden" name="toernooi_id" value="<?php echo e($toernooi->id); ?>">
                                <?php endif; ?>
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 <?php echo e(app()->getLocale() === 'en' ? 'font-bold' : ''); ?>">
                                    <?php echo $__env->make('partials.flag-icon', ['lang' => 'en'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> English
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if(Auth::guard('organisator')->check()): ?>
                    
                    <div class="relative" x-data="{ open: false, showAbout: false }">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none">
                            <?php if(Auth::guard('organisator')->user()->isSitebeheerder()): ?>
                                👑
                            <?php else: ?>
                                📋
                            <?php endif; ?>
                            <?php echo e(Auth::guard('organisator')->user()->naam); ?>

                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50">
                            <?php if(Auth::guard('organisator')->user()->isSitebeheerder()): ?>
                            <a href="<?php echo e(route('admin.index')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Admin Dashboard')); ?></a>
                            <?php endif; ?>
                            <a href="<?php echo e(route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug])); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Mijn Toernooien')); ?></a>
                            <a href="<?php echo e(route('help')); ?>" target="_blank" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Help & Handleiding')); ?> ↗</a>
                            <a href="<?php echo e(route('auth.account')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Account Instellingen')); ?></a>
                            <hr class="my-1">
                            <button type="button" onclick="if(typeof forceRefresh==='function'){forceRefresh()}else{location.reload(true)}" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">🔄 <?php echo e(__('Forceer Update')); ?></button>
                            <button type="button" @click="showAbout = true; open = false" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Over')); ?></button>
                            <form action="<?php echo e(route('logout')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Uitloggen')); ?></button>
                            </form>
                        </div>

                        
                        <div x-show="showAbout" x-cloak
                             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                             @click.self="showAbout = false"
                             @keydown.escape.window="showAbout = false">
                            <div class="bg-white rounded-lg shadow-xl w-80 overflow-hidden" @click.outside="showAbout = false">
                                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 text-white text-center relative">
                                    <button type="button" @click="showAbout = false"
                                            class="absolute top-2 right-2 text-white/70 hover:text-white text-xl leading-none">&times;</button>
                                    <h2 class="text-xl font-bold"><?php echo e(__('JudoToernooi')); ?></h2>
                                    <p class="text-blue-200 text-sm"><?php echo e(__('Toernooi Management')); ?></p>
                                </div>
                                <div class="px-6 py-4 space-y-3">
                                    <div>
                                        <p class="text-xs text-gray-400"><?php echo e(__('Versie')); ?></p>
                                        <p class="font-medium text-gray-800">v<?php echo e(config('toernooi.version')); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400"><?php echo e(__('Laatste update')); ?></p>
                                        <p class="font-medium text-gray-800"><?php echo e(config('toernooi.version_date')); ?></p>
                                    </div>
                                    <hr>
                                    <div>
                                        <p class="text-xs text-gray-400"><?php echo e(__('Ontwikkeld door')); ?></p>
                                        <p class="font-medium text-gray-800">Havun</p>
                                        <p class="text-sm text-gray-500">havun22@gmail.com</p>
                                    </div>
                                    <hr>
                                    <button type="button" onclick="location.reload(true)"
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded text-sm font-medium">
                                        <?php echo e(__('Ververs app')); ?>

                                    </button>
                                </div>
                                <div class="px-6 py-3 bg-gray-50 text-center">
                                    <button type="button" @click="showAbout = false" class="text-sm text-gray-500 hover:text-gray-700"><?php echo e(__('Sluiten')); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif(isset($toernooi) && session("toernooi_{$toernooi->id}_rol")): ?>
                    
                    <?php $rol = session("toernooi_{$toernooi->id}_rol"); ?>
                    <span class="text-blue-200 text-sm">
                        <?php switch($rol):
                            case ('admin'): ?> 👑 Admin <?php break; ?>
                            <?php case ('jury'): ?> ⚖️ Jury <?php break; ?>
                            <?php case ('weging'): ?> ⚖️ Weging <?php break; ?>
                            <?php case ('mat'): ?> 🥋 Mat <?php echo e(session("toernooi_{$toernooi->id}_mat")); ?> <?php break; ?>
                            <?php case ('spreker'): ?> 🎙️ Spreker <?php break; ?>
                        <?php endswitch; ?>
                    </span>
                    <form action="<?php echo e(route('toernooi.auth.logout', $toernooi->routeParams())); ?>" method="POST" class="inline">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="text-blue-200 hover:text-white text-sm"><?php echo e(__('Uitloggen')); ?></button>
                    </form>
                    <?php else: ?>
                    <?php if(isset($toernooi) && !app()->environment('production')): ?>
                    <a href="<?php echo e(route('toernooi.auth.login', $toernooi->routeParams())); ?>" class="text-blue-200 hover:text-white text-sm"><?php echo e(__('Inloggen')); ?></a>
                    <?php elseif(!Auth::guard('organisator')->check()): ?>
                    <a href="<?php echo e(route('login')); ?>" class="text-blue-200 hover:text-white text-sm"><?php echo e(__('Inloggen')); ?></a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    
    <div id="app-toast" class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 hidden">
        <div id="app-toast-content" class="px-6 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2">
            <span id="app-toast-message"></span>
            <button onclick="hideAppToast()" class="ml-2 text-current opacity-70 hover:opacity-100">&times;</button>
        </div>
    </div>

    <?php if(session('success')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast('✓ ' + <?php echo json_encode(session('success'), 15, 512) ?>, 'success'));
    </script>
    <?php endif; ?>

    <?php if(session('error')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast('⚠️ ' + <?php echo json_encode(session('error'), 15, 512) ?>, 'error', 10000));
    </script>
    <?php endif; ?>

    <?php if(session('warning')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => showAppToast(<?php echo json_encode(session('warning'), 15, 512) ?>, 'warning'));
    </script>
    <?php endif; ?>

    <main class="<?php echo $__env->yieldContent('main-class', 'max-w-7xl mx-auto'); ?> px-4 py-8 flex-grow">
        <?php echo $__env->yieldContent('content'); ?>
    </main>

    
    <footer class="bg-gray-800 text-white py-4 mt-auto shrink-0">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 text-xs text-gray-300 mb-1">
                <a href="<?php echo e(route('legal.terms')); ?>" class="hover:text-white"><?php echo e(__('Voorwaarden')); ?></a>
                <span class="text-gray-500">•</span>
                <a href="<?php echo e(route('legal.privacy')); ?>" class="hover:text-white"><?php echo e(__('Privacy')); ?></a>
                <span class="text-gray-500">•</span>
                <a href="<?php echo e(route('legal.cookies')); ?>" class="hover:text-white"><?php echo e(__('Cookies')); ?></a>
                <span class="text-gray-500">•</span>
                <a href="<?php echo e(route('legal.herroeping')); ?>" class="hover:text-white"><?php echo e(__('Herroeping')); ?></a>
                <span class="text-gray-500">•</span>
                <a href="mailto:havun22@gmail.com" class="hover:text-white"><?php echo e(__('Contact')); ?></a>
            </div>
            <div class="text-center text-xs text-gray-400">
                &copy; <?php echo e(date('Y')); ?> Havun
                <span class="mx-1">•</span>
                <?php echo e(__('KvK')); ?> <?php echo e(config('company.kvk', '98516000')); ?>

                <span class="mx-1">•</span>
                <?php echo e(__('BTW-vrij (KOR)')); ?>

                <span class="mx-1">•</span>
                <?php echo e(config('company.address', 'Jacques Bloemhof 57')); ?>, <?php echo e(config('company.postal_code', '1628 VN')); ?> <?php echo e(config('company.city', 'Hoorn')); ?>

            </div>
        </div>
    </footer>

    
    <?php if(isset($toernooi) && session("toernooi_{$toernooi->id}_rol")): ?>
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
                            <h3 style="font-size:18px;font-weight:bold;margin-bottom:12px;color:#b91c1c;">⚠️ <?php echo e(__('Sessie verloopt bijna')); ?></h3>
                            <p style="margin-bottom:16px;color:#374151;"><?php echo e(__('Je wordt over 2 minuten automatisch uitgelogd wegens inactiviteit.')); ?></p>
                            <button onclick="document.getElementById('idle-warning').remove();resetIdleTimers();"
                                    style="background:#2563eb;color:white;padding:10px 24px;border-radius:6px;border:none;cursor:pointer;font-weight:500;">
                                <?php echo e(__('Actief blijven')); ?>

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
                    window.location.href = '<?php echo e(route("toernooi.auth.login", $toernooi->routeParams())); ?>';
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
    <?php endif; ?>

    
    <script>
        (function() {
            const originalFetch = window.fetch;
            const loginUrl = '<?php echo e(route("login")); ?>';
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

    
    <?php echo $__env->make('partials.pwa-mobile', ['pwaApp' => $pwaApp ?? 'admin'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <!-- DEBUG: toernooi=<?php echo e(isset($toernooi) ? $toernooi->id : 'NOT_SET'); ?> -->
    <?php if(isset($toernooi)): ?>
        <?php echo $__env->make('partials.chat-widget-hoofdjury', ['toernooi' => $toernooi], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    
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

    
    <?php if(isset($toernooi) && $toernooi): ?>
    <script>
        (function() {
            const toernooiId = <?php echo e($toernooi->id); ?>;
            const syncUrl = '<?php echo e(route("toernooi.noodplan.sync-data", $toernooi->routeParams())); ?>';
            const storageKey = `noodplan_${toernooiId}_poules`;
            const syncKey = `noodplan_${toernooiId}_laatste_sync`;
            const countKey = `noodplan_${toernooiId}_count`;

            let uitslagCount = 0;
            let lastSyncTime = null;
            let syncInterval = null;

            const __backupActief = <?php echo json_encode(__('Backup actief'), 15, 512) ?>;
            const __uitslagen = <?php echo json_encode(__('uitslagen'), 15, 512) ?>;
            const __offlineBackup = <?php echo json_encode(__('Offline - backup beschikbaar'), 15, 512) ?>;
            const __synchroniseren = <?php echo json_encode(__('Synchroniseren...'), 15, 512) ?>;

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

                    const time = lastSyncTime.toLocaleTimeString(document.documentElement.lang || 'nl-NL', {hour: '2-digit', minute: '2-digit'});
                    updateIndicator('connected', `${__backupActief} | ${uitslagCount} ${__uitslagen} | ${time}`);
                } catch (e) {
                    console.error('Noodplan: sync error', e);
                    // Toon alleen offline als we langer dan 2 minuten geen sync hebben
                    if (!lastSyncTime || (new Date() - lastSyncTime) > 120000) {
                        updateIndicator('disconnected', __offlineBackup);
                    }
                }
            }

            function startSync() {
                // Stop bestaande interval
                if (syncInterval) clearInterval(syncInterval);

                // Direct sync
                updateIndicator('syncing', __synchroniseren);
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
    <?php endif; ?>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/layouts/app.blade.php ENDPATH**/ ?>