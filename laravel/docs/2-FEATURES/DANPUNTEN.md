# Danpunten

## Wat zijn danpunten?

Danpunten zijn gewonnen wedstrijden voor judoka's met een **bruine band**. Bij 10 gewonnen wedstrijden mag de judoka dan-examen doen met alleen worpen en grondtechnieken (zonder kata's). De gewonnen wedstrijden moeten doorgegeven worden aan de JBN.

## Doelgroep

- **Alleen judoka's met band = bruin**
- Lagere banden worden NIET bijgehouden

## Hoe werkt het?

### 1. Toernooi instelling

De organisator activeert danpunten per toernooi:

**Locatie:** Instellingen → Organisatie tab (of Basis tab)

```
┌─────────────────────────────────────────────────────────┐
│ Danpunten                                                │
├─────────────────────────────────────────────────────────┤
│ ☐ Danpunten registreren                                 │
│                                                         │
│ (Wanneer actief: JBN lidnummer wordt verplicht          │
│  voor judoka's met bruine band)                         │
└─────────────────────────────────────────────────────────┘
```

**Database:** `toernooien.danpunten_actief` (boolean, default false)

### 2. JBN lidnummer per judoka

Wanneer danpunten actief is, moet elke judoka met bruine band een JBN lidnummer hebben.

**Database:** `judokas.jbn_lidnummer` (string, nullable)

**Formaat:** Vrij tekstveld — oude nummers bevatten letters+cijfers (bijv. `D7GJ44V`, `D6KC35B`), nieuwe nummers zijn alleen cijfers (bijv. `703828`, `656298`).

**Waar invoeren:**
- Import (CSV kolom mapping)
- Coach portal (judoka bewerken)
- Organisator judoka bewerken
- Weging interface (optioneel)

**Validatie:** Wanneer `danpunten_actief = true` en `band = bruin`:
- JBN lidnummer is verplicht
- Waarschuwing tonen als het ontbreekt

### 3. Automatisch tellen

Het systeem telt **automatisch** gewonnen wedstrijden voor bruine banden:
- Poule wedstrijden: winnaar krijgt +1
- Eliminatie wedstrijden: winnaar krijgt +1
- Byes tellen NIET

**Geen aparte tabel nodig** — het aantal wordt berekend uit bestaande wedstrijdresultaten (query op `wedstrijden` waar judoka winnaar is).

### 4. JBN Export

Na afloop van het toernooi kan de organisator een export genereren met danpunten.

**Locatie:** Resultaten pagina → "Danpunten export" knop (alleen zichtbaar als `danpunten_actief = true`)

**Formaat:** CSV

**Velden:**

| Kolom | Bron |
|-------|------|
| Naam | `judokas.voornaam` + `judokas.achternaam` |
| JBN Lidnummer | `judokas.jbn_lidnummer` |
| Judoschool | `clubs.naam` |
| Toernooi | `toernooien.naam` |
| Toernooi datum | `toernooien.datum` |
| Aantal gewonnen wedstrijden | Berekend uit wedstrijdresultaten |

**Filter:** Alleen judoka's met `band = bruin` EN minimaal 1 gewonnen wedstrijd.

## Database wijzigingen

| Tabel | Veld | Type | Beschrijving |
|-------|------|------|--------------|
| `toernooien` | `danpunten_actief` | boolean (default false) | Danpunten aan/uit |
| `judokas` | `jbn_lidnummer` | string, nullable | JBN lidmaatschapsnummer |

## Schermen die aangepast worden

| Scherm | Wijziging |
|--------|-----------|
| **Toernooi instellingen** | Checkbox "Danpunten registreren" |
| **Import** | JBN lidnummer als mappable kolom |
| **Coach portal** | JBN lidnummer veld (bij bruine band) |
| **Judoka bewerken** | JBN lidnummer veld |
| **Resultaten** | "Danpunten export" knop |

## Niet in scope

- Bijhouden van totaal danpunten over meerdere toernooien (dat doet de JBN)
- Tegenstander informatie in export
- Automatisch doorsturen naar JBN (handmatige CSV export)
