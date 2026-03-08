# Judoka Database (Stambestand)

> **Status:** Deels gebouwd, uitbreiding nodig
> **Doel:** Organisatoren (judoscholen) beheren een permanente judoka database die hergebruikt wordt over toernooien heen

## Concept

- **Organisator = judoschool.** De judoka database is het stambestand van die ene judoschool
- Andere clubs die meedoen aan toernooien hebben NIETS met het stambestand te maken
- Het stambestand is per `organisator_id`, GEEN `club_id` nodig
- Organisatoren zonder judoschool gebruiken het stambestand niet

## Bestaand (al gebouwd)

| Component | Status | Bestanden |
|-----------|--------|-----------|
| `StamJudoka` model | Done | `app/Models/StamJudoka.php` |
| `stam_judokas` tabel | Done | Migratie `2026_02_17_200000` |
| `judokas.stam_judoka_id` FK | Done | Migratie `2026_02_17_200002` |
| CRUD beheer | Done | `StamJudokaController`, `organisator/stambestand/index.blade.php` |
| CSV/Excel import | Done | `StamJudokaController::importUpload/importConfirm` |
| Wimpel/punten koppeling | Done | `WimpelService::matchJudoka()` |
| Import naar toernooi | Done | `StambestandService::importNaarToernooi()` |
| Sync vanuit CSV import | Done | `StambestandService::syncVanuitImport()` |
| Dashboard link "Mijn Judoka's" | Done | `dashboard.blade.php` |
| Unique constraint | Done | `(organisator_id, naam, geboortejaar)` |

## Nieuw (te bouwen)

### 1. Coach Portal: "Kies uit database"

De coach van de **eigen club** van de organisator kan bij het aanmelden voor een toernooi judoka's kiezen uit het stambestand.

**Wanneer beschikbaar:**
- Alleen als de organisator een stambestand heeft (stam_judokas > 0)
- Alleen als het portaal op `volledig` modus staat (mag inschrijven)
- Inschrijving moet open zijn

**Flow:**
1. Coach opent judoka's pagina in portal
2. Naast "Judoka toevoegen" ziet coach een **"Kies uit database"** knop
3. Modal opent met zoekbare lijst van alle actieve StamJudoka's van de organisator
4. Judoka's die al in dit toernooi staan worden uitgegrijsd met label "Al aangemeld"
5. Coach selecteert judoka → velden worden ingevuld in het toevoeg-formulier
6. Coach kan gewicht/band aanpassen (kunnen veranderd zijn)
7. Opslaan → toernooi-judoka aangemaakt met `stam_judoka_id` koppeling

**API endpoint:**
- `GET /{org}/{toernooi}/coach/{code}/stambestand` → JSON lijst stam judoka's
- Gefilterd: actief, organisator scope
- Bevat `al_aangemeld: true/false` per judoka (check `stam_judoka_id` in toernooi)

### 2. Coach Portal: nieuwe judoka's → stambestand

Als een coach een **nieuwe** judoka handmatig toevoegt (niet uit database):
- Na opslaan: automatisch `StambestandService::syncVanuitImport()` aanroepen
- Judoka wordt toegevoegd aan stambestand als die er nog niet in staat
- `stam_judoka_id` wordt gezet op de toernooi-judoka

### 3. Puntencompetitie koppeling

Als een toernooi `plan_type = 'wimpel_abo'` (puntencompetitie):
- Bij deelnemers beheer: judoka's selecteren uit stambestand (i.p.v. handmatig/CSV import)
- Bestaand: `StambestandService::importNaarToernooi()` doet dit al
- Te doen: UI in toernooi deelnemers pagina om stam judoka's te selecteren en importeren

## Routes

| Route | Method | Controller | Beschrijving |
|-------|--------|-----------|-------------|
| `/{org}/{toernooi}/coach/{code}/stambestand` | GET | CoachPortalController | Stam judoka's lijst (JSON) |

## Bestaande routes (geen wijziging)

| Route | Beschrijving |
|-------|-------------|
| `/{slug}/judokas` | Stambestand beheer (CRUD) |
| `/{slug}/judokas/import` | CSV/Excel import |
| `/{slug}/wimpeltoernooi/*` | Wimpel/puntencompetitie |

## Regels

- Matching: `naam + geboortejaar` (niet gewicht/band — die veranderen)
- StamJudoka scope: altijd per `organisator_id`
- Gewicht en band in toernooi-judoka kunnen afwijken van stam (actuele waarden)
- Na toernooi: stam gewicht/band NIET automatisch updaten (bewuste keuze organisator)
- `StambestandService::importNaarToernooi()` pakt nu `$organisator->clubs()->first()` als club — dit is correct voor de eigen club
