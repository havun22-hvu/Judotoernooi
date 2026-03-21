# Club Stambestand & Wimpeltoernooi Integratie

> **Status:** Ontwerp (fase 1)
> **Doel:** Elke club bouwt een eigen judoka-database op, herbruikbaar voor eigen toernooien en wimpelcompetities.

---

## Probleemstelling

### Huidige situatie
- Judoka's leven **per toernooi** — bij elk toernooi opnieuw invoeren/importeren
- Clubs zijn persistent per organisator, maar hun judoka's niet
- `wimpel_judokas` is een los mini-bestand (alleen naam + geboortejaar + punten)
- Geen hergebruik van judoka-gegevens tussen toernooien

### Gewenste situatie
- Club heeft **één stambestand** met alle judoka's (persistent)
- Bij nieuw toernooi: selecteer uit stambestand → klaar
- Wimpelpunten gekoppeld aan stambestand (niet apart)
- Later (fase 2): portal-inschrijving ook vanuit stambestand

---

## Fasering

| Fase | Wat | Wanneer |
|------|-----|---------|
| **1** | Club-account + stambestand + wimpelkoppeling | Dit ontwerp |
| **2** | Portal vullen vanuit stambestand | Later |
| **3** | Judoka's eigen gewicht bijhouden, leraar beheert banden | Later |

---

## Architectuur

### Kernprincipe

**Een club die zich registreert IS een organisator.** Geen nieuw account-type. Het bestaande organisator-systeem wordt hergebruikt.

```
Judoschool "De Jagers" registreert zich
         │
         ▼
    Organisator account (bestaand systeem)
         │
         ├── Eigen club (bestaand, via ensureOrganisatorClubExists)
         │
         ├── STAMBESTAND ← NIEUW
         │     Piet  (2014, M, oranje, 34kg)
         │     Jan   (2011, M, groene, 52kg)
         │     Klaas (2013, M, gele, 30kg)
         │
         ├── Wimpelcompetitie (bestaand, nu gekoppeld aan stambestand)
         │     Piet: 12 punten (groene wimpel bereikt!)
         │     Jan: 8 punten
         │
         ├── Wimpeltoernooi maart → selectie: Piet, Jan, Klaas
         └── Wimpeltoernooi juni → selectie: Piet, Klaas
```

### Betalingsmodel

Sluit aan op bestaand freemium model (per toernooi):

| Situatie | Prijs | Reden |
|----------|-------|-------|
| Toernooi ≤ 50 judoka's | **Gratis** | Bestaande free tier |
| Toernooi > 50 judoka's | **Vanaf €20** | Bestaande staffels |
| Stambestand bijhouden | **Gratis** | Geen limiet op stambestand |

Het stambestand zelf is gratis en onbeperkt. Betaling is alleen bij toernooien met >50 deelnemers — exact zoals nu.

---

## Database Ontwerp

### Nieuwe tabel: `stam_judokas`

Het centrale stambestand per organisator (= per club).

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | bigint PK | |
| `organisator_id` | bigint FK | Eigenaar (de club-als-organisator) |
| `naam` | varchar(255) | Volledige naam |
| `geboortejaar` | year | |
| `geslacht` | char(1) | M / V |
| `band` | varchar(20) | Huidige bandkleur |
| `gewicht` | decimal(4,1) | Laatst bekende gewicht |
| `notities` | text nullable | Vrij veld voor leraar |
| `actief` | boolean default true | false = gestopt, uit selectielijsten |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Unique constraint:** `[organisator_id, naam, geboortejaar]`

> **Geen `club_id`** — het stambestand hoort bij de organisator (= de club zelf).
> De club is al impliciet via `ensureOrganisatorClubExists()`.

### Wijziging: `wimpel_judokas` koppelen aan stambestand

De bestaande `wimpel_judokas` tabel krijgt een FK naar `stam_judokas`:

| Kolom | Wijziging |
|-------|-----------|
| `stam_judoka_id` | **NIEUW** — FK naar `stam_judokas` (nullable, voor migratie) |
| `naam` | Blijft (voor weergave / fallback) |
| `geboortejaar` | Blijft (voor weergave / fallback) |

**Koppellogica:**
- Bij nieuwe wimpel-judoka: automatisch `stam_judoka_id` vullen (match op naam+geboortejaar)
- Als stam-judoka niet bestaat: automatisch aanmaken in stambestand
- Bestaande wimpel_judokas zonder stam_judoka_id: migratie-script koppelt ze

