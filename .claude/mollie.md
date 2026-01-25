# Mollie Betalingen

> **Docs:** `laravel/docs/2-FEATURES/BETALINGEN.md`

## Twee Modi

| Modus | Geld naar | OAuth nodig | Toeslag |
|-------|-----------|-------------|---------|
| **Connect** | Organisator's Mollie | Ja | Nee |
| **Platform** | JudoToernooi's Mollie | Nee | Ja (€0,50) |

## Database Velden

**toernooien tabel:**
- `betaling_actief` - boolean
- `inschrijfgeld` - decimal
- `mollie_mode` - 'connect' of 'platform'
- `platform_toeslag` - decimal
- `mollie_access_token` - encrypted
- `mollie_onboarded` - boolean

## Model Methods

```php
$toernooi->usesMollieConnect()
$toernooi->usesPlatformPayments()
$toernooi->hasMollieConfigured()
$toernooi->calculatePaymentAmount(5)
```

## Routes

```
GET  toernooi/{t}/mollie/authorize  → Start OAuth
GET  mollie/callback                → OAuth callback
POST toernooi/{t}/mollie/disconnect → Ontkoppelen
POST mollie/webhook                 → Updates (no CSRF!)
```

## Environment

```env
MOLLIE_PLATFORM_API_KEY=live_xxx
MOLLIE_CLIENT_ID=app_xxx
MOLLIE_CLIENT_SECRET=xxx
MOLLIE_PLATFORM_FEE=0.50
```

## Webhook (Kritiek!)

```php
Route::post('/mollie/webhook', ...)->withoutMiddleware(VerifyCsrfToken::class);
```

- Webhook = source of truth
- Kan meerdere keren komen → idempotent
