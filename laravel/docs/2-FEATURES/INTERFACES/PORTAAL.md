---
title: Budoschool Portaal Instellingen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Budoschool Portaal Instellingen

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Budoschool Portaal Instellingen

### Portaal modus (Instellingen → Organisatie)

| Modus | Nieuwe judoka's | Mutaties/wijzigen | Bekijken |
|-------|-----------------|-------------------|----------|
| **UIT** | ❌ | ❌ | ✅ |
| **Alleen mutaties** | ❌ | ✅ | ✅ |
| **Volledig** | ✅ | ✅ | ✅ |

### Wanneer welke modus?

| Modus | Gebruik |
|-------|---------|
| **UIT** | Organisator beheert alles (CSV import of handmatig) |
| **Alleen mutaties** | Inschrijving extern, clubs kunnen gewicht/band/etc wijzigen via portaal |
| **Volledig** | Clubs schrijven zelf in én kunnen wijzigen |

### UI Flow (Instellingen → Organisatie)

```
┌─────────────────────────────────────────────────────────┐
│ Inschrijving & Portaal                                  │
├─────────────────────────────────────────────────────────┤
│ Portaal modus:  [Volledig           ▼]                  │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ ☐ Betalingen via Mollie                             │ │
│ │   (alleen zichtbaar bij "Volledig")                 │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Combinatie met Mollie

| Portaal | Mollie checkbox | Scenario |
|---------|-----------------|----------|
| UIT | (verborgen) | Organisator doet alles zelf |
| Alleen mutaties | (verborgen) | Extern inschrijven, mutaties via portaal |
| Volledig | ☐ uit | Inschrijven via portaal, betaling extern (contant/factuur) |
| Volledig | ☑ aan | Volledig via ons systeem (portaal + Mollie) |

> **Let op:** Mollie checkbox alleen zichtbaar bij "Volledig" modus.

### Altijd beschikbaar (passief/read-only)

- **Budoschool portaal**: Overzicht ingeschreven judoka's (alleen lezen)
- **Publieke site**: Overzicht deelnemers per categorie

### Handmatige invoer (organisator)

Organisator kan ALTIJD zelf judoka's toevoegen via:
- **Judoka lijst** → "Judoka toevoegen" knop (nieuw)
- **CSV/Excel import** (bestaand)

### Database veld

**toernooien tabel:**
```
portaal_modus    ENUM('uit', 'mutaties', 'volledig') DEFAULT 'uit'
```

> **Mollie velden:** Zie `BETALINGEN.md` voor `betaling_actief` etc.

#### Tab: Test
- Test/debug functies voor development

---

