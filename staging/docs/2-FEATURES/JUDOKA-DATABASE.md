# Judoka Database (Stambestand)

> **Status:** Deels gebouwd, UI uitbreiding nodig
> **Doel:** Organisatoren (judoscholen) beheren een permanente judoka database die hergebruikt wordt over eigen toernooien

## Concept

- **Organisator = judoschool.** De judoka database is het stambestand van die ene judoschool
- Het stambestand is per `organisator_id`
- Bij eigen toernooien of puntencompetitie: judoka's importeren uit stambestand
- Coach portal (externe clubs) heeft GEEN toegang tot het stambestand

## Bestaand (al gebouwd)

| Component | Bestanden |
|-----------|-----------|
| `StamJudoka` model + tabel | `app/Models/StamJudoka.php`, migratie `2026_02_17_200000` |
| `judokas.stam_judoka_id` FK | Migratie `2026_02_17_200002` |
| CRUD beheer `/{slug}/judokas` | `StamJudokaController`, `organisator/stambestand/index.blade.php` |
| CSV/Excel import stambestand | `StamJudokaController::importUpload/importConfirm` |
| Wimpel/punten koppeling | `WimpelService::matchJudoka()` |
| Import stam → toernooi (backend) | `StambestandService::importNaarToernooi()` |
| Sync toernooi → stam (backend) | `StambestandService::syncVanuitImport()` |
| Dashboard link "Mijn Judoka's" | `dashboard.blade.php` |

## Nieuw (te bouwen)

### UI: Importeer uit stambestand bij toernooi deelnemersbeheer

De organisator kan bij het beheren van deelnemers voor een toernooi judoka's selecteren uit zijn stambestand.

**Waar:** Toernooi deelnemers pagina (naast bestaande CSV import en handmatig toevoegen)

**Flow:**
1. Organisator opent deelnemers van een toernooi
2. Knop "Importeer uit database" (naast bestaande import/toevoeg opties)
3. Modal/lijst toont alle actieve StamJudoka's van de organisator
4. Judoka's die al in dit toernooi staan worden uitgegrijsd ("Al aangemeld")
5. Organisator vinkt judoka's aan → klik "Importeer"
6. `StambestandService::importNaarToernooi()` wordt aangeroepen
7. Toernooi-judoka's aangemaakt met `stam_judoka_id` koppeling

**Geldt voor:**
- Reguliere toernooien
- Puntencompetitie (`plan_type = 'wimpel_abo'`)

## Regels

- Matching: `naam + geboortejaar` (niet gewicht/band — die veranderen)
- StamJudoka scope: altijd per `organisator_id`
- Gewicht en band in toernooi-judoka kunnen afwijken van stam (actuele waarden)
- Na toernooi: stam gewicht/band NIET automatisch updaten (bewuste keuze organisator)
