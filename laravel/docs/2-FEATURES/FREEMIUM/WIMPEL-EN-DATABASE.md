---
title: Wimpel-abonnement & database-schema
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Wimpel-abonnement & database-schema

> Onderdeel van [Freemium Model](../FREEMIUM.md).

## Wimpel Abonnement

Naast per-toernooi staffels is er een **jaarabonnement** voor wimpeltoernooien (puntencompetitie).

| Aspect | Details |
|--------|---------|
| **Scope** | Per organisator (jaarabonnement) |
| **Toernooien** | Onbeperkt puntencompetitie toernooien |
| **Judoka limiet** | Onbeperkt |
| **Print/Noodplan** | Volledig beschikbaar |
| **Prijs** | ~€50/jaar (instelbaar per klant) |
| **Beperking** | Alleen puntencompetitie, geen poules/eliminatie |

### Activering
- Admin activeert via Klantenbeheer (`/admin/klanten/{id}/edit`)
- Start- en einddatum instelbaar, standaard 1 jaar
- Bij aanmaken toernooi: checkbox "Wimpel puntencompetitie" (alleen zichtbaar als abo actief)
- `plan_type = 'wimpel_abo'` op het toernooi

### Verloop
- Bestaande wimpel_abo toernooien **blijven werken** tot afsluiting
- Alleen nieuwe toernooi aanmaak wordt geblokkeerd bij verlopen abo
- Waarschuwing als abo <30 dagen resterend

### Database

```sql
-- organisatoren tabel
wimpel_abo_actief       BOOLEAN DEFAULT FALSE
wimpel_abo_start        DATE NULL
wimpel_abo_einde        DATE NULL
wimpel_abo_prijs        DECIMAL(8,2) NULL
wimpel_abo_notities     TEXT NULL
```

---

## Database Schema

### toernooien tabel

```sql
-- Freemium velden
plan_type               ENUM('free', 'paid', 'wimpel_abo') DEFAULT 'free'
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

