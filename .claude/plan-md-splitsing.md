# Plan — MD-docs terugbrengen tot leesbare grootte

> **Aanleiding:** de KB indexeert alleen het **begin** van een bestand (~2000-8000 tekens). Alles
> daarna is onvindbaar via `docs:search`. 13 docs staan boven de 200-regelnorm uit CLAUDE.md; de
> grootste (`CLASSIFICATIE.md`, 63k tekens) is voor ~85% onvindbaar. Gebleken bij het documenteren
> van de device-toegangen-fix op 15-07: de nieuwe sectie stond op regel 517 en kwam niet boven.
> **Norm:** `HavunCore/docs/kb/standards/md-doc-grootte.md` — KB-doc/runbook max 200 regels.
> **Status:** in uitvoering.

## Het patroon

Elk te groot doc wordt **index + deeldocs**:

```
2-FEATURES/CLASSIFICATIE.md        ← index, max ~150 regels, blijft op de oude naam
2-FEATURES/CLASSIFICATIE/          ← deeldocs, elk max ~200 regels
    ALGORITME.md
    POULEGROOTTE.md
    ...
```

**De index blijft op de oude bestandsnaam** — niet `CLASSIFICATIE/README.md`. Code verwijst naar
deze paden (`Poule.php:497` → `CLASSIFICATIE.md`, 3 controllers → `INTERFACES.md`,
`DynamischeIndelingService.php:33` → `GEBRUIKERSHANDLEIDING.md`); een map-verhuizing breekt die
stilzwijgend. `ELIMINATIE/README.md` heeft geen inkomende links en blijft zoals het is.

**De index bevat** (in deze volgorde, want de KB leest alleen het begin):
1. Eén alinea: wat dit onderwerp is en wat de status is.
2. De kernfeiten die je in 90% van de gevallen nodig hebt — de tabel die nu middenin verstopt zit.
3. Een verwijstabel: deeldoc → wanneer je het nodig hebt.

## Volgorde (grootste/meest gebruikt eerst)

| # | Doc | Regels | Splitsing |
|---|-----|--------|-----------|
| 1 | `2-FEATURES/CLASSIFICATIE.md` | 1465 | index + algoritme, criteria, poulegrootte, presets, solver, overpoulen |
| 2 | `2-FEATURES/INTERFACES.md` | 1338 | index + per rol (weging, coach, mat, spreker, hoofdjury, publiek, portaal) |
| 3 | `2-FEATURES/GEBRUIKERSHANDLEIDING.md` | 866 | index + per taak |
| 4 | `3-DEVELOPMENT/REDUNDANTIE.md` | 736 | index + offline-pakket, failover, recovery, roadmap |
| 5 | `3-DEVELOPMENT/STABILITY.md` | 734 | index + deeldocs |
| 6 | `2-FEATURES/NOODPLAN-HANDLEIDING.md` | 695 | index + deeldocs |
| 7 | `3-DEVELOPMENT/CODE-STANDAARDEN.md` | 601 | index + deeldocs |
| 8 | `3-DEVELOPMENT/API.md` | 582 | index + per endpoint-groep |
| 9 | `2-FEATURES/BETALINGEN.md` | 551 | index + Mollie, freemium-grens |
| 10 | `2-FEATURES/SCOREBORD-APP.md` | 541 | index + app, LCD/TV, protocol |
| 11 | `2-FEATURES/BLOKVERDELING.md` | 504 | index + deeldocs |
| 12 | `2-FEATURES/FREEMIUM.md` | 488 | index + deeldocs |
| 13 | `4-PLANNING/MULTI-TENANCY-ROADMAP.md` | 355 | index + fasen |

Docs van 200-360 regels (`CHAT`, `MAT-WEDSTRIJD-SELECTIE`, `ONTWIKKELAAR`, `PRINTBARE-BRACKETS`,
`URL-STRUCTUUR`, `DATABASE`, `WEDSTRIJDSCHEMA`, `JBN-REGLEMENT`, `FUNCTIES`,
`LOKALE-SERVER-HANDLEIDING`, `ROLLEN_HIERARCHIE`) zitten net over de norm maar wél binnen het
index-venster van de KB. **Lage prioriteit** — pas aanpakken als ze verder groeien.

## Regels bij het splitsen

- **Inhoud verhuist, niet herschrijven.** Geen herformulering, geen "verbeteringen" — knippen en
  de kop meenemen. Zo blijft de diff leesbaar en gaat er geen kennis verloren.
- **Niets weggooien.** Ook `## Legacy` en `## Implementatie Status` verhuizen; ze zijn oud, maar
  dat oordeel is niet aan deze opruimactie.
- **Links meeverhuizen.** Na elke splitsing: `grep -rn "OUDE-NAAM.md"` over `laravel/` en
  `.claude/` — code-comments en README's die naar een verplaatste sectie wijzen, bijwerken.
- **Per doc één commit**, zodat een misser terug te draaien is zonder de rest te raken.
- **KB herindexeren na afloop:** `php artisan docs:index judotoernooi --no-code --force`.

## Verificatie per doc

1. `wc -l` op index + elk deeldoc → alles onder de norm.
2. `docs:search` op een term die eerst diep in het bestand stond → moet nu boven komen.
3. Geen dode links: `grep -rn "OUDE-NAAM.md"` levert alleen bedoelde treffers.

## Risico

- **Kennis verliezen bij het knippen** — daarom verhuizen zonder herschrijven, en per doc een
  aparte commit.
- **Stille dode links** in code-comments; `grep` na elke splitsing dekt dat af.
- De offline-build (`offline/build/laravel/`) bevat een kopie van de code met dezelfde
  doc-verwijzingen. Die is gegenereerd — **niet met de hand bijwerken**, hij komt bij de
  volgende build mee.
