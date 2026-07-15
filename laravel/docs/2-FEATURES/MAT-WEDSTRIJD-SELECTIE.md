---
title: Mat Wedstrijd Selectie (Groen/Geel/Blauw Systeem)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Mat Wedstrijd Selectie (Groen/Geel/Blauw Systeem)

> **Index-doc:** wedstrijdselectie op mat-niveau met drie kleuren. Hieronder het probleem en de oplossing; de details staan in deeldocs onder `MAT-WEDSTRIJD-SELECTIE/` — zie [Waar staat wat](#waar-staat-wat).

## Probleem

Voorheen had elke **poule** een eigen selectie. Dit werkt niet goed wanneer meerdere poules op dezelfde mat staan:

- 3 poules op 1 mat = meerdere selecties mogelijk
- Fysiek kan er maar 1 wedstrijd tegelijk op de mat
- Verwarring bij mat-jury en toeschouwers

## Oplossing: Mat-niveau selectie met 3 kleuren

Selecties worden opgeslagen op **mat niveau**, niet op poule niveau. Er zijn 3 statussen:

| Kleur | Database veld | Betekenis | UI |
|-------|---------------|-----------|-----|
| **Groen** | `mat.actieve_wedstrijd_id` | Wedstrijd speelt NU | Groene achtergrond |
| **Geel** | `mat.volgende_wedstrijd_id` | Judoka's staan KLAAR | Gele achtergrond |
| **Blauw** | `mat.gereedmaken_wedstrijd_id` | Judoka's moeten GEREEDMAKEN | Blauwe achtergrond |
| **Neutraal** | NULL | Geen selectie | Witte achtergrond |

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---|---|
| [DATABASE-EN-INTERACTIE.md](MAT-WEDSTRIJD-SELECTIE/DATABASE-EN-INTERACTIE.md) | Je wilt weten welke kolommen de selectie opslaan, wat er gebeurt bij klikken/deselecteren/afronden, of wat een kleur betekent. |
| [POULE-VERPLAATSING.md](MAT-WEDSTRIJD-SELECTIE/POULE-VERPLAATSING.md) | Een poule gaat naar een andere mat, of wordt toegevoegd of verwijderd — wat gebeurt er met de selecties. |
| [CONTROLLER-LOGICA.md](MAT-WEDSTRIJD-SELECTIE/CONTROLLER-LOGICA.md) | Je raakt `MatController@setWedstrijdStatus` of de doorschuif-regels na deselectie aan. |
| [VIEW-UPDATES.md](MAT-WEDSTRIJD-SELECTIE/VIEW-UPDATES.md) | Je past de mat-interface (`_content.blade.php`), de selectie-logica in de view of de DB-migratie aan. |
| [TEST-SCENARIOS.md](MAT-WEDSTRIJD-SELECTIE/TEST-SCENARIOS.md) | Je test selectie, deselectie, doorschuiving, multi-poule of de eliminatie A/B-split. |
| [PLAATSBEPALING-STANDINGS.md](MAT-WEDSTRIJD-SELECTIE/PLAATSBEPALING-STANDINGS.md) | Je rekent aan punten, ranking-volgorde, afwezige judoka's of cirkel-resultaten. |
