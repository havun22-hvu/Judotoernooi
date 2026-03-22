<?php $__env->startSection('title', __('Upgrade Toernooi') . ' - ' . $toernooi->naam); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="<?php echo e(route('toernooi.show', $toernooi->routeParams())); ?>" class="text-blue-600 hover:underline">&larr; <?php echo e(__('Terug naar toernooi')); ?></a>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-2">
        <?php echo e(($isReUpgrade ?? false) ? __('Meer judoka\'s nodig?') : __('Upgrade naar Betaald')); ?>

    </h1>
    <p class="text-gray-600 mb-8"><?php echo e($toernooi->naam); ?></p>

    
    <div class="<?php echo e(($isReUpgrade ?? false) ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200'); ?> border rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold <?php echo e(($isReUpgrade ?? false) ? 'text-green-800' : 'text-blue-800'); ?> mb-4">
            <?php echo e(__('Huidige Status')); ?>: <?php echo e(($isReUpgrade ?? false) ? __('Betaald') . ' (max ' . $status['max_judokas'] . ' ' . __("judoka's") . ')' : __('Gratis Tier')); ?>

        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-600"><?php echo e(__("Judoka's")); ?></span>
                <p class="text-2xl font-bold <?php echo e(($isReUpgrade ?? false) ? 'text-green-700' : 'text-blue-700'); ?>"><?php echo e($status['current_judokas']); ?> / <?php echo e($status['max_judokas']); ?></p>
            </div>
            <div>
                <span class="text-gray-600"><?php echo e(__('Plaatsen over')); ?></span>
                <p class="text-2xl font-bold <?php echo e(($isReUpgrade ?? false) ? 'text-green-700' : 'text-blue-700'); ?>"><?php echo e($status['remaining_slots']); ?></p>
            </div>
            <div>
                <span class="text-gray-600"><?php echo e(__('Print/Noodplan')); ?></span>
                <p class="text-2xl font-bold <?php echo e($status['can_use_print'] ? 'text-green-600' : 'text-red-600'); ?>">
                    <?php echo e($status['can_use_print'] ? __('Beschikbaar') : __('Geblokkeerd')); ?>

                </p>
            </div>
            <div>
                <span class="text-gray-600"><?php echo e(__('Status')); ?></span>
                <p class="text-2xl font-bold <?php echo e(($isReUpgrade ?? false) ? 'text-green-600' : 'text-orange-600'); ?>">
                    <?php echo e(($isReUpgrade ?? false) ? __('Betaald') : __('Gratis')); ?>

                </p>
            </div>
        </div>
    </div>

    
    <div class="flex items-center mb-8">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full <?php echo e($kycCompleet ? 'bg-green-500' : 'bg-blue-500'); ?> text-white flex items-center justify-center text-sm font-bold">
                <?php if($kycCompleet): ?> ✓ <?php else: ?> 1 <?php endif; ?>
            </div>
            <span class="ml-2 font-medium <?php echo e($kycCompleet ? 'text-green-600' : 'text-blue-600'); ?>"><?php echo e(__('Facturatiegegevens')); ?></span>
        </div>
        <div class="flex-1 h-1 mx-4 <?php echo e($kycCompleet ? 'bg-green-300' : 'bg-gray-300'); ?>"></div>
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full <?php echo e($kycCompleet ? 'bg-blue-500' : 'bg-gray-300'); ?> text-white flex items-center justify-center text-sm font-bold">2</div>
            <span class="ml-2 font-medium <?php echo e($kycCompleet ? 'text-blue-600' : 'text-gray-400'); ?>"><?php echo e(__('Staffel kiezen & betalen')); ?></span>
        </div>
    </div>

    <?php if(!$kycCompleet): ?>
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo e(__('Stap 1: Facturatiegegevens')); ?></h2>
        <p class="text-gray-600 mb-6"><?php echo e(__('Vul je organisatiegegevens in. Deze worden gebruikt voor de factuur.')); ?></p>

        <form action="<?php echo e(route('toernooi.upgrade.kyc', $toernooi->routeParams())); ?>" method="POST">
            <?php echo csrf_field(); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="organisatie_naam" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Organisatie/Verenigingsnaam')); ?> *</label>
                    <input type="text" name="organisatie_naam" id="organisatie_naam"
                           value="<?php echo e(old('organisatie_naam', $organisator->organisatie_naam)); ?>"
                           placeholder="<?php echo e(__('Bijv. Judoschool Cees Veen')); ?>"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['organisatie_naam'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['organisatie_naam'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="kvk_nummer" class="block text-gray-700 font-medium mb-1"><?php echo e(__('KvK-nummer')); ?></label>
                    <input type="text" name="kvk_nummer" id="kvk_nummer"
                           value="<?php echo e(old('kvk_nummer', $organisator->kvk_nummer)); ?>"
                           placeholder="12345678"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="btw_nummer" class="block text-gray-700 font-medium mb-1"><?php echo e(__('BTW-nummer')); ?></label>
                    <input type="text" name="btw_nummer" id="btw_nummer"
                           value="<?php echo e(old('btw_nummer', $organisator->btw_nummer)); ?>"
                           placeholder="NL123456789B01"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div class="md:col-span-2">
                    <label for="straat" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Straat en huisnummer')); ?> *</label>
                    <input type="text" name="straat" id="straat"
                           value="<?php echo e(old('straat', $organisator->straat)); ?>"
                           placeholder="<?php echo e(__('Hoofdstraat 123')); ?>"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['straat'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['straat'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="postcode" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Postcode')); ?> *</label>
                    <input type="text" name="postcode" id="postcode"
                           value="<?php echo e(old('postcode', $organisator->postcode)); ?>"
                           placeholder="1234 AB"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['postcode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['postcode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="plaats" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Plaats')); ?> *</label>
                    <input type="text" name="plaats" id="plaats"
                           value="<?php echo e(old('plaats', $organisator->plaats)); ?>"
                           placeholder="<?php echo e(__('Amsterdam')); ?>"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['plaats'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['plaats'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="land" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Land')); ?> *</label>
                    <input type="text" name="land" id="land"
                           value="<?php echo e(old('land', $organisator->land ?? 'Nederland')); ?>"
                           class="w-full border rounded px-3 py-2" required>
                </div>

                <div>
                    <label for="contactpersoon" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Contactpersoon')); ?> *</label>
                    <input type="text" name="contactpersoon" id="contactpersoon"
                           value="<?php echo e(old('contactpersoon', $organisator->contactpersoon)); ?>"
                           placeholder="Jan Jansen"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['contactpersoon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['contactpersoon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="telefoon" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Telefoonnummer')); ?></label>
                    <input type="tel" name="telefoon" id="telefoon"
                           value="<?php echo e(old('telefoon', $organisator->telefoon)); ?>"
                           placeholder="06-12345678"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="factuur_email" class="block text-gray-700 font-medium mb-1"><?php echo e(__('E-mail voor factuur')); ?> *</label>
                    <input type="email" name="factuur_email" id="factuur_email"
                           value="<?php echo e(old('factuur_email', $organisator->factuur_email ?? $organisator->email)); ?>"
                           placeholder="factuur@judoschool.nl"
                           class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['factuur_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                    <?php $__errorArgs = ['factuur_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="website" class="block text-gray-700 font-medium mb-1"><?php echo e(__('Website of Facebook')); ?></label>
                    <input type="text" name="website" id="website"
                           value="<?php echo e(old('website', $organisator->website)); ?>"
                           placeholder="judoschool.nl of facebook.com/judoschool"
                           class="w-full border rounded px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1"><?php echo e(__('Ter verificatie van je organisatie')); ?></p>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                    <?php echo e(__('Opslaan en doorgaan')); ?>

                </button>
            </div>
        </form>
    </div>

    <?php else: ?>
    
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="font-semibold text-green-800 mb-2"><?php echo e(__('Facturatiegegevens')); ?></h3>
                <div class="text-sm text-green-700">
                    <p><strong><?php echo e($organisator->organisatie_naam); ?></strong></p>
                    <p><?php echo e($organisator->straat); ?></p>
                    <p><?php echo e($organisator->postcode); ?> <?php echo e($organisator->plaats); ?></p>
                    <?php if($organisator->kvk_nummer): ?><p><?php echo e(__('KvK')); ?>: <?php echo e($organisator->kvk_nummer); ?></p><?php endif; ?>
                    <p><?php echo e($organisator->factuur_email); ?></p>
                </div>
            </div>
            <a href="<?php echo e(route('toernooi.upgrade', $toernooi->routeParams())); ?>?edit=1" class="text-green-600 hover:underline text-sm"><?php echo e(__('Wijzigen')); ?></a>
        </div>
    </div>

    
    <h2 class="text-2xl font-bold text-gray-800 mb-4"><?php echo e(__('Stap 2: Kies je aantal judoka\'s')); ?></h2>

    <?php if(count($upgradeOptions) === 0): ?>
        <div class="bg-gray-100 rounded-lg p-6 text-center">
            <p class="text-gray-600"><?php echo e(__('Er zijn geen upgrade opties beschikbaar. Het maximum aantal judoka\'s is bereikt.')); ?></p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            
            <div class="flex flex-col md:flex-row md:items-end gap-6">
                <div class="flex-1">
                    <label for="tier-select" class="block text-gray-700 font-medium mb-2"><?php echo e(__('Hoeveel judoka\'s heb je nodig?')); ?></label>
                    <select id="tier-select" class="w-full md:w-64 border-2 border-gray-300 rounded-lg px-4 py-3 text-lg focus:border-blue-500 focus:outline-none">
                        <option value="">-- <?php echo e(__('Kies aantal')); ?> --</option>
                        <?php $__currentLoopData = $upgradeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option['tier']); ?>" data-prijs="<?php echo e($option['prijs']); ?>" data-max="<?php echo e($option['max']); ?>">
                            <?php echo e(__('Tot')); ?> <?php echo e($option['max']); ?> <?php echo e(__("judoka's")); ?>

                        </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div id="price-display" class="hidden">
                    <p class="text-gray-600 text-sm mb-1"><?php echo e(__('Eenmalige kosten')); ?></p>
                    <p class="text-4xl font-bold text-blue-600">&euro;<span id="prijs-amount">0</span></p>
                </div>
            </div>

            
            <div id="features-section" class="hidden mt-6 pt-6 border-t">
                <p class="text-gray-700 font-medium mb-3"><?php echo e(__('Dit krijg je:')); ?></p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo e(__('Tot')); ?> <span id="max-judokas" class="font-semibold mx-1">0</span> <?php echo e(__("judoka's")); ?>

                    </div>
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo e(__('Volledige Print/Noodplan')); ?>

                    </div>
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo e(__('Judoka\'s verwijderen/wijzigen')); ?>

                    </div>
                </div>
            </div>

            
            <div id="pay-section" class="hidden mt-6">
                <form id="upgrade-form" action="<?php echo e(route('toernooi.upgrade.start', $toernooi->routeParams())); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="tier" id="selected-tier" value="">
                    <input type="hidden" name="payment_provider" id="selected-provider" value="mollie">

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" onclick="document.getElementById('selected-provider').value='mollie'"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                            <?php echo e(__('Betalen via Mollie')); ?>

                        </button>
                        <button type="submit" onclick="document.getElementById('selected-provider').value='stripe'"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                            <?php echo e(__('Betalen via Stripe')); ?>

                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2"><?php echo e(__('Mollie: iDEAL, Bancontact, creditcard (Europa) — Stripe: Creditcard, Google Pay, Apple Pay (wereldwijd)')); ?></p>
                </form>
            </div>
        </div>

        
        <?php if($isReUpgrade ?? false): ?>
            <p class="text-sm text-gray-500 text-center"><?php echo e(__('Je betaalt alleen het verschil met je huidige staffel')); ?></p>
        <?php else: ?>
            <p class="text-sm text-gray-500 text-center"><?php echo e(__('Prijzen: €10 per 50 judoka\'s (boven de gratis 50)')); ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('tier-select');
    const tierInput = document.getElementById('selected-tier');
    const priceDisplay = document.getElementById('price-display');
    const prijsAmount = document.getElementById('prijs-amount');
    const maxJudokas = document.getElementById('max-judokas');
    const featuresSection = document.getElementById('features-section');
    const paySection = document.getElementById('pay-section');

    if (!select) return;

    select.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];

        if (this.value) {
            const prijs = selected.dataset.prijs;
            const max = selected.dataset.max;

            tierInput.value = this.value;
            prijsAmount.textContent = parseInt(prijs);
            maxJudokas.textContent = max;

            priceDisplay.classList.remove('hidden');
            featuresSection.classList.remove('hidden');
            paySection.classList.remove('hidden');
        } else {
            priceDisplay.classList.add('hidden');
            featuresSection.classList.add('hidden');
            paySection.classList.add('hidden');
            tierInput.value = '';
        }
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/upgrade.blade.php ENDPATH**/ ?>