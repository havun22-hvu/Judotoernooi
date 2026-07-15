---
title: Simulatie, statussen, env vars, admin & testen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Simulatie, statussen, env vars, admin & testen

> Onderdeel van [Betalingen](../BETALINGEN.md).

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
- [x] Stripe Connect code (coach → organisator) via Account Links
- [ ] Stripe Connect testen op staging (organisator onboarding flow)
- [ ] `account.updated` webhook voor automatische onboarding status updates

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

