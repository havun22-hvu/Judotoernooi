# Betalingen - Mollie & Stripe Integratie

> Online betalingen voor judoscholen bij inschrijving van judoka's.

> **Let op:** Dit document gaat over **inschrijfgeld** (coach betaalt aan organisator).
> Voor **toernooi upgrades** (organisator betaalt aan JudoToernooi), zie [FREEMIUM.md](./FREEMIUM.md).

## Overzicht

JudoToernooi ondersteunt twee **betaalproviders** en twee **betalingsmodi** per provider:

### Providers

| Provider | Dekking | Methodes | Kosten |
|----------|---------|----------|--------|
| **Mollie** (standaard) | Europa | iDEAL, Bancontact, creditcard | €0,29 + 0% |
| **Stripe** | Wereldwijd | Creditcard, Google Pay, Apple Pay | 1,5% + €0,25 |

Keuze per toernooi via `payment_provider` veld (`'mollie'` of `'stripe'`).

### Modi (per provider)

| Modus | Beschrijving | Toeslag |
|-------|--------------|---------|
| **Connect** | Organisator koppelt eigen account (Mollie Connect / Stripe Connect) | Geen |
| **Platform** | Betalingen via JudoToernooi's account | €0,50 per betaling |

De organisator kiest provider en modus in de toernooi instellingen (tabblad Organisatie).

### Architectuur

```
PaymentProviderInterface
├── MolliePaymentProvider (wraps MollieService)
└── StripePaymentProvider (Stripe Checkout + Connect)

PaymentProviderFactory::forToernooi($toernooi) → juiste provider
```

**Key files:**
- `app/Contracts/PaymentProviderInterface.php` — Interface
- `app/DTOs/PaymentResult.php` — Genormaliseerd resultaat
- `app/Services/PaymentProviderFactory.php` — Factory
- `app/Services/Payments/MolliePaymentProvider.php` — Mollie wrapper
- `app/Services/Payments/StripePaymentProvider.php` — Stripe implementatie

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
- [ ] Stripe Connect testen (stroom 2: coach → organisator via Account Links)

---

## Admin Factuuroverzicht

### Route
`GET /admin/facturen` → `AdminController@facturen` (sitebeheerder only)

### Wat toont het?
Alle `toernooi_betalingen` in één tabel:

| Kolom | Bron |
|-------|------|
| Factuurnummer | `factuurnummer` (format: `JT-YYYYMMDD-{slug}-NNN`) |
| Datum | `created_at` |
| Klant | `organisator.naam` (link naar klant-edit) |
| Toernooi | `toernooi.naam` (link naar toernooi) |
| Tier | `tier` + `max_judokas` |
| Provider | `payment_provider` (Mollie/Stripe badge) |
| Bedrag | `bedrag` |
| Status | `status` (paid/open/expired/failed) |
| Betaald op | `betaald_op` |

### Stats (bovenaan)
- Totaal ontvangen (som betaalde facturen)
- Aantal betaalde facturen
- Aantal open betalingen

### Toegang
- Via admin dashboard → Betalingen tab → "Factuuroverzicht openen"
- Direct: `/{org}/admin/facturen`

### Factuurnummer formaat
Zie `HavunCore/docs/kb/patterns/invoice-numbering.md`

```
JT-YYYYMMDD-{toernooi-slug}-NNN
Voorbeeld: JT-20260308-noordzee-cup-001
```

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
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
```
- Mollie: test keys, echte API, geen echt geld
- Stripe: sandbox mode, testkaartnummer `4242 4242 4242 4242`
- Stripe upgrade betaling getest op 8 maart 2026: werkend

### Production
```env
MOLLIE_PLATFORM_API_KEY=live_xxx
```
- Echte betalingen!
- Zorg dat webhook URL bereikbaar is

---

---

## Stripe Integratie

### Stripe Checkout (hosted page)

Zelfde redirect-flow als Mollie:
1. Maak Stripe Checkout Session aan
2. Redirect naar Stripe hosted checkout page
3. Na betaling: webhook ontvangt `checkout.session.completed`
4. Update betaling status

### Stripe Connect (voor coach betalingen — inschrijfgeld)

- Account Links onboarding (geen legacy OAuth/`ca_...` nodig)
- `stripe_account_id` opslaan na onboarding
- Coach betalingen: `transfer_data.destination` = organisator's Stripe account
- **Geen application fee** — JudoToernooi verdient niets aan inschrijfgeld
- Organisator ontvangt het volledige bedrag (minus Stripe transactiekosten)

### Stripe Direct (voor upgrade betalingen)

- Altijd naar JudoToernooi's Stripe account (zelfde als Mollie Platform mode)

### Stripe Database Velden

```sql
-- toernooien tabel (naast bestaande Mollie velden)
payment_provider              VARCHAR(20) DEFAULT 'mollie'  -- 'mollie' | 'stripe'
stripe_account_id             VARCHAR(255) NULL
stripe_access_token           TEXT NULL                      -- encrypted!
stripe_refresh_token          TEXT NULL                      -- encrypted!
stripe_publishable_key        VARCHAR(255) NULL

-- betalingen tabel
payment_provider              VARCHAR(20) DEFAULT 'mollie'
stripe_payment_id             VARCHAR(255) NULL

-- toernooi_betalingen tabel
payment_provider              VARCHAR(20) DEFAULT 'mollie'
stripe_payment_id             VARCHAR(255) NULL
```

### Stripe Routes

```php
GET  /stripe/callback                              → OAuth callback
POST /stripe/webhook                               → Coach payment webhook
POST /stripe/webhook/toernooi                      → Upgrade payment webhook
GET  /{org}/toernooi/{toernooi}/stripe/authorize   → Start OAuth
POST /{org}/toernooi/{toernooi}/stripe/disconnect  → Disconnect
```

### Stripe Environment Variables

```env
STRIPE_KEY=           # pk_test_... of pk_live_...
STRIPE_SECRET=        # sk_test_... of sk_live_...
STRIPE_WEBHOOK_SECRET= # whsec_...
```

---

## Referenties

- [Mollie API Docs](https://docs.mollie.com/)
- [Mollie Connect](https://docs.mollie.com/connect/overview)
- [Stripe Checkout Docs](https://stripe.com/docs/payments/checkout)
- [Stripe Connect](https://stripe.com/docs/connect)
- HavunCore Mollie Pattern: `HavunCore/docs/kb/patterns/mollie-payments.md`
