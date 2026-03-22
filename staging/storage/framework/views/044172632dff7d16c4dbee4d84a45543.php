<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(__('Dashboard')); ?> - <?php echo e(__('JudoToernooi')); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(["resources/css/app.css", "resources/js/app.js"]); ?>
</head>
<body class="bg-gray-100 min-h-screen">
    
    <nav class="bg-blue-800 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold"><?php echo e(__('JudoToernooi')); ?></span>
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
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 <?php echo e(app()->getLocale() === 'nl' ? 'font-bold' : ''); ?>">
                                    <?php echo $__env->make('partials.flag-icon', ['lang' => 'nl'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Nederlands
                                </button>
                            </form>
                            <form action="<?php echo e(route('locale.switch', 'en')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 <?php echo e(app()->getLocale() === 'en' ? 'font-bold' : ''); ?>">
                                    <?php echo $__env->make('partials.flag-icon', ['lang' => 'en'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> English
                                </button>
                            </form>
                        </div>
                    </div>

                    
                    <div class="relative" x-data="{ open: false, showAbout: false }">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none">
                            <?php if($organisator->isSitebeheerder()): ?>
                                👑
                            <?php else: ?>
                                📋
                            <?php endif; ?>
                            <?php echo e($organisator->naam); ?>

                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50">
                            <?php if($organisator->isSitebeheerder()): ?>
                            <a href="<?php echo e(route('admin.index')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Admin Dashboard')); ?></a>
                            <?php endif; ?>
                            <a href="<?php echo e(route('organisator.dashboard', ['organisator' => $organisator->slug])); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Mijn Toernooien')); ?></a>
                            <a href="<?php echo e(route('help')); ?>" target="_blank" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Help & Handleiding')); ?> ↗</a>
                            <a href="<?php echo e(route('auth.account')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><?php echo e(__('Account Instellingen')); ?></a>
                            <hr class="my-1">
                            <button type="button" onclick="location.reload(true)" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">🔄 <?php echo e(__('Forceer Update')); ?></button>
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
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if(session('success')): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?php echo e(session('success')); ?>

        </div>
        <?php endif; ?>

        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php echo e(__('Mijn Toernooien')); ?>

            </h2>
            
            <div class="flex space-x-3">
                <a href="<?php echo e(route('organisator.wimpel.index', $organisator)); ?>"
                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    <?php echo e(__('Wimpeltoernooi')); ?>

                </a>
                <a href="<?php echo e(route('organisator.stambestand.index', $organisator)); ?>"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    <?php echo e(__('Mijn Judoka\'s')); ?>

                </a>
                <a href="<?php echo e(route('organisator.clubs.index', $organisator)); ?>"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">
                    <?php echo e(__('Mijn Clubs')); ?>

                </a>
                <a href="<?php echo e(route('toernooi.create', ['organisator' => $organisator])); ?>"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    <?php echo e(__('Nieuw Toernooi')); ?>

                </a>
            </div>
        </div>

        
        <div x-data="templateManager()" class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-700"><?php echo e(__('Mijn Templates')); ?></h3>
                <button @click="open = !open" class="text-blue-600 hover:text-blue-800 text-sm">
                    <span x-show="!open"><?php echo e(__('Toon templates')); ?></span>
                    <span x-show="open"><?php echo e(__('Verberg')); ?></span>
                </button>
            </div>

            <div x-show="open" x-collapse class="bg-white rounded-lg shadow p-4">
                <template x-if="templates.length === 0">
                    <p class="text-gray-500 text-sm"><?php echo e(__('Nog geen templates. Sla instellingen op vanuit een bestaand toernooi.')); ?></p>
                </template>
                <template x-if="templates.length > 0">
                    <div class="space-y-3">
                        <template x-for="template in templates" :key="template.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded border">
                                <div>
                                    <span class="font-medium" x-text="template.naam"></span>
                                    <span x-show="template.beschrijving" class="text-gray-500 text-sm ml-2" x-text="'- ' + template.beschrijving"></span>
                                    <span x-show="template.max_judokas" class="text-gray-400 text-xs ml-2" x-text="'(max ' + template.max_judokas + ' judokas)'"></span>
                                </div>
                                <button @click="deleteTemplate(template.id)" class="text-red-500 hover:text-red-700 text-sm"><?php echo e(__('Verwijderen')); ?></button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <script>
            const __t = { confirmDeleteTemplate: <?php echo json_encode(__('Weet je zeker dat je deze template wilt verwijderen?'), 15, 512) ?> };
            function templateManager() {
                return {
                    open: false,
                    templates: <?php echo json_encode($organisator->toernooiTemplates ?? [], 15, 512) ?>,
                    async deleteTemplate(id) {
                        if (!confirm(__t.confirmDeleteTemplate)) return;
                        const response = await fetch(`/<?php echo e($organisator->slug); ?>/templates/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                                'Accept': 'application/json'
                            }
                        });
                        if (response.ok) {
                            this.templates = this.templates.filter(t => t.id !== id);
                        }
                    }
                }
            }
        </script>

        <?php if($toernooien->isEmpty()): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-600 mb-4"><?php echo e(__('Je hebt nog geen toernooien aangemaakt.')); ?></p>
            <a href="<?php echo e(route('toernooi.create', ['organisator' => $organisator])); ?>"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors inline-block">
                <?php echo e(__('Maak je eerste toernooi aan')); ?>

            </a>
        </div>
        <?php else: ?>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php $__currentLoopData = $toernooien; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $toernooi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <h3 class="text-lg font-bold text-gray-800"><?php echo e($toernooi->naam); ?></h3>
                    <?php if($organisator->isSitebeheerder() && $toernooi->organisator_id !== $organisator->id): ?>
                    <span class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-800">
                        Admin (<?php echo e($toernooi->organisator?->naam); ?>)
                    </span>
                    <?php elseif($toernooi->pivot): ?>
                    <span class="text-xs px-2 py-1 rounded
                        <?php if($toernooi->pivot->rol === 'eigenaar'): ?> bg-blue-100 text-blue-800
                        <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?>">
                        <?php echo e(ucfirst($toernooi->pivot->rol)); ?>

                    </span>
                    <?php endif; ?>
                </div>

                
                <div class="flex items-center text-gray-600 mb-2">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="font-medium"><?php echo e($toernooi->datum ? $toernooi->datum->format('d-m-Y') : __('Geen datum')); ?></span>
                </div>

                
                <div class="mb-3">
                    <?php if($toernooi->isPaidTier()): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            <?php echo e(__('Betaald')); ?> (<?php echo e($toernooi->paid_tier); ?>)
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                            <?php echo e(__('Gratis (max 50)')); ?>

                        </span>
                    <?php endif; ?>
                </div>

                
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-500 mb-3">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <?php echo e($toernooi->judokas_count ?? $toernooi->judokas()->count()); ?>/<?php echo e($toernooi->getEffectiveMaxJudokas()); ?> judoka's
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <?php echo e($toernooi->poules_count ?? $toernooi->poules()->count()); ?> poules
                    </div>
                </div>

                
                <div class="text-xs text-gray-400 border-t pt-3 space-y-1">
                    <div class="flex justify-between">
                        <span><?php echo e(__('Aangemaakt')); ?>:</span>
                        <span><?php echo e($toernooi->created_at ? $toernooi->created_at->format('d-m-Y H:i') : '-'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span><?php echo e(__('Laatst bewerkt')); ?>:</span>
                        <span><?php echo e($toernooi->updated_at ? $toernooi->updated_at->diffForHumans() : '-'); ?></span>
                    </div>
                </div>

                
                <div class="flex items-center justify-between mt-4 pt-3 border-t">
                    <a href="<?php echo e(route('toernooi.show', $toernooi->routeParams())); ?>"
                       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded transition-colors">
                        <?php echo e(__('Start')); ?>

                    </a>
                    <div class="flex items-center gap-2">
                        
                        <form action="<?php echo e(route('toernooi.reset', $toernooi->routeParams())); ?>" method="POST" class="inline"
                              onsubmit="return confirm('Weet je zeker dat je \'<?php echo e($toernooi->naam); ?>\' wilt resetten?\n\nDit verwijdert:\n- Alle judoka\'s\n- Alle poules\n- Alle wedstrijden\n- Alle wegingen\n\nDe toernooi naam en instellingen blijven behouden.')">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="text-orange-400 hover:text-orange-600" title="<?php echo e(__('Reset toernooi (verwijder judoka\'s en poules)')); ?>">
                                🔄
                            </button>
                        </form>
                        
                        <form action="<?php echo e(route('toernooi.destroy', $toernooi->routeParams())); ?>" method="POST" class="inline"
                              onsubmit="return confirm('Weet je zeker dat je \'<?php echo e($toernooi->naam); ?>\' wilt verwijderen?\n\nDit verwijdert ALLE data:\n- Judoka\'s\n- Poules\n- Wedstrijden\n\nDit kan niet ongedaan worden!')">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-400 hover:text-red-600" title="<?php echo e(__('Verwijder toernooi')); ?>">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
    </main>

    
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
                            <h3 style="font-size:18px;font-weight:bold;margin-bottom:12px;color:#b91c1c;"><?php echo e(__('Sessie verloopt bijna')); ?></h3>
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
                const logoutForm = document.querySelector('form[action*="logout"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    window.location.href = '<?php echo e(route("login")); ?>';
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
<?php /**PATH /var/www/judotoernooi/staging/resources/views/organisator/dashboard.blade.php ENDPATH**/ ?>