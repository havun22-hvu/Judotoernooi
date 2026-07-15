---
title: Stripe-integratie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Stripe-integratie

> Onderdeel van [Betalingen](../BETALINGEN.md).

## Stripe Integratie

### Stripe Checkout (hosted page)

Zelfde redirect-flow als Mollie:
1. Maak Stripe Checkout Session aan
2. Redirect naar Stripe hosted checkout page
3. Na betaling: webhook ontvangt `checkout.session.completed`
4. Update betaling status

### Stripe Connect (voor coach betalingen — inschrijfgeld)

Gebruikt **Account Links** (Stripe-hosted onboarding), NIET de legacy OAuth flow.
Geen `STRIPE_CLIENT_ID` (`ca_...`) nodig — alleen de platform secret key.

**Onboarding flow:**
```
1. Organisator klikt "Koppel Stripe" in toernooi instellingen
2. Backend: POST /v1/accounts → maakt Express connected account (acct_...)
3. Backend: POST /v1/account_links → maakt onboarding URL
4. Redirect organisator → Stripe-hosted onboarding pagina
5. Organisator vult gegevens in (KYC, bankrekening, etc.)
6. Stripe redirect terug → backend checkt charges_enabled
7. Als volledig: mollie_mode = 'connect', klaar voor betalingen
```

**Betalingen in Connect mode:**
- `transfer_data.destination` = organisator's Stripe account
- **Geen application fee** — organisator ontvangt het volledige bedrag
- Stripe transactiekosten zijn voor rekening van de organisator

**Onboarding statussen:**
| Status | UI | Actie |
|--------|-----|-------|
| Geen account | Grijs | "Koppel Stripe" knop |
| Account aangemaakt, niet onboarded | Geel | "Onboarding afronden" knop |
| Volledig onboarded | Groen | "Ontkoppelen" knop |

### Stripe Direct (voor upgrade betalingen)

- Altijd naar JudoToernooi's Stripe account (zelfde als Mollie Platform mode)

### Stripe Database Velden

```sql
-- toernooien tabel (naast bestaande Mollie velden)
payment_provider              VARCHAR(20) DEFAULT 'mollie'  -- 'mollie' | 'stripe'
stripe_account_id             VARCHAR(255) NULL              -- acct_... van Account Links

-- Legacy velden (niet meer gebruikt door Account Links, bewaard voor compatibiliteit):
-- stripe_access_token, stripe_refresh_token, stripe_publishable_key

-- betalingen tabel
payment_provider              VARCHAR(20) DEFAULT 'mollie'
stripe_payment_id             VARCHAR(255) NULL

-- toernooi_betalingen tabel
payment_provider              VARCHAR(20) DEFAULT 'mollie'
stripe_payment_id             VARCHAR(255) NULL
```

### Stripe Routes

```php
GET  /stripe/callback                              → Return URL na Stripe onboarding
POST /stripe/webhook                               → Coach payment webhook
POST /stripe/webhook/toernooi                      → Upgrade payment webhook
GET  /{org}/toernooi/{toernooi}/stripe/authorize   → Start onboarding (maakt account + redirect)
POST /{org}/toernooi/{toernooi}/stripe/disconnect  → Disconnect
```

### Stripe Environment Variables

```env
STRIPE_KEY=           # pk_test_... of pk_live_...
STRIPE_SECRET=        # sk_test_... of sk_live_...
STRIPE_WEBHOOK_SECRET= # whsec_...
# Geen STRIPE_CLIENT_ID nodig — Account Links gebruikt alleen de secret key
```

---

