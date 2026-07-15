---
title: Technische implementatie, routes & webhook
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Technische implementatie, routes & webhook

> Onderdeel van [Freemium Model](../FREEMIUM.md).

## Technische Implementatie

### Key Files

| Bestand | Functie |
|---------|---------|
| `app/Services/FreemiumService.php` | Limieten, staffels, checks |
| `app/Models/ToernooiBetaling.php` | Betaling records |
| `app/Models/Toernooi.php` | Helper methods |
| `app/Http/Controllers/ToernooiBetalingController.php` | Upgrade flow |
| `app/Http/Middleware/CheckFreemiumPrint.php` | Print blokkade |

### FreemiumService Methods

```php
// Constanten
FreemiumService::FREE_MAX_JUDOKAS      // 50
FreemiumService::FREE_MAX_CLUBS        // 2
FreemiumService::FREE_MAX_PRESETS      // 1

// Checks
$service->checkJudokaLimit($toernooi);     // throws FreemiumLimitException
$service->checkClubLimit($organisator);
$service->checkPresetLimit($organisator);

// Staffels
$service->getUpgradeOptions();             // array van staffels
$service->getStaffelPrijs($maxJudokas);    // berekent prijs
$service->activatePaidPlan($toernooi, $betaling);
```

### Toernooi Model Methods

```php
$toernooi->isFreeTier();              // true als plan_type = 'free'
$toernooi->isPaidTier();              // true als plan_type = 'paid'
$toernooi->getEffectiveMaxJudokas();  // 50 (free) of paid_max_judokas
$toernooi->canAddMoreJudokas();       // check tegen limiet
$toernooi->getRemainingJudokaSlots(); // hoeveel nog vrij
$toernooi->canUsePrint();             // false voor free tier
```

### Limiet Enforcement

| Waar | Wat | Hoe |
|------|-----|-----|
| `JudokaController::store()` | Judoka toevoegen | `canAddMoreJudokas()` check |
| `CoachPortalController::storeJudoka()` | Coach voegt toe | `canAddMoreJudokas()` check |
| `ClubController::store()` | Club activeren | `FreemiumService::checkClubLimit()` |
| `GewichtsklassenPresetController::store()` | Preset maken | `FreemiumService::checkPresetLimit()` |
| Noodplan routes | Print functies | `CheckFreemiumPrint` middleware |

---

## Routes

```php
// Upgrade flow (onder toernooi beheer)
Route::get('{org}/toernooi/{toernooi}/upgrade', [ToernooiBetalingController::class, 'showUpgrade'])
    ->name('toernooi.upgrade');

Route::post('{org}/toernooi/{toernooi}/upgrade', [ToernooiBetalingController::class, 'startPayment'])
    ->name('toernooi.upgrade.start');

Route::get('{org}/toernooi/{toernooi}/upgrade/succes/{betaling}', [ToernooiBetalingController::class, 'success'])
    ->name('toernooi.upgrade.succes');

Route::get('{org}/toernooi/{toernooi}/upgrade/geannuleerd', [ToernooiBetalingController::class, 'cancelled'])
    ->name('toernooi.upgrade.geannuleerd');

// Webhook (CSRF-exempt, aangeroepen door Mollie)
Route::post('mollie/webhook/toernooi', [MollieController::class, 'webhookToernooi'])
    ->name('mollie.webhook.toernooi');
```

---

## Webhook Handling

Toernooi upgrade betalingen gebruiken een aparte webhook:

```php
// MollieController::webhookToernooi()
public function webhookToernooi(Request $request)
{
    $paymentId = $request->input('id');

    $betaling = ToernooiBetaling::where('mollie_payment_id', $paymentId)->first();
    if (!$betaling) {
        return response('Unknown payment', 404);
    }

    // Haal status op bij Mollie
    $mollie = app(MollieService::class);
    $payment = $mollie->getPlatformPayment($paymentId);

    $betaling->status = $payment->status;

    if ($payment->status === 'paid' && !$betaling->betaald_op) {
        $betaling->markeerAlsBetaald();

        // Activeer betaald plan
        $betaling->toernooi->update([
            'plan_type' => 'paid',
            'paid_tier' => $betaling->tier,
            'paid_max_judokas' => $betaling->max_judokas,
            'paid_at' => now(),
            'toernooi_betaling_id' => $betaling->id,
        ]);
    }

    $betaling->save();

    return response('OK', 200);
}
```

---

