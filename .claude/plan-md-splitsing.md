# Plan — MD-docs terugbrengen tot leesbare grootte

> **Aanleiding:** de KB indexeert alleen het **begin** van een bestand. Alles daarna is onvindbaar
> via `docs:search`. 13 docs stonden boven de 200-regelnorm uit CLAUDE.md; de grootste
> (`CLASSIFICATIE.md`, 63k tekens) was voor ~85% onvindbaar. Gebleken bij het documenteren van de
> device-toegangen-fix op 15-07: de nieuwe sectie stond op regel 517 en kwam niet boven.
> **Status: klaar.** Ronde 1 (13 docs, `34ce77ad`..`dc4684ff`) + ronde 2 (9 docs, `c6b9f517`).
> Docs: 48 → ~193. Alles binnen het indexvenster op één ondeelbaar ASCII-diagram na, zie onderaan.

## Meet in tekens, niet in regels

Nagekeken in de echte indexer (`HavunCore/app/Services/DocIntelligence/DocIndexer.php:123,751`):
`EMBEDDING_CHAR_LIMITS = [8000, 4000, 2000]`. Ollama's `nomic-embed-text` draait op een ~2048-token
context en **weigert** (HTTP 500) wat langer is in plaats van af te kappen; de indexer knipt daarom
op 8000 tekens en halveert bij een context-error naar 4000, dan 2000. Alleen dát deel krijgt een
echte embedding — de rest van het bestand valt buiten de semantische zoekindex.

**Dus: de regelnorm van 200 is een proxy en soms een misleidende.** `CLASSIFICATIE/OVERPOULEN.md`
kwam uit op 198 regels — netjes binnen de norm — maar 12.411 tekens, want het bestaat uit brede
tabellen. Dat doc was nog steeds voor tweederde onvindbaar.

**Hanteer: max ~4000 tekens per deeldoc.** Dat is de eerste halvering en dus de veilige bovengrens
voor tabelrijke markdown; 8000 haal je alleen met ijle prose. `wc -c`, niet `wc -l`.

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

Alle 13 gesplitst (`34ce77ad` t/m `4bcf6ea2`, plus `dc4684ff` voor de deeldocs die op tekens
alsnog te groot bleken).

## Ronde 2 — op tekens (`c6b9f517`)

Negen docs die onder de 200 regels bleven maar boven 8k tekens zaten: `ELIMINATIE/README` (15.7k),
`PRINTBARE-BRACKETS` (13.8k), `MAT-WEDSTRIJD-SELECTIE`, `DATABASE`, `CHAT`, `URL-STRUCTUUR`,
`WEDSTRIJDSCHEMA`, `ONTWIKKELAAR`, `ELIMINATIE/FORMULES`. Allemaal gesplitst, deeldocs onder ~4000.

`ELIMINATIE/` had al een index (`README.md`) + deeldocs. Daar dus **geen** `README/`-map: README
ingekort, overloop naar nieuwe deeldocs náást de bestaande. Zo hoort het bij een map die het
patroon al volgt.

## Wat bewust blijft staan

| Doc | Tekens | Waarom |
|-----|--------|--------|
| `3-DEVELOPMENT/REDUNDANTIE/ARCHITECTUUR.md` | 9.252 | Eén ASCII-diagram van 84 regels. Ondeelbaar; knippen maakt het onleesbaar. |
| `3-DEVELOPMENT/DATABASE/ERD.md` | 4.653 | Idem: één ERD-codeblok. |
| ~~`2-FEATURES/HAVUNCLUB-INTEGRATIE-PLAN.md`~~ | ~~8.933~~ | **Alsnog gesplitst (15-07):** index (3.940) + `SCENARIOS`/`ENDPOINTS`/`HAVUNCLUB-SCOPE`, elk onder 3.2k. Geen inkomende code-links. |

Onder de 8k (`SLOT-SYSTEEM` 7.151, `ROLLEN_HIERARCHIE` 7.901, `JBN-REGLEMENT` 7.786, `FUNCTIES`
6.422, `LOKALE-SERVER-HANDLEIDING` 5.091): met rust laten.

## Nog te doen buiten dit project

`HavunCore/docs/kb/standards/md-doc-grootte.md` schrijft nog "max 200 regels" voor. Dat is de norm
die deze hele operatie nodig maakte én misleidde. Zou tekens moeten worden — SaaS-breed.

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
