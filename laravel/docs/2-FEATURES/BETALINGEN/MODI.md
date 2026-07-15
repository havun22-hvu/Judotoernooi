---
title: Connect & Platform mode, toernooi-instellingen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Connect & Platform mode, toernooi-instellingen

> Onderdeel van [Betalingen](../BETALINGEN.md).

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
│ 3. Redirect naar Mollie (iDEAL | Wero, etc.)                │
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

