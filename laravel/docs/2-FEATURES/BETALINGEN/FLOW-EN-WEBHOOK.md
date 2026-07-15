---
title: Coach-portal flow, implementatie & webhook
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coach-portal flow, implementatie & webhook

> Onderdeel van [Betalingen](../BETALINGEN.md).

## Coach Portal - Afrekenen

### Flow

```
1. Coach bekijkt judoka's lijst
2. Ziet "X judoka's klaar voor betaling"
3. Klikt "Afrekenen"
4. Bevestigingsscherm:
   - 5 judoka's × €15,00 = €75,00
   - [Platform mode: + €0,50 toeslag = €75,50]
5. Klik "Betalen"
6. Redirect naar Mollie
7. Kies betaalmethode (iDEAL | Wero, creditcard, etc.)
8. Betaal
9. Terug naar JudoToernooi
10. Judoka's gemarkeerd als betaald
```

### Judoka Statussen

| Status | Betekenis |
|--------|-----------|
| Onvolledig | Gegevens missen |
| Klaar voor betaling | Volledig, nog niet betaald |
| Betaald | `betaald_op` is gezet |

---

## Technische Implementatie

### Bestanden

| Bestand | Functie |
|---------|---------|
| `app/Services/MollieService.php` | API calls, OAuth, token management |
| `app/Models/Betaling.php` | Payment records |
| `app/Models/Toernooi.php` | Mollie helper methods |
| `config/services.php` | API keys configuratie |

### MollieService Methods

```php
// API key voor toernooi (connect of platform)
$service->getApiKeyForToernooi($toernooi);

// Payment aanmaken
$service->createPayment($toernooi, [
    'amount' => ['currency' => 'EUR', 'value' => '75.00'],
    'description' => 'Inschrijving WestFries Open - 5 judoka\'s',
    'redirectUrl' => route('betaling.succes', ['token' => $club->token]),
    'webhookUrl' => route('mollie.webhook'),
    'metadata' => ['betaling_id' => 123],
]);

// Payment status ophalen
$service->getPayment($toernooi, 'tr_xxx');

// OAuth URL genereren
$service->getOAuthAuthorizeUrl($toernooi);

// Code uitwisselen voor tokens
$service->exchangeCodeForTokens($code);

// Tokens opslaan bij toernooi
$service->saveTokensToToernooi($toernooi, $tokens);

// Ontkoppelen
$service->disconnectFromToernooi($toernooi);
```

### Toernooi Model Methods

```php
$toernooi->usesMollieConnect();       // true als connect mode + onboarded
$toernooi->usesPlatformPayments();    // true als platform mode
$toernooi->hasMollieConfigured();     // true als betalingen mogelijk
$toernooi->calculatePaymentAmount(5); // bedrag voor 5 judoka's incl. toeslag
$toernooi->getMollieStatusText();     // "Gekoppeld: Judoschool Cees Veen"
$toernooi->getPlatformFee();          // 0.50
```

### Routes

```php
// OAuth (onder toernooi prefix)
Route::get('toernooi/{toernooi}/mollie/authorize', [MollieController::class, 'authorize'])->name('mollie.authorize');
Route::get('mollie/callback', [MollieController::class, 'callback'])->name('mollie.callback');
Route::post('toernooi/{toernooi}/mollie/disconnect', [MollieController::class, 'disconnect'])->name('mollie.disconnect');

// Webhook - ZONDER CSRF!
Route::post('mollie/webhook', [MollieController::class, 'webhook'])->name('mollie.webhook');

// Simulatie (staging only)
Route::get('betaling/simulate', [MollieController::class, 'simulate'])->name('betaling.simulate');
Route::post('betaling/simulate', [MollieController::class, 'simulateComplete'])->name('betaling.simulate.complete');

// Coach Portal betaling returns (onder /club/{token}/ of /coach/{code}/)
Route::get('{token}/betaling/succes', [CoachPortalController::class, 'betalingSucces'])->name('betaling.succes');
Route::get('{token}/betaling/geannuleerd', [CoachPortalController::class, 'betalingGeannuleerd'])->name('betaling.geannuleerd');
```

---

## Webhook Handling

### Kritieke Punten

1. **Webhook is source of truth** - Vertrouw nooit alleen op redirect
2. **Idempotent** - Webhook kan meerdere keren komen
3. **CSRF uitsluiten** - Mollie kan geen CSRF token sturen

### Implementation

```php
public function webhook(Request $request)
{
    $paymentId = $request->input('id');

    // Vind betaling in database
    $betaling = Betaling::where('mollie_payment_id', $paymentId)->first();
    if (!$betaling) {
        return response('Unknown payment', 404);
    }

    // Haal actuele status op bij Mollie
    $mollie = app(MollieService::class);
    $payment = $mollie->getPayment($betaling->toernooi, $paymentId);

    // Update status
    $betaling->status = $payment->status;

    if ($payment->status === 'paid' && !$betaling->betaald_op) {
        $betaling->markeerAlsBetaald();
    }

    $betaling->save();

    return response('OK', 200);
}
```

---

