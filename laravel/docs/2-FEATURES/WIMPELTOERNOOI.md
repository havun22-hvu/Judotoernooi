# Wimpeltoernooi (Doorlopend Puntensysteem)

## Wat is het?

Een doorlopend puntensysteem per organisator, waarbij judoka's over meerdere **puntencompetitie** toernooien punten sparen. Bij het bereiken van milestones (bijv. 10, 20, 30 punten) ontvangt de judoka een prijsje.

## Scope

- **Alleen puntencompetitie** toernooien tellen mee
- **Per organisator** — elke organisator heeft zijn eigen wimpeltoernooi-database
- **1 punt per gewonnen wedstrijd** (conform wimpelcompetitie regels)

## Judoka Herkenning

- Judoka's worden herkend op **naam + geboortejaar**
- Bij dubbele naam+geboortejaar combinatie → **waarschuwing** aan organisator
- Organisator maakt zelf onderscheid (bijv. toevoeging bij naam)
- Judoka's worden automatisch toegevoegd bij eerste deelname

## Functionaliteit

### Dashboard
- **Wimpeltoernooi** link op het organisator dashboard
- Overzicht van alle judoka's met hun puntentotaal
- Zoekfunctie om judoka's snel te vinden
- Waarschuwingen voor judoka's die een milestone bereiken

### Punten bijschrijven
- **Automatisch per poule** — zodra een poule klaar is op de mat (spreker_klaar) worden punten direct bijgeschreven
- **Bulk verwerking** — bij toernooi afsluiten worden alle nog niet-verwerkte poules verwerkt
- **Handmatig vanuit wimpeltoernooi pagina** — via "Punten bijschrijven" voor onverwerkte toernooien
- 1 punt per gewonnen wedstrijd wordt bijgeschreven
- **Handmatig aanpasbaar** door organisator (voor bestaande standen én foutcorrecties)
- Nieuwe judoka's worden gemarkeerd met **NIEUW** badge zodat eventuele oude punten erbij gezet kunnen worden
- Organisator kan judoka bevestigen (badge verwijderen) of badge verdwijnt automatisch bij handmatige puntenaanpassing

### Milestone Waarschuwingen
- Bij het bereiken van een geconfigureerd puntenaantal → melding
- Melding toont welk prijsje de judoka moet ontvangen
- Organisator kan milestones zelf configureren

### Prijsjes Configuratie
- Apart instelbaar bij wimpeltoernooi instellingen
- Per milestone: puntenaantal + omschrijving prijsje
- Voorbeelden: beeldje, wimpeltje, kleur bandje
- Standaard milestones: 10, 20, 30, 40, 50 (aanpasbaar)

### Export / Backup
- Download knop op de **instellingen** pagina (Excel of CSV)
- Inhoud: naam, geboortejaar, totaalpunten + dynamische kolommen per toernooi-datum
- Kolom "Handmatig" verschijnt alleen als er handmatige aanpassingen zijn
- Bestandsnaam: `wimpel_{slug}_{datum}.xlsx/csv`
- Code: `app/Exports/WimpelExport.php`

## Workflow

```
1. Organisator configureert milestones + prijsjes (eenmalig)
2. Optioneel: handmatig bestaande standen invoeren
3. Toernooi draaien → punten worden per poule automatisch bijgeschreven
4. Nieuwe judoka's controleren (NIEUW badge) en eventueel oude punten bijschrijven
5. Dashboard toont waarschuwingen bij milestones
6. Organisator reikt prijsjes uit
```

## Data Model

### wimpel_judokas (per organisator)
| Veld | Type | Beschrijving |
|------|------|--------------|
| id | int | PK |
| organisator_id | int | FK naar organisatoren |
| naam | string | Naam judoka |
| geboortejaar | int | Geboortejaar |
| punten_totaal | int | Actueel puntentotaal |
| is_nieuw | boolean | Nieuw toegevoegd (default true), organisator kan bevestigen |
| created_at | timestamp | Eerste deelname |

### wimpel_punten_log (audit trail)
| Veld | Type | Beschrijving |
|------|------|--------------|
| id | int | PK |
| wimpel_judoka_id | int | FK naar wimpel_judokas |
| toernooi_id | int | FK (nullable, null bij handmatig) |
| poule_id | int | FK (nullable, voor per-poule bijhouden) |
| punten | int | Aantal punten (+/-) |
| type | enum | 'automatisch' / 'handmatig' |
| notitie | string | Optionele toelichting |
| created_at | timestamp | Wanneer bijgeschreven |

### wimpel_milestones (configuratie)
| Veld | Type | Beschrijving |
|------|------|--------------|
| id | int | PK |
| organisator_id | int | FK naar organisatoren |
| punten | int | Bij hoeveel punten |
| omschrijving | string | Welk prijsje |
| volgorde | int | Sortering |

## UI Locaties

| Pagina | Beschrijving |
|--------|-------------|
| `/{slug}/dashboard` | Link naar wimpeltoernooi |
| `/{slug}/wimpeltoernooi` | Overzicht judoka's + punten bijschrijven |
| `/{slug}/wimpeltoernooi/instellingen` | Milestones + prijsjes configuratie |
| `/{slug}/wimpeltoernooi/{id}` | Detail judoka met puntenhistorie + handmatige aanpassing |
| `/{slug}/wimpeltoernooi/export/{format}` | Export (xlsx/csv) |