### Wijziging: `judokas` tabel (toernooi-judoka's)

Toernooi-judoka's krijgen een optionele link terug naar het stambestand:

| Kolom | Wijziging |
|-------|-----------|
| `stam_judoka_id` | **NIEUW** — FK naar `stam_judokas` (nullable) |

**Doel:** Na afloop van een toernooi kan het stambestand bijgewerkt worden (bijv. gewogen gewicht → stambestand). Dit is fase 3 functionaliteit maar de FK leggen we nu al.

---

## Flows

### Flow 1: Stambestand opbouwen

```
Dashboard → "Mijn judoka's" (nieuwe pagina)
│
├── Tabel met alle stam-judoka's
│   ├── Kolommen: naam, geboortejaar, geslacht, band, gewicht, actief
│   ├── Sorteerbaar op alle kolommen
│   ├── Zoekfunctie
│   └── Filter: actief / inactief / alle
│
├── Acties:
│   ├── + Judoka toevoegen (formulier)
│   ├── Bewerken (inline of modal)
│   ├── Archiveren (actief → inactief, NIET verwijderen)
│   └── CSV import (hergebruik bestaande import-logica)
│
└── Statistieken:
    ├── Totaal judoka's (actief)
    └── Per band verdeling (visueel)
```

**Route:** `/{slug}/judokas` (organisator-level, niet toernooi-level)

### Flow 2: Toernooi aanmaken vanuit stambestand

```
1. Organisator maakt nieuw toernooi aan (bestaande flow)
2. "Judoka's toevoegen" → tabblad-keuze:
   │
   ├── [Uit stambestand] ← NIEUW
   │   ├── Checkboxes per judoka
   │   ├── "Selecteer alle actieve" optie
   │   ├── Gewicht/band overgenomen uit stambestand
   │   ├── "Kopieer naar toernooi" knop
   │   └── judokas records aangemaakt MET stam_judoka_id
   │
   ├── [CSV import] (bestaand)
   │   └── Na import: match met stambestand op naam+geboortejaar
   │       └── Nieuwe judoka's automatisch toevoegen aan stambestand
   │
   └── [Handmatig] (bestaand)
       └── Na opslaan: aanbieden om toe te voegen aan stambestand
```

### Flow 3: Wimpeltoernooi met stambestand

```
1. Toernooi type = puntencompetitie (bestaand)
2. Judoka's selecteren uit stambestand (flow 2)
3. Poules draaien → punten worden bijgeschreven (bestaand)
4. Punten gekoppeld via stam_judoka_id:
   │
   │  toernooi-judoka (judokas tabel)
   │       │ stam_judoka_id
   │       ▼
   │  stam_judoka (stam_judokas tabel)
   │       │ id
   │       ▼
   │  wimpel_judoka (wimpel_judokas tabel)
   │       └── punten_totaal bijgewerkt
   │
5. Milestone bereikt → spreker notificatie (bestaand)
```

**Voordeel:** Geen dubbele herkenning meer nodig op naam+geboortejaar — de koppeling gaat via `stam_judoka_id`.

---

## Nieuwe Componenten

### Model: `StamJudoka`

```
StamJudoka
  ├── belongsTo(Organisator)
  ├── hasMany(Judoka)           // toernooi-kopieën
  ├── hasOne(WimpelJudoka)      // wimpelpunten
  └── scopeActief()             // where actief = true
```

### Controller: `StamJudokaController`

| Method | Route | Beschrijving |
|--------|-------|--------------|
| `index` | `GET /{slug}/judokas` | Overzicht stambestand |
| `store` | `POST /{slug}/judokas` | Judoka toevoegen |
| `update` | `PUT /{slug}/judokas/{id}` | Judoka bewerken |
| `toggleActief` | `POST /{slug}/judokas/{id}/toggle` | Archiveren/activeren |
| `importCsv` | `POST /{slug}/judokas/import` | CSV import |
| `selectieVoorToernooi` | `GET /{slug}/toernooi/{t}/selectie` | Selectie UI |
| `importVanuitStam` | `POST /{slug}/toernooi/{t}/import-stam` | Selectie uitvoeren |

### Views

