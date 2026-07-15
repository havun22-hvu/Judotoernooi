---
title: B-groep op aparte mat en poule-weergave
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# B-groep op aparte mat en poule-weergave

> Onderdeel van [Eliminatie Systeem](./README.md).

## B-groep op Aparte Mat

Eliminatie poules kunnen de B-groep (herkansing) op een **andere mat** draaien dan de A-groep (hoofdboom). Dit maakt parallel spelen mogelijk.

### Database

| Veld | Tabel | Beschrijving |
|------|-------|--------------|
| `mat_id` | poules | Mat voor A-groep (of beide als geen split) |
| `b_mat_id` | poules | Mat voor B-groep (nullable, FK naar matten) |

### Werking

- **Zaaloverzicht**: Eliminatie poule toont ALTIJD 2 entries: `#N - A` en `#N - B`
- **Standaard**: `b_mat_id = mat_id` (beide groepen op zelfde mat)
- **Split**: Sleep B-chip in zaaloverzicht naar andere mat → `b_mat_id` wordt geüpdatet
- **Mat interface**: Toont alleen relevante groep (`groep_filter` = A, B of null)
- **Aan de beurt**: A+B op zelfde mat → 1 groen/geel/blauw. Gesplitst → apart per mat
- **Afronden**: Pas na ALLE wedstrijden (A+B) klaar, broadcast naar beide mats

### Bestanden

| Bestand | Wijziging |
|---------|-----------|
| `BlokMatVerdelingService::getZaalOverzicht()` | Split A/B entries per mat |
| `BlokMatVerdelingService::verdeelOverMatten()` | Default `b_mat_id = mat_id` |
| `BlokController::verplaatsPoule()` | `groep` parameter (A/B) |
| `WedstrijdSchemaService::getSchemaVoorMat()` | Query op `b_mat_id`, `groep_filter` |
| `MatController::doPouleKlaar()` | Cross-mat check + dual broadcast |
| `zaaloverzicht.blade.php` | A/B chips + drag-drop |
| `_content.blade.php` | Tab filtering op `groep_filter` |

## Poule Weergave per Pagina

Welke poules worden waar getoond, afhankelijk van type en vulling:

| Type poule | Judoka's | Blokverdeling | Zaaloverzicht | Wedstrijddag |
|------------|----------|---------------|---------------|--------------|
| **Normaal** | >0 | Ja | Ja | Ja |
| **Normaal** | 0 | Nee | Nee | Ja* |
| **Eliminatie** | >0 | Ja (geheel) | Ja (A+B split) | Ja |
| **Eliminatie** | 0 | Nee | Nee | Ja* |
| **Kruisfinale** | virtueel | Ja | Ja | Ja |

*\* Lege poules bij vaste gewichtsklassen blijven op wedstrijddag zichtbaar voor overpoulen*

**Eliminatie A/B split** → altijd in zaaloverzicht (2 chips), altijd in wedstrijddag (2 tabs of 1 tab per mat). Niet in blokverdeling (geheel).

### Filter logica

| Locatie | Filter | Bestand |
|---------|--------|---------|
| Service (data) | `judokas > 1 \|\| type === 'kruisfinale'` | `BlokMatVerdelingService:1025` |
| Zaaloverzicht chips | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:63` |
| Zaaloverzicht matten | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:199` |
| Poule.updateStatistieken | Bewaar virtueel count alleen voor `kruisfinale` | `Poule.php:222` |

