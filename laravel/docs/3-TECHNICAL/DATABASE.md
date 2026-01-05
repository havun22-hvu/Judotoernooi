# Database Schema

## Entity Relationship Diagram

```
┌─────────────────┐
│   toernooien    │
├─────────────────┤
│ id              │
│ naam            │
│ organisatie     │
│ datum           │◄───────────────────────────────┐
│ locatie         │                                │
│ aantal_matten   │                                │
│ aantal_blokken  │                                │
│ ...             │                                │
└─────────────────┘                                │
        │                                          │
        │ 1:N                                      │
        ▼                                          │
┌─────────────────┐      ┌─────────────────┐      │
│    blokken      │      │     matten      │      │
├─────────────────┤      ├─────────────────┤      │
│ id              │      │ id              │      │
│ toernooi_id   ──┼──────│ toernooi_id   ──┼──────┤
│ nummer          │      │ nummer          │      │
│ starttijd       │      │ naam            │      │
│ weging_gesloten │      │ kleur           │      │
└─────────────────┘      └─────────────────┘      │
        │                        │                │
        │ 1:N                    │ 1:N            │
        ▼                        ▼                │
┌─────────────────────────────────────────┐      │
│                poules                    │      │
├─────────────────────────────────────────┤      │
│ id                                       │      │
│ toernooi_id                            ──┼──────┘
│ blok_id                                ──┼──────┐
│ mat_id                                 ──┼──────┤
│ nummer                                   │      │
│ titel                                    │      │
│ leeftijdsklasse                         │      │
│ gewichtsklasse                          │      │
│ aantal_judokas                          │      │
│ aantal_wedstrijden                      │      │
└─────────────────────────────────────────┘      │
        │                                         │
        │ N:M (via poule_judoka)                 │
        ▼                                         │
┌─────────────────┐                              │
│    judokas      │                              │
├─────────────────┤                              │
│ id              │                              │
│ toernooi_id   ──┼──────────────────────────────┘
│ club_id       ──┼──────┐
│ naam            │      │
│ geboortejaar    │      │
│ geslacht        │      │
│ band            │      │
│ gewicht         │      │
│ leeftijdsklasse │      │
│ gewichtsklasse  │      │
│ judoka_code     │      │
│ aanwezigheid    │      │
│ gewicht_gewogen │      │
│ qr_code         │      │
└─────────────────┘      │
        │                │
        │ N:1            │
        ▼                │
┌─────────────────┐      │
│     clubs       │◄─────┘
├─────────────────┤
│ id              │
│ naam            │
│ afkorting       │
│ plaats          │
└─────────────────┘
```

## Tabellen

### toernooien

Hoofdtabel voor toernooien.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| naam | varchar(255) | Naam van het toernooi |
| organisatie | varchar(255) | Organiserende club |
| datum | date | Datum van het toernooi |
| locatie | varchar(255) | Locatie |
| aantal_matten | tinyint | Aantal beschikbare matten |
| aantal_blokken | tinyint | Aantal tijdsblokken |
| min_judokas_poule | tinyint | Minimum judoka's per poule |
| optimal_judokas_poule | tinyint | Optimaal aantal per poule |
| max_judokas_poule | tinyint | Maximum judoka's per poule |
| gewicht_tolerantie | decimal(3,1) | Tolerantie in kg |
| is_actief | boolean | Actief toernooi |
| poules_gegenereerd_op | timestamp | Timestamp poule generatie |
| blokken_verdeeld_op | timestamp | Timestamp blok verdeling |
| judoka_code_volgorde | varchar(20) | Sorteervolgorde: gewicht_band of band_gewicht |
| max_kg_verschil | decimal(3,1) | Max kg verschil dynamische indeling (default 3.0) |
| max_leeftijd_verschil | tinyint | Max leeftijd verschil dynamische indeling (default 2) |
| gebruik_gewichtsklassen | boolean | Gebruik vaste gewichtsklassen |
| gewichtsklassen | json | Configuratie per leeftijdsgroep |

### gewichtsklassen_presets

Opgeslagen preset configuraties per organisator.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| organisator_id | bigint | FK naar organisators |
| naam | varchar(100) | Naam van de preset |
| configuratie | json | Gewichtsklassen configuratie |
| created_at | timestamp | Aangemaakt op |
| updated_at | timestamp | Gewijzigd op |

**Unique constraint:** `[organisator_id, naam]`

### clubs

Judo clubs/verenigingen.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| naam | varchar(255) | Naam van de club |
| afkorting | varchar(10) | Korte naam |
| plaats | varchar(255) | Vestigingsplaats |
| email | varchar(255) | Contact email |

### judokas

Deelnemende judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| club_id | bigint | FK naar clubs |
| naam | varchar(255) | Volledige naam |
| geboortejaar | year | Geboortejaar |
| geslacht | char(1) | M of V |
| band | varchar(20) | Bandkleur |
| gewicht | decimal(4,1) | Opgegeven gewicht |
| leeftijdsklasse | varchar(20) | Berekende klasse |
| gewichtsklasse | varchar(10) | Berekende klasse |
| judoka_code | varchar(20) | Unieke code |
| aanwezigheid | varchar(20) | Status |
| gewicht_gewogen | decimal(4,1) | Gemeten gewicht |
| qr_code | varchar(50) | QR code identifier |

### poules

Poules met judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| blok_id | bigint | FK naar blokken |
| mat_id | bigint | FK naar matten |
| nummer | int | Poulenummer |
| titel | varchar(255) | Beschrijvende titel |
| leeftijdsklasse | varchar(20) | Leeftijdsklasse |
| gewichtsklasse | varchar(10) | Gewichtsklasse |
| aantal_judokas | tinyint | Cached count |
| aantal_wedstrijden | smallint | Cached count |
| spreker_klaar | timestamp | Tijdstip klaar voor spreker |
| afgeroepen_at | timestamp | Tijdstip prijzen uitgereikt |

### poule_judoka (pivot)

Koppeltabel poules ↔ judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| poule_id | bigint | FK naar poules |
| judoka_id | bigint | FK naar judokas |
| positie | tinyint | Volgorde in poule |
| punten | tinyint | Totaal punten |
| gewonnen | tinyint | Aantal gewonnen |
| verloren | tinyint | Aantal verloren |
| gelijk | tinyint | Aantal gelijk |
| eindpositie | tinyint | Eindrangschikking |

### wedstrijden

Individuele wedstrijden.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| poule_id | bigint | FK naar poules |
| judoka_wit_id | bigint | FK naar judokas |
| judoka_blauw_id | bigint | FK naar judokas |
| volgorde | tinyint | Wedstrijdnummer |
| winnaar_id | bigint | FK naar judokas |
| score_wit | varchar(20) | Score witte judoka |
| score_blauw | varchar(20) | Score blauwe judoka |
| uitslag_type | varchar(20) | Type uitslag |
| is_gespeeld | boolean | Wedstrijd afgerond |
| gespeeld_op | timestamp | Tijdstip |

### wegingen

Wegingsregistraties.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| judoka_id | bigint | FK naar judokas |
| gewicht | decimal(4,1) | Gemeten gewicht |
| binnen_klasse | boolean | Binnen gewichtsklasse |
| alternatieve_poule | varchar(255) | Suggestie |
| opmerking | varchar(255) | Opmerking |
| geregistreerd_door | varchar(255) | Registrant |
