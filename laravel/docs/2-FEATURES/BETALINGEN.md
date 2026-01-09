# Betalingen - Mollie Integratie

> Online betalingen voor judoscholen bij inschrijving van judoka's.

## Overzicht

JudoToernooi ondersteunt twee betalingsmodi:

| Modus | Beschrijving | Toeslag |
|-------|--------------|---------|
| **Connect** | Organisator koppelt eigen Mollie account | Geen |
| **Platform** | Betalingen via JudoToernooi's Mollie | €0,50 per betaling |

De organisator kiest de modus in de toernooi instellingen (tabblad Organisatie).

---

## Connect Mode (Aanbevolen)

### Wat is het?
- Organisator koppelt eigen Mollie account via OAuth
- Betalingen gaan direct naar organisator
- Geen tussenkomst van JudoToernooi

### Voordelen
- Direct geld op eigen rekening
- Geen extra kosten
- Volledige controle

### Vereisten
- Organisator heeft Mollie account nodig
- Eenmalige OAuth koppeling per toernooi

### Flow

```
┌─────────────────────────────────────────────────────────────┐
│ ORGANISATOR                                                  │
├──────────────────────────────────────────────────────────────┤
│ 1. Ga naar Toernooi → Instellingen → Organisatie            │
│ 2. Klik "Koppel Mollie Account"                             │
│ 3. Log in bij Mollie (redirect)                             │
│ 4. Keur JudoToernooi app goed                               │
│ 5. Teruggestuurd → Account gekoppeld                        │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ COACH/JUDOSCHOOL                                            │
├──────────────────────────────────────────────────────────────┤
│ 1. Voeg judoka's toe in Coach Portal                        │
│ 2. Klik "Afrekenen" (X judoka's × €Y = totaal)              │
│ 3. Redirect naar Mollie (iDEAL, etc.)                       │
│ 4. Betaal                                                    │
│ 5. Teruggestuurd → Judoka's gemarkeerd als betaald          │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ GELD                                                         │
├──────────────────────────────────────────────────────────────┤
│ → Direct naar organisator's Mollie/bankrekening             │
└──────────────────────────────────────────────────────────────┘
```

---

## Platform Mode

### Wat is het?
- Geen Mollie account nodig voor organisator
- Betalingen gaan via JudoToernooi's Mollie
- Na afloop: handmatige uitbetaling aan organisator

### Voordelen
- Geen eigen Mollie account nodig
- Direct aan de slag

### Nadelen
- Toeslag per betaling (€0,50 of percentage)
- Uitbetaling na toernooi (handmatig)

### Wanneer gebruiken?
- Organisator heeft geen Mollie
- Eenmalig/klein toernooi
- Snel opstarten belangrijker dan kosten

---

## Toernooi Instellingen

### UI: Tabblad Organisatie

```
╔═══════════════════════════════════════════════════════════════╗
║ BETALINGEN                                                     ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                ║
║ ☑ Betalingen actief                                           ║
║                                                                ║
║ Inschrijfgeld per judoka: € [15.00]                           ║
║                                                                ║
║ ─────────────────────────────────────────────────────────────  ║
║                                                                ║
║ Mollie Account:                                                ║
║                                                                ║
║   ○ Eigen Mollie account (aanbevolen)                         ║
║     Status: ✓ Gekoppeld als "Judoschool Cees Veen"            ║
║     [Ontkoppelen]                                              ║
║                                                                ║
║   ○ Via JudoToernooi platform                                 ║
║     ⚠️ Toeslag: €0,50 per betaling                            ║
║                                                                ║
╚═══════════════════════════════════════════════════════════════╝
```

### Database Velden

```sql
-- toernooien tabel
betaling_actief           BOOLEAN DEFAULT FALSE
inschrijfgeld             DECIMAL(8,2) NULL
mollie_mode               VARCHAR(20) DEFAULT 'platform'  -- 'connect' of 'platform'
platform_toeslag          DECIMAL(8,2) DEFAULT 0.50
platform_toeslag_percentage BOOLEAN DEFAULT FALSE
mollie_account_id         VARCHAR(255) NULL
mollie_access_token       TEXT NULL                       -- encrypted!
mollie_refresh_token      TEXT NULL                       -- encrypted!
mollie_token_expires_at   TIMESTAMP NULL
mollie_onboarded          BOOLEAN DEFAULT FALSE
mollie_organization_name  VARCHAR(255) NULL
```

---

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
7. Kies betaalmethode (iDEAL, creditcard, etc.)
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

## Simulatie Mode (Staging)

Voor testen zonder echte Mollie keys:

```php
// MollieService checkt automatisch
if ($this->isSimulationMode()) {
    return $this->simulatePayment($data);
}
```

### Simulatie Flow

1. Payment wordt "aangemaakt" met fake ID
2. Redirect naar `/betaling/simulate/{id}`
3. Pagina toont "iDEAL simulatie"
4. Keuze: Betaald / Mislukt / Geannuleerd
5. Webhook wordt lokaal getriggerd
6. Redirect naar return URL

---

## Mollie Payment Statussen

| Status | Betekenis | Actie |
|--------|-----------|-------|
| `open` | Wacht op klant | Niets |
| `pending` | In behandeling | Niets |
| `paid` | Betaald! | Markeer judoka's als betaald |
| `failed` | Mislukt | Toon foutmelding, opnieuw proberen |
| `expired` | Verlopen (15 min) | Opnieuw proberen |
| `canceled` | Geannuleerd | Opnieuw proberen |

---

## Environment Variables

```env
# Platform mode (JudoToernooi's Mollie account)
MOLLIE_PLATFORM_API_KEY=live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MOLLIE_PLATFORM_TEST_KEY=test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Connect mode (OAuth app credentials)
MOLLIE_CLIENT_ID=app_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MOLLIE_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MOLLIE_REDIRECT_URI=${APP_URL}/mollie/callback

# Platform fee (default)
MOLLIE_PLATFORM_FEE=0.50
```

---

## Checklist Implementatie

### Klaar
- [x] Database migration
- [x] MollieService (dual mode)
- [x] Toernooi model methods
- [x] Config en .env.example
- [x] Documentatie
- [x] MollieController (OAuth + webhook)
- [x] Routes registreren
- [x] CSRF uitsluiting voor webhook
- [x] Views: instellingen sectie (Organisatie tab)
- [x] Views: coach afrekenen flow (`pages/coach/afrekenen.blade.php`)
- [x] Views: betaling return pagina (`pages/coach/betaling-succes.blade.php`)
- [x] Simulatie pagina (staging)
- [x] .env variabelen op production (5 jan 2026)

### TODO
- [ ] Testen met echt Mollie account (Connect mode)

---

## Testen

### Lokaal (Simulatie)
```bash
php artisan serve --port=8007
# Geen Mollie keys nodig
# Betalingen worden gesimuleerd
```

### Staging (Test Mode)
```env
MOLLIE_PLATFORM_TEST_KEY=test_xxx
```
- Gebruik Mollie test keys
- Echte API, geen echt geld
- Test iDEAL: kies "Betaald" in testomgeving

### Production
```env
MOLLIE_PLATFORM_API_KEY=live_xxx
```
- Echte betalingen!
- Zorg dat webhook URL bereikbaar is

---

## Referenties

- [Mollie API Docs](https://docs.mollie.com/)
- [Mollie Connect](https://docs.mollie.com/connect/overview)
- [HavunCore Mollie Pattern](../../HavunCore/docs/kb/patterns/mollie-payments.md)