| View | Beschrijving |
|------|--------------|
| `pages/stambestand/index.blade.php` | Overzicht + CRUD |
| `pages/stambestand/import.blade.php` | CSV import |
| `pages/toernooi/selectie-stambestand.blade.php` | Toernooi selectie |
| `components/stambestand-selector.blade.php` | Herbruikbare selectie component |

### Service: `StambestandService`

| Method | Beschrijving |
|--------|--------------|
| `importNaarToernooi($stamJudokaIds, $toernooi)` | Kopieer selectie naar toernooi |
| `syncVanuitImport($judoka, $organisator)` | Na CSV import: match/maak stam-judoka |
| `koppelWimpelJudoka($stamJudoka)` | Koppel bestaande wimpel-record |
| `migratieBestaandeWimpelJudokas($organisator)` | Eenmalige koppeling |

---

## Wat verandert er

| Component | Wijziging | Impact |
|-----------|-----------|--------|
| **Database** | Nieuwe `stam_judokas` tabel | Migratie |
| **Database** | `wimpel_judokas.stam_judoka_id` FK toevoegen | Migratie |
| **Database** | `judokas.stam_judoka_id` FK toevoegen | Migratie |
| **Model** | Nieuw `StamJudoka` model | Nieuw bestand |
| **Model** | `WimpelJudoka` → relatie naar `StamJudoka` | Kleine wijziging |
| **Model** | `Judoka` → optionele relatie naar `StamJudoka` | Kleine wijziging |
| **Controller** | Nieuw `StamJudokaController` | Nieuw bestand |
| **Views** | Stambestand pagina + selectie component | Nieuwe views |
| **Views** | Dashboard: "Mijn judoka's" link toevoegen | Kleine wijziging |
| **Routes** | Stambestand routes toevoegen | `web.php` |
| **Import** | Na CSV import: sync met stambestand | `ImportService` wijziging |
| **Wimpel** | Koppeling via `stam_judoka_id` ipv naam-match | `WimpelService` wijziging |

## Wat verandert er NIET

- Organisator registratie/login
- Toernooi aanmaak flow (alleen extra tab bij judoka's toevoegen)
- Mat/poule/eliminatie systeem
- Coach portal (fase 2)
- Betalingsmodel (freemium staffels blijven gelijk)
- Bestaande CSV import (blijft werken, wordt aangevuld met stam-sync)

---

## Migratie-strategie

### Voor bestaande organisatoren

1. **Automatisch stambestand opbouwen** uit bestaande toernooi-judoka's:
   - Unieke combinaties van `naam + geboortejaar` verzamelen
   - Meest recente `geslacht`, `band`, `gewicht` overnemen
   - `stam_judokas` records aanmaken

2. **Wimpel-judoka's koppelen:**
   - Match `wimpel_judokas` op `naam + geboortejaar` met `stam_judokas`
   - `stam_judoka_id` vullen

3. **Geen data verlies** — bestaande `wimpel_judokas` velden (naam, geboortejaar) blijven als fallback

---

## Vooruitblik: Fase 2 (Portal-koppeling)

De link tussen portal en stambestand wordt simpel doordat alles binnen JudoToernooi leeft:

```
Club ontvangt uitnodiging voor extern toernooi
         │
         ▼
Coach opent portal → "Importeer uit mijn club"
         │
         ▼
Login met club-account (= organisator credentials)
         │
         ▼
Selecteer judoka's uit stambestand
         │
         ▼
Benodigde velden gekopieerd naar toernooi van andere organisator
```

**Geen API nodig** — interne database query met cross-organisator autorisatie.

---

## Vooruitblik: Fase 3 (Judoka self-service)

- Judoka's kunnen via PWA hun eigen gewicht bijhouden
- Leraar/coach beheert banden in stambestand
- Na weging op toernooi: optie om gewogen gewicht terug te syncen naar stambestand
- `stam_judoka_id` FK op `judokas` maakt deze terug-sync mogelijk

---

## Beslissingen

1. **Dashboard navigatie:** Top-level menu item, naast wimpeltoernooi
2. **Stambestand CSV import:** Zelfde formaat als toernooi-import
3. **Verwijderen:** Echt verwijderen is mogelijk (geen soft-delete/archiveren)
4. **Meerdere clubs per organisator:** Stambestand geldt alleen voor de eigen club (via `ensureOrganisatorClubExists`)
