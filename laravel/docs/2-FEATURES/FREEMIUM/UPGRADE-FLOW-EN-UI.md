---
title: Upgrade-flow & UI-componenten
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Upgrade-flow & UI-componenten

> Onderdeel van [Freemium Model](../FREEMIUM.md).

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
│ [Betalen met iDEAL | Wero]                                  │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. MOLLIE BETALING                                           │
├──────────────────────────────────────────────────────────────┤
│ - Platform mode (geld naar JudoToernooi)                    │
│ - iDEAL | Wero, creditcard, etc.                            │
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

