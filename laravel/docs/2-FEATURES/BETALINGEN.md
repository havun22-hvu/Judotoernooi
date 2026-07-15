---
title: Betalingen - Mollie & Stripe Integratie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Betalingen - Mollie & Stripe Integratie

> Online betalingen voor judoscholen bij inschrijving van judoka's.

> **Let op:** Dit document gaat over **inschrijfgeld** (coach betaalt aan organisator).
> Voor **toernooi upgrades** (organisator betaalt aan JudoToernooi), zie [FREEMIUM.md](./FREEMIUM.md).

> **Index-doc:** de kern (providers, modi, architectuur) staat hieronder; de details staan in de deeldocs onder [`BETALINGEN/`](./BETALINGEN/).

## Overzicht

JudoToernooi ondersteunt twee **betaalproviders** en twee **betalingsmodi** per provider:

### Providers

| Provider | Dekking | Methodes | Kosten |
|----------|---------|----------|--------|
| **Mollie** (standaard) | Europa | iDEAL | Wero, Bancontact, creditcard | €0,29 + 0% |
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

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [MODI.md](./BETALINGEN/MODI.md) | Je moet weten wat Connect vs Platform mode doet, wat de organisator moet koppelen, of welk instellingen-tabblad en DB-veld een keuze opslaat. |
| [FLOW-EN-WEBHOOK.md](./BETALINGEN/FLOW-EN-WEBHOOK.md) | Je werkt aan het afrekenen in het coach-portal, judoka-betaalstatussen, `MollieService`/`Toernooi`-methods, routes of de webhook-afhandeling. |
| [CONFIGURATIE-EN-TESTEN.md](./BETALINGEN/CONFIGURATIE-EN-TESTEN.md) | Je zet simulatie-mode op staging aan, zoekt een env var of Mollie-status, bekijkt het admin-factuuroverzicht/factuurnummers, of gaat betalingen testen. |
| [STRIPE.md](./BETALINGEN/STRIPE.md) | Je bouwt of debugt iets Stripe-specifieks: Checkout, Stripe Connect voor inschrijfgeld, Stripe Direct voor upgrades, de Stripe-velden, -routes of -env vars. |

## Referenties

- [Mollie API Docs](https://docs.mollie.com/)
- [Mollie Connect](https://docs.mollie.com/connect/overview)
- [Stripe Checkout Docs](https://stripe.com/docs/payments/checkout)
- [Stripe Connect](https://stripe.com/docs/connect)
- HavunCore Mollie Pattern: `HavunCore/docs/kb/patterns/mollie-payments.md`
