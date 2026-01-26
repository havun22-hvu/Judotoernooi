# Freemium Model

> **Doel:** Organisatoren gratis laten uitproberen, betalen voor grotere toernooien.

---

## Business Model

JudoToernooi hanteert een freemium model:

| Aspect | Gratis | Betaald |
|--------|--------|---------|
| Judoka's | Max 50 | 51-500+ |
| Clubs | Max 2 actief | Onbeperkt |
| Presets | Max 1 | Onbeperkt |
| Print/Noodplan | Beperkt | Volledig |
| Prijs | Gratis | Vanaf €20 |

---

## Gratis Tier Limieten

| Limiet | Waarde | Enforcement |
|--------|--------|-------------|
| Judoka's | 50 | `Toernooi::canAddMoreJudokas()` |
| Actieve clubs | 2 | `ClubController` |
| Presets | 1 | `GewichtsklassenPresetController` |
| Print/Noodplan | Beperkt | `CheckFreemiumPrint` middleware |

### Waarom deze limieten?

- **50 judoka's** = Klein clubtoernooi, genoeg om te testen
- **2 clubs** = Eigen club + 1 gastclub
- **1 preset** = Basis gewichtsklassen
- **Print beperkt** = Stimuleert upgrade voor wedstrijddag

---

## Betaalde Staffels

| Staffel | Max Judoka's | Prijs |
|---------|--------------|-------|
| Klein | 100 | €20 |
| Medium | 150 | €30 |
| Groot | 200 | €40 |
| XL | 250 | €50 |
| XXL | 300+ | €60+ |

**Formule:** Basis €20 + €10 per extra 50 judoka's

```php
// FreemiumService::getStaffelPrijs()
$basis = 20;
$perExtra50 = 10;
$staffels = ceil(($maxJudokas - 50) / 50);
return $basis + (($staffels - 1) * $perExtra50);
```

---

## Upgrade Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. TRIGGER                                                   │
├──────────────────────────────────────────────────────────────┤
│ - Judoka limiet bereikt (50)                                │
│ - Print functie geblokkeerd                                 │
│ - Organisator klikt "Upgrade"                               │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. STAFFEL SELECTIE                                          │
├──────────────────────────────────────────────────────────────┤
│ /{org}/toernooi/{toernooi}/upgrade                          │
│                                                              │
│ Kies je plan:                                               │
│ ○ 100 judoka's - €20                                        │
│ ● 150 judoka's - €30  ← geselecteerd                        │
│ ○ 200 judoka's - €40                                        │
│                                                              │
│ [Betalen met iDEAL]                                         │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. MOLLIE BETALING                                           │
├──────────────────────────────────────────────────────────────┤
│ - Platform mode (geld naar JudoToernooi)                    │
│ - iDEAL, creditcard, etc.                                   │
│ - Webhook bevestigt betaling                                │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. ACTIVATIE                                                 │
├──────────────────────────────────────────────────────────────┤
│ Toernooi wordt geüpgraded:                                  │
│ - plan_type = 'paid'                                        │
│ - paid_tier = 'medium'                                      │
│ - paid_max_judokas = 150                                    │
│ - paid_at = now()                                           │
│                                                              │
│ Alle limieten opgeheven!                                    │
└──────────────────────────────────────────────────────────────┘
```

---

## UI Componenten

### Freemium Banner

Getoond op toernooi pagina's wanneer limiet nadert:

```blade
@include('components.freemium-banner', ['toernooi' => $toernooi])
```

| Situatie | Banner |
|----------|--------|
| < 80% vol | Geen banner |
| 80-99% vol | Gele waarschuwing |
| 100% vol | Rode blokkade + upgrade link |
| Betaald | Groene "✓ Betaald" badge |

### Upgrade Pagina

`resources/views/pages/toernooi/upgrade.blade.php`

- Staffel radio buttons
- Prijs berekening
- Mollie betaalknop

### Print Blokkade

`resources/views/pages/noodplan/upgrade-required.blade.php`

- Uitleg waarom geblokkeerd
- Link naar upgrade pagina

---

## Database Schema

### toernooien tabel

```sql
-- Freemium velden
plan_type               ENUM('free', 'paid') DEFAULT 'free'
paid_tier               VARCHAR(20) NULL        -- 'klein', 'medium', 'groot'
paid_max_judokas        INT NULL                -- 100, 150, 200, etc.
paid_at                 TIMESTAMP NULL
toernooi_betaling_id    BIGINT UNSIGNED NULL    -- FK naar toernooi_betalingen
```

### toernooi_betalingen tabel

```sql
CREATE TABLE toernooi_betalingen (
    id                  BIGINT PRIMARY KEY,
    toernooi_id         BIGINT NOT NULL,
    organisator_id      BIGINT NOT NULL,
    mollie_payment_id   VARCHAR(255) UNIQUE,
    bedrag              DECIMAL(8,2) NOT NULL,
    tier                VARCHAR(20) NOT NULL,
    max_judokas         INT NOT NULL,
    status              VARCHAR(20) DEFAULT 'open',  -- open/paid/failed/expired/canceled
    betaald_op          TIMESTAMP NULL,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP
);
```

---

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

## Testen

### Lokaal (Simulatie)

```bash
php artisan serve --port=8007
```

1. Maak toernooi aan (gratis)
2. Voeg 50 judoka's toe → OK
3. Voeg 51e toe → Geblokkeerd, upgrade banner
4. Ga naar upgrade pagina
5. Kies staffel, klik betalen
6. Simulatie pagina → kies "Betaald"
7. Toernooi is nu upgraded
8. Voeg meer judoka's toe → OK

### Print Test

1. Free tier → `/noodplan/poules` → Redirect naar upgrade
2. Na upgrade → Print werkt

---

## Relatie met BETALINGEN.md

Dit document gaat over **toernooi upgrades** (organisator betaalt aan JudoToernooi).

`BETALINGEN.md` gaat over **inschrijfgeld** (coach betaalt aan organisator).

| Aspect | Freemium (dit doc) | Inschrijfgeld |
|--------|-------------------|---------------|
| Wie betaalt | Organisator | Coach/judoschool |
| Aan wie | JudoToernooi | Organisator |
| Waarvoor | Meer capaciteit | Deelname judoka's |
| Mollie mode | Platform (altijd) | Connect of Platform |
| Webhook | `/mollie/webhook/toernooi` | `/mollie/webhook` |
