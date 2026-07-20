---
title: Gewicht en geslacht verplicht — alle invoerpaden
type: reference
scope: judotoernooi
last_check: 2026-07-21
---

# Gewicht en geslacht verplicht

**Kern:** een judoka zonder gewicht kan niet worden ingedeeld, en **niet elk toernooi heeft een
weging** — er is dus geen later moment dat het gat vult. Daarom eist elk invoerpad een gewicht, en
sluit de indeling zelf judoka's zonder gewicht uit als vangnet.

Hetzelfde geldt voor `geslacht`: gewichtsklasse-bepaling én poule-indeling zijn geslachtsafhankelijk.

## Waar het wordt afgedwongen

| Pad | Bestand | Gedrag |
|-----|---------|--------|
| Laatkomer op de mat | `WedstrijddagMobielController::nieuweJudoka()` | 422 |
| Handmatig aanmaken | `JudokaStoreRequest` | 422 |
| Handmatig bewerken | `JudokaUpdateRequest` | 422 |
| Coach-portaal (nieuw + bewerken) | `CoachPortalController:275,365` | 422 |
| HavunClub-inschrijving | `Api/InschrijvingRequest` | 422 |
| Schoolportaal-inschrijving | `Api/SchoolPortalInschrijvingRequest` | 422 |
| CSV/Excel-import | `ImportService::verwerkRij()` | **rij afgekeurd, bestand loopt door** |
| **Indeling (vangnet)** | `PouleIndeling\JudokaGrouper::group()` | judoka uitgesloten + gemeld |

Grenzen zijn overal gelijk: `numeric|min:10|max:200`. De twee sync-endpoints stonden op
`min:0|max:300` — een judoka van 0,5 kg of 280 kg is een invoerfout, geen deelnemer.

## Import wijkt bewust af

Eén rij zonder gewicht mag niet het hele bestand blokkeren. Een club die 200 judoka's uploadt
waarvan er drie geen gewicht hebben, krijgt de 197 gewoon binnen plus een regel per afgekeurde rij:

```
Fout op regel 42: gewicht ontbreekt voor 'Jan Jansen' — vul een gewicht of gewichtsklasse in.
```

Dat loopt via `ImportException::rowValidation()` en het bestaande `fouten`-kanaal
(`JudokaController:378` → `pages/judoka/index.blade.php:249-269`). De rij telt als `overgeslagen`.

**Een gewichtsklasse zonder gewicht is genoeg** — het gewicht wordt eruit afgeleid
(`gewichtVanKlasse()`). Alleen als beide ontbreken wordt de rij afgekeurd.

## Waarom óók een vangnet in de indeling

Validatie aan de poort dekt de bekende paden niet allemaal:

- **`LocalSyncService::importJudoka()`** doet een blinde `updateOrCreate` en omzeilt elke
  Form Request — offline-sync kan gewichtloze judoka's terugschrijven.
- **Bestaande data** van vóór deze regel staat gewoon in de database.
- Elk pad dat er later bij komt.

Zonder vangnet belandde zo'n judoka in een groep met sleutel `"{leeftijdsklasse}|Onbekend"`, en dat
werd een echte poule. `JudokaGrouper::group()` filtert ze er nu uit; `zonderGewicht()` levert
dezelfde lijst op voor de melding, die `PouleIndelingService` in `statistieken['waarschuwingen']`
zet zodat de organisator ziet wie er ontbreekt.

## Zichtbaar maken van bestaande gaten

- Gele balk organisator: `pages/judoka/index.blade.php:146-182` (telde `!$j->gewicht` al mee)
- Gele balk coach-portaal: `pages/coach/judokas.blade.php:264-270` via `Judoka::isVolledig()`
- Knop "Valideren": `JudokaController::voerValidatieUit()` — **gewicht ontbrak in die check**, is
  toegevoegd. Anders meldde de validatie een gewichtloze judoka als in orde.
